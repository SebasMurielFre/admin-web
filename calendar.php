<?php
$page_title = "Calendario de Citas - Sistema Administrativo PROINVESTEC SA";
include 'config/database.php';
include 'includes/auth.php';

requireAuth();

// Configurar zona horaria de Ecuador (Guayaquil)
date_default_timezone_set('America/Guayaquil');

// Obtener citas externas para el calendario - SOLO del usuario actual (o todas si es admin)
try {
    if (canViewAllAppointments()) {
        // Los administradores pueden ver todas las citas
        $stmt = $pdo->prepare("
            SELECT a.*, au.nombre as user_name 
            FROM appointments a 
            JOIN admin_users au ON a.user_id = au.id 
            WHERE a.fecha_hora >= CURRENT_DATE - INTERVAL '1 month'
            AND a.fecha_hora <= CURRENT_DATE + INTERVAL '3 months'
            ORDER BY a.fecha_hora ASC
        ");
        $stmt->execute();
    } else {
        // Los usuarios normales solo ven sus propias citas
        $stmt = $pdo->prepare("
            SELECT a.*, au.nombre as user_name 
            FROM appointments a 
            JOIN admin_users au ON a.user_id = au.id 
            WHERE a.user_id = ?
            AND a.fecha_hora >= CURRENT_DATE - INTERVAL '1 month'
            AND a.fecha_hora <= CURRENT_DATE + INTERVAL '3 months'
            ORDER BY a.fecha_hora ASC
        ");
        $stmt->execute([$_SESSION['admin_user_id']]);
    }
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $appointments = [];
}

// Obtener citas internas donde el usuario participa
try {
    $stmt_internal = $pdo->prepare("
        SELECT DISTINCT ia.*, 
               creator.nombre as creator_name,
               iap_current.status as my_status
        FROM internal_appointments ia
        JOIN admin_users creator ON ia.created_by_user_id = creator.id
        LEFT JOIN internal_appointment_participants iap ON ia.id = iap.appointment_id
        LEFT JOIN internal_appointment_participants iap_current ON ia.id = iap_current.appointment_id AND iap_current.user_id = ?
        WHERE (ia.created_by_user_id = ? OR iap.user_id = ?)
        AND ia.fecha_hora >= CURRENT_DATE - INTERVAL '1 month'
        AND ia.fecha_hora <= CURRENT_DATE + INTERVAL '3 months'
        ORDER BY ia.fecha_hora ASC
    ");
    $stmt_internal->execute([$_SESSION['admin_user_id'], $_SESSION['admin_user_id'], $_SESSION['admin_user_id']]);
    $internal_appointments = $stmt_internal->fetchAll();
} catch (PDOException $e) {
    $internal_appointments = [];
}

// Convertir citas externas a formato JSON para FullCalendar
$calendar_events = [];
foreach ($appointments as $appointment) {
    $color = '';
    switch ($appointment['estado']) {
        case 'programada':
            $color = '#004D40'; // Azul petróleo oscuro
            break;
        case 'completada':
            $color = '#1B5E20'; // Verde bosque oscuro
            break;
        case 'cancelada':
            $color = '#7F0000'; // Rojo vino oscuro
            break;
        case 'reagendada':
            $color = '#FF6F00'; // Amarillo mostaza oscuro
            break;
        default:
            $color = '#6b7280'; // Gris
    }
    
    // Agregar indicador visual si es cita de otro usuario (solo para admins)
    $title = $appointment['contact_name'];
    if (canViewAllAppointments() && $appointment['user_id'] != $_SESSION['admin_user_id']) {
        $title = $appointment['contact_name'] . ' (' . $appointment['user_name'] . ')';
    }
    
    $calendar_events[] = [
        'id' => 'ext_' . $appointment['id'],
        'title' => $title,
        'start' => date('Y-m-d\TH:i:s', strtotime($appointment['fecha_hora'])),
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'type' => 'external',
            'contact_email' => $appointment['contact_email'],
            'motivo' => $appointment['motivo'],
            'estado' => $appointment['estado'],
            'user_name' => $appointment['user_name'],
            'user_id' => $appointment['user_id'],
            'can_edit' => ($appointment['user_id'] == $_SESSION['admin_user_id'] || canViewAllAppointments()),
            'fecha_hora_formatted' => date('l, j \d\e F \d\e Y, H:i', strtotime($appointment['fecha_hora']))
        ]
    ];
}

// Convertir citas internas a formato JSON para FullCalendar
foreach ($internal_appointments as $appointment) {
    $color = '';
    switch ($appointment['estado']) {
        case 'programada':
            $color = '#B3E5FC'; // Azul cielo claro
            break;
        case 'completada':
            $color = '#C8E6C9'; // Verde menta claro
            break;
        case 'cancelada':
            $color = '#FF8A80'; // Rojo claro
            break;
        case 'reagendada':
            $color = '#FFF9C4'; // Amarillo pastel claro
            break;
        default:
            $color = '#4b5563'; // Gris más oscuro
    }
    
    $title = '🏢 ' . $appointment['titulo'];
    if ($appointment['created_by_user_id'] != $_SESSION['admin_user_id']) {
        $title .= ' (por ' . $appointment['creator_name'] . ')';
    }
    
    $calendar_events[] = [
        'id' => 'int_' . $appointment['id'],
        'title' => $title,
        'start' => date('Y-m-d\TH:i:s', strtotime($appointment['fecha_hora'])),
        'end' => date('Y-m-d\TH:i:s', strtotime($appointment['fecha_hora'] . ' +' . $appointment['duracion_minutos'] . ' minutes')),
        'backgroundColor' => $color,
        'borderColor' => $color,
        'extendedProps' => [
            'type' => 'internal',
            'descripcion' => $appointment['descripcion'],
            'estado' => $appointment['estado'],
            'creator_name' => $appointment['creator_name'],
            'created_by_user_id' => $appointment['created_by_user_id'],
            'my_status' => $appointment['my_status'],
            'duracion_minutos' => $appointment['duracion_minutos'],
            'can_edit' => ($appointment['created_by_user_id'] == $_SESSION['admin_user_id']),
            'fecha_hora_formatted' => date('l, j \d\e F \d\e Y, H:i', strtotime($appointment['fecha_hora']))
        ]
    ];
}

include 'includes/admin_header.php';
?>

<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex-1">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Calendario de Citas</h1>
                <p class="text-gray-600 mt-2">
                    <?php if (canViewAllAppointments()): ?>
                        Vista de calendario para todas las citas del sistema
                    <?php else: ?>
                        Vista de calendario para tus citas programadas
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex space-x-3">
                <a href="appointments.php?create=1" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-plus mr-2"></i>Cita Externa
                </a>
                <a href="internal-appointments.php?create=1" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    <i class="fas fa-users mr-2"></i>Cita Interna
                </a>
            </div>
        </div>
    </div>

    <!-- Leyenda de colores -->
    <div class="proinvestec-card p-4 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 mb-3">Leyenda de Estados</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">Citas Externas (Clientes)</h4>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-[#004D40] rounded"></div>
                        <span class="text-sm text-gray-700">Programada</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-[#1B5E20] rounded"></div>
                        <span class="text-sm text-gray-700">Completada</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-[#7F0000] rounded"></div>
                        <span class="text-sm text-gray-700">Cancelada</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-[#FF6F00] rounded"></div>
                        <span class="text-sm text-gray-700">Reagendada</span>
                    </div>
                </div>
            </div>
            <div>
                <h4 class="text-sm font-medium text-gray-700 mb-2">Citas Internas (Usuarios) 🏢</h4>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-[#B3E5FC] rounded"></div>
                        <span class="text-sm text-gray-700">Programada</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-[#C8E6C9] rounded"></div>
                        <span class="text-sm text-gray-700">Completada</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-[#FF8A80] rounded"></div>
                        <span class="text-sm text-gray-700">Cancelada</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-[#FFF9C4] rounded"></div>
                        <span class="text-sm text-gray-700">Reagendada</span>
                    </div>
                </div>
            </div>
        </div>
        <?php if (canViewAllAppointments()): ?>
        <div class="mt-3 pt-3 border-t border-gray-300">
            <div class="flex items-center space-x-2">
                <i class="fas fa-info-circle text-blue-500"></i>
                <span class="text-sm text-gray-700">Las citas de otros usuarios aparecen con el nombre del responsable</span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Calendario -->
    <div class="proinvestec-card p-6">
        <div id="calendar"></div>
    </div>

    <!-- Modal para detalles de cita -->
    <div id="appointment-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold">Detalles de la Cita</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="modal-content">
                <!-- El contenido se llenará dinámicamente -->
            </div>
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                    Cerrar
                </button>
                <a id="edit-appointment-link" href="#" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-edit mr-2"></i>Editar Cita
                </a>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        timeZone: 'America/Guayaquil',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Hoy',
            month: 'Mes',
            week: 'Semana',
            day: 'Día'
        },
        events: <?php echo json_encode($calendar_events); ?>,
        eventClick: function(info) {
            showAppointmentDetails(info.event);
        },
        eventMouseEnter: function(info) {
            info.el.style.cursor = 'pointer';
        },
        height: 'auto',
        eventDisplay: 'block',
        dayMaxEvents: 3,
        moreLinkClick: 'popover'
    });
    
    calendar.render();
});

function showAppointmentDetails(event) {
    const props = event.extendedProps;
    
    if (props.type === 'external') {
        showExternalAppointmentDetails(event, props);
    } else {
        showInternalAppointmentDetails(event, props);
    }
}

function showExternalAppointmentDetails(event, props) {
    // Determinar si el usuario puede editar esta cita
    const canEdit = props.can_edit;
    const isOwnAppointment = props.user_id == <?php echo $_SESSION['admin_user_id']; ?>;
    
    let ownershipInfo = '';
    if (<?php echo canViewAllAppointments() ? 'true' : 'false'; ?> && !isOwnAppointment) {
        ownershipInfo = `
            <div class="bg-purple-50 border border-purple-200 rounded-md p-3 mb-3">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-purple-500 mr-2"></i>
                    <span class="text-sm text-purple-700">Esta cita pertenece a otro usuario</span>
                </div>
            </div>
        `;
    }
    
    const modalContent = `
        <div class="bg-blue-50 border border-blue-200 rounded-md p-3 mb-3">
            <div class="flex items-center">
                <i class="fas fa-user text-blue-500 mr-2"></i>
                <span class="text-sm text-blue-700 font-medium">Cita Externa (Cliente)</span>
            </div>
        </div>
        ${ownershipInfo}
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Cliente:</label>
                <p class="text-sm text-gray-900">${event.title.split(' (')[0]}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Email:</label>
                <p class="text-sm text-gray-900">${props.contact_email}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Fecha y Hora:</label>
                <p class="text-sm text-gray-900">${props.fecha_hora_formatted}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Estado:</label>
                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full ${getStatusClass(props.estado)}">
                    ${props.estado.charAt(0).toUpperCase() + props.estado.slice(1)}
                </span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Asignado a:</label>
                <p class="text-sm text-gray-900">${props.user_name}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Motivo:</label>
                <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${props.motivo}</p>
            </div>
        </div>
    `;
    
    document.getElementById('modal-content').innerHTML = modalContent;
    
    // Mostrar u ocultar el botón de editar según los permisos
    const editButton = document.getElementById('edit-appointment-link');
    if (canEdit) {
        editButton.href = `appointments.php#edit-form-${event.id.replace('ext_', '')}`;
        editButton.style.display = 'inline-flex';
        editButton.className = 'px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors';
        editButton.innerHTML = '<i class="fas fa-edit mr-2"></i>Editar Cita';
    } else {
        editButton.style.display = 'none';
    }
    
    document.getElementById('appointment-modal').classList.remove('hidden');
}

async function showInternalAppointmentDetails(event, props) {
    const canEdit = props.can_edit;
    const isCreator = props.created_by_user_id == <?php echo $_SESSION['admin_user_id']; ?>;
    
    // Obtener participantes
    const appointmentId = event.id.replace('int_', '');
    
    let ownershipInfo = '';
    if (!isCreator) {
        ownershipInfo = `
            <div class="bg-purple-50 border border-purple-200 rounded-md p-3 mb-3">
                <div class="flex items-center">
                    <i class="fas fa-info-circle text-purple-500 mr-2"></i>
                    <span class="text-sm text-purple-700">Cita creada por ${props.creator_name}</span>
                </div>
            </div>
        `;
    }
    
    const modalContent = `
        <div class="bg-green-50 border border-green-200 rounded-md p-3 mb-3">
            <div class="flex items-center">
                <i class="fas fa-users text-green-500 mr-2"></i>
                <span class="text-sm text-green-700 font-medium">Cita Interna (Reunión)</span>
            </div>
        </div>
        ${ownershipInfo}
        <div class="space-y-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Título:</label>
                <p class="text-sm text-gray-900">${event.title.replace('🏢 ', '').split(' (por ')[0]}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Fecha y Hora:</label>
                <p class="text-sm text-gray-900">${props.fecha_hora_formatted}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Duración:</label>
                <p class="text-sm text-gray-900">${props.duracion_minutos} minutos</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Estado:</label>
                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full ${getInternalStatusClass(props.estado)}">
                    ${props.estado.charAt(0).toUpperCase() + props.estado.slice(1)}
                </span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Mi participación:</label>
                <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full ${getParticipationStatusClass(props.my_status)}">
                    ${props.my_status ? props.my_status.charAt(0).toUpperCase() + props.my_status.slice(1) : 'No definido'}
                </span>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Creado por:</label>
                <p class="text-sm text-gray-900">${props.creator_name}</p>
            </div>
            ${props.descripcion ? `
            <div>
                <label class="block text-sm font-medium text-gray-700">Descripción:</label>
                <p class="text-sm text-gray-900 bg-gray-50 p-2 rounded">${props.descripcion}</p>
            </div>
            ` : ''}
        </div>
    `;
    
    document.getElementById('modal-content').innerHTML = modalContent;
    
    // Mostrar u ocultar el botón de editar según los permisos
    const editButton = document.getElementById('edit-appointment-link');
    if (canEdit) {
        editButton.href = `internal-appointments.php#edit-form-${appointmentId}`;
        editButton.style.display = 'inline-flex';
        editButton.className = 'px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors';
        editButton.innerHTML = '<i class="fas fa-edit mr-2"></i>Editar Reunión';
    } else {
        editButton.style.display = 'none';
    }
    
    document.getElementById('appointment-modal').classList.remove('hidden');
}

function getStatusClass(status) {
    switch(status) {
        case 'programada':
            return 'bg-[#004D40] text-white';
        case 'completada':
            return 'bg-[#1B5E20] text-white';
        case 'cancelada':
            return 'bg-[#7F0000] text-white';
        case 'reagendada':
            return 'bg-[#FF6F00] text-white';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getInternalStatusClass(status) {
    switch(status) {
        case 'programada':
            return 'bg-[#B3E5FC] text-[#004D40]';
        case 'completada':
            return 'bg-[#C8E6C9] text-[#1B5E20]';
        case 'cancelada':
            return 'bg-[#FF8A80] text-[#7F0000]';
        case 'reagendada':
            return 'bg-[#FFF9C4] text-[#FF6F00]';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function getParticipationStatusClass(status) {
    switch(status) {
        case 'aceptada':
            return 'bg-[#C8E6C9] text-[#1B5E20]';
        case 'rechazada':
            return 'bg-[#FF8A80] text-[#7F0000]';
        case 'pendiente':
            return 'bg-[#FFF9C4] text-[#FF6F00]';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}

function closeModal() {
    document.getElementById('appointment-modal').classList.add('hidden');
}

// Cerrar modal al hacer clic fuera de él
document.getElementById('appointment-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>

<?php include 'includes/admin_footer.php'; ?>

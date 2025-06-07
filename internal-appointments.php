<?php
// Configurar zona horaria de Ecuador (Guayaquil)
date_default_timezone_set('America/Guayaquil');

$page_title = "Citas Internas - Sistema Administrativo PROINVESTEC SA";
include 'config/database.php';
include 'includes/auth.php';

requireAuth();

$success_message = '';
$error_message = '';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Agregar al inicio del archivo PHP, después de las variables existentes:
$highlight_appointment_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;

// Procesar acciones
if ($_POST) {
    if (isset($_POST['create_internal_appointment'])) {
        $titulo = trim($_POST['titulo']);
        $descripcion = trim($_POST['descripcion']);
        $fecha_hora = $_POST['fecha_hora'];
        $duracion_minutos = (int)$_POST['duracion_minutos'];
        $participant_ids = isset($_POST['participants']) ? $_POST['participants'] : [];
        
        if (!empty($titulo) && !empty($fecha_hora) && !empty $participant_ids)) {
            $appointment_id = createInternalAppointment($titulo, $descripcion, $fecha_hora, $duracion_minutos, $participant_ids);
            
            if ($appointment_id) {
                $success_message = "Cita interna creada correctamente.";
                // Notificar a los participantes sobre la nueva cita
                notifyInternalAppointmentInvite($appointment_id, $participant_ids, $_SESSION['admin_user_id']);
            } else {
                $error_message = "Error al crear la cita interna.";
            }
        } else {
            $error_message = "Por favor, completa todos los campos obligatorios y selecciona al menos un participante.";
        }
    }
    
    if (isset($_POST['update_internal_appointment'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        
        if (canAccessInternalAppointment($appointment_id)) {
            $titulo = trim($_POST['titulo']);
            $descripcion = trim($_POST['descripcion']);
            $fecha_hora = $_POST['fecha_hora'];
            $duracion_minutos = (int)$_POST['duracion_minutos'];
            $estado = $_POST['estado'];
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE internal_appointments 
                    SET titulo = ?, descripcion = ?, fecha_hora = ?, duracion_minutos = ?, estado = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                if ($stmt->execute([$titulo, $descripcion, $fecha_hora, $duracion_minutos, $estado, $appointment_id])) {
                    $success_message = "Cita interna actualizada correctamente.";
                    // Notificar sobre la modificación
                    $changes_description = "Fecha/hora actualizada a " . date('d/m/Y H:i', strtotime($fecha_hora));
                    notifyInternalAppointmentModified($appointment_id, $_SESSION['admin_user_id'], $changes_description);
                    
                    // Si cambió el estado, notificar también
                    if ($estado !== 'programada') {
                        notifyInternalAppointmentStatusChange($appointment_id, $estado, $_SESSION['admin_user_id']);
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Error al actualizar la cita interna.";
            }
        } else {
            $error_message = "No tienes permisos para modificar esta cita.";
        }
    }
    
    if (isset($_POST['update_participation_status'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        $status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("
                UPDATE internal_appointment_participants 
                SET status = ? 
                WHERE appointment_id = ? AND user_id = ?
            ");
            if ($stmt->execute([$status, $appointment_id, $_SESSION['admin_user_id']])) {
                $success_message = "Estado de participación actualizado.";
                // Si se aceptó o rechazó, notificar al creador
                if ($status === 'aceptada' || $status === 'rechazada') {
                    try {
                        $stmt_creator = $pdo->prepare("SELECT created_by_user_id FROM internal_appointments WHERE id = ?");
                        $stmt_creator->execute([$appointment_id]);
                        $creator_id = $stmt_creator->fetchColumn();
                        
                        if ($creator_id && $creator_id != $_SESSION['admin_user_id']) {
                            $user_name = $_SESSION['admin_user_nombre'];
                            $status_text = $status === 'aceptada' ? 'aceptó' : 'rechazó';
                            
                            createNotification(
                                $creator_id,
                                'internal_appointment_status',
                                'Respuesta a invitación',
                                "{$user_name} {$status_text} la invitación a la reunión",
                                'internal_appointment',
                                $appointment_id
                            );
                        }
                    } catch (PDOException $e) {
                        // Ignorar errores de notificación
                    }
                }
            }
        } catch (PDOException $e) {
            $error_message = "Error al actualizar el estado de participación.";
        }
    }
}

// Obtener datos para formulario de creación
$create_mode = isset($_GET['create']) && $_GET['create'] == '1';
$all_users = getAllUsers();

// Filtros
$estado_filter = isset($_GET['estado']) ? $_GET['estado'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Construir consulta para citas internas
$where_conditions = [];
$params = [];

if (!empty($estado_filter)) {
    $where_conditions[] = "ia.estado = ?";
    $params[] = $estado_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(ia.fecha_hora) = ?";
    $params[] = $date_filter;
}

$where_clause = !empty($where_conditions) ? "AND " . implode(" AND ", $where_conditions) : "";

// Obtener total de citas internas para paginación
try {
    $count_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ia.id)
        FROM internal_appointments ia
        JOIN admin_users creator ON ia.created_by_user_id = creator.id
        LEFT JOIN internal_appointment_participants iap ON ia.id = iap.appointment_id
        LEFT JOIN internal_appointment_participants iap_current ON ia.id = iap_current.appointment_id AND iap_current.user_id = ?
        WHERE (ia.created_by_user_id = ? OR iap.user_id = ?) $where_clause
    ");
    $params_count = array_merge([$_SESSION['admin_user_id'], $_SESSION['admin_user_id'], $_SESSION['admin_user_id']], $params);
    $count_stmt->execute($params_count);
    $total_internal_appointments = $count_stmt->fetchColumn();
    $total_pages = ceil($total_internal_appointments / $per_page);
} catch (PDOException $e) {
    $total_internal_appointments = 0;
    $total_pages = 1;
}

// Obtener citas internas donde el usuario es creador o participante
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT ia.*, 
               creator.nombre as creator_name,
               iap_current.status as my_status
        FROM internal_appointments ia
        JOIN admin_users creator ON ia.created_by_user_id = creator.id
        LEFT JOIN internal_appointment_participants iap ON ia.id = iap.appointment_id
        LEFT JOIN internal_appointment_participants iap_current ON ia.id = iap_current.appointment_id AND iap_current.user_id = ?
        WHERE (ia.created_by_user_id = ? OR iap.user_id = ?) $where_clause
        ORDER BY ia.fecha_hora ASC
        LIMIT $per_page OFFSET $offset
    ");
    $params = array_merge([$_SESSION['admin_user_id'], $_SESSION['admin_user_id'], $_SESSION['admin_user_id']], $params);
    $stmt->execute($params);
    $internal_appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $internal_appointments = [];
    $error_message = "Error al cargar las citas internas.";
}

include 'includes/admin_header.php';
?>

<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex-1">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Citas Internas</h1>
                <p class="text-gray-600 mt-2">Agenda reuniones con otros usuarios del sistema</p>
            </div>
            <button onclick="toggleCreateForm()" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                <i class="fas fa-users mr-2"></i>Nueva Cita Interna
            </button>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <!-- Formulario de creación -->
    <div id="create-form" class="<?php echo $create_mode ? '' : 'hidden'; ?> proinvestec-card p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Crear Nueva Cita Interna</h2>
        <form method="POST" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título de la Reunión *</label>
                    <input
                        type="text"
                        id="titulo"
                        name="titulo"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                        placeholder="Ej: Reunión de planificación"
                        required
                    />
                </div>
                
                <div>
                    <label for="fecha_hora" class="block text-sm font-medium text-gray-700 mb-2">Fecha y Hora *</label>
                    <input
                        type="datetime-local"
                        id="fecha_hora"
                        name="fecha_hora"
                        min="<?php echo date('Y-m-d\TH:i'); ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                        required
                    />
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="duracion_minutos" class="block text-sm font-medium text-gray-700 mb-2">Duración (minutos)</label>
                    <select
                        id="duracion_minutos"
                        name="duracion_minutos"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                        <option value="30">30 minutos</option>
                        <option value="60" selected>1 hora</option>
                        <option value="90">1.5 horas</option>
                        <option value="120">2 horas</option>
                        <option value="180">3 horas</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Participantes *</label>
                    <div class="relative">
                        <button
                            type="button"
                            id="participants-dropdown-button"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 bg-white text-left flex items-center justify-between"
                            onclick="toggleParticipantsDropdown()"
                        >
                            <span id="participants-selected-text" class="text-gray-500">Seleccionar participantes...</span>
                            <i class="fas fa-chevron-down text-gray-400"></i>
                        </button>
                        
                        <div id="participants-dropdown" class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg max-h-48 overflow-y-auto">
                            <div class="p-2">
                                <div class="mb-2">
                                    <input
                                        type="text"
                                        id="participants-search"
                                        placeholder="Buscar usuarios..."
                                        class="w-full px-2 py-1 text-sm border border-gray-200 rounded focus:outline-none focus:ring-1 focus:ring-green-500"
                                        onkeyup="filterParticipants()"
                                    />
                                </div>
                                <div id="participants-list">
                                    <?php foreach ($all_users as $user): ?>
                                    <label class="participant-item flex items-center space-x-2 py-2 px-2 hover:bg-gray-50 rounded cursor-pointer" data-name="<?php echo strtolower(htmlspecialchars($user['nombre'] . ' ' . $user['email'])); ?>">
                                        <input
                                            type="checkbox"
                                            name="participants[]"
                                            value="<?php echo $user['id']; ?>"
                                            class="rounded border-gray-300 text-green-600 focus:ring-green-500 participant-checkbox"
                                            onchange="updateParticipantsText()"
                                        />
                                        <div class="flex-1">
                                            <span class="text-sm text-gray-900"><?php echo htmlspecialchars($user['nombre']) . ' - ' . htmlspecialchars($user['email']); ?></span>
                                        </div>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php if (empty($all_users)): ?>
                                <div class="text-center py-4 text-gray-500 text-sm">
                                    No hay otros usuarios disponibles
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Selecciona uno o más usuarios para la reunión</p>
                </div>
            </div>
            
            <div>
                <label for="descripcion" class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                <textarea
                    id="descripcion"
                    name="descripcion"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                    placeholder="Describe el propósito de la reunión..."
                ></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="toggleCreateForm()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                    Cancelar
                </button>
                <button type="submit" name="create_internal_appointment" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    <i class="fas fa-save mr-2"></i>Crear Cita
                </button>
            </div>
        </form>
    </div>

    <!-- Filtros -->
    <div class="proinvestec-card p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label for="date" class="block text-sm font-medium text-gray-700 mb-2">Fecha</label>
                <input
                    type="date"
                    id="date"
                    name="date"
                    value="<?php echo htmlspecialchars($date_filter); ?>"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                />
            </div>
            <div>
                <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select
                    id="estado"
                    name="estado"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                >
                    <option value="">Todos los estados</option>
                    <option value="programada" <?php echo $estado_filter === 'programada' ? 'selected' : ''; ?>>Programada</option>
                    <option value="completada" <?php echo $estado_filter === 'completada' ? 'selected' : ''; ?>>Completada</option>
                    <option value="cancelada" <?php echo $estado_filter === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    <option value="reagendada" <?php echo $estado_filter === 'reagendada' ? 'selected' : ''; ?>>Reagendada</option>
                </select>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Filtrar
                </button>
            </div>
            <div>
                <a href="internal-appointments.php" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                    <i class="fas fa-times mr-2"></i>Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Información de paginación -->
    <div class="mb-4 flex justify-between items-center">
        <p class="text-sm text-gray-600">
            Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_internal_appointments); ?> de <?php echo $total_internal_appointments; ?> citas internas
        </p>
        <div class="text-sm text-gray-600">
            Página <?php echo $page; ?> de <?php echo $total_pages; ?>
        </div>
    </div>

    <!-- Lista de citas internas -->
    <div class="space-y-4">
        <?php if (empty($internal_appointments)): ?>
        <div class="proinvestec-card p-8 text-center">
            <i class="fas fa-users text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay citas internas</h3>
            <p class="text-gray-600">No se encontraron citas internas con los filtros aplicados.</p>
        </div>
        <?php else: ?>
        <?php foreach ($internal_appointments as $appointment): ?>
        <?php
        // Obtener participantes
        $stmt_participants = $pdo->prepare("
            SELECT u.nombre, u.email, iap.status 
            FROM internal_appointment_participants iap 
            JOIN admin_users u ON iap.user_id = u.id 
            WHERE iap.appointment_id = ?
        ");
        $stmt_participants->execute([$appointment['id']]);
        $participants = $stmt_participants->fetchAll();
        ?>
        <div id="appointment-<?php echo $appointment['id']; ?>" class="proinvestec-card p-6 <?php echo $highlight_appointment_id == $appointment['id'] ? 'border-yellow-400 bg-yellow-50' : ''; ?>">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center space-x-3 mb-2">
                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($appointment['titulo']); ?></h3>
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php 
                            echo $appointment['estado'] === 'programada' ? 'bg-[#B3E5FC] text-[#004D40]' :
                                ($appointment['estado'] === 'completada' ? 'bg-[#C8E6C9] text-[#1B5E20]' :
                                ($appointment['estado'] === 'cancelada' ? 'bg-[#FF8A80] text-[#7F0000]' : 'bg-[#FFF9C4] text-[#FF6F00]'));
                        ?>">
                            <?php echo ucfirst($appointment['estado']); ?>
                        </span>
                        <?php if ($appointment['my_status']): ?>
                        <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?php 
                            echo $appointment['my_status'] === 'aceptada' ? 'bg-[#C8E6C9] text-[#1B5E20]' :
                                ($appointment['my_status'] === 'rechazada' ? 'bg-[#FF8A80] text-[#7F0000]' : 'bg-[#FFF9C4] text-[#FF6F00]');
                        ?>">
                            Mi estado: <?php echo ucfirst($appointment['my_status']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1 mb-3">
                        <p><i class="fas fa-calendar mr-2"></i><?php echo date('d/m/Y H:i', strtotime($appointment['fecha_hora'])); ?></p>
                        <p><i class="fas fa-clock mr-2"></i>Duración: <?php echo $appointment['duracion_minutos']; ?> minutos</p>
                        <p><i class="fas fa-user mr-2"></i>Creado por: <?php echo htmlspecialchars($appointment['creator_name']); ?></p>
                    </div>
                    
                    <?php if (!empty($appointment['descripcion'])): ?>
                    <div class="bg-gray-50 p-3 rounded-md mb-3">
                        <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($appointment['descripcion'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="bg-blue-50 p-3 rounded-md">
                        <h4 class="text-sm font-medium text-gray-900 mb-2">Participantes:</h4>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($participants as $participant): ?>
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full <?php 
                                echo $participant['status'] === 'aceptada' ? 'bg-[#C8E6C9] text-[#1B5E20]' :
                                    ($participant['status'] === 'rechazada' ? 'bg-[#FF8A80] text-[#7F0000]' : 'bg-[#FFF9C4] text-[#FF6F00]');
                            ?>">
                                <?php echo htmlspecialchars($participant['nombre']); ?>
                                <span class="ml-1">(<?php echo ucfirst($participant['status']); ?>)</span>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="flex flex-col space-y-2 ml-4">
                    <?php if ($appointment['my_status'] === 'pendiente'): ?>
                    <form method="POST" class="inline">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                        <input type="hidden" name="status" value="aceptada">
                        <button type="submit" name="update_participation_status" class="px-3 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition-colors text-sm">
                            <i class="fas fa-check mr-1"></i>Aceptar
                        </button>
                    </form>
                    <form method="POST" class="inline">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                        <input type="hidden" name="status" value="rechazada">
                        <button type="submit" name="update_participation_status" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition-colors text-sm">
                            <i class="fas fa-times mr-1"></i>Rechazar
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($appointment['created_by_user_id'] == $_SESSION['admin_user_id']): ?>
                    <button onclick="toggleEditForm(<?php echo $appointment['id']; ?>)" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors text-sm">
                        <i class="fas fa-edit mr-1"></i>Editar
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulario de edición - Solo para el creador -->
            <?php if ($appointment['created_by_user_id'] == $_SESSION['admin_user_id']): ?>
            <div id="edit-form-<?php echo $appointment['id']; ?>" class="hidden mt-4 pt-4 border-t">
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título</label>
                        <input
                            type="text"
                            name="titulo"
                            value="<?php echo htmlspecialchars($appointment['titulo']); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                            required
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                        <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="programada" <?php echo $appointment['estado'] === 'programada' ? 'selected' : ''; ?>>Programada</option>
                            <option value="completada" <?php echo $appointment['estado'] === 'completada' ? 'selected' : ''; ?>>Completada</option>
                            <option value="cancelada" <?php echo $appointment['estado'] === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                            <option value="reagendada" <?php echo $appointment['estado'] === 'reagendada' ? 'selected' : ''; ?>>Reagendada</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Fecha y Hora</label>
                        <input
                            type="datetime-local"
                            name="fecha_hora"
                            value="<?php echo date('Y-m-d\TH:i', strtotime($appointment['fecha_hora'])); ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                            required
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Duración (minutos)</label>
                        <select name="duracion_minutos" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                            <option value="30" <?php echo $appointment['duracion_minutos'] == 30 ? 'selected' : ''; ?>>30 minutos</option>
                            <option value="60" <?php echo $appointment['duracion_minutos'] == 60 ? 'selected' : ''; ?>>1 hora</option>
                            <option value="90" <?php echo $appointment['duracion_minutos'] == 90 ? 'selected' : ''; ?>>1.5 horas</option>
                            <option value="120" <?php echo $appointment['duracion_minutos'] == 120 ? 'selected' : ''; ?>>2 horas</option>
                            <option value="180" <?php echo $appointment['duracion_minutos'] == 180 ? 'selected' : ''; ?>>3 horas</option>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Descripción</label>
                        <textarea
                            name="descripcion"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                        ><?php echo htmlspecialchars($appointment['descripcion']); ?></textarea>
                    </div>
                    
                    <div class="md:col-span-2 flex justify-end space-x-3">
                        <button type="button" onclick="toggleEditForm(<?php echo $appointment['id']; ?>)" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" name="update_internal_appointment" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-8 flex justify-center">
        <nav class="flex items-center space-x-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&estado=<?php echo urlencode($estado_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&estado=<?php echo urlencode($estado_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
               class="px-3 py-2 border rounded-md transition-colors <?php echo $i === $page ? 'bg-green-600 text-white border-green-600' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&estado=<?php echo urlencode($estado_filter); ?>&date=<?php echo urlencode($date_filter); ?>" 
               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</main>

<script>
function toggleCreateForm() {
    const form = document.getElementById('create-form');
    form.classList.toggle('hidden');
}

function toggleEditForm(appointmentId) {
    const form = document.getElementById(`edit-form-${appointmentId}`);
    form.classList.toggle('hidden');
}

// Funciones para el dropdown de participantes
function toggleParticipantsDropdown() {
    const dropdown = document.getElementById('participants-dropdown');
    const button = document.getElementById('participants-dropdown-button');
    const icon = button.querySelector('i');
    
    dropdown.classList.toggle('hidden');
    
    if (dropdown.classList.contains('hidden')) {
        icon.className = 'fas fa-chevron-down text-gray-400';
    } else {
        icon.className = 'fas fa-chevron-up text-gray-400';
        // Focus en el campo de búsqueda cuando se abre
        setTimeout(() => {
            document.getElementById('participants-search').focus();
        }, 100);
    }
}

function updateParticipantsText() {
    const checkboxes = document.querySelectorAll('.participant-checkbox:checked');
    const textElement = document.getElementById('participants-selected-text');
    
    if (checkboxes.length === 0) {
        textElement.textContent = 'Seleccionar participantes...';
        textElement.className = 'text-gray-500';
    } else if (checkboxes.length === 1) {
        const label = checkboxes[0].closest('.participant-item').querySelector('.text-sm');
        textElement.textContent = label.textContent.trim();
        textElement.className = 'text-gray-900';
    } else {
        textElement.textContent = `${checkboxes.length} participantes seleccionados`;
        textElement.className = 'text-gray-900';
    }
}

function filterParticipants() {
    const searchTerm = document.getElementById('participants-search').value.toLowerCase();
    const items = document.querySelectorAll('.participant-item');
    
    items.forEach(item => {
        const name = item.getAttribute('data-name');
        if (name.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('participants-dropdown');
    const button = document.getElementById('participants-dropdown-button');
    
    if (!button.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.add('hidden');
        const icon = button.querySelector('i');
        icon.className = 'fas fa-chevron-down text-gray-400';
    }
});

// Prevenir que el dropdown se cierre al hacer clic dentro
document.getElementById('participants-dropdown').addEventListener('click', function(event) {
    event.stopPropagation();
});

// Si hay una cita resaltada, hacer scroll hacia ella
<?php if ($highlight_appointment_id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    const highlightedAppointment = document.getElementById('appointment-<?php echo $highlight_appointment_id; ?>');
    if (highlightedAppointment) {
        highlightedAppointment.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Remover resaltado después de 3 segundos
        setTimeout(() => {
            highlightedAppointment.classList.remove('border-yellow-400', 'bg-yellow-50');
        }, 3000);
    }
});
<?php endif; ?>
</script>

<?php include 'includes/admin_footer.php'; ?>

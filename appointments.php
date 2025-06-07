<?php

// Configurar zona horaria de Ecuador (Guayaquil)
date_default_timezone_set('America/Guayaquil');

$page_title = "Gestión de Citas - Sistema Administrativo PROINVESTEC SA";
include 'config/database.php';
include 'includes/auth.php';

requireAuth();

$success_message = '';
$error_message = '';

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Procesar acciones
if ($_POST) {
    if (isset($_POST['create_appointment'])) {
        $contact_email = trim($_POST['contact_email']);
        $contact_name = trim($_POST['contact_name']);
        $fecha_hora = $_POST['fecha_hora'];
        $motivo = trim($_POST['motivo']);
        $message_id = !empty($_POST['message_id']) ? (int)$_POST['message_id'] : null;
        
        if (!empty($contact_email) && !empty($contact_name) && !empty($fecha_hora) && !empty($motivo)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO appointments (user_id, contact_email, contact_name, fecha_hora, motivo, message_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$_SESSION['admin_user_id'], $contact_email, $contact_name, $fecha_hora, $motivo, $message_id])) {
                    $success_message = "Cita creada correctamente.";
                    if ($message_id) {
                        logAction($message_id, 'appointment_created', "Cita programada para $fecha_hora");
                    } else {
                        // Log para citas sin mensaje asociado
                        try {
                            $stmt_log = $pdo->prepare("INSERT INTO message_history (message_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                            $stmt_log->execute([null, $_SESSION['admin_user_id'], 'appointment_created', "Nueva cita creada para $contact_name - $fecha_hora"]);
                        } catch (PDOException $e) {
                            // Ignorar error de log
                        }
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Error al crear la cita: " . $e->getMessage();
            }
        } else {
            $error_message = "Por favor, completa todos los campos obligatorios.";
        }
    }
    
    if (isset($_POST['update_appointment'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        
        // VERIFICAR QUE LA CITA PERTENECE AL USUARIO ACTUAL
        if (!canAccessAppointment($appointment_id)) {
            $error_message = "No tienes permisos para modificar esta cita.";
        } else {
            $estado = $_POST['estado'];
            $fecha_hora = $_POST['fecha_hora'];
            $motivo = trim($_POST['motivo']);
            
            try {
                $stmt = $pdo->prepare("UPDATE appointments SET estado = ?, fecha_hora = ?, motivo = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$estado, $fecha_hora, $motivo, $appointment_id, $_SESSION['admin_user_id']])) {
                    $success_message = "Cita actualizada correctamente.";
                    // Log de actualización
                    try {
                        $stmt_log = $pdo->prepare("INSERT INTO message_history (message_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                        $stmt_log->execute([null, $_SESSION['admin_user_id'], 'appointment_updated', "Cita ID $appointment_id actualizada - Estado: $estado"]);
                    } catch (PDOException $e) {
                        // Ignorar error de log
                    }
                } else {
                    $error_message = "No se pudo actualizar la cita.";
                }
            } catch (PDOException $e) {
                $error_message = "Error al actualizar la cita.";
            }
        }
    }
    
    if (isset($_POST['delete_appointment'])) {
        $appointment_id = (int)$_POST['appointment_id'];
        
        // VERIFICAR QUE LA CITA PERTENECE AL USUARIO ACTUAL
        if (!canAccessAppointment($appointment_id)) {
            $error_message = "No tienes permisos para eliminar esta cita.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM appointments WHERE id = ? AND user_id = ?");
                if ($stmt->execute([$appointment_id, $_SESSION['admin_user_id']])) {
                    $success_message = "Cita eliminada correctamente.";
                    // Log de eliminación
                    try {
                        $stmt_log = $pdo->prepare("INSERT INTO message_history (message_id, user_id, action, details) VALUES (?, ?, ?, ?)");
                        $stmt_log->execute([null, $_SESSION['admin_user_id'], 'appointment_deleted', "Cita ID $appointment_id eliminada"]);
                    } catch (PDOException $e) {
                        // Ignorar error de log
                    }
                } else {
                    $error_message = "No se pudo eliminar la cita.";
                }
            } catch (PDOException $e) {
                $error_message = "Error al eliminar la cita.";
            }
        }
    }
}

// Obtener datos para formulario de creación
$create_mode = isset($_GET['create']) && $_GET['create'] == '1';
$prefill_data = [];
if ($create_mode) {
    $prefill_data = [
        'message_id' => isset($_GET['message_id']) ? (int)$_GET['message_id'] : '',
        'email' => isset($_GET['email']) ? $_GET['email'] : '',
        'name' => isset($_GET['name']) ? $_GET['name'] : ''
    ];
}

// Filtros
$estado_filter = isset($_GET['estado']) ? $_GET['estado'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Construir consulta - MODIFICADO para filtrar por usuario
$where_conditions = [];
$params = [];

if (!empty($estado_filter)) {
    $where_conditions[] = "a.estado = ?";
    $params[] = $estado_filter;
}

if (!empty($date_filter)) {
    $where_conditions[] = "DATE(a.fecha_hora) = ?";
    $params[] = $date_filter;
}

$where_clause = !empty($where_conditions) ? "AND " . implode(" AND ", $where_conditions) : "";

// Obtener total de citas para paginación
try {
    if (canViewAllAppointments()) {
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments a 
            JOIN admin_users au ON a.user_id = au.id 
            WHERE 1=1 $where_clause
        ");
        $count_stmt->execute($params);
    } else {
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM appointments a 
            JOIN admin_users au ON a.user_id = au.id 
            WHERE a.user_id = ? $where_clause
        ");
        $params_count = array_merge([$_SESSION['admin_user_id']], $params);
        $count_stmt->execute($params_count);
    }
    $total_appointments = $count_stmt->fetchColumn();
    $total_pages = ceil($total_appointments / $per_page);
} catch (PDOException $e) {
    $total_appointments = 0;
    $total_pages = 1;
}

// Obtener citas - SOLO del usuario actual (o todas si es admin)
try {
    if (canViewAllAppointments()) {
        // Los administradores pueden ver todas las citas
        $stmt = $pdo->prepare("
            SELECT a.*, au.nombre as user_name 
            FROM appointments a 
            JOIN admin_users au ON a.user_id = au.id 
            WHERE 1=1 $where_clause 
            ORDER BY a.fecha_hora ASC
            LIMIT $per_page OFFSET $offset
        ");
        $stmt->execute($params);
    } else {
        // Los usuarios normales solo ven sus propias citas
        $stmt = $pdo->prepare("
            SELECT a.*, au.nombre as user_name 
            FROM appointments a 
            JOIN admin_users au ON a.user_id = au.id 
            WHERE a.user_id = ? $where_clause 
            ORDER BY a.fecha_hora ASC
            LIMIT $per_page OFFSET $offset
        ");
        $params = array_merge([$_SESSION['admin_user_id']], $params);
        $stmt->execute($params);
    }
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $appointments = [];
    $error_message = "Error al cargar las citas.";
}

include 'includes/admin_header.php';
?>

<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex-1">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Gestión de Citas</h1>
                <p class="text-gray-600 mt-2">
                    <?php if (canViewAllAppointments()): ?>
                        Administra todas las citas del sistema
                    <?php else: ?>
                        Administra tus citas programadas
                    <?php endif; ?>
                </p>
            </div>
            <button onclick="toggleCreateForm()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                <i class="fas fa-plus mr-2"></i>Nueva Cita
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
        <h2 class="text-xl font-semibold mb-4">Crear Nueva Cita</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php if (!empty($prefill_data['message_id'])): ?>
            <input type="hidden" name="message_id" value="<?php echo $prefill_data['message_id']; ?>">
            <?php endif; ?>
            
            <div>
                <label for="contact_name" class="block text-sm font-medium text-gray-700 mb-2">Nombre del Cliente *</label>
                <input
                    type="text"
                    id="contact_name"
                    name="contact_name"
                    value="<?php echo htmlspecialchars($prefill_data['name'] ?? ''); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                />
            </div>
            
            <div>
                <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">Email del Cliente *</label>
                <input
                    type="email"
                    id="contact_email"
                    name="contact_email"
                    value="<?php echo htmlspecialchars($prefill_data['email'] ?? ''); ?>"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                />
            </div>
            
            <div class="md:col-span-2">
                <label for="motivo" class="block text-sm font-medium text-gray-700 mb-2">Motivo de la Cita *</label>
                <textarea
                    id="motivo"
                    name="motivo"
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Describe el motivo de la cita..."
                    required
                ></textarea>
            </div>
            
            <div class="md:col-span-2 flex justify-end space-x-3">
                <button type="button" onclick="toggleCreateForm()" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                    Cancelar
                </button>
                <button type="submit" name="create_appointment" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
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
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>
            <div>
                <label for="estado" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select
                    id="estado"
                    name="estado"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Todos los estados</option>
                    <option value="programada" <?php echo $estado_filter === 'programada' ? 'selected' : ''; ?>>Programada</option>
                    <option value="completada" <?php echo $estado_filter === 'completada' ? 'selected' : ''; ?>>Completada</option>
                    <option value="cancelada" <?php echo $estado_filter === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    <option value="reagendada" <?php echo $estado_filter === 'reagendada' ? 'selected' : ''; ?>>Reagendada</option>
                </select>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Filtrar
                </button>
            </div>
            <div>
                <a href="appointments.php" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                    <i class="fas fa-times mr-2"></i>Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Información de paginación -->
    <div class="mb-4 flex justify-between items-center">
        <p class="text-sm text-gray-600">
            Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_appointments); ?> de <?php echo $total_appointments; ?> citas
        </p>
        <div class="text-sm text-gray-600">
            Página <?php echo $page; ?> de <?php echo $total_pages; ?>
        </div>
    </div>

    <!-- Lista de citas -->
    <div class="space-y-4">
        <?php if (empty($appointments)): ?>
        <div class="proinvestec-card p-8 text-center">
            <i class="fas fa-calendar-times text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay citas</h3>
            <p class="text-gray-600">
                <?php if (canViewAllAppointments()): ?>
                    No se encontraron citas en el sistema con los filtros aplicados.
                <?php else: ?>
                    No tienes citas programadas con los filtros aplicados.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <?php foreach ($appointments as $appointment): ?>
        <div class="proinvestec-card p-6">
            <div class="flex justify-between items-start">
                <div class="flex-1">
                    <div class="flex items-center space-x-3 mb-2">
                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($appointment['contact_name']); ?></h3>
                        <span class="px-2 py-1 text-xs font-medium rounded-full <?php 
                            echo $appointment['estado'] === 'programada' ? 'bg-[#004D40] text-white' :
                                ($appointment['estado'] === 'completada' ? 'bg-[#1B5E20] text-white' :
                                ($appointment['estado'] === 'cancelada' ? 'bg-[#7F0000] text-white' : 'bg-[#FF6F00] text-white'));
                        ?>">
                            <?php echo ucfirst($appointment['estado']); ?>
                        </span>
                        <?php if (canViewAllAppointments() && $appointment['user_id'] != $_SESSION['admin_user_id']): ?>
                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800">
                            Cita de: <?php echo htmlspecialchars($appointment['user_name']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="text-sm text-gray-600 space-y-1 mb-3">
                        <p><i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($appointment['contact_email']); ?></p>
                        <p><i class="fas fa-calendar mr-2"></i><?php echo date('d/m/Y H:i', strtotime($appointment['fecha_hora'])); ?></p>
                        <p><i class="fas fa-user mr-2"></i>Asignado a: <?php echo htmlspecialchars($appointment['user_name']); ?></p>
                    </div>
                    <div class="bg-gray-50 p-3 rounded-md">
                        <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($appointment['motivo'])); ?></p>
                    </div>
                </div>
                <div class="flex space-x-2 ml-4">
                    <?php if ($appointment['user_id'] == $_SESSION['admin_user_id'] || canViewAllAppointments()): ?>
                    <button onclick="toggleEditForm(<?php echo $appointment['id']; ?>)" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors text-sm">
                        <i class="fas fa-edit mr-1"></i>Editar
                    </button>
                    <button onclick="showDeleteConfirm(<?php echo $appointment['id']; ?>)" class="px-3 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition-colors text-sm">
                        <i class="fas fa-trash mr-1"></i>Eliminar
                    </button>
                    <?php else: ?>
                    <span class="px-3 py-1 bg-gray-100 text-gray-500 rounded-md text-sm">
                        <i class="fas fa-lock mr-1"></i>Solo lectura
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulario de edición - Solo si el usuario puede editar -->
            <?php if ($appointment['user_id'] == $_SESSION['admin_user_id'] || canViewAllAppointments()): ?>
            <div id="edit-form-<?php echo $appointment['id']; ?>" class="hidden mt-4 pt-4 border-t">
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                        <select name="estado" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Motivo</label>
                        <textarea
                            name="motivo"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required
                        ><?php echo htmlspecialchars($appointment['motivo']); ?></textarea>
                    </div>
                    
                    <div class="md:col-span-2 flex justify-end space-x-3">
                        <button type="button" onclick="toggleEditForm(<?php echo $appointment['id']; ?>)" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" name="update_appointment" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                            <i class="fas fa-save mr-2"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Modal de confirmación para eliminar - Solo si el usuario puede eliminar -->
        <?php if ($appointment['user_id'] == $_SESSION['admin_user_id'] || canViewAllAppointments()): ?>
        <div id="delete-modal-<?php echo $appointment['id']; ?>" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Confirmar eliminación</h3>
                <p class="text-gray-600 mb-6">¿Estás seguro de que deseas eliminar esta cita? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button onclick="hideDeleteConfirm(<?php echo $appointment['id']; ?>)" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Cancelar
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                        <button type="submit" name="delete_appointment" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
               class="px-3 py-2 border rounded-md transition-colors <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
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

function showDeleteConfirm(appointmentId) {
    document.getElementById(`delete-modal-${appointmentId}`).classList.remove('hidden');
}

function hideDeleteConfirm(appointmentId) {
    document.getElementById(`delete-modal-${appointmentId}`).classList.add('hidden');
}
</script>

<?php include 'includes/admin_footer.php'; ?>

<?php
$page_title = "Notificaciones - Sistema Administrativo PROINVESTEC SA";
include 'config/database.php';
include 'includes/auth.php';

requireAuth();

$success_message = '';
$error_message = '';

// Procesar acciones
if ($_POST) {
    if (isset($_POST['mark_as_read'])) {
        $notification_id = (int)$_POST['notification_id'];
        if (markNotificationAsRead($notification_id, $_SESSION['admin_user_id'])) {
            $success_message = "Notificación marcada como leída.";
        }
    }
    
    if (isset($_POST['mark_all_as_read'])) {
        if (markAllNotificationsAsRead($_SESSION['admin_user_id'])) {
            $success_message = "Todas las notificaciones han sido marcadas como leídas.";
        }
    }
    
    if (isset($_POST['delete_notification'])) {
        $notification_id = (int)$_POST['notification_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$notification_id, $_SESSION['admin_user_id']])) {
                $success_message = "Notificación eliminada.";
            }
        } catch (PDOException $e) {
            $error_message = "Error al eliminar la notificación.";
        }
    }
}

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Filtros
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Construir consulta
$where_conditions = ["user_id = ?"];
$params = [$_SESSION['admin_user_id']];

if (!empty($type_filter)) {
    $where_conditions[] = "type = ?";
    $params[] = $type_filter;
}

if ($status_filter === 'unread') {
    $where_conditions[] = "is_read = FALSE";
} elseif ($status_filter === 'read') {
    $where_conditions[] = "is_read = TRUE";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Obtener total de notificaciones para paginación
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications $where_clause");
    $count_stmt->execute($params);
    $total_notifications = $count_stmt->fetchColumn();
    $total_pages = ceil($total_notifications / $per_page);
} catch (PDOException $e) {
    $total_notifications = 0;
    $total_pages = 1;
}

// Obtener notificaciones con paginación
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        $where_clause 
        ORDER BY created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
    $error_message = "Error al cargar las notificaciones.";
}

// Función para obtener el ícono de la notificación
function getNotificationIcon($type) {
    switch ($type) {
        case 'message_comment':
            return 'fas fa-comment';
        case 'message_status':
            return 'fas fa-envelope';
        case 'internal_appointment_invite':
            return 'fas fa-calendar-plus';
        case 'internal_appointment_today':
            return 'fas fa-clock';
        case 'internal_appointment_status':
            return 'fas fa-calendar-check';
        case 'internal_appointment_modified':
            return 'fas fa-calendar-edit';
        default:
            return 'fas fa-bell';
    }
}

// Función para obtener la URL de redirección
function getNotificationUrl($reference_type, $reference_id) {
    switch ($reference_type) {
        case 'message':
            return "messages.php?highlight={$reference_id}#message-{$reference_id}";
        case 'internal_appointment':
            return "internal-appointments.php?highlight={$reference_id}#appointment-{$reference_id}";
        default:
            return 'dashboard.php';
    }
}

include 'includes/admin_header.php';
?>

<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex-1">
    <div class="mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Notificaciones</h1>
                <p class="text-gray-600 mt-2">Gestiona todas tus notificaciones del sistema</p>
            </div>
            <div class="flex space-x-3">
                <?php if ($total_notifications > 0): ?>
                <form method="POST" class="inline">
                    <button type="submit" name="mark_all_as_read" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                        <i class="fas fa-check-double mr-2"></i>Marcar todas como leídas
                    </button>
                </form>
                <?php endif; ?>
            </div>
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

    <!-- Filtros -->
    <div class="proinvestec-card p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label for="type" class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                <select
                    id="type"
                    name="type"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Todos los tipos</option>
                    <option value="message_comment" <?php echo $type_filter === 'message_comment' ? 'selected' : ''; ?>>Comentarios en mensajes</option>
                    <option value="message_status" <?php echo $type_filter === 'message_status' ? 'selected' : ''; ?>>Cambios de estado</option>
                    <option value="internal_appointment_invite" <?php echo $type_filter === 'internal_appointment_invite' ? 'selected' : ''; ?>>Invitaciones a reuniones</option>
                    <option value="internal_appointment_today" <?php echo $type_filter === 'internal_appointment_today' ? 'selected' : ''; ?>>Reuniones de hoy</option>
                    <option value="internal_appointment_status" <?php echo $type_filter === 'internal_appointment_status' ? 'selected' : ''; ?>>Estados de reuniones</option>
                    <option value="internal_appointment_modified" <?php echo $type_filter === 'internal_appointment_modified' ? 'selected' : ''; ?>>Reuniones modificadas</option>
                </select>
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select
                    id="status"
                    name="status"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Todas</option>
                    <option value="unread" <?php echo $status_filter === 'unread' ? 'selected' : ''; ?>>No leídas</option>
                    <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Leídas</option>
                </select>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Filtrar
                </button>
            </div>
            <div>
                <a href="notifications.php" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                    <i class="fas fa-times mr-2"></i>Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Información de paginación -->
    <div class="mb-4 flex justify-between items-center">
        <p class="text-sm text-gray-600">
            Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_notifications); ?> de <?php echo $total_notifications; ?> notificaciones
        </p>
        <div class="text-sm text-gray-600">
            Página <?php echo $page; ?> de <?php echo $total_pages; ?>
        </div>
    </div>

    <!-- Lista de notificaciones -->
    <div class="space-y-4">
        <?php if (empty($notifications)): ?>
        <div class="proinvestec-card p-8 text-center">
            <i class="fas fa-bell-slash text-gray-400 text-4xl mb-4"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No hay notificaciones</h3>
            <p class="text-gray-600">No se encontraron notificaciones con los filtros aplicados.</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
        <div class="proinvestec-card p-6 <?php echo !$notification['is_read'] ? 'border-l-4 border-blue-500 bg-blue-50' : ''; ?>">
            <div class="flex items-start space-x-4">
                <div class="flex-shrink-0">
                    <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                        <i class="<?php echo getNotificationIcon($notification['type']); ?> text-blue-600"></i>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <?php echo htmlspecialchars($notification['title']); ?>
                            <?php if (!$notification['is_read']): ?>
                            <span class="ml-2 inline-flex items-center px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">
                                Nueva
                            </span>
                            <?php endif; ?>
                        </h3>
                        <div class="flex items-center space-x-2">
                            <?php if (!$notification['is_read']): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" name="mark_as_read" class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-check mr-1"></i>Marcar como leída
                                </button>
                            </form>
                            <?php endif; ?>
                            <button onclick="showDeleteConfirm(<?php echo $notification['id']; ?>)" class="text-red-600 hover:text-red-800 text-sm">
                                <i class="fas fa-trash mr-1"></i>Eliminar
                            </button>
                        </div>
                    </div>
                    <p class="text-gray-700 mb-3"><?php echo htmlspecialchars($notification['message']); ?></p>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-clock mr-1"></i>
                            <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                        </span>
                        <?php if ($notification['reference_type'] && $notification['reference_id']): ?>
                        <a href="<?php echo getNotificationUrl($notification['reference_type'], $notification['reference_id']); ?>" 
                           class="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i>Ver detalles
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para eliminar -->
        <div id="delete-modal-<?php echo $notification['id']; ?>" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Confirmar eliminación</h3>
                <p class="text-gray-600 mb-6">¿Estás seguro de que deseas eliminar esta notificación?</p>
                <div class="flex justify-end space-x-3">
                    <button onclick="hideDeleteConfirm(<?php echo $notification['id']; ?>)" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Cancelar
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                        <button type="submit" name="delete_notification" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-8 flex justify-center">
        <nav class="flex items-center space-x-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
               class="px-3 py-2 border rounded-md transition-colors <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&type=<?php echo urlencode($type_filter); ?>&status=<?php echo urlencode($status_filter); ?>" 
               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</main>

<script>
function showDeleteConfirm(notificationId) {
    document.getElementById(`delete-modal-${notificationId}`).classList.remove('hidden');
}

function hideDeleteConfirm(notificationId) {
    document.getElementById(`delete-modal-${notificationId}`).classList.add('hidden');
}

// Auto-refresh cada 30 segundos
setInterval(() => {
    // Solo recargar si no hay modales abiertos
    const modals = document.querySelectorAll('[id^="delete-modal-"]');
    const hasOpenModal = Array.from(modals).some(modal => !modal.classList.contains('hidden'));
    
    if (!hasOpenModal) {
        location.reload();
    }
}, 30000);
</script>

<?php include 'includes/admin_footer.php'; ?>

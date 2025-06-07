<?php
include 'config/database.php';
include 'includes/auth.php';

requireAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_notifications':
        $notifications = getUnreadNotifications($_SESSION['admin_user_id'], 10);
        $count = getUnreadNotificationsCount($_SESSION['admin_user_id']);
        
        // Formatear las notificaciones para el frontend
        $formatted_notifications = [];
        foreach ($notifications as $notification) {
            $formatted_notifications[] = [
                'id' => $notification['id'],
                'type' => $notification['type'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'reference_type' => $notification['reference_type'],
                'reference_id' => $notification['reference_id'],
                'created_at' => $notification['created_at'],
                'time_ago' => getTimeAgo($notification['created_at']),
                'icon' => getNotificationIcon($notification['type']),
                'url' => getNotificationUrl($notification['reference_type'], $notification['reference_id'])
            ];
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $formatted_notifications,
            'count' => $count
        ]);
        break;
        
    case 'mark_as_read':
        $notification_id = (int)($_POST['notification_id'] ?? 0);
        if ($notification_id > 0) {
            $success = markNotificationAsRead($notification_id, $_SESSION['admin_user_id']);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'error' => 'ID de notificación inválido']);
        }
        break;
        
    case 'mark_all_as_read':
        $success = markAllNotificationsAsRead($_SESSION['admin_user_id']);
        echo json_encode(['success' => $success]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        break;
}

// Función para obtener el tiempo transcurrido
function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Hace un momento';
    if ($time < 3600) return 'Hace ' . floor($time/60) . ' min';
    if ($time < 86400) return 'Hace ' . floor($time/3600) . ' h';
    if ($time < 2592000) return 'Hace ' . floor($time/86400) . ' días';
    
    return date('d/m/Y', strtotime($datetime));
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
?>

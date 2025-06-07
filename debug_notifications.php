<?php
include 'config/database.php';
include 'includes/auth.php';

requireAuth();

// Solo para administradores
if (!isAdmin()) {
    die('Acceso denegado');
}

echo "<h1>Debug de Notificaciones</h1>";

// Mostrar todas las notificaciones del usuario actual
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$_SESSION['admin_user_id']]);
    $notifications = $stmt->fetchAll();
    
    echo "<h2>Notificaciones del usuario actual (ID: {$_SESSION['admin_user_id']}):</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Tipo</th><th>Título</th><th>Leída</th><th>Creada</th><th>Leída en</th></tr>";
    
    foreach ($notifications as $notification) {
        echo "<tr>";
        echo "<td>{$notification['id']}</td>";
        echo "<td>{$notification['type']}</td>";
        echo "<td>{$notification['title']}</td>";
        echo "<td>" . ($notification['is_read'] ? 'SÍ' : 'NO') . "</td>";
        echo "<td>{$notification['created_at']}</td>";
        echo "<td>" . ($notification['read_at'] ?? 'No leída') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Contar notificaciones no leídas
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt_count->execute([$_SESSION['admin_user_id']]);
    $unread_count = $stmt_count->fetchColumn();
    
    echo "<h3>Total de notificaciones no leídas: {$unread_count}</h3>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Botón para crear notificación de prueba
if (isset($_POST['create_test'])) {
    $result = createNotification(
        $_SESSION['admin_user_id'],
        'message_comment',
        'Notificación de prueba',
        'Esta es una notificación de prueba creada desde el debug',
        'message',
        1
    );
    
    if ($result) {
        echo "<p style='color: green;'>Notificación de prueba creada exitosamente</p>";
    } else {
        echo "<p style='color: red;'>Error al crear notificación de prueba</p>";
    }
    
    // Recargar la página para mostrar la nueva notificación
    echo "<script>setTimeout(() => location.reload(), 1000);</script>";
}

// Botón para marcar todas como leídas
if (isset($_POST['mark_all_read'])) {
    $result = markAllNotificationsAsRead($_SESSION['admin_user_id']);
    
    if ($result) {
        echo "<p style='color: green;'>Todas las notificaciones marcadas como leídas</p>";
    } else {
        echo "<p style='color: red;'>Error al marcar notificaciones como leídas</p>";
    }
    
    // Recargar la página
    echo "<script>setTimeout(() => location.reload(), 1000);</script>";
}
?>

<form method="POST" style="margin: 20px 0;">
    <button type="submit" name="create_test" style="padding: 10px; background: blue; color: white; border: none; cursor: pointer;">
        Crear Notificación de Prueba
    </button>
</form>

<form method="POST" style="margin: 20px 0;">
    <button type="submit" name="mark_all_read" style="padding: 10px; background: green; color: white; border: none; cursor: pointer;">
        Marcar Todas como Leídas
    </button>
</form>

<p><a href="dashboard.php">Volver al Dashboard</a></p>

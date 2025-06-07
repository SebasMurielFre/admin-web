<?php
// Archivo para ejecutar notificaciones automáticas (debe ejecutarse diariamente)
include 'config/database.php';
include 'includes/auth.php';

// Este archivo debe ejecutarse como cron job diariamente
// Ejemplo: 0 8 * * * /usr/bin/php /path/to/cron_notifications.php

try {
    // Notificar sobre citas del día actual
    notifyTodayAppointments();
    
    // Limpiar notificaciones antiguas (más de 30 días)
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE created_at < CURRENT_DATE - INTERVAL '30 days'");
    $stmt->execute();
    
    echo "Notificaciones procesadas correctamente: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    error_log("Error en cron de notificaciones: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
}
?>

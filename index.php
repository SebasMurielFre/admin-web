<?php
// Página principal del sistema administrativo - redirige al login o dashboard
include 'config/database.php';
include 'includes/auth.php';

if (isAuthenticated()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>

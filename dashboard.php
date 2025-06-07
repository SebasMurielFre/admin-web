<?php
$page_title = "Dashboard - Sistema Administrativo PROINVESTEC SA";
include 'config/database.php';
include 'includes/auth.php';

requireAuth();

// Obtener estadísticas
try {
    // Estadísticas de mensajes (todos los usuarios pueden ver todas)
    $messages_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_messages,
            COUNT(CASE WHEN status = 'pendiente' THEN 1 END) as pending_messages,
            COUNT(CASE WHEN status = 'respondido' THEN 1 END) as responded_messages
        FROM contact_messages
    ")->fetch();

    // Estadísticas de citas - MODIFICADO para mostrar solo las del usuario actual (o todas si es admin)
    if (canViewAllAppointments()) {
        // Los administradores ven estadísticas de todas las citas
        $appointments_stats = $pdo->query("
            SELECT 
                COUNT(*) as total_appointments,
                COUNT(CASE WHEN DATE(fecha_hora) = CURRENT_DATE THEN 1 END) as today_appointments,
                COUNT(CASE WHEN fecha_hora >= CURRENT_DATE AND fecha_hora <= CURRENT_DATE + INTERVAL '7 days' THEN 1 END) as upcoming_appointments
            FROM appointments
            WHERE estado != 'cancelada'
        ")->fetch();
    } else {
        // Los usuarios normales solo ven estadísticas de sus propias citas
        $appointments_stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_appointments,
                COUNT(CASE WHEN DATE(fecha_hora) = CURRENT_DATE THEN 1 END) as today_appointments,
                COUNT(CASE WHEN fecha_hora >= CURRENT_DATE AND fecha_hora <= CURRENT_DATE + INTERVAL '7 days' THEN 1 END) as upcoming_appointments
            FROM appointments
            WHERE estado != 'cancelada' AND user_id = ?
        ");
        $appointments_stats_stmt->execute([$_SESSION['admin_user_id']]);
        $appointments_stats = $appointments_stats_stmt->fetch();
    }

    // Estadísticas de usuarios (solo para administradores)
    if (canViewAllAppointments()) {
        $users_stats = $pdo->query("SELECT COUNT(*) as total_users FROM admin_users")->fetch();
    } else {
        $users_stats = ['total_users' => 0];
    }

} catch (PDOException $e) {
    $messages_stats = ['total_messages' => 0, 'pending_messages' => 0, 'responded_messages' => 0];
    $appointments_stats = ['total_appointments' => 0, 'today_appointments' => 0, 'upcoming_appointments' => 0];
    $users_stats = ['total_users' => 0];
}

include 'includes/admin_header.php';
?>

<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex-1">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600 mt-2">
            <?php if (canViewAllAppointments()): ?>
                Resumen general del sistema de administración
            <?php else: ?>
                Resumen de tu actividad en el sistema
            <?php endif; ?>
        </p>
    </div>

    <!-- Estadísticas principales -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">
        <div class="proinvestec-card p-8 bg-gradient-to-br from-blue-50 to-blue-100 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-blue-700 uppercase tracking-wide">Total Mensajes</p>
                    <p class="text-3xl font-bold text-blue-900 mt-2"><?php echo $messages_stats['total_messages']; ?></p>
                    <p class="text-xs text-blue-600 mt-1">Mensajes recibidos en total</p>
                </div>
                <div class="p-4 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg">
                    <i class="fas fa-envelope text-white text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="proinvestec-card p-8 bg-gradient-to-br from-orange-50 to-orange-100 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-orange-700 uppercase tracking-wide">Mensajes Pendientes</p>
                    <p class="text-3xl font-bold text-orange-900 mt-2"><?php echo $messages_stats['pending_messages']; ?></p>
                    <p class="text-xs text-orange-600 mt-1">Requieren atención</p>
                </div>
                <div class="p-4 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg">
                    <i class="fas fa-clock text-white text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="proinvestec-card p-8 bg-gradient-to-br from-green-50 to-green-100 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-700 uppercase tracking-wide">Mensajes Respondidos</p>
                    <p class="text-3xl font-bold text-green-900 mt-2"><?php echo $messages_stats['responded_messages']; ?></p>
                    <p class="text-xs text-green-600 mt-1">Completados exitosamente</p>
                </div>
                <div class="p-4 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg">
                    <i class="fas fa-check-circle text-white text-2xl"></i>
                </div>
            </div>
        </div>

        <div class="proinvestec-card p-8 bg-gradient-to-br from-purple-50 to-purple-100 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-purple-700 uppercase tracking-wide">
                        <?php echo canViewAllAppointments() ? 'Citas Programadas' : 'Mis Citas'; ?>
                    </p>
                    <p class="text-3xl font-bold text-purple-900 mt-2"><?php echo $appointments_stats['total_appointments']; ?></p>
                    <p class="text-xs text-purple-600 mt-1">
                        <?php echo canViewAllAppointments() ? 'Total de citas agendadas' : 'Citas que tienes programadas'; ?>
                    </p>
                </div>
                <div class="p-4 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg">
                    <i class="fas fa-calendar text-white text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Información adicional -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
        <div class="proinvestec-card p-8 bg-gradient-to-br from-indigo-50 to-indigo-100">
            <div class="flex items-center space-x-4 mb-6">
                <div class="p-3 bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl shadow-lg">
                    <i class="fas fa-calendar-day text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-indigo-900">
                        <?php echo canViewAllAppointments() ? 'Citas de Hoy' : 'Mis Citas de Hoy'; ?>
                    </h3>
                    <p class="text-indigo-700 text-sm">
                        <?php echo canViewAllAppointments() ? 'Citas programadas para el día de hoy' : 'Tus citas programadas para hoy'; ?>
                    </p>
                </div>
            </div>
            <div class="text-4xl font-bold text-indigo-900 mb-3"><?php echo $appointments_stats['today_appointments']; ?></div>
            <p class="text-sm text-indigo-700 bg-indigo-50 p-3 rounded-lg">
                <?php echo $appointments_stats['upcoming_appointments']; ?> 
                <?php echo canViewAllAppointments() ? 'citas próximas esta semana' : 'de tus citas próximas esta semana'; ?>
            </p>
        </div>

        <?php if (canViewAllAppointments()): ?>
        <div class="proinvestec-card p-8 bg-gradient-to-br from-teal-50 to-teal-100">
            <div class="flex items-center space-x-4 mb-6">
                <div class="p-3 bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl shadow-lg">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-teal-900">Usuarios del Sistema</h3>
                    <p class="text-teal-700 text-sm">Total de usuarios registrados</p>
                </div>
            </div>
            <div class="text-4xl font-bold text-teal-900 mb-3"><?php echo $users_stats['total_users']; ?></div>
            <p class="text-sm text-teal-700 bg-teal-50 p-3 rounded-lg">Administradores y usuarios activos</p>
        </div>
        <?php else: ?>
        <div class="proinvestec-card p-8 bg-gradient-to-br from-teal-50 to-teal-100">
            <div class="flex items-center space-x-4 mb-6">
                <div class="p-3 bg-gradient-to-br from-teal-500 to-teal-600 rounded-xl shadow-lg">
                    <i class="fas fa-user-check text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-teal-900">Mi Perfil</h3>
                    <p class="text-teal-700 text-sm">Información de tu cuenta</p>
                </div>
            </div>
            <div class="text-lg font-bold text-teal-900 mb-3"><?php echo htmlspecialchars($_SESSION['admin_user_nombre']); ?></div>
            <p class="text-sm text-teal-700 bg-teal-50 p-3 rounded-lg">
                Rol: <?php echo ucfirst($_SESSION['admin_user_rol']); ?> | 
                Email: <?php echo htmlspecialchars($_SESSION['admin_user_email']); ?>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Acciones rápidas -->
    <div class="proinvestec-card p-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-3">Acciones Rápidas</h2>
        <p class="text-gray-600 mb-8">Accede rápidamente a las funciones más utilizadas</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <a href="messages.php" class="group flex items-center space-x-4 p-6 bg-gradient-to-br from-blue-50 to-blue-100 hover:from-blue-100 hover:to-blue-200 rounded-xl transition-all duration-300 border border-blue-200 hover:border-blue-300 hover:shadow-lg">
                <div class="p-3 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg group-hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-envelope text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-blue-900 group-hover:text-blue-800">Ver Mensajes</h3>
                    <p class="text-sm text-blue-700">Gestionar mensajes de contacto</p>
                </div>
            </a>

            <a href="appointments.php" class="group flex items-center space-x-4 p-6 bg-gradient-to-br from-purple-50 to-purple-100 hover:from-purple-100 hover:to-purple-200 rounded-xl transition-all duration-300 border border-purple-200 hover:border-purple-300 hover:shadow-lg">
                <div class="p-3 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg group-hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-calendar text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-purple-900 group-hover:text-purple-800">
                        <?php echo canViewAllAppointments() ? 'Gestionar Citas' : 'Mis Citas'; ?>
                    </h3>
                    <p class="text-sm text-purple-700">
                        <?php echo canViewAllAppointments() ? 'Administrar todas las citas' : 'Ver y programar tus citas'; ?>
                    </p>
                </div>
            </a>

            <a href="calendar.php" class="group flex items-center space-x-4 p-6 bg-gradient-to-br from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 rounded-xl transition-all duration-300 border border-green-200 hover:border-green-300 hover:shadow-lg">
                <div class="p-3 bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg group-hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-calendar-alt text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-green-900 group-hover:text-green-800">Ver Calendario</h3>
                    <p class="text-sm text-green-700">
                        <?php echo canViewAllAppointments() ? 'Vista de calendario completa' : 'Tu calendario personal'; ?>
                    </p>
                </div>
            </a>

            <?php if (isAdmin()): ?>
            <a href="users.php" class="group flex items-center space-x-4 p-6 bg-gradient-to-br from-orange-50 to-orange-100 hover:from-orange-100 hover:to-orange-200 rounded-xl transition-all duration-300 border border-orange-200 hover:border-orange-300 hover:shadow-lg">
                <div class="p-3 bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl shadow-lg group-hover:shadow-xl transition-all duration-300">
                    <i class="fas fa-users text-white text-xl"></i>
                </div>
                <div>
                    <h3 class="font-bold text-orange-900 group-hover:text-orange-800">Gestionar Usuarios</h3>
                    <p class="text-sm text-orange-700">Administrar usuarios del sistema</p>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include 'includes/admin_footer.php'; ?>

<?php
session_start();

// Función para validar contraseña según las reglas establecidas
function validatePassword($password) {
    $errors = [];
    
    // Verificar longitud (8-16 caracteres)
    if (strlen($password) < 8 || strlen($password) > 16) {
        $errors[] = "La contraseña debe tener entre 8 y 16 caracteres.";
    }
    
    // Verificar al menos una letra mayúscula
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "La contraseña debe contener al menos una letra mayúscula.";
    }
    
    // Verificar al menos una letra minúscula
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "La contraseña debe contener al menos una letra minúscula.";
    }
    
    // Verificar al menos un carácter especial
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "La contraseña debe contener al menos un carácter especial (!@#$%^&*()_+-=[]{}|;':\",./<>?).";
    }
    
    return $errors;
}

// Función para hashear contraseña con bcrypt
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

// Función para verificar contraseña con hash
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['admin_user_id']) && !empty($_SESSION['admin_user_id']);
}

// Función para verificar si el usuario es administrador
function isAdmin() {
    return isset($_SESSION['admin_user_rol']) && $_SESSION['admin_user_rol'] === 'admin';
}

// Función para requerir autenticación
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
}

// Función para requerir permisos de administrador
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}

// Función para obtener datos del usuario actual
function getCurrentUser() {
    global $pdo;
    
    if (!isAuthenticated()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, cedula, email, rol, created_at FROM admin_users WHERE id = ?");
        $stmt->execute([$_SESSION['admin_user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// Función para hacer login (MODIFICADA para usar bcrypt)
function login($email, $password) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Verificar si el usuario existe y la contraseña es correcta
        if ($user && verifyPassword($password, $user['password_hash'])) {
            $_SESSION['admin_user_id'] = $user['id'];
            $_SESSION['admin_user_nombre'] = $user['nombre'];
            $_SESSION['admin_user_email'] = $user['email'];
            $_SESSION['admin_user_rol'] = $user['rol'];
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        return false;
    }
}

// Función para hacer logout
function logout() {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Función para registrar acción en el historial
function logAction($message_id, $action, $details = null) {
    global $pdo;
    
    if (!isAuthenticated()) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO message_history (message_id, user_id, action, details) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$message_id, $_SESSION['admin_user_id'], $action, $details]);
    } catch (PDOException $e) {
        return false;
    }
}

// FUNCIÓN: Verificar si una cita pertenece al usuario actual
function canAccessAppointment($appointment_id) {
    global $pdo;
    
    if (!isAuthenticated()) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE id = ? AND user_id = ?");
        $stmt->execute([$appointment_id, $_SESSION['admin_user_id']]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// FUNCIÓN: Verificar si el usuario puede ver todas las citas (solo admins)
function canViewAllAppointments() {
    return isAdmin();
}

// NUEVA FUNCIÓN: Verificar si el usuario puede acceder a una cita interna
function canAccessInternalAppointment($appointment_id) {
    global $pdo;
    
    if (!isAuthenticated()) {
        return false;
    }
    
    try {
        // Verificar si es el creador o un participante
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM internal_appointments ia 
            LEFT JOIN internal_appointment_participants iap ON ia.id = iap.appointment_id 
            WHERE ia.id = ? AND (ia.created_by_user_id = ? OR iap.user_id = ?)
        ");
        $stmt->execute([$appointment_id, $_SESSION['admin_user_id'], $_SESSION['admin_user_id']]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// NUEVA FUNCIÓN: Obtener todos los usuarios para selección
function getAllUsers() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, email, rol FROM admin_users WHERE id != ? ORDER BY nombre ASC");
        $stmt->execute([$_SESSION['admin_user_id']]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// NUEVA FUNCIÓN: Crear cita interna
function createInternalAppointment($titulo, $descripcion, $fecha_hora, $duracion_minutos, $participant_ids) {
    global $pdo;
    
    if (!isAuthenticated()) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Crear la cita
        $stmt = $pdo->prepare("
            INSERT INTO internal_appointments (created_by_user_id, titulo, descripcion, fecha_hora, duracion_minutos) 
            VALUES (?, ?, ?, ?, ?) RETURNING id
        ");
        $stmt->execute([$_SESSION['admin_user_id'], $titulo, $descripcion, $fecha_hora, $duracion_minutos]);
        $appointment_id = $stmt->fetchColumn();
        
        // Agregar participantes
        $stmt_participant = $pdo->prepare("
            INSERT INTO internal_appointment_participants (appointment_id, user_id, status) 
            VALUES (?, ?, 'pendiente')
        ");
        
        foreach ($participant_ids as $user_id) {
            $stmt_participant->execute([$appointment_id, $user_id]);
        }
        
        // Agregar al creador como participante aceptado
        $stmt_creator = $pdo->prepare("
            INSERT INTO internal_appointment_participants (appointment_id, user_id, status) 
            VALUES (?, ?, 'aceptada')
        ");
        $stmt_creator->execute([$appointment_id, $_SESSION['admin_user_id']]);
        
        $pdo->commit();
        return $appointment_id;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

// NUEVA FUNCIÓN: Crear usuario con validación y hasheo de contraseña
function createUser($nombre, $cedula, $email, $password, $rol) {
    global $pdo;
    
    // Validar contraseña
    $password_errors = validatePassword($password);
    if (!empty($password_errors)) {
        return ['success' => false, 'errors' => $password_errors];
    }
    
    try {
        // Verificar si el email o cédula ya existen
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE email = ? OR cedula = ?");
        $check_stmt->execute([$email, $cedula]);
        
        if ($check_stmt->fetchColumn() > 0) {
            return ['success' => false, 'errors' => ['Ya existe un usuario con ese email o cédula.']];
        }
        
        // Hashear la contraseña
        $password_hash = hashPassword($password);
        
        // Crear el usuario
        $stmt = $pdo->prepare("INSERT INTO admin_users (nombre, cedula, email, password_hash, rol) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$nombre, $cedula, $email, $password_hash, $rol])) {
            return ['success' => true, 'message' => 'Usuario creado correctamente.'];
        } else {
            return ['success' => false, 'errors' => ['Error al crear el usuario.']];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ['Error de base de datos: ' . $e->getMessage()]];
    }
}

// NUEVA FUNCIÓN: Actualizar usuario con validación opcional de contraseña
function updateUser($user_id, $nombre, $cedula, $email, $rol, $new_password = null) {
    global $pdo;
    
    // Si se proporciona nueva contraseña, validarla
    if (!empty($new_password)) {
        $password_errors = validatePassword($new_password);
        if (!empty($password_errors)) {
            return ['success' => false, 'errors' => $password_errors];
        }
    }
    
    try {
        // Verificar si el email o cédula ya existen en otros usuarios
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE (email = ? OR cedula = ?) AND id != ?");
        $check_stmt->execute([$email, $cedula, $user_id]);
        
        if ($check_stmt->fetchColumn() > 0) {
            return ['success' => false, 'errors' => ['Ya existe otro usuario con ese email o cédula.']];
        }
        
        // Actualizar usuario
        if (!empty($new_password)) {
            // Con nueva contraseña
            $password_hash = hashPassword($new_password);
            $stmt = $pdo->prepare("UPDATE admin_users SET nombre = ?, cedula = ?, email = ?, password_hash = ?, rol = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$nombre, $cedula, $email, $password_hash, $rol, $user_id]);
        } else {
            // Sin cambiar contraseña
            $stmt = $pdo->prepare("UPDATE admin_users SET nombre = ?, cedula = ?, email = ?, rol = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $result = $stmt->execute([$nombre, $cedula, $email, $rol, $user_id]);
        }
        
        if ($result) {
            return ['success' => true, 'message' => 'Usuario actualizado correctamente.'];
        } else {
            return ['success' => false, 'errors' => ['Error al actualizar el usuario.']];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'errors' => ['Error de base de datos: ' . $e->getMessage()]];
    }
}

// NUEVAS FUNCIONES PARA SISTEMA DE NOTIFICACIONES

// Función para crear una notificación
function createNotification($user_id, $type, $title, $message, $reference_type = null, $reference_id = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, reference_type, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $type, $title, $message, $reference_type, $reference_id]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

// Función para obtener notificaciones no leídas de un usuario
function getUnreadNotifications($user_id, $limit = 10) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = FALSE 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error getting unread notifications: " . $e->getMessage());
        return [];
    }
}

// Función para contar notificaciones no leídas
function getUnreadNotificationsCount($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting unread notifications: " . $e->getMessage());
        return 0;
    }
}

// Función para marcar una notificación como leída
function markNotificationAsRead($notification_id, $user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND user_id = ?
        ");
        $result = $stmt->execute([$notification_id, $user_id]);
        
        // Verificar si se actualizó alguna fila
        return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

// Función para marcar todas las notificaciones como leídas
function markAllNotificationsAsRead($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
            WHERE user_id = ? AND is_read = FALSE
        ");
        $result = $stmt->execute([$user_id]);
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error marking all notifications as read: " . $e->getMessage());
        return false;
    }
}

// Actualizar la función notifyMessageComment para notificar a TODOS los usuarios
function notifyMessageComment($message_id, $commenter_user_id) {
    global $pdo;
    
    try {
        // Obtener información del mensaje
        $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        $message = $stmt->fetch();
        
        if (!$message) return false;
        
        // Obtener nombre del usuario que comentó
        $stmt = $pdo->prepare("SELECT nombre FROM admin_users WHERE id = ?");
        $stmt->execute([$commenter_user_id]);
        $commenter_name = $stmt->fetchColumn();
        
        // Obtener TODOS los usuarios del sistema (excepto el que acaba de comentar)
        $stmt = $pdo->prepare("SELECT id, nombre FROM admin_users WHERE id != ?");
        $stmt->execute([$commenter_user_id]);
        $users_to_notify = $stmt->fetchAll();
        
        // Crear notificaciones para TODOS los usuarios
        foreach ($users_to_notify as $user) {
            createNotification(
                $user['id'],
                'message_comment',
                'Nuevo comentario en mensaje',
                "{$commenter_name} agregó un comentario al mensaje de {$message['name']}",
                'message',
                $message_id
            );
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error notifying message comment: " . $e->getMessage());
        return false;
    }
}

// Actualizar la función notifyMessageStatusChange para notificar a TODOS los usuarios
function notifyMessageStatusChange($message_id, $new_status, $changed_by_user_id) {
    global $pdo;
    
    try {
        // Obtener información del mensaje
        $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE id = ?");
        $stmt->execute([$message_id]);
        $message = $stmt->fetch();
        
        if (!$message) return false;
        
        // Obtener nombre del usuario que cambió el estado
        $stmt = $pdo->prepare("SELECT nombre FROM admin_users WHERE id = ?");
        $stmt->execute([$changed_by_user_id]);
        $changer_name = $stmt->fetchColumn();
        
        // Obtener TODOS los usuarios del sistema (excepto el que cambió el estado)
        $stmt = $pdo->prepare("SELECT id, nombre FROM admin_users WHERE id != ?");
        $stmt->execute([$changed_by_user_id]);
        $users_to_notify = $stmt->fetchAll();
        
        // Crear notificaciones para TODOS los usuarios
        foreach ($users_to_notify as $user) {
            createNotification(
                $user['id'],
                'message_status',
                'Estado de mensaje actualizado',
                "{$changer_name} cambió el estado del mensaje de {$message['name']} a {$new_status}",
                'message',
                $message_id
            );
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error notifying message status change: " . $e->getMessage());
        return false;
    }
}

// Función para notificar invitación a cita interna
function notifyInternalAppointmentInvite($appointment_id, $participant_ids, $creator_user_id) {
    global $pdo;
    
    try {
        // Obtener información de la cita
        $stmt = $pdo->prepare("
            SELECT ia.*, au.nombre as creator_name 
            FROM internal_appointments ia 
            JOIN admin_users au ON ia.created_by_user_id = au.id 
            WHERE ia.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) return false;
        
        // Crear notificaciones para cada participante
        foreach ($participant_ids as $participant_id) {
            if ($participant_id != $creator_user_id) {
                createNotification(
                    $participant_id,
                    'internal_appointment_invite',
                    'Invitación a reunión interna',
                    "{$appointment['creator_name']} te invitó a la reunión '{$appointment['titulo']}' el " . date('d/m/Y H:i', strtotime($appointment['fecha_hora'])),
                    'internal_appointment',
                    $appointment_id
                );
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error notifying internal appointment invite: " . $e->getMessage());
        return false;
    }
}

// Función para notificar citas del día actual
function notifyTodayAppointments() {
    global $pdo;
    
    try {
        // Obtener todas las citas internas de hoy
        $stmt = $pdo->prepare("
            SELECT DISTINCT ia.*, au.nombre as creator_name, iap.user_id as participant_id
            FROM internal_appointments ia
            JOIN admin_users au ON ia.created_by_user_id = au.id
            JOIN internal_appointment_participants iap ON ia.id = iap.appointment_id
            WHERE DATE(ia.fecha_hora) = CURRENT_DATE 
            AND ia.estado = 'programada'
            AND iap.status = 'aceptada'
        ");
        $stmt->execute();
        $appointments = $stmt->fetchAll();
        
        foreach ($appointments as $appointment) {
            // Verificar si ya se notificó hoy
            $stmt_check = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND type = 'internal_appointment_today' 
                AND reference_id = ? AND DATE(created_at) = CURRENT_DATE
            ");
            $stmt_check->execute([$appointment['participant_id'], $appointment['id']]);
            
            if ($stmt_check->fetchColumn() == 0) {
                createNotification(
                    $appointment['participant_id'],
                    'internal_appointment_today',
                    'Reunión programada para hoy',
                    "Tienes la reunión '{$appointment['titulo']}' programada para las " . date('H:i', strtotime($appointment['fecha_hora'])),
                    'internal_appointment',
                    $appointment['id']
                );
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error notifying today appointments: " . $e->getMessage());
        return false;
    }
}

// Función para notificar cambio de estado de cita interna
function notifyInternalAppointmentStatusChange($appointment_id, $new_status, $changed_by_user_id) {
    global $pdo;
    
    try {
        // Obtener información de la cita y participantes
        $stmt = $pdo->prepare("
            SELECT ia.*, au.nombre as creator_name 
            FROM internal_appointments ia 
            JOIN admin_users au ON ia.created_by_user_id = au.id 
            WHERE ia.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) return false;
        
        // Obtener nombre del usuario que cambió el estado
        $stmt = $pdo->prepare("SELECT nombre FROM admin_users WHERE id = ?");
        $stmt->execute([$changed_by_user_id]);
        $changer_name = $stmt->fetchColumn();
        
        // Obtener todos los participantes (excepto el que cambió el estado)
        $stmt = $pdo->prepare("
            SELECT iap.user_id 
            FROM internal_appointment_participants iap 
            WHERE iap.appointment_id = ? AND iap.user_id != ?
            UNION
            SELECT ia.created_by_user_id 
            FROM internal_appointments ia 
            WHERE ia.id = ? AND ia.created_by_user_id != ?
        ");
        $stmt->execute([$appointment_id, $changed_by_user_id, $appointment_id, $changed_by_user_id]);
        $participants = $stmt->fetchAll();
        
        // Crear notificaciones
        foreach ($participants as $participant) {
            createNotification(
                $participant['user_id'],
                'internal_appointment_status',
                'Estado de reunión actualizado',
                "{$changer_name} cambió el estado de la reunión '{$appointment['titulo']}' a {$new_status}",
                'internal_appointment',
                $appointment_id
            );
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error notifying internal appointment status change: " . $e->getMessage());
        return false;
    }
}

// Función para notificar modificación de cita interna
function notifyInternalAppointmentModified($appointment_id, $modified_by_user_id, $changes_description) {
    global $pdo;
    
    try {
        // Obtener información de la cita
        $stmt = $pdo->prepare("
            SELECT ia.*, au.nombre as creator_name 
            FROM internal_appointments ia 
            JOIN admin_users au ON ia.created_by_user_id = au.id 
            WHERE ia.id = ?
        ");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) return false;
        
        // Obtener nombre del usuario que modificó
        $stmt = $pdo->prepare("SELECT nombre FROM admin_users WHERE id = ?");
        $stmt->execute([$modified_by_user_id]);
        $modifier_name = $stmt->fetchColumn();
        
        // Obtener todos los participantes (excepto el que modificó)
        $stmt = $pdo->prepare("
            SELECT iap.user_id 
            FROM internal_appointment_participants iap 
            WHERE iap.appointment_id = ? AND iap.user_id != ?
            UNION
            SELECT ia.created_by_user_id 
            FROM internal_appointments ia 
            WHERE ia.id = ? AND ia.created_by_user_id != ?
        ");
        $stmt->execute([$appointment_id, $modified_by_user_id, $appointment_id, $modified_by_user_id]);
        $participants = $stmt->fetchAll();
        
        // Crear notificaciones
        foreach ($participants as $participant) {
            createNotification(
                $participant['user_id'],
                'internal_appointment_modified',
                'Reunión modificada',
                "{$modifier_name} modificó la reunión '{$appointment['titulo']}'. {$changes_description}",
                'internal_appointment',
                $appointment_id
            );
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error notifying internal appointment modification: " . $e->getMessage());
        return false;
    }
}
// Función para verificar inactividad y cerrar sesión
function checkInactivity() {
    $inactivity_timeout = 1800; // 30 minutos en segundos
    
    if (isset($_SESSION['LAST_ACTIVITY']) {
        $session_lifetime = time() - $_SESSION['LAST_ACTIVITY'];
        
        if ($session_lifetime > $inactivity_timeout) {
            // Registrar el cierre de sesión por inactividad
            if (isset($_SESSION['admin_user_id'])) {
                logAction(null, 'logout', 'Sesión cerrada por inactividad');
            }
            
            // Destruir la sesión y redirigir
            session_unset();
            session_destroy();
            header('Location: login.php?reason=inactivity');
            exit();
        }
    }
    
    // Actualizar marca de tiempo de última actividad
    $_SESSION['LAST_ACTIVITY'] = time();
    
    // Para prevenir sesiones demasiado largas (opcional)
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > $inactivity_timeout * 2) {
        // Sesión empezó hace más de 1 hora (2x tiempo de inactividad)
        session_regenerate_id(true);    // Cambiar ID de sesión
        $_SESSION['CREATED'] = time(); // Actualizar tiempo de creación
    }
}

// Función para requerir autenticación con verificación de inactividad
function requireAuth() {
    checkInactivity();
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit();
    }
}

// Función para requerir permisos de administrador con verificación de inactividad
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit();
    }
}
?>

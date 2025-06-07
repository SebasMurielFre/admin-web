-- Insertar usuarios administradores por defecto
-- Contraseñas en texto plano (NO recomendado para producción)

INSERT INTO admin_users (nombre, cedula, email, password_hash, rol) 
VALUES 
    (
        'Administrador Sistema',
        '1234567890',
        'admin@proinvestec.com.ec',
        'admin123',
        'admin'
    ),
    (
        'Usuario Prueba',
        '0987654321',
        'usuario@proinvestec.com.ec',
        'usuario123',
        'usuario'
    ),
    (
        'María González Supervisor',
        '1756432890',
        'maria.gonzalez@proinvestec.com.ec',
        'maria123',
        'admin'
    ),
    (
        'Carlos Rodríguez Operador',
        '1345678901',
        'carlos.rodriguez@proinvestec.com.ec',
        'carlos123',
        'usuario'
    ),
    (
        'Ana Martínez Asistente',
        '1567890123',
        'ana.martinez@proinvestec.com.ec',
        'ana123',
        'usuario'
    )
ON CONFLICT (email) DO NOTHING;

-- Insertar comentarios de ejemplo para mensajes (asumiendo que existen mensajes con IDs 1-5)
INSERT INTO message_comments (message_id, user_id, comment) 
VALUES 
    (1, 1, 'Cliente requiere información urgente sobre certificados digitales para empresa. Prioridad alta.'),
    (1, 2, 'Se envió cotización por email. Pendiente de respuesta del cliente.'),
    (2, 1, 'Caso resuelto satisfactoriamente. Cliente muy conforme con el servicio.'),
    (3, 3, 'Problema técnico complejo. Requiere seguimiento especializado.'),
    (2, 4, 'Cliente solicita reunión presencial para revisar documentación.')
ON CONFLICT DO NOTHING;

-- Insertar historial de acciones para mensajes
INSERT INTO message_history (message_id, user_id, action, details) 
VALUES 
    (1, 1, 'status_change', 'Estado cambiado de pendiente a en_proceso'),
    (1, 1, 'comment_added', 'Comentario interno agregado sobre prioridad'),
    (2, 2, 'status_change', 'Estado cambiado a respondido'),
    (3, 3, 'appointment_created', 'Cita programada para resolución técnica'),
    (1, 4, 'follow_up', 'Seguimiento programado para próxima semana')
ON CONFLICT DO NOTHING;

-- Insertar citas de ejemplo
INSERT INTO appointments (user_id, contact_email, contact_name, fecha_hora, motivo, message_id, estado) 
VALUES 
    (
        1,
        'juan.perez@email.com',
        'Juan Pérez',
        CURRENT_TIMESTAMP + INTERVAL '2 days',
        'Consulta sobre implementación de certificados digitales para empresa de 50 empleados. Revisión de requisitos técnicos y documentación necesaria.',
        1,
        'programada'
    ),
    (
        2,
        'laura.silva@empresa.com',
        'Laura Silva',
        CURRENT_TIMESTAMP + INTERVAL '1 day',
        'Reunión para entrega de certificados digitales y capacitación del personal en el uso de firma electrónica.',
        2,
        'programada'
    ),
    (
        3,
        'roberto.vega@gmail.com',
        'Roberto Vega',
        CURRENT_TIMESTAMP - INTERVAL '1 day',
        'Soporte técnico para instalación de certificado digital en Windows 11. Resolución de problemas de compatibilidad.',
        3,
        'completada'
    ),
    (
        1,
        'sofia.mendez@consultora.com',
        'Sofía Méndez',
        CURRENT_TIMESTAMP + INTERVAL '3 days',
        'Asesoría legal sobre validez jurídica de documentos firmados electrónicamente para procesos judiciales.',
        NULL,
        'programada'
    ),
    (
        4,
        'diego.torres@startup.ec',
        'Diego Torres',
        CURRENT_TIMESTAMP - INTERVAL '3 days',
        'Cancelada por el cliente. Reprogramar para la próxima semana según disponibilidad.',
        NULL,
        'cancelada'
    )
ON CONFLICT DO NOTHING;

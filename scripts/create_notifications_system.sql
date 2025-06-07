-- Crear tabla de notificaciones
CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL CHECK (type IN ('message_comment', 'message_status', 'internal_appointment_invite', 'internal_appointment_today', 'internal_appointment_status', 'internal_appointment_modified')),
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    reference_type VARCHAR(50) CHECK (reference_type IN ('message', 'internal_appointment')),
    reference_id INTEGER,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL
);

-- Índices para mejorar el rendimiento
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_notifications_is_read ON notifications(is_read);
CREATE INDEX IF NOT EXISTS idx_notifications_created_at ON notifications(created_at);
CREATE INDEX IF NOT EXISTS idx_notifications_user_unread ON notifications(user_id, is_read) WHERE is_read = FALSE;

-- Comentarios para documentación
COMMENT ON TABLE notifications IS 'Sistema de notificaciones para usuarios del sistema administrativo';
COMMENT ON COLUMN notifications.type IS 'Tipo de notificación: message_comment, message_status, internal_appointment_invite, internal_appointment_today, internal_appointment_status, internal_appointment_modified';
COMMENT ON COLUMN notifications.reference_type IS 'Tipo de referencia: message o internal_appointment';
COMMENT ON COLUMN notifications.reference_id IS 'ID del mensaje o cita interna referenciada';

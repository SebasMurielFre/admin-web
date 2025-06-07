-- Agregar nueva tabla para citas internas entre usuarios
CREATE TABLE IF NOT EXISTS internal_appointments (
    id SERIAL PRIMARY KEY,
    created_by_user_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT,
    fecha_hora TIMESTAMP NOT NULL,
    duracion_minutos INTEGER DEFAULT 60,
    estado VARCHAR(50) DEFAULT 'programada' CHECK (estado IN ('programada', 'completada', 'cancelada', 'reagendada')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla para los participantes de las citas internas
CREATE TABLE IF NOT EXISTS internal_appointment_participants (
    id SERIAL PRIMARY KEY,
    appointment_id INTEGER NOT NULL REFERENCES internal_appointments(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    status VARCHAR(50) DEFAULT 'pendiente' CHECK (status IN ('pendiente', 'aceptada', 'rechazada')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(appointment_id, user_id)
);

-- Índices para mejorar el rendimiento
CREATE INDEX IF NOT EXISTS idx_internal_appointments_fecha_hora ON internal_appointments(fecha_hora);
CREATE INDEX IF NOT EXISTS idx_internal_appointments_created_by ON internal_appointments(created_by_user_id);
CREATE INDEX IF NOT EXISTS idx_internal_appointment_participants_appointment ON internal_appointment_participants(appointment_id);
CREATE INDEX IF NOT EXISTS idx_internal_appointment_participants_user ON internal_appointment_participants(user_id);

-- Comentarios para documentación
COMMENT ON TABLE internal_appointments IS 'Citas internas entre usuarios del sistema';
COMMENT ON TABLE internal_appointment_participants IS 'Participantes de las citas internas';
COMMENT ON COLUMN internal_appointments.duracion_minutos IS 'Duración estimada de la cita en minutos';
COMMENT ON COLUMN internal_appointment_participants.status IS 'Estado de participación del usuario en la cita';

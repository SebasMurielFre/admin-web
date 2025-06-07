-- Script para crear las tablas del sistema administrativo

-- Crear tabla de usuarios del sistema administrativo
CREATE TABLE IF NOT EXISTS admin_users (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    cedula VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL CHECK (rol IN ('admin', 'usuario')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Crear tabla de comentarios internos para mensajes
CREATE TABLE IF NOT EXISTS message_comments (
    id SERIAL PRIMARY KEY,
    message_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL REFERENCES admin_users(id),
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES contact_messages(id) ON DELETE CASCADE
);

-- Crear tabla de historial de acciones en mensajes
CREATE TABLE IF NOT EXISTS message_history (
    id SERIAL PRIMARY KEY,
    message_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL REFERENCES admin_users(id),
    action VARCHAR(50) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES contact_messages(id) ON DELETE CASCADE
);

-- Crear tabla de citas
CREATE TABLE IF NOT EXISTS appointments (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES admin_users(id),
    contact_email VARCHAR(100) NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    fecha_hora TIMESTAMP NOT NULL,
    motivo TEXT NOT NULL,
    message_id INTEGER,
    estado VARCHAR(20) DEFAULT 'programada' CHECK (estado IN ('programada', 'completada', 'cancelada', 'reagendada')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES contact_messages(id) ON DELETE SET NULL
);

-- Crear índices para mejor rendimiento
CREATE INDEX IF NOT EXISTS idx_contact_messages_status ON contact_messages(status);
CREATE INDEX IF NOT EXISTS idx_contact_messages_date ON contact_messages(date);
CREATE INDEX IF NOT EXISTS idx_message_comments_message_id ON message_comments(message_id);
CREATE INDEX IF NOT EXISTS idx_message_history_message_id ON message_history(message_id);
CREATE INDEX IF NOT EXISTS idx_appointments_fecha_hora ON appointments(fecha_hora);
CREATE INDEX IF NOT EXISTS idx_appointments_user_id ON appointments(user_id);

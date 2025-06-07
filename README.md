# Sistema Administrativo PROINVESTEC S.A.

Sistema completo de administración para la gestión de mensajes de contacto y citas de PROINVESTEC S.A., desarrollado en PHP con PostgreSQL.

## 🚀 Características

### ✅ **Funcionalidades Implementadas:**

- **🔐 Sistema de Autenticación Completo**
  - Login seguro con sesiones PHP
  - Roles de usuario (Administrador/Usuario)
  - Protección de rutas por permisos
  - Logout seguro

- **📊 Dashboard Principal**
  - Estadísticas en tiempo real
  - Métricas de mensajes y citas
  - Acciones rápidas
  - Diseño responsivo

- **📧 Gestión de Mensajes**
  - Lista completa de mensajes de contacto
  - Filtros por estado y búsqueda
  - Cambio de estado (Pendiente/Respondido)
  - Comentarios internos
  - Historial de acciones
  - Creación de citas desde mensajes

- **📅 Gestión de Citas**
  - Programación de nuevas citas
  - Edición y actualización de citas existentes
  - Filtros por fecha y estado
  - Estados: Programada, Completada, Cancelada, Reagendada
  - Vinculación con mensajes de contacto

- **📈 Estadísticas Avanzadas**
  - Gráficos interactivos con Chart.js
  - Análisis por períodos de tiempo
  - Métricas de respuesta
  - Reportes mensuales
  - Estadísticas por día de la semana

- **👥 Gestión de Usuarios** (Solo Administradores)
  - Crear, editar y eliminar usuarios
  - Asignación de roles
  - Gestión de contraseñas
  - Validaciones de seguridad

## 🛠️ Tecnologías Utilizadas

- **Backend:** PHP 8.0+
- **Base de Datos:** PostgreSQL
- **Frontend:** HTML5, CSS3, JavaScript
- **Estilos:** Tailwind CSS
- **Iconos:** Font Awesome
- **Gráficos:** Chart.js
- **Arquitectura:** MVC con PDO

## 📋 Requisitos del Sistema

- PHP 8.0 o superior
- PostgreSQL 12 o superior
- Extensiones PHP: PDO, pdo_pgsql, session
- Servidor web (Apache/Nginx)

## 🔧 Instalación

1. **Clonar el repositorio:**
   \`\`\`bash
   git clone [URL_DEL_REPOSITORIO]
   cd proinvestec-admin
   \`\`\`

2. **Configurar la base de datos:**
   - Editar `config/database.php` con tus credenciales
   - Ejecutar los scripts SQL en orden:
     \`\`\`sql
     -- Ejecutar en PostgreSQL
     \i scripts/create_admin_tables.sql
     \i scripts/seed_admin_users.sql
     \`\`\`

3. **Configurar el servidor web:**
   - Apuntar el DocumentRoot a la carpeta del proyecto
   - Asegurar que PHP tenga permisos de escritura en la carpeta de sesiones

4. **Acceder al sistema:**
   - URL: `http://tu-dominio.com/`
   - Será redirigido automáticamente al login

## 👤 Credenciales de Acceso

### Administrador:
- **Email:** admin@proinvestec.com.ec
- **Contraseña:** admin123

### Usuario:
- **Email:** usuario@proinvestec.com.ec
- **Contraseña:** usuario123

## 📁 Estructura del Proyecto

\`\`\`
proinvestec-admin/
├── config/
│   └── database.php          # Configuración de base de datos
├── includes/
│   ├── auth.php             # Sistema de autenticación
│   ├── admin_header.php     # Header del sistema
│   └── admin_footer.php     # Footer del sistema
├── scripts/
│   ├── create_admin_tables.sql  # Creación de tablas
│   └── seed_admin_users.sql     # Datos iniciales
├── assets/
│   └── css/
│       └── admin-style.css  # Estilos personalizados
├── index.php               # Página principal (redirección)
├── login.php              # Página de login
├── logout.php             # Logout
├── dashboard.php          # Dashboard principal
├── messages.php           # Gestión de mensajes
├── appointments.php       # Gestión de citas
├── calendar.php           # Calendario de citas
├── users.php             # Gestión de usuarios
└── README.md             # Este archivo
\`\`\`

## 🔒 Seguridad

- **Autenticación:** Sesiones PHP seguras
- **Contraseñas:** Hash con `password_hash()` de PHP
- **SQL Injection:** Prevención con PDO prepared statements
- **XSS:** Escape de datos con `htmlspecialchars()`
- **CSRF:** Validación de origen de formularios
- **Permisos:** Control de acceso por roles

## 🎨 Diseño Visual

El sistema mantiene la identidad visual de PROINVESTEC S.A.:
- **Colores primarios:** Azul corporativo (#1e3a8a)
- **Colores secundarios:** Amarillo (#fbbf24)
- **Tipografía:** Inter (Google Fonts)
- **Componentes:** Diseño moderno con Tailwind CSS
- **Responsivo:** Adaptable a móviles y tablets

## 📊 Base de Datos

### Tablas Principales:
- `admin_users` - Usuarios del sistema administrativo
- `contact_messages` - Mensajes de contacto del sitio web
- `message_comments` - Comentarios internos en mensajes
- `message_history` - Historial de acciones
- `appointments` - Citas programadas
- `testimonials` - Testimonios del sitio web

## 🚀 Funcionalidades Futuras

- [ ] Notificaciones por email
- [ ] Exportación de reportes a PDF/Excel
- [ ] API REST para integración móvil
- [ ] Sistema de tickets de soporte
- [ ] Chat en tiempo real
- [ ] Integración con calendario externo
- [ ] Backup automático de base de datos

## 🐛 Solución de Problemas

### Error de conexión a base de datos:
1. Verificar credenciales en `config/database.php`
2. Asegurar que PostgreSQL esté ejecutándose
3. Verificar permisos de conexión

### Problemas de sesión:
1. Verificar permisos de escritura en `/tmp` o directorio de sesiones
2. Comprobar configuración de `session.save_path` en PHP

### Estilos no cargan:
1. Verificar que Tailwind CSS se carga desde CDN
2. Comprobar conexión a internet
3. Verificar ruta de archivos CSS personalizados

## 📞 Soporte

Para soporte técnico o consultas sobre el sistema:
- **Email:** soporte@proinvestec.com.ec
- **Teléfono:** +593 99 888 777

## 📄 Licencia

Este sistema es propiedad de PROINVESTEC S.A. Todos los derechos reservados.

---

**Desarrollado para PROINVESTEC S.A.**  
*Especialistas en Certificación Digital y Firma Electrónica*

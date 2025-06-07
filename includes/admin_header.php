<?php
$page_title = isset($page_title) ? $page_title : 'Sistema Administrativo - PROINVESTEC SA';
$current_user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $page_title; ?></title>
  <meta name="description" content="Sistema de administración de mensajes y citas - PROINVESTEC SA">
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
      tailwind.config = {
          theme: {
              extend: {
                  colors: {
                      'primary': '#1e3a8a',
                      'secondary': '#fbbf24',
                      'accent': '#000000'
                  }
              }
          }
      }
  </script>
  
  <!-- Font Awesome para iconos -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  
  <!-- Chart.js para gráficos -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <!-- FullCalendar para el calendario -->
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
  
  <!-- Estilos personalizados -->
  <style>
  .proinvestec-gradient {
      background: linear-gradient(135deg, #1e3a8a 0%, #3730a3 50%, #1e40af 100%);
  }
  
  .proinvestec-card {
      background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
      border-radius: 1rem;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      transition: all 0.3s ease;
      border: 1px solid rgba(59, 130, 246, 0.1);
  }
  
  .proinvestec-card:hover {
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 25px 25px -5px rgba(0, 0, 0, 0.04);
      transform: translateY(-4px);
      border-color: rgba(59, 130, 246, 0.2);
  }
  
  .sidebar-link {
      transition: all 0.3s ease;
  }
  
  .sidebar-link:hover {
      background-color: rgba(30, 58, 138, 0.1);
      color: #1e3a8a;
  }
  
  .sidebar-link.active {
      background-color: #1e3a8a;
      color: white;
  }
  
  .message-card {
      background: linear-gradient(145deg, #f0f9ff 0%, #e0f2fe 100%);
      border: 1px solid rgba(14, 165, 233, 0.2);
      border-radius: 1rem;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
  }
  
  .message-card:hover {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
      border-color: rgba(14, 165, 233, 0.3);
  }
  
  .appointment-card {
      background: linear-gradient(145deg, #fefce8 0%, #fef3c7 100%);
      border: 1px solid rgba(245, 158, 11, 0.2);
      border-radius: 1rem;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
  }
  
  .appointment-card:hover {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
      border-color: rgba(245, 158, 11, 0.3);
  }
  
  .user-card {
      background: linear-gradient(145deg, #f0fdf4 0%, #dcfce7 100%);
      border: 1px solid rgba(34, 197, 94, 0.2);
      border-radius: 1rem;
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
  }
  
  .user-card:hover {
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
      border-color: rgba(34, 197, 94, 0.3);
  }

/* Media queries para modo landscape móvil */
@media (max-height: 500px) and (orientation: landscape) {
  .desktop-nav {
    display: none !important;
  }
  
  .mobile-menu-button {
    display: block !important;
  }
  
  /* Forzar que el menú móvil sea visible en landscape */
  #mobile-menu {
    display: block !important;
  }
  
  /* Cuando está hidden, ocultarlo */
  #mobile-menu.hidden {
    display: none !important;
  }
}

/* Asegurar que el menú móvil funcione en landscape */
@media (max-height: 500px) {
  #mobile-menu {
    max-height: calc(100vh - 80px);
    overflow-y: auto;
  }
}

/* Media query adicional para pantallas pequeñas en landscape */
@media (max-width: 900px) and (max-height: 500px) {
  .desktop-nav {
    display: none !important;
  }
  
  .mobile-menu-button {
    display: block !important;
  }
  
  #mobile-menu {
    display: block !important;
  }
  
  #mobile-menu.hidden {
    display: none !important;
  }
}
</style>
<!-- Sistema de notificaciones en tiempo real -->
<script src="assets/js/notifications-realtime.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-50 min-h-screen flex flex-col">
  <!-- Navigation -->
<nav class="bg-gradient-to-r from-blue-800 to-blue-900 shadow-lg border-b border-blue-700">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-20">
          <!-- Logo section with more left spacing -->
          <div class="flex items-center mr-8">
              <a href="dashboard.php" class="flex items-center space-x-4">
                  <img src="assets/images/proinvestec-logo.png" alt="PROINVESTEC" class="h-12 w-auto">
                  <span class="text-sm text-blue-200">Admin</span>
              </a>
          </div>

          <!-- Desktop Navigation with proper spacing -->
          <div class="desktop-nav hidden md:flex items-center space-x-4 flex-1 justify-start ml-8">
              <a href="dashboard.php" class="sidebar-link flex items-center space-x-3 px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-700 text-white shadow-lg' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                  <i class="fas fa-tachometer-alt w-5 h-5"></i>
                  <span>Dashboard</span>
              </a>
              <a href="messages.php" class="sidebar-link flex items-center space-x-3 px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'bg-blue-700 text-white shadow-lg' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                  <i class="fas fa-envelope w-5 h-5"></i>
                  <span>Mensajes</span>
              </a>
              <a href="appointments.php" class="sidebar-link flex items-center space-x-3 px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'bg-blue-700 text-white shadow-lg' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                  <i class="fas fa-calendar w-5 h-5"></i>
                  <span>Citas Externas</span>
              </a>
              <a href="internal-appointments.php" class="sidebar-link flex items-center space-x-3 px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'internal-appointments.php' ? 'bg-green-700 text-white shadow-lg' : 'text-blue-100 hover:bg-green-700 hover:text-white'; ?>">
                  <i class="fas fa-users w-5 h-5"></i>
                  <span>Citas Internas</span>
              </a>
              <a href="calendar.php" class="sidebar-link flex items-center space-x-3 px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'bg-purple-700 text-white shadow-lg' : 'text-blue-100 hover:bg-purple-700 hover:text-white'; ?>">
                  <i class="fas fa-calendar-alt w-5 h-5"></i>
                  <span>Calendario</span>
              </a>
              <?php if (isAdmin()): ?>
              <a href="users.php" class="sidebar-link flex items-center space-x-3 px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-blue-700 text-white shadow-lg' : 'text-blue-100 hover:bg-blue-700 hover:text-white'; ?>">
                  <i class="fas fa-users w-5 h-5"></i>
                  <span>Usuarios</span>
              </a>
              <?php endif; ?>
          </div>

          <!-- User Menu con notificaciones -->
          <div class="flex items-center space-x-6">
              <!-- Sistema de Notificaciones -->
              <div class="relative">
                  <button 
                      id="notifications-button" 
                      class="relative p-2 text-blue-100 hover:text-white hover:bg-blue-700 rounded-lg transition-all duration-200"
                      onclick="toggleNotifications()"
                  >
                      <i class="fas fa-bell text-lg"></i>
                      <span 
                          id="notifications-badge" 
                          class="hidden absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold"
                      >
                          0
                      </span>
                  </button>
                  
                  <!-- Dropdown de notificaciones -->
                  <div 
                      id="notifications-dropdown" 
                      class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50"
                  >
                      <div class="p-4 border-b border-gray-200">
                          <div class="flex justify-between items-center">
                              <h3 class="text-lg font-semibold text-gray-900">Notificaciones</h3>
                              <button 
                                  id="mark-all-read-btn"
                                  class="text-sm text-blue-600 hover:text-blue-800 transition-colors"
                                  onclick="markAllAsRead()"
                              >
                                  Marcar todas como leídas
                              </button>
                          </div>
                      </div>
                      
                      <div id="notifications-list" class="max-h-96 overflow-y-auto">
                          <div class="p-4 text-center text-gray-500">
                              <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                              <p>Cargando notificaciones...</p>
                          </div>
                      </div>
                      
                      <div class="p-3 border-t border-gray-200 text-center">
                          <a href="notifications.php" class="text-sm text-blue-600 hover:text-blue-800 transition-colors">
                              Ver todas las notificaciones
                          </a>
                      </div>
                  </div>
              </div>

              <?php if ($current_user): ?>
              <div class="hidden md:flex items-center space-x-4 text-sm text-blue-100 ml-8">
                  <i class="fas fa-user w-5 h-5"></i>
                  <span class="font-medium"><?php echo htmlspecialchars($current_user['nombre']); ?></span>
                  <span class="text-xs bg-blue-600 text-blue-100 px-3 py-1 rounded-full font-medium"><?php echo htmlspecialchars($current_user['rol']); ?></span>
              </div>
              <?php endif; ?>

              <a href="logout.php" class="hidden md:flex items-center space-x-3 text-blue-100 hover:text-white hover:bg-blue-700 px-6 py-3 rounded-lg text-sm font-medium transition-all duration-200">
                  <i class="fas fa-sign-out-alt w-5 h-5"></i>
                  <span class="hidden lg:inline">Salir</span>
              </a>

              <!-- Mobile menu button -->
              <button id="mobile-menu-button" class="mobile-menu-button md:hidden text-blue-100 hover:text-white p-2">
                  <i class="fas fa-bars text-xl"></i>
              </button>
          </div>
      </div>
  </div>

  <!-- Mobile menu -->
  <div id="mobile-menu" class="hidden md:hidden bg-blue-900 border-t border-blue-700">
      <div class="px-4 py-3 space-y-2">
          <a href="dashboard.php" class="block px-4 py-3 rounded-lg text-blue-100 hover:bg-blue-700 hover:text-white transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-700 text-white' : ''; ?>">
              <i class="fas fa-tachometer-alt w-5 h-5 mr-3"></i>Dashboard
          </a>
          <a href="messages.php" class="block px-4 py-3 rounded-lg text-blue-100 hover:bg-blue-700 hover:text-white transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'bg-blue-700 text-white' : ''; ?>">
              <i class="fas fa-envelope w-5 h-5 mr-3"></i>Mensajes
          </a>
          <a href="appointments.php" class="block px-4 py-3 rounded-lg text-blue-100 hover:bg-blue-700 hover:text-white transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'bg-blue-700 text-white' : ''; ?>">
              <i class="fas fa-calendar w-5 h-5 mr-3"></i>Citas Externas
          </a>
          <a href="internal-appointments.php" class="block px-4 py-3 rounded-lg text-blue-100 hover:bg-green-700 hover:text-white transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'internal-appointments.php' ? 'bg-green-700 text-white' : ''; ?>">
              <i class="fas fa-users w-5 h-5 mr-3"></i>Citas Internas
          </a>
          <a href="calendar.php" class="block px-4 py-3 rounded-lg text-blue-100 hover:bg-purple-700 hover:text-white transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'calendar.php' ? 'bg-purple-700 text-white' : ''; ?>">
              <i class="fas fa-calendar-alt w-5 h-5 mr-3"></i>Calendario
          </a>
          <?php if (isAdmin()): ?>
          <a href="users.php" class="block px-4 py-3 rounded-lg text-blue-100 hover:bg-blue-700 hover:text-white transition-all duration-200 <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-blue-700 text-white' : ''; ?>">
              <i class="fas fa-users w-5 h-5 mr-3"></i>Usuarios
          </a>
          <?php endif; ?>
          
          <!-- User info and logout for mobile -->
          <?php if ($current_user): ?>
          <div class="border-t border-blue-700 pt-3 mt-3">
              <div class="px-4 py-2 text-blue-200 text-sm">
                  <i class="fas fa-user w-4 h-4 mr-2"></i>
                  <?php echo htmlspecialchars($current_user['nombre']); ?>
                  <span class="block text-xs mt-1 bg-blue-600 text-blue-100 px-2 py-1 rounded-full inline-block">
                      <?php echo htmlspecialchars($current_user['rol']); ?>
                  </span>
              </div>
              <a href="logout.php" class="block px-4 py-3 rounded-lg text-blue-100 hover:bg-blue-700 hover:text-white transition-all duration-200">
                  <i class="fas fa-sign-out-alt w-5 h-5 mr-3"></i>Salir
              </a>
          </div>
          <?php endif; ?>
      </div>
  </div>
</nav>

<script>
  // Mobile menu toggle
  document.getElementById('mobile-menu-button').addEventListener('click', function() {
      const mobileMenu = document.getElementById('mobile-menu');
      const button = document.getElementById('mobile-menu-button');
      const icon = button.querySelector('i');
      
      mobileMenu.classList.toggle('hidden');
      
      // Cambiar icono entre hamburguesa y X
      if (mobileMenu.classList.contains('hidden')) {
          icon.className = 'fas fa-bars text-xl';
      } else {
          icon.className = 'fas fa-times text-xl';
      }
  });

  // Cerrar menú móvil al hacer clic en un enlace
  document.querySelectorAll('#mobile-menu a').forEach(link => {
      link.addEventListener('click', function() {
          const mobileMenu = document.getElementById('mobile-menu');
          const button = document.getElementById('mobile-menu-button');
          const icon = button.querySelector('i');
          
          mobileMenu.classList.add('hidden');
          icon.className = 'fas fa-bars text-xl';
      });
  });

// Sistema de Notificaciones
let notificationsInterval;

// Inicializar notificaciones al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    loadNotifications();
    // Actualizar notificaciones cada 30 segundos
    // Actualizar notificaciones cada 5 segundos para mayor inmediatez
    notificationsInterval = setInterval(loadNotifications, 5000);
});

function toggleNotifications() {
    const dropdown = document.getElementById('notifications-dropdown');
    const button = document.getElementById('notifications-button');
    
    if (dropdown.classList.contains('hidden')) {
        dropdown.classList.remove('hidden');
        loadNotifications(); // Recargar al abrir
    } else {
        dropdown.classList.add('hidden');
    }
}

function loadNotifications() {
    fetch('notifications_handler.php?action=get_notifications')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationsBadge(data.count);
                renderNotifications(data.notifications);
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

function updateNotificationsBadge(count) {
    const badge = document.getElementById('notifications-badge');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function renderNotifications(notifications) {
    const container = document.getElementById('notifications-list');
    
    if (notifications.length === 0) {
        container.innerHTML = `
            <div class="p-6 text-center text-gray-500">
                <i class="fas fa-bell-slash text-3xl mb-3 text-gray-300"></i>
                <p class="text-sm">No tienes notificaciones nuevas</p>
            </div>
        `;
        return;
    }
    
    container.innerHTML = notifications.map(notification => `
        <div class="notification-item p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer transition-colors" 
             onclick="handleNotificationClick(${notification.id}, '${notification.url}')">
            <div class="flex items-start space-x-3">
                <div class="flex-shrink-0">
                    <i class="${notification.icon} text-blue-500 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-sm font-medium text-gray-900 truncate">
                        ${notification.title}
                    </h4>
                    <p class="text-sm text-gray-600 mt-1 line-clamp-2">
                        ${notification.message}
                    </p>
                    <p class="text-xs text-gray-400 mt-2">
                        ${notification.time_ago}
                    </p>
                </div>
                <div class="flex-shrink-0">
                    <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                </div>
            </div>
        </div>
    `).join('');
}

function markAllAsRead() {
    fetch('notifications_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_as_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            showNotification('Todas las notificaciones han sido marcadas como leídas', 'success');
        } else {
            showNotification('Error al marcar las notificaciones como leídas', 'error');
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
        showNotification('Error de conexión', 'error');
    });
}

function handleNotificationClick(notificationId, url) {
    // Marcar como leída
    fetch('notifications_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_as_read&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Recargar notificaciones para actualizar el badge
            loadNotifications();
            // Cerrar dropdown
            document.getElementById('notifications-dropdown').classList.add('hidden');
            // Redirigir a la URL correspondiente
            if (url && url !== 'dashboard.php') {
                window.location.href = url;
            }
        } else {
            console.error('Error marking notification as read');
            // Redirigir de todas formas
            if (url && url !== 'dashboard.php') {
                window.location.href = url;
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
        // Redirigir de todas formas
        if (url && url !== 'dashboard.php') {
            window.location.href = url;
        }
    });
}

// Función para mostrar notificaciones toast (agregar esta nueva función)
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-black' :
        'bg-blue-500 text-white'
    }`;
    notification.innerHTML = `
        <div class="flex items-center justify-between">
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">&times;</button>
        </div>
    `;
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

// Cerrar dropdown al hacer clic fuera
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('notifications-dropdown');
    const button = document.getElementById('notifications-button');
    
    if (!button.contains(event.target) && !dropdown.contains(event.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>

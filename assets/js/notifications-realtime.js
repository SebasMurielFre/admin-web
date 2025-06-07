// Sistema de notificaciones en tiempo real
class NotificationSystem {
  constructor() {
    this.lastNotificationCheck = Date.now()
    this.checkInterval = 3000 // Verificar cada 3 segundos
    this.init()
  }

  init() {
    // Inicializar el sistema
    this.startPolling()

    // Escuchar eventos de visibilidad de la página
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "visible") {
        this.checkForNewNotifications()
      }
    })
  }

  startPolling() {
    setInterval(() => {
      if (document.visibilityState === "visible") {
        this.checkForNewNotifications()
      }
    }, this.checkInterval)
  }

  async checkForNewNotifications() {
    try {
      const response = await fetch(
        `notifications_handler.php?action=get_notifications&since=${this.lastNotificationCheck}`,
      )
      const data = await response.json()

      if (data.success) {
        // Actualizar badge
        this.updateNotificationsBadge(data.count)

        // Si hay notificaciones nuevas, mostrar una notificación del navegador
        if (data.notifications && data.notifications.length > 0) {
          const newNotifications = data.notifications.filter(
            (n) => new Date(n.created_at).getTime() > this.lastNotificationCheck,
          )

          if (newNotifications.length > 0) {
            this.showBrowserNotification(newNotifications[0])
            this.playNotificationSound()
          }
        }

        // Actualizar el dropdown si está abierto
        const dropdown = document.getElementById("notifications-dropdown")
        if (!dropdown.classList.contains("hidden")) {
          this.renderNotifications(data.notifications)
        }

        this.lastNotificationCheck = Date.now()
      }
    } catch (error) {
      console.error("Error checking notifications:", error)
    }
  }

  updateNotificationsBadge(count) {
    const badge = document.getElementById("notifications-badge")
    if (count > 0) {
      badge.textContent = count > 99 ? "99+" : count
      badge.classList.remove("hidden")

      // Agregar animación de pulso
      badge.classList.add("animate-pulse")
      setTimeout(() => {
        badge.classList.remove("animate-pulse")
      }, 2000)
    } else {
      badge.classList.add("hidden")
    }
  }

  showBrowserNotification(notification) {
    // Verificar si las notificaciones del navegador están permitidas
    if (Notification.permission === "granted") {
      new Notification(notification.title, {
        body: notification.message,
        icon: "/assets/images/proinvestec-logo.png",
        tag: "proinvestec-notification",
      })
    } else if (Notification.permission !== "denied") {
      // Solicitar permiso
      Notification.requestPermission().then((permission) => {
        if (permission === "granted") {
          new Notification(notification.title, {
            body: notification.message,
            icon: "/assets/images/proinvestec-logo.png",
            tag: "proinvestec-notification",
          })
        }
      })
    }
  }

  playNotificationSound() {
    // Crear un sonido de notificación sutil
    const audioContext = new (window.AudioContext || window.webkitAudioContext)()
    const oscillator = audioContext.createOscillator()
    const gainNode = audioContext.createGain()

    oscillator.connect(gainNode)
    gainNode.connect(audioContext.destination)

    oscillator.frequency.setValueAtTime(800, audioContext.currentTime)
    oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.1)

    gainNode.gain.setValueAtTime(0, audioContext.currentTime)
    gainNode.gain.linearRampToValueAtTime(0.1, audioContext.currentTime + 0.01)
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2)

    oscillator.start(audioContext.currentTime)
    oscillator.stop(audioContext.currentTime + 0.2)
  }

  renderNotifications(notifications) {
    const container = document.getElementById("notifications-list")

    if (notifications.length === 0) {
      container.innerHTML = `
                <div class="p-6 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-3xl mb-3 text-gray-300"></i>
                    <p class="text-sm">No tienes notificaciones nuevas</p>
                </div>
            `
      return
    }

    container.innerHTML = notifications
      .map(
        (notification) => `
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
        `,
      )
      .join("")
  }
}

// Inicializar el sistema cuando se carga la página
document.addEventListener("DOMContentLoaded", () => {
  // Solo inicializar si estamos autenticados (verificar si existe el elemento de notificaciones)
  if (document.getElementById("notifications-button")) {
    window.notificationSystem = new NotificationSystem()

    // Solicitar permiso para notificaciones del navegador
    if ("Notification" in window && Notification.permission === "default") {
      Notification.requestPermission()
    }
  }
})

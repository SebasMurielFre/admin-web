<!-- Footer -->
<footer class="bg-gradient-to-r from-blue-800 to-blue-900 border-t border-blue-700 mt-auto">
    <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col md:flex-row justify-between items-center">
            <div class="flex items-center space-x-3 mb-6 md:mb-0">
                <img src="assets/images/proinvestec-logo.png" alt="PROINVESTEC" class="h-8 w-auto">
                <span class="text-sm font-semibold text-blue-100">Sistema Administrativo</span>
            </div>
            
            <div class="flex flex-col md:flex-row items-center space-y-3 md:space-y-0 md:space-x-8 text-sm text-blue-200">
                <span>&copy; <?php echo date('Y'); ?> PROINVESTEC S.A. Todos los derechos reservados.</span>
                <span class="hidden md:inline text-blue-400">|</span>
                <span>Especialistas en Certificación Digital y Firma Electrónica</span>
            </div>
            
            <div class="flex items-center space-x-6 mt-6 md:mt-0">
                <span class="text-xs text-blue-300">Sistema Administrativo v1.0</span>
                <div class="flex items-center space-x-2">
                    <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                    <span class="text-xs text-blue-300">En línea</span>
                </div>
            </div>
        </div>
    </div>
</footer>

    <script>
        // Funciones JavaScript para el sistema administrativo
        
        // Función para mostrar notificaciones
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
        
        // Función para confirmar acciones
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
        
        // Función para formatear fechas
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('es-EC', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        // Auto-refresh para estadísticas cada 5 minutos
        if (window.location.pathname.includes('dashboard.php')) {
            setInterval(() => {
                location.reload();
            }, 300000); // 5 minutos
        }
    </script>
</body>
</html>

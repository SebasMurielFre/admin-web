<?php
$page_title = "Gestión de Mensajes - Sistema Administrativo PROINVESTEC SA";
include 'config/database.php';
include 'includes/auth.php';

requireAuth();

$success_message = '';
$error_message = '';

// Agregar al inicio del archivo PHP, después de las variables existentes:
$highlight_message_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0;

// Procesar acciones
if ($_POST) {
    if (isset($_POST['update_status'])) {
        $message_id = (int)$_POST['message_id'];
        $new_status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $message_id])) {
                logAction($message_id, 'status_change', "Estado cambiado a: $new_status");
                // Notificar a otros usuarios sobre el cambio de estado
                notifyMessageStatusChange($message_id, $new_status, $_SESSION['admin_user_id']);
                $success_message = "Estado del mensaje actualizado correctamente.";
            }
        } catch (PDOException $e) {
            $error_message = "Error al actualizar el estado del mensaje.";
        }
    }
    
    if (isset($_POST['add_comment'])) {
        $message_id = (int)$_POST['message_id'];
        $comment = trim($_POST['comment']);
        
        if (!empty($comment)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO message_comments (message_id, user_id, comment) VALUES (?, ?, ?)");
                if ($stmt->execute([$message_id, $_SESSION['admin_user_id'], $comment])) {
                    logAction($message_id, 'comment_added', "Comentario agregado");
                    // Notificar a otros usuarios sobre el nuevo comentario
                    notifyMessageComment($message_id, $_SESSION['admin_user_id']);
                    $success_message = "Comentario agregado correctamente.";
                }
            } catch (PDOException $e) {
                $error_message = "Error al agregar el comentario.";
            }
        }
    }
    
    if (isset($_POST['delete_message'])) {
        $message_id = (int)$_POST['message_id'];
        
        try {
            $stmt = $pdo->prepare("DELETE FROM contact_messages WHERE id = ?");
            if ($stmt->execute([$message_id])) {
                logAction($message_id, 'message_deleted', "Mensaje eliminado");
                $success_message = "Mensaje eliminado correctamente.";
            }
        } catch (PDOException $e) {
            $error_message = "Error al eliminar el mensaje.";
        }
    }
}

// Paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtros
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construir consulta
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(name ILIKE ? OR email ILIKE ? OR subject ILIKE ? OR message ILIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Obtener total de mensajes para paginación
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages $where_clause");
    $count_stmt->execute($params);
    $total_messages = $count_stmt->fetchColumn();
    $total_pages = ceil($total_messages / $per_page);
} catch (PDOException $e) {
    $total_messages = 0;
    $total_pages = 1;
}

// Obtener mensajes con paginación
try {
    $stmt = $pdo->prepare("
        SELECT * FROM contact_messages 
        $where_clause 
        ORDER BY 
            CASE 
                WHEN status = 'pendiente' THEN 0
                ELSE 1
            END,
            CASE 
                WHEN status = 'pendiente' THEN date
                WHEN status = 'respondido' THEN date
                ELSE date
            END DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    $messages = [];
    $error_message = "Error al cargar los mensajes: " . $e->getMessage();
}

include 'includes/admin_header.php';
?>

<main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex-1">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Gestión de Mensajes</h1>
        <p class="text-gray-600 mt-2">Administra los mensajes de contacto recibidos</p>
    </div>

    <?php if ($success_message): ?>
    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-md">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-md">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="message-card p-6 mb-6">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div class="flex-1 min-w-64">
                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Buscar</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    value="<?php echo htmlspecialchars($search); ?>"
                    placeholder="Buscar por nombre, email, asunto o mensaje..."
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Estado</label>
                <select
                    id="status"
                    name="status"
                    class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Todos los estados</option>
                    <option value="pendiente" <?php echo $status_filter === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                    <option value="respondido" <?php echo $status_filter === 'respondido' ? 'selected' : ''; ?>>Respondido</option>
                </select>
            </div>
            <div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <i class="fas fa-search mr-2"></i>Filtrar
                </button>
            </div>
            <div>
                <a href="messages.php" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors">
                    <i class="fas fa-times mr-2"></i>Limpiar
                </a>
            </div>
        </form>
    </div>

    <!-- Información de paginación -->
    <div class="mb-4 flex justify-between items-center">
        <p class="text-sm text-gray-600">
            Mostrando <?php echo $offset + 1; ?> - <?php echo min($offset + $per_page, $total_messages); ?> de <?php echo $total_messages; ?> mensajes
        </p>
        <div class="text-sm text-gray-600">
            Página <?php echo $page; ?> de <?php echo $total_pages; ?>
        </div>
    </div>

    <!-- Lista de mensajes -->
    <div class="space-y-8">
        <?php if (empty($messages)): ?>
        <div class="message-card text-center py-12">
            <i class="fas fa-inbox text-blue-400 text-5xl mb-6"></i>
            <h3 class="text-xl font-semibold text-blue-900 mb-3">No hay mensajes</h3>
            <p class="text-blue-700">No se encontraron mensajes con los filtros aplicados.</p>
        </div>
        <?php else: ?>
        <?php foreach ($messages as $message): ?>
        <!-- En la sección donde se muestran los mensajes, agregar el ID y clase de resaltado: -->
        <!-- Cambiar esta línea: -->
        <!-- Por esta: -->
        <div id="message-<?php echo $message['id']; ?>" class="message-card <?php echo $highlight_message_id == $message['id'] ? 'border-yellow-400 bg-yellow-50' : ''; ?>">
            <div class="flex justify-between items-start mb-6">
                <div class="flex-1">
                    <div class="flex items-center space-x-4 mb-3">
                        <h3 class="text-xl font-bold text-blue-900"><?php echo htmlspecialchars($message['name']); ?></h3>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full <?php echo $message['status'] === 'pendiente' ? 'bg-orange-200 text-orange-800 border border-orange-300' : 'bg-green-200 text-green-800 border border-green-300'; ?>">
                            <?php echo ucfirst($message['status']); ?>
                        </span>
                    </div>
                    <div class="text-sm text-blue-700 space-y-2">
                        <p><i class="fas fa-envelope mr-3 text-blue-500"></i><?php echo htmlspecialchars($message['email']); ?></p>
                        <?php if ($message['phone']): ?>
                        <p><i class="fas fa-phone mr-3 text-blue-500"></i><?php echo htmlspecialchars($message['phone']); ?></p>
                        <?php endif; ?>
                        <p><i class="fas fa-clock mr-3 text-blue-500"></i><?php echo date('d/m/Y H:i', strtotime($message['date'])); ?></p>
                    </div>
                </div>
                <div class="flex space-x-3">
                    <button onclick="toggleDetails(<?php echo $message['id']; ?>)" class="px-4 py-2 bg-blue-200 text-blue-800 rounded-lg hover:bg-blue-300 transition-colors text-sm font-medium border border-blue-300">
                        <i class="fas fa-eye mr-2"></i>Ver detalles
                    </button>
                    <button onclick="showDeleteConfirm(<?php echo $message['id']; ?>)" class="px-4 py-2 bg-red-200 text-red-800 rounded-lg hover:bg-red-300 transition-colors text-sm font-medium border border-red-300">
                        <i class="fas fa-trash mr-2"></i>Eliminar
                    </button>
                </div>
            </div>

            <div class="mb-6">
                <h4 class="font-semibold text-blue-900 mb-3 text-lg">Asunto: <?php echo htmlspecialchars($message['subject']); ?></h4>
                <p class="text-blue-800 bg-blue-50 p-4 rounded-lg border border-blue-200 leading-relaxed"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
            </div>

            <!-- Detalles expandibles -->
            <div id="details-<?php echo $message['id']; ?>" class="hidden border-t pt-4">
                <!-- Cambiar estado -->
                <div class="mb-4">
                    <form method="POST" class="flex items-center space-x-3">
                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                        <label class="text-sm font-medium text-gray-700">Estado:</label>
                        <select name="status" class="px-3 py-1 border border-gray-300 rounded-md text-sm">
                            <option value="pendiente" <?php echo $message['status'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                            <option value="respondido" <?php echo $message['status'] === 'respondido' ? 'selected' : ''; ?>>Respondido</option>
                        </select>
                        <button type="submit" name="update_status" class="px-3 py-1 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm">
                            Actualizar
                        </button>
                    </form>
                </div>

                <!-- Comentarios -->
                <div class="mb-4">
                    <h5 class="font-medium text-gray-900 mb-2">Comentarios internos:</h5>
                    <div id="comments-<?php echo $message['id']; ?>" class="space-y-2 mb-3">
                        <?php
                        try {
                            $comments_stmt = $pdo->prepare("
                                SELECT mc.*, au.nombre as user_name 
                                FROM message_comments mc 
                                JOIN admin_users au ON mc.user_id = au.id 
                                WHERE mc.message_id = ? 
                                ORDER BY mc.created_at DESC
                            ");
                            $comments_stmt->execute([$message['id']]);
                            $comments = $comments_stmt->fetchAll();
                            
                            foreach ($comments as $comment):
                        ?>
                        <div class="bg-gray-50 p-3 rounded-md">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($comment['user_name']); ?></span>
                                <span class="text-xs text-gray-500"><?php echo date('d/m/Y H:i', strtotime($comment['created_at'])); ?></span>
                            </div>
                            <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                        </div>
                        <?php endforeach; } catch (PDOException $e) { /* Ignorar errores */ } ?>
                    </div>
                    
                    <form method="POST" class="flex space-x-3">
                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                        <input
                            type="text"
                            name="comment"
                            placeholder="Agregar comentario interno..."
                            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                            required
                        />
                        <button type="submit" name="add_comment" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors text-sm">
                            <i class="fas fa-plus mr-1"></i>Agregar
                        </button>
                    </form>
                </div>

                <!-- Crear cita -->
                <div>
                    <a href="appointments.php?create=1&message_id=<?php echo $message['id']; ?>&email=<?php echo urlencode($message['email']); ?>&name=<?php echo urlencode($message['name']); ?>" 
                       class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors text-sm">
                        <i class="fas fa-calendar-plus mr-2"></i>Crear Cita
                    </a>
                </div>
            </div>
        </div>

        <!-- Modal de confirmación para eliminar -->
        <div id="delete-modal-<?php echo $message['id']; ?>" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Confirmar eliminación</h3>
                <p class="text-gray-600 mb-6">¿Estás seguro de que deseas eliminar este mensaje? Esta acción no se puede deshacer.</p>
                <div class="flex justify-end space-x-3">
                    <button onclick="hideDeleteConfirm(<?php echo $message['id']; ?>)" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 transition-colors">
                        Cancelar
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="message_id" value="<?php echo $message['id']; ?>">
                        <button type="submit" name="delete_message" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginación -->
    <?php if ($total_pages > 1): ?>
    <div class="mt-8 flex justify-center">
        <nav class="flex items-center space-x-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
               class="px-3 py-2 border rounded-md transition-colors <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>" 
               class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>
</main>

<script>
function toggleDetails(messageId) {
    const details = document.getElementById(`details-${messageId}`);
    details.classList.toggle('hidden');
}

function showDeleteConfirm(messageId) {
    document.getElementById(`delete-modal-${messageId}`).classList.remove('hidden');
}

function hideDeleteConfirm(messageId) {
    document.getElementById(`delete-modal-${messageId}`).classList.add('hidden');
}

// Agregar al final del script JavaScript:
// Si hay un mensaje resaltado, hacer scroll hacia él
<?php if ($highlight_message_id > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    const highlightedMessage = document.getElementById('message-<?php echo $highlight_message_id; ?>');
    if (highlightedMessage) {
        highlightedMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Expandir detalles automáticamente
        const details = document.getElementById('details-<?php echo $highlight_message_id; ?>');
        if (details && details.classList.contains('hidden')) {
            details.classList.remove('hidden');
        }
        // Remover resaltado después de 3 segundos
        setTimeout(() => {
            highlightedMessage.classList.remove('border-yellow-400', 'bg-yellow-50');
        }, 3000);
    }
});
<?php endif; ?>
</script>

<?php include 'includes/admin_footer.php'; ?>

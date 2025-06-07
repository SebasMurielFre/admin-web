<?php
include 'config/database.php';
include 'includes/auth.php';

// Redirigir si ya está autenticado
if (isAuthenticated()) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';

// Procesar login
if ($_POST && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $error_message = 'Por favor, completa todos los campos.';
    } else {
        if (login($email, $password)) {
            header('Location: dashboard.php');
            exit();
        } else {
            $error_message = 'Credenciales inválidas. Verifica tu email y contraseña.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Administrativo PROINVESTEC SA</title>
    
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
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-blue-900 via-blue-800 to-blue-900 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-lg shadow-2xl border-0">
            <div class="text-center pb-6 pt-8 px-8">
                <div class="mx-auto mb-4 w-20 h-20 bg-gradient-to-br from-blue-600 to-blue-700 rounded-full flex items-center justify-center">
                    <i class="fas fa-lock text-white text-3xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Sistema Administrativo</h1>
                <p class="text-gray-600">PROINVESTEC S.A.</p>
            </div>
            
            <div class="px-8 pb-8">
                <?php if ($error_message): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                    <p class="text-red-700 text-sm"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div class="space-y-2">
                        <label for="email" class="block text-gray-700 font-medium text-sm">
                            Correo Electrónico
                        </label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-3 top-3 h-4 w-4 text-gray-400"></i>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                placeholder="tu@proinvestec.com.ec"
                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="block text-gray-700 font-medium text-sm">
                            Contraseña
                        </label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-3 top-3 h-4 w-4 text-gray-400"></i>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="••••••••"
                                class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        name="login"
                        class="w-full py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-semibold rounded-md transition-all duration-200"
                    >
                        Iniciar Sesión
                    </button>
                </form>

            </div>
        </div>
    </div>
</body>
</html>

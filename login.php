<?php
define('SEGURO', true);

if (!defined('SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}
    
// Configurar cookies de sesión seguras ANTES de session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();
require_once 'conexion.php';

// Obtener colores del sitio desde configuración
$color_primario = '#667eea'; // color por defecto
$color_secundario = '#764ba2'; // color por defecto

$result = $conn->query("SELECT clave, valor FROM configuracion WHERE clave IN ('color_primario', 'color_secundario')");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        if ($row['clave'] == 'color_primario') {
            $color_primario = $row['valor'];
        } elseif ($row['clave'] == 'color_secundario') {
            $color_secundario = $row['valor'];
        }
    }
}

// Obtener número de teléfono para WhatsApp
$telefono_contacto = '';
$result = $conn->query("SELECT valor FROM configuracion WHERE clave = 'sitio_telefono'");
if ($result && $row = $result->fetch_assoc()) {
    $telefono_contacto = $row['valor'];
}

// Si ya está logueado, redirigir
if (isset($_SESSION['usuario_id'])) {
    header('Location: admin.php');
    exit;
}

// ============================================
// CONTROL DE INTENTOS FALLIDOS
// ============================================
$max_intentos = 5;
$tiempo_bloqueo = 900; // 15 minutos en segundos

if (!isset($_SESSION['intentos_login'])) {
    $_SESSION['intentos_login'] = 0;
    $_SESSION['primer_intento'] = time();
}

// Verificar si está bloqueado
if ($_SESSION['intentos_login'] >= $max_intentos) {
    $tiempo_restante = $tiempo_bloqueo - (time() - $_SESSION['primer_intento']);
    if ($tiempo_restante > 0) {
        $error = "⛔ Demasiados intentos. Espera " . ceil($tiempo_restante / 60) . " minutos.";
        $bloqueado = true;
    } else {
        // Resetear intentos
        $_SESSION['intentos_login'] = 0;
        $_SESSION['primer_intento'] = time();
        $bloqueado = false;
    }
} else {
    $bloqueado = false;
}

// Generar token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$mensaje_login = '';

// Mostrar mensajes de cierre de sesión
if (isset($_GET['expired'])) {
    $mensaje_login = '<div class="info">⏰ Sesión expirada. Por favor, inicia sesión nuevamente.</div>';
} elseif (isset($_GET['timeout'])) {
    $mensaje_login = '<div class="info">⏱️ Sesión cerrada por inactividad (10 minutos).</div>';
} elseif (isset($_GET['logout'])) {
    $mensaje_login = '<div class="success">👋 Has cerrado sesión correctamente.</div>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "❌ Error de seguridad. Por favor, recarga la página.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $error = "❌ Por favor, completa todos los campos";
            $_SESSION['intentos_login']++;
        } else {
            $sql = "SELECT u.*, r.nombre as rol_nombre, r.nivel, 
                           r.puede_crear, r.puede_editar, r.puede_eliminar, r.puede_gestionar_usuarios 
                    FROM usuarios u 
                    LEFT JOIN roles r ON u.rol_id = r.id 
                    WHERE (u.username = ? OR u.email = ?) AND u.activo = 1";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $resultado = $stmt->get_result();
            
            if ($usuario = $resultado->fetch_assoc()) {
                if (password_verify($password, $usuario['password'])) {
                    // Login exitoso - resetear intentos
                    $_SESSION['intentos_login'] = 0;
                    $_SESSION['primer_intento'] = time();
                    
                    $update = $conn->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
                    $update->bind_param("i", $usuario['id']);
                    $update->execute();
                    
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['username'] = $usuario['username'];
                    $_SESSION['nombre'] = $usuario['nombre_completo'];
                    $_SESSION['rol'] = $usuario['rol_nombre'];
                    $_SESSION['rol_nivel'] = $usuario['nivel'];
                    $_SESSION['ultimo_acceso'] = time();
                    $_SESSION['permisos'] = [
                        'crear' => (bool)$usuario['puede_crear'],
                        'editar' => (bool)$usuario['puede_editar'],
                        'eliminar' => (bool)$usuario['puede_eliminar'],
                        'usuarios' => (bool)$usuario['puede_gestionar_usuarios']
                    ];
                    
                    // Regenerar session ID para prevenir fijación
                    session_regenerate_id(true);
                    
                    header('Location: admin.php');
                    exit;
                } else {
                    $error = "❌ Contraseña incorrecta";
                    $_SESSION['intentos_login']++;
                }
            } else {
                $error = "❌ Usuario no encontrado o inactivo";
                $_SESSION['intentos_login']++;
            }
        }
    }
}

// Generar nuevo token para el formulario
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel Administrativo</title>
    <link rel="stylesheet" href="estilo.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 420px;
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-container h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
            font-size: 2rem;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.9rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: <?php echo $color_primario; ?>;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid #f5c6cb;
            font-weight: 500;
        }
        
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid #bee5eb;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
            border: 1px solid #c3e6cb;
        }
        
        .intentos-restantes {
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            margin-top: 0.5rem;
        }
        
        .login-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
            border-top: 1px solid #e0e0e0;
            padding-top: 1.5rem;
        }
        
        .login-footer a {
            color: <?php echo $color_primario; ?>;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem;
            }
            
            .login-container h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Panel de Administración</h1>
        
        <?php echo $mensaje_login; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($_SESSION['intentos_login'] > 0 && $_SESSION['intentos_login'] < $max_intentos && !$bloqueado): ?>
            <div class="intentos-restantes">
                Intentos restantes: <?php echo $max_intentos - $_SESSION['intentos_login']; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="login-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            
            <div class="form-group">
                <label for="username">Usuario o Email</label>
                <input type="text" id="username" name="username" required 
                       placeholder="Ingresa tu usuario o email"
                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                       <?php echo $bloqueado ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Ingresa tu contraseña"
                       <?php echo $bloqueado ? 'disabled' : ''; ?>>
            </div>
            
            <button type="submit" class="btn-login" <?php echo $bloqueado ? 'disabled' : ''; ?>>
                Iniciar Sesión
            </button>
        </form>
        
        <div class="login-footer">
            <p>¿Olvidaste tu contraseña? 
                <a href="#" id="btnWhatsApp">📱 Contacta al administrador por WhatsApp</a>
            </p>
        </div>
    </div>

    <script>
        document.getElementById('btnWhatsApp').addEventListener('click', function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value.trim();
            let mensaje = "El usuario ";
            
            if (username) {
                mensaje += username;
            } else {
                mensaje += "(usuario no especificado)";
            }
            
            mensaje += " está notificando que olvidó la contraseña del panel de administración.";
            
            const mensajeCodificado = encodeURIComponent(mensaje);
            const numero = "<?php echo preg_replace('/[^0-9]/', '', $telefono_contacto); ?>";
            
            if (numero) {
                window.open('https://wa.me/' + numero + '?text=' + mensajeCodificado, '_blank');
            } else {
                alert('⚠️ El número de teléfono no está configurado en la sección de configuración general.');
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
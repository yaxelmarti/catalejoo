<?php
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

// Registrar el cierre de sesión (opcional)
if (isset($_SESSION['usuario_id']) && isset($_SESSION['username'])) {
    // Aquí podrías guardar un log del cierre de sesión si lo deseas
    $username = $_SESSION['username'];
    // error_log("Usuario $username cerró sesión el " . date('Y-m-d H:i:s'));
}
// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Determinar el mensaje a mostrar
$redirect = 'login.php';
if (isset($_GET['expired'])) {
    $redirect .= '?expired=1';
} elseif (isset($_GET['timeout'])) {
    $redirect .= '?timeout=1';
} elseif (isset($_GET['instant'])) {
    // Cierre instantáneo (desde JavaScript), no mostrar mensaje
    $redirect .= '?logout=1';
} else {
    $redirect .= '?logout=1';
}

// Redirigir al login
header("Location: $redirect");
exit;
?>
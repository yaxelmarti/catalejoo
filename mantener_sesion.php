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

// Este archivo se usa para mantener la sesión activa con peticiones AJAX
session_start();

if (isset($_SESSION['usuario_id'])) {
    // Actualizar el tiempo de último acceso
    $_SESSION['ultimo_acceso'] = time();
    
    // Devolver el tiempo restante (opcional)
    $tiempo_restante = 600 - (time() - $_SESSION['ultimo_acceso']);
    echo json_encode([
        'success' => true,
        'tiempo_restante' => $tiempo_restante,
        'message' => 'Sesión mantenida'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No hay sesión activa'
    ]);
}
?>
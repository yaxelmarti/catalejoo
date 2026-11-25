<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ruta absoluta usando __DIR__
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/cliente_identificador.php';

// Obtener el cliente actual usando TU sistema
$cliente = obtenerClienteActual($conn);
$uuid = $cliente['uuid'] ?? $_COOKIE['cliente_uuid'] ?? null;
$session_id = session_id();
$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$pagina_actual = $_SERVER['HTTP_REFERER'] ?? 'desconocida';

// Solo para clientes identificados (id > 0)
if ($cliente && isset($cliente['id']) && $cliente['id'] > 0) {
    $cliente_id = $cliente['id'];
    
    // Verificar si ya existe un registro para esta sesión
    $check = $conn->prepare("SELECT id FROM clientes_online WHERE session_id = ?");
    $check->bind_param("s", $session_id);
    $check->execute();
    $result = $check->get_result();
    
    if ($result->num_rows > 0) {
        // Actualizar registro existente
        $update = $conn->prepare("UPDATE clientes_online SET ultima_actividad = NOW(), pagina_actual = ?, ip_address = ?, user_agent = ? WHERE session_id = ?");
        $update->bind_param("ssss", $pagina_actual, $ip, $user_agent, $session_id);
        $update->execute();
    } else {
        // Insertar nuevo registro
        $insert = $conn->prepare("INSERT INTO clientes_online (cliente_id, session_id, uuid, ip_address, user_agent, pagina_actual) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->bind_param("isssss", $cliente_id, $session_id, $uuid, $ip, $user_agent, $pagina_actual);
        $insert->execute();
    }
    
    // Limpiar registros inactivos (mayor a 2 minutos)
    $conn->query("DELETE FROM clientes_online WHERE ultima_actividad < DATE_SUB(NOW(), INTERVAL 2 MINUTE)");
    
    echo json_encode([
        'status' => 'ok', 
        'cliente_id' => $cliente_id,
        'uuid' => $uuid,
        'nombre' => ($cliente['nombre'] ?? '') . ' ' . ($cliente['apellidos'] ?? '')
    ]);
} else {
    // Visitante anónimo o inactivo
    echo json_encode([
        'status' => 'no_identificado',
        'uuid' => $uuid
    ]);
}

$conn->close();
?>
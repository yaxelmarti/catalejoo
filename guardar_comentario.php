<?php
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'comentario';
$mensaje = isset($_POST['mensaje']) ? trim($_POST['mensaje']) : '';
$url = isset($_POST['url']) ? $_POST['url'] : '';

if (empty($mensaje)) {
    echo json_encode(['success' => false, 'mensaje' => 'El mensaje es obligatorio']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO comentarios_clientes (nombre, email, tipo, mensaje, url) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $nombre, $email, $tipo, $mensaje, $url);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'mensaje' => 'Comentario enviado. ¡Gracias!']);
} else {
    echo json_encode(['success' => false, 'mensaje' => 'Error al guardar']);
}
?>
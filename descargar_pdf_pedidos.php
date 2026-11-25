<?php
// ============================================
// DESCARGAR PDF DEL PEDIDO
// ============================================

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();
require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';

// Obtener cliente actual
$cliente = obtenerClienteActual($conn);
$cliente_id = $cliente['id'] ?? 0;

if ($cliente_id == 0) {
    http_response_code(403);
    die("❌ Cliente no identificado");
}

// Validar pedido
$pedido_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($pedido_id == 0) {
    http_response_code(400);
    die("❌ Pedido no válido");
}

// Verificar que el pedido pertenece al cliente
$stmt = $conn->prepare("SELECT id, codigo FROM pedidos WHERE id = ? AND cliente_id = ?");
$stmt->bind_param("ii", $pedido_id, $cliente_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    http_response_code(404);
    die("❌ Pedido no encontrado o no te pertenece");
}

$pedido = $result->fetch_assoc();

// Incluir el generador de PDF
require_once 'includes/generar_pdf_pedido.php';

// Generar el contenido del PDF
$pdf_content = generarPDFPedido($pedido_id, $conn);

if ($pdf_content) {
    // Limpiar buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Cabeceras de seguridad
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="pedido_' . $pedido['codigo'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf_content));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $pdf_content;
    exit;
} else {
    http_response_code(500);
    die("❌ Error al generar el PDF");
}
?>
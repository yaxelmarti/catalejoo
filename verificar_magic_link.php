<?php
// ============================================
// VERIFICAR ENLACE MÁGICO - VINCULAR DISPOSITIVO
// ============================================

// Asegurar que la sesión esté activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';
require_once 'includes/carrito_funciones.php';

// ============================================
// OBTENER PARÁMETROS
// ============================================
$token = $_GET['token'] ?? '';
$uuid = $_GET['uuid'] ?? '';

if (empty($token) || empty($uuid)) {
    die('❌ Enlace inválido');
}

// Validar formato del token (64 caracteres hexadecimales)
if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    die('❌ Token inválido');
}

// ============================================
// VALIDAR TOKEN
// ============================================
$stmt = $conn->prepare("SELECT id FROM clientes WHERE reset_token = ? AND reset_expires > NOW() AND activo = 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('❌ El enlace ha expirado o es inválido');
}

$cliente = $result->fetch_assoc();
$cliente_id = (int)$cliente['id'];

// ============================================
// VINCULAR DISPOSITIVO
// ============================================

// Verificar cuántos dispositivos tiene vinculados
$stmt = $conn->prepare("SELECT uuid, uuid2, uuid3 FROM clientes WHERE id = ?");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$result = $stmt->get_result();
$cliente_actual = $result->fetch_assoc();

// Buscar un campo vacío para el nuevo UUID
if (empty($cliente_actual['uuid'])) {
    $campo = 'uuid';
} elseif (empty($cliente_actual['uuid2'])) {
    $campo = 'uuid2';
} elseif (empty($cliente_actual['uuid3'])) {
    $campo = 'uuid3';
} else {
    die('❌ Ya tienes 3 dispositivos vinculados. Elimina uno para agregar este.');
}

// Actualizar el campo correspondiente
$sql = "UPDATE clientes SET $campo = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $uuid, $cliente_id);
$stmt->execute();

// Establecer cookie de sesión
setcookie('cliente_uuid', $uuid, time() + 86400 * 365, '/', '', true, true);
$_SESSION['cliente_id'] = $cliente_id;

// Verificar email automáticamente si no lo estaba
$stmt = $conn->prepare("UPDATE clientes SET email_verified = 1 WHERE id = ? AND email_verified = 0");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();

// ============================================
// AGREGAR PRODUCTO PENDIENTE SI EXISTE
// ============================================

$producto_pendiente = $_SESSION['producto_pendiente'] ?? null;

// Si no está en sesión, buscar en cookie (respaldo para enlace mágico)
if (!$producto_pendiente && isset($_COOKIE['producto_pendiente'])) {
    $producto_pendiente = json_decode($_COOKIE['producto_pendiente'], true);
}

if (!empty($producto_pendiente) && is_array($producto_pendiente)) {
    $producto_id = (int)($producto_pendiente['id'] ?? 0);
    $cantidad = (int)($producto_pendiente['cantidad'] ?? 1);
    $es_combo = (bool)($producto_pendiente['combo'] ?? false);
    
    if ($producto_id > 0 && $cantidad > 0) {
        // Obtener carrito del cliente
        $carrito = obtenerOCrearCarrito($conn, $cliente_id);
        
        if ($carrito) {
            $carrito_id = is_array($carrito) ? $carrito['id'] : $carrito;
            
            if ($es_combo) {
                // Verificar que el combo existe y está activo
                $stmt_check = $conn->prepare("SELECT id, precio_final FROM combos WHERE id = ? AND activo = 1");
                $stmt_check->bind_param("i", $producto_id);
                $stmt_check->execute();
                $combo = $stmt_check->get_result()->fetch_assoc();
                
                if ($combo) {
                    // Verificar que el combo no esté ya en el carrito
                    $stmt_check = $conn->prepare("SELECT id FROM carrito_combos WHERE carrito_id = ? AND combo_id = ?");
                    $stmt_check->bind_param("ii", $carrito_id, $producto_id);
                    $stmt_check->execute();
                    $existe = $stmt_check->get_result()->fetch_assoc();
                    
                    if (!$existe) {
                        $precio_total = (float)$combo['precio_final'] * $cantidad;
                        $stmt_insert = $conn->prepare("INSERT INTO carrito_combos (carrito_id, combo_id, cantidad, precio_total) VALUES (?, ?, ?, ?)");
                        $stmt_insert->bind_param("iiid", $carrito_id, $producto_id, $cantidad, $precio_total);
                        $stmt_insert->execute();
                    }
                }
            } else {
                // Verificar que el producto existe y está activo
                $stmt_check = $conn->prepare("SELECT id, precio FROM productos WHERE id = ? AND activo = 1");
                $stmt_check->bind_param("i", $producto_id);
                $stmt_check->execute();
                $producto = $stmt_check->get_result()->fetch_assoc();
                
                if ($producto) {
                    // Verificar que el producto no esté ya en el carrito
                    $stmt_check = $conn->prepare("SELECT id FROM carrito_productos WHERE carrito_id = ? AND producto_id = ?");
                    $stmt_check->bind_param("ii", $carrito_id, $producto_id);
                    $stmt_check->execute();
                    $existe = $stmt_check->get_result()->fetch_assoc();
                    
                    if ($existe) {
                        // Si ya existe, actualizar cantidad
                        $stmt_update = $conn->prepare("UPDATE carrito_productos SET cantidad = cantidad + ? WHERE id = ?");
                        $stmt_update->bind_param("ii", $cantidad, $existe['id']);
                        $stmt_update->execute();
                    } else {
                        // ✅ CORREGIDO: Guardar en variable antes de bind_param
                        $precio_unitario = (float)$producto['precio'];
                        $stmt_insert = $conn->prepare("INSERT INTO carrito_productos (carrito_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
                        $stmt_insert->bind_param("iiid", $carrito_id, $producto_id, $cantidad, $precio_unitario);
                        $stmt_insert->execute();
                    }
                }
            }
        }
    }
    
    // Limpiar producto pendiente
    unset($_SESSION['producto_pendiente']);
    setcookie('producto_pendiente', '', time() - 3600, '/');
}

// ============================================
// REDIRIGIR CON PARÁMETRO PARA ABRIR MODAL
// ============================================
$redirect_url = '/?abrir_editar=1';

// Si se agregó producto pendiente, ir directo a la tienda sin abrir modal
if (isset($producto_pendiente) && !empty($producto_pendiente)) {
    $redirect_url = '/';
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dispositivo vinculado - Catalejo</title>
    <meta http-equiv="refresh" content="2;url=<?php echo $redirect_url; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            text-align: center;
            padding: 50px 20px;
            background: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            max-width: 400px;
            width: 100%;
        }
        .success { 
            color: #48bb78; 
            font-size: 64px;
            display: block;
            margin-bottom: 10px;
        }
        h2 {
            color: #2d3748;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        p {
            color: #718096;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-top: 1rem;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .producto-info {
            background: #f7fafc;
            border-radius: 12px;
            padding: 12px 16px;
            margin: 16px 0;
            font-size: 0.9rem;
            color: #2d3748;
            border: 1px solid #e2e8f0;
            display: <?php echo (isset($producto_pendiente) && !empty($producto_pendiente)) ? 'block' : 'none'; ?>;
        }
        .producto-info strong {
            color: #667eea;
        }
        @media (max-width: 480px) {
            .card { padding: 24px; }
            .success { font-size: 48px; }
            h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="success">✅</span>
        <h2>¡Dispositivo vinculado!</h2>
        <p>Este dispositivo ya está vinculado a tu cuenta.</p>
        
        <?php if (isset($producto_pendiente) && !empty($producto_pendiente) && $producto_id > 0): ?>
        <div class="producto-info">
            🛒 <strong><?php echo $es_combo ? 'Combo' : 'Producto'; ?></strong> agregado al carrito 
            (<?php echo $cantidad; ?> unidad<?php echo $cantidad > 1 ? 'es' : ''; ?>)
        </div>
        <?php endif; ?>
        
        <p style="font-size: 0.85rem; color: #a0aec0; margin-top: 0.5rem;">
            Redirigiendo a la tienda...
        </p>
        <div class="spinner"></div>
    </div>
</body>
</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// ============================================
// VERIFICAR SESIÓN
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

require_once 'conexion.php';
require_once 'includes/proveedor_utils.php';

$usuario_id = intval($_SESSION['usuario_id']);
$rol = trim($_SESSION['rol'] ?? '');

// ============================================
// DETECTAR PROVEEDOR DEL USUARIO
// ============================================
$proveedor_id = intval(obtenerProveedorId($conn, $usuario_id, $rol));
$es_proveedor = esRolProveedor($rol);

// ============================================
// VALIDAR PROVEEDOR OBLIGATORIO
// ============================================
if ($es_proveedor && $proveedor_id == 0) {
    session_destroy();
    header('Location: /login.php?error=sin_tienda');
    exit;
}

// ============================================
// VALIDAR TIENDA ACTIVA
// ============================================
if ($proveedor_id > 0) {
    $stmt_check_prov = $conn->prepare("SELECT id FROM proveedores WHERE id = ? AND activo = 1");
    if ($stmt_check_prov) {
        $stmt_check_prov->bind_param("i", $proveedor_id);
        $stmt_check_prov->execute();
        $prov_activo = $stmt_check_prov->get_result()->fetch_assoc();
        $stmt_check_prov->close();

        if (!$prov_activo) {
            session_destroy();
            header('Location: /login.php?error=tienda_inactiva');
            exit;
        }
    }
}

$nombre_tienda = obtenerNombreTienda($conn, $proveedor_id);

// ============================================
// OBTENER DATOS DEL USUARIO
// ============================================
$stmt = $conn->prepare("SELECT u.*, r.nombre as rol_nombre, r.nivel 
                        FROM usuarios u 
                        JOIN roles r ON u.rol_id = r.id 
                        WHERE u.id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

// ============================================
// ESTADÍSTICAS CON FILTRO POR PROVEEDOR (PREPARED STATEMENTS)
// ============================================
$total_productos = 0;
$productos_activos = 0;
$productos_agotados = 0;
$total_pedidos = 0;
$pedidos_pendientes = 0;
$pedidos_completados = 0;
$ingresos_totales = 0;
$total_clientes = 0;
$total_combos = 0;

// Helper para contar con/sin filtro de proveedor
function contarConFiltro($conn, $tabla, $proveedor_id, $condicion_extra = '') {
    if ($proveedor_id > 0) {
        $sql = "SELECT COUNT(*) as total FROM $tabla WHERE proveedor_id = ?" . ($condicion_extra ? " AND $condicion_extra" : "");
        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;
        $stmt->bind_param("i", $proveedor_id);
    } else {
        $sql = "SELECT COUNT(*) as total FROM $tabla" . ($condicion_extra ? " WHERE $condicion_extra" : "");
        $stmt = $conn->prepare($sql);
        if (!$stmt) return 0;
    }
    $stmt->execute();
    $total = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
    return $total;
}

// Productos
$total_productos = contarConFiltro($conn, 'productos', $proveedor_id);
$productos_activos = contarConFiltro($conn, 'productos', $proveedor_id, 'activo = 1');
$productos_agotados = contarConFiltro($conn, 'productos', $proveedor_id, 'cantidad <= 0');

// Pedidos (con JOIN a pedido_detalles)
if ($proveedor_id > 0) {
    // Total
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as total FROM pedidos p JOIN pedido_detalles pd ON p.id = pd.pedido_id WHERE pd.proveedor_id = ?");
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $total_pedidos = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Pendientes
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as total FROM pedidos p JOIN pedido_detalles pd ON p.id = pd.pedido_id WHERE pd.proveedor_id = ? AND p.estado_actual NOT IN ('entregado', 'cancelado')");
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $pedidos_pendientes = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Completados
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as total FROM pedidos p JOIN pedido_detalles pd ON p.id = pd.pedido_id WHERE pd.proveedor_id = ? AND p.estado_actual = 'entregado'");
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $pedidos_completados = intval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();

    // Ingresos
    $stmt = $conn->prepare("SELECT SUM(p.total_convertido) as total FROM pedidos p JOIN pedido_detalles pd ON p.id = pd.pedido_id WHERE pd.proveedor_id = ? AND p.estado_actual = 'entregado'");
    $stmt->bind_param("i", $proveedor_id);
    $stmt->execute();
    $ingresos_totales = floatval($stmt->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt->close();
} else {
    $result = $conn->query("SELECT COUNT(DISTINCT p.id) as total FROM pedidos p JOIN pedido_detalles pd ON p.id = pd.pedido_id");
    if ($result) $total_pedidos = intval($result->fetch_assoc()['total'] ?? 0);

    $result = $conn->query("SELECT COUNT(DISTINCT p.id) as total FROM pedidos p JOIN pedido_detalles pd ON p.id = pd.pedido_id WHERE p.estado_actual NOT IN ('entregado', 'cancelado')");
    if ($result) $pedidos_pendientes = intval($result->fetch_assoc()['total'] ?? 0);

    $result = $conn->query("SELECT COUNT(DISTINCT p.id) as total FROM pedidos p JOIN pedido_detalles pd ON p.id = pd.pedido_id WHERE p.estado_actual = 'entregado'");
    if ($result) $pedidos_completados = intval($result->fetch_assoc()['total'] ?? 0);

    $result = $conn->query("SELECT SUM(p.total_convertido) as total FROM pedidos p JOIN pedido_detalles pd ON p.id = pd.pedido_id WHERE p.estado_actual = 'entregado'");
    if ($result) $ingresos_totales = floatval($result->fetch_assoc()['total'] ?? 0);
}

// Clientes (solo admins)
$es_admin = in_array($rol, ['Super Admin', 'Gerente']);
if ($es_admin) {
    $result = $conn->query("SELECT COUNT(*) as total FROM clientes");
    if ($result) $total_clientes = intval($result->fetch_assoc()['total'] ?? 0);
}

// Combos
$total_combos = contarConFiltro($conn, 'combos', $proveedor_id);

// ============================================
// CONFIGURACIÓN
// ============================================
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion");
if ($result) {
    while($row = $result->fetch_assoc()) {
        $config[$row['clave']] = $row['valor'];
    }
}
$moneda_simbolo = $config['moneda_simbolo'] ?? '$';
$sitio_nombre = $config['sitio_nombre'] ?? 'Catalejoo';

// ============================================
// INCLUIR HEADER (YA ABRE <html><head><body><main>)
// ============================================
include 'header.php';
?>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }
    @media (max-width: 992px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 576px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.04);
        transition: all 0.3s ease;
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.10); }

    .stat-icon {
        width: 52px; height: 52px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; margin-bottom: 12px;
    }
    .stat-icon.blue { background: #e0e7ff; color: #4f46e5; }
    .stat-icon.orange { background: #fef3c7; color: #d97706; }
    .stat-icon.green { background: #d1fae5; color: #065f46; }
    .stat-icon.pink { background: #fce4ec; color: #be185d; }

    .stat-number { font-size: 28px; font-weight: 800; color: #1a1a2e; }
    .stat-label { font-size: 14px; color: #6b7280; font-weight: 500; }

    .stat-change {
        font-size: 12px; font-weight: 600; padding: 2px 10px;
        border-radius: 20px; display: inline-block; margin-top: 6px;
    }
    .stat-change.up { background: #d1fae5; color: #065f46; }
    .stat-change.down { background: #fee2e2; color: #991b1b; }
    .stat-change.warning { background: #fef3c7; color: #92400e; }

    .section-title { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 20px; }
    .section-title .icon { color: <?php echo $color_primario; ?>; margin-right: 8px; }

    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }
    @media (max-width: 992px) { .quick-actions-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 576px) { .quick-actions-grid { grid-template-columns: 1fr; } }

    .quick-action-btn {
        padding: 16px 20px; border-radius: 12px; border: 1px solid #e5e7eb;
        background: white; transition: all 0.2s ease; text-decoration: none;
        color: #1a1a2e; display: flex; align-items: center; gap: 14px;
    }
    .quick-action-btn:hover {
        background: #f9fafb; border-color: #d1d5db; transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.06); color: #1a1a2e;
    }
    .quick-action-btn .icon { font-size: 20px; color: <?php echo $color_primario; ?>; width: 24px; text-align: center; }
    .quick-action-btn .text { font-weight: 500; font-size: 14px; }

    .badge-count {
        background: #ef4444; color: white; font-size: 11px; font-weight: 600;
        padding: 2px 10px; border-radius: 20px; margin-left: auto;
    }

    .dashboard-header {
        display: flex; flex-wrap: wrap; align-items: center;
        justify-content: space-between; margin-bottom: 30px;
    }
    .dashboard-title { font-size: 24px; font-weight: 700; color: #1a1a2e; margin: 0; }
    .dashboard-title .icon { color: <?php echo $color_primario; ?>; margin-right: 10px; }
    .dashboard-subtitle { color: #6b7280; font-size: 14px; margin-top: 4px; }

    .role-badge {
        display: inline-block; background: rgba(102, 126, 234, 0.1);
        color: <?php echo $color_primario; ?>; padding: 4px 14px;
        border-radius: 20px; font-size: 13px; font-weight: 500;
    }
    .tienda-badge {
        display: inline-block; background: rgba(16, 185, 129, 0.1);
        color: #065f46; padding: 4px 14px; border-radius: 20px;
        font-size: 13px; font-weight: 500; margin-left: 8px;
    }
    .dashboard-date { color: #6b7280; font-size: 14px; }

    .mt-4 { margin-top: 30px; }
    .ms-1 { margin-left: 6px; }
</style>

<!-- ===== BIENVENIDA ===== -->
<div class="dashboard-header">
    <div>
        <h1 class="dashboard-title">
            <span class="icon">📊</span>
            ¡Bienvenido, <?php echo htmlspecialchars($usuario['nombre'] ?? 'Usuario'); ?>!
        </h1>
        <p class="dashboard-subtitle">
            <span class="role-badge"><?php echo htmlspecialchars($usuario['rol_nombre'] ?? 'Sin rol'); ?></span>
            <?php if ($proveedor_id > 0 && !empty($nombre_tienda)): ?>
                <span class="tienda-badge">🏪 <?php echo htmlspecialchars($nombre_tienda); ?></span>
            <?php endif; ?>
        </p>
    </div>
    <div class="dashboard-date">
        📅 <?php echo date('d/m/Y H:i'); ?>
    </div>
</div>

<!-- ===== TARJETAS DE ESTADÍSTICAS ===== -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">📦</div>
        <div class="stat-number"><?php echo number_format($total_productos); ?></div>
        <div class="stat-label">Productos</div>
        <div>
            <span class="stat-change up">✅ <?php echo number_format($productos_activos); ?> activos</span>
            <?php if ($productos_agotados > 0): ?>
                <span class="stat-change down ms-1">⚠️ <?php echo number_format($productos_agotados); ?> agotados</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">🛒</div>
        <div class="stat-number"><?php echo number_format($total_pedidos); ?></div>
        <div class="stat-label">Pedidos</div>
        <div>
            <span class="stat-change warning">⏳ <?php echo number_format($pedidos_pendientes); ?> pendientes</span>
            <span class="stat-change up ms-1">✅ <?php echo number_format($pedidos_completados); ?> completados</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">💰</div>
        <div class="stat-number"><?php echo $moneda_simbolo . number_format($ingresos_totales, 2); ?></div>
        <div class="stat-label">Ingresos totales</div>
        <div>
            <span class="stat-change up">📈 Completados</span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon pink">
            <?php echo ($es_admin) ? '👥' : '🧩'; ?>
        </div>
        <div class="stat-number">
            <?php echo ($es_admin) ? number_format($total_clientes) : number_format($total_combos); ?>
        </div>
        <div class="stat-label">
            <?php echo ($es_admin) ? 'Clientes' : 'Combos'; ?>
        </div>
    </div>
</div>

<!-- ===== ACCESOS RÁPIDOS ===== -->
<div class="mt-4">
    <h4 class="section-title">
        <span class="icon">⚡</span> Accesos rápidos
    </h4>

    <div class="quick-actions-grid">
        <a href="/admin/stock/productos.php" class="quick-action-btn">
            <span class="icon">📦</span>
            <span class="text">Gestionar productos</span>
        </a>

        <a href="/admin/pedidos.php" class="quick-action-btn">
            <span class="icon">🛒</span>
            <span class="text">Ver pedidos</span>
            <?php if ($pedidos_pendientes > 0): ?>
                <span class="badge-count"><?php echo $pedidos_pendientes; ?></span>
            <?php endif; ?>
        </a>

        <?php if ($es_admin): ?>
            <a href="/admin/clientes.php" class="quick-action-btn">
                <span class="icon">👥</span>
                <span class="text">Clientes</span>
            </a>
            <a href="/admin/proveedores/gestionar_proveedores.php" class="quick-action-btn">
                <span class="icon">🏪</span>
                <span class="text">Proveedores</span>
            </a>
        <?php endif; ?>

        <a href="/admin/stock/combos.php" class="quick-action-btn">
            <span class="icon">🧩</span>
            <span class="text">Combos</span>
        </a>

        <a href="/admin/pagos.php" class="quick-action-btn">
            <span class="icon">💳</span>
            <span class="text">Pagos</span>
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>
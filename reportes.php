<?php
// ============================================
// REPORTES - PANEL DE REPORTES (raíz)
// Versión 27 Oct 2026
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/includes/proveedor_utils.php';

// ============================================
// VALIDAR SESIÓN
// ============================================
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

$rol_actual = trim($_SESSION['rol'] ?? '');
$usuario_id_actual = intval($_SESSION['usuario_id'] ?? 0);

// ============================================
// MATRIZ DE PERMISOS POR REPORTE
// ============================================
// Nota: esta matriz SOLO controla el acceso al reporte.
// El filtrado por proveedor se hace dentro de cada archivo incluido.
$permisos_reportes = [
    'productos' => ['Super Admin', 'Gerente', 'Supervisor', 'Contador', 'Proveedor', 'Inventario', 'Vendedor'],
    'clientes'  => ['Super Admin', 'Gerente', 'Supervisor', 'Contador'],
    'carritos'  => ['Super Admin', 'Gerente', 'Supervisor', 'Contador', 'Proveedor', 'Inventario'],
    'dashboard' => ['Super Admin', 'Gerente', 'Supervisor', 'Contador', 'Proveedor'],
    'ventas'    => [
        'productos' => ['Super Admin', 'Gerente', 'Supervisor', 'Contador', 'Proveedor', 'Vendedor'],
        'pedidos'   => ['Super Admin', 'Gerente', 'Supervisor', 'Contador', 'Proveedor', 'Vendedor'],
        'clientes'  => ['Super Admin', 'Gerente', 'Supervisor', 'Contador'],
    ],
];

// ============================================
// DETERMINAR TAB Y TIPO DE VENTAS
// ============================================
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'productos';
$tipo_reporte_ventas = $_GET['tipo_reporte'] ?? 'productos';

// ============================================
// VALIDAR PERMISO DEL TAB SOLICITADO
// ============================================
$roles_para_tab = [];
if ($tab === 'ventas') {
    if (!isset($permisos_reportes['ventas'][$tipo_reporte_ventas])) {
        $tipo_reporte_ventas = 'productos';
    }
    $roles_para_tab = $permisos_reportes['ventas'][$tipo_reporte_ventas];
} elseif (isset($permisos_reportes[$tab])) {
    $roles_para_tab = $permisos_reportes[$tab];
} else {
    $tab = 'productos';
    $roles_para_tab = $permisos_reportes['productos'];
}

if (!in_array($rol_actual, $roles_para_tab, true)) {
    error_log("[reportes] Permiso denegado. Rol: $rol_actual, tab: $tab, tipo_ventas: $tipo_reporte_ventas, usuario: $usuario_id_actual");
    $_SESSION['mensaje'] = '❌ No tienes permiso para ver este reporte';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: /admin.php?error=permiso');
    exit;
}

// ============================================
// DETECTAR PROVEEDOR
// ============================================
$proveedor_id_usuario = intval(obtenerProveedorId($conn, $usuario_id_actual, $rol_actual));
$es_proveedor = esRolProveedor($rol_actual);

if ($es_proveedor && $proveedor_id_usuario == 0) {
    $_SESSION['mensaje'] = '❌ No se encontró una tienda asociada a tu usuario';
    $_SESSION['tipo_mensaje'] = 'error';
    header('Location: /admin.php');
    exit;
}

if ($proveedor_id_usuario > 0) {
    $stmt_check = $conn->prepare("SELECT id FROM proveedores WHERE id = ? AND activo = 1 LIMIT 1");
    if ($stmt_check) {
        $stmt_check->bind_param("i", $proveedor_id_usuario);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows === 0) {
            $stmt_check->close();
            $_SESSION['mensaje'] = '❌ Tu tienda no está activa';
            $_SESSION['tipo_mensaje'] = 'error';
            header('Location: /admin.php');
            exit;
        }
        $stmt_check->close();
    }
}

// ============================================
// MAPA TAB → ARCHIVO
// ============================================
$archivos_reporte = [
    'productos' => __DIR__ . '/reportes/reporte_productos.php',
    'clientes'  => __DIR__ . '/reportes/reportes_clientes.php',
    'carritos'  => __DIR__ . '/reportes/reportes_carritos.php',
    'dashboard' => __DIR__ . '/reportes/reportes_dashboard.php',
];

$archivos_ventas = [
    'productos' => __DIR__ . '/reportes/reportes_ventas_productos.php',
    'pedidos'   => __DIR__ . '/reportes/reportes_ventas_pedidos.php',
    'clientes'  => __DIR__ . '/reportes/reportes_ventas_clientes.php',
];

$archivo_a_incluir = null;
if ($tab === 'ventas') {
    $archivo_a_incluir = $archivos_ventas[$tipo_reporte_ventas] ?? $archivos_ventas['productos'];
} else {
    $archivo_a_incluir = $archivos_reporte[$tab] ?? $archivos_reporte['productos'];
}

$archivo_existe = ($archivo_a_incluir !== null && file_exists($archivo_a_incluir));
if (!$archivo_existe) {
    error_log("[reportes] Archivo no encontrado. Tab: $tab, tipo: $tipo_reporte_ventas, archivo: " . ($archivo_a_incluir ?? 'NULL'));
}
?>

<style>
.tab-content {
    background: white;
    border-radius: 20px;
    padding: clamp(1rem, 4vw, 1.5rem);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    overflow-x: auto;
    position: relative;
}

.tabla-reporte th:last-child,
.tabla-reporte td:last-child,
.tabla-reporte-clientes th:last-child,
.tabla-reporte-clientes td:last-child,
.tabla-reporte-carritos th:last-child,
.tabla-reporte-carritos td:last-child {
    position: sticky;
    right: 0;
    background: white;
    box-shadow: -2px 0 5px rgba(0,0,0,0.1);
    z-index: 2;
}

.tabla-reporte th:last-child,
.tabla-reporte-clientes th:last-child,
.tabla-reporte-carritos th:last-child {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    z-index: 3;
}

.btn-accion {
    background: #667eea;
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 4px;
    text-decoration: none;
    font-size: 0.8rem;
    display: inline-block;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
    min-height: 32px;
}

.btn-accion:hover {
    background: #5a67d8;
    transform: translateY(-2px);
}

.tabla-reporte,
.tabla-reporte-clientes,
.tabla-reporte-carritos {
    overflow-x: auto;
    width: 100%;
}

@media (max-width: 768px) {
    .btn-accion {
        padding: 0.25rem 0.6rem;
        font-size: 0.7rem;
    }
}

.reporte-error {
    background: #fef5e7;
    border: 2px solid #f6ad55;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    color: #7b341e;
    font-size: 0.95rem;
    margin: 1rem 0;
}
.reporte-error strong { display: block; margin-bottom: 0.5rem; font-size: 1.1rem; }
.reporte-error code {
    background: #fff; padding: 0.2rem 0.5rem; border-radius: 4px;
    font-family: monospace; color: #c53030; word-break: break-all;
}
</style>

<div class="container" style="margin-top: 0; margin-bottom: 2rem;">
    <div class="tab-content">
        <?php if ($archivo_existe): ?>
            <?php include $archivo_a_incluir; ?>
        <?php else: ?>
            <div class="reporte-error">
                <strong>⚠️ Reporte no disponible</strong>
                El archivo del reporte no se encontró en el servidor.
                <?php if ($rol_actual === 'Super Admin'): ?>
                    <br><small>Archivo esperado: <code><?php echo htmlspecialchars($archivo_a_incluir ?? 'NULL'); ?></code></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/footer.php';
?>
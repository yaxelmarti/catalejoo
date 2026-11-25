<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== CONTROL DE SEGURIDAD DE SESIÓN =====
define('TIEMPO_MAXIMO_INACTIVIDAD', 1800);

if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php?expired=1');
    exit;
}

if (isset($_SESSION['ultimo_acceso'])) {
    if (time() - $_SESSION['ultimo_acceso'] > TIEMPO_MAXIMO_INACTIVIDAD) {
        session_unset();
        session_destroy();
        header('Location: /login.php?timeout=1');
        exit;
    }
}

$_SESSION['ultimo_acceso'] = time();

require_once $_SERVER['DOCUMENT_ROOT'] . '/conexion.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/proveedor_utils.php';

// ============================================
// CSRF GLOBAL (añadido 23 Oct 2026)
// Genera token si no existe. Helpers disponibles
// en todos los archivos que incluyan header.php.
// ============================================
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!function_exists('csrf_field')) {
    /**
     * Devuelve el input hidden con el token CSRF.
     * Uso: <?php echo csrf_field(); ?>
     */
    function csrf_field() {
        $token = $_SESSION['csrf_token'] ?? '';
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_validar')) {
    /**
     * Valida el token recibido contra el de sesión.
     * Uso: if (!csrf_validar($_POST['csrf_token'] ?? '')) { ... }
     */
    function csrf_validar($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], (string)$token);
    }
}

// ===== CARGAR DATOS ACTUALIZADOS DEL USUARIO =====
$stmt = $conn->prepare("SELECT u.*, r.nombre as rol_nombre, r.nivel, 
                               r.puede_crear, r.puede_editar, r.puede_eliminar, r.puede_gestionar_usuarios 
                        FROM usuarios u 
                        LEFT JOIN roles r ON u.rol_id = r.id 
                        WHERE u.id = ? AND u.activo = 1");
$stmt->bind_param("i", $_SESSION['usuario_id']);
$stmt->execute();
$usuario_actual = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario_actual) {
    session_destroy();
    header('Location: /login.php?error=inactivo');
    exit;
}

// Actualizar datos de sesión
$_SESSION['username'] = $usuario_actual['username'];
$_SESSION['nombre'] = $usuario_actual['nombre_completo'];
$_SESSION['rol'] = $usuario_actual['rol_nombre'];
$_SESSION['rol_nivel'] = $usuario_actual['nivel'];
$_SESSION['permisos'] = [
    'crear' => (bool)$usuario_actual['puede_crear'],
    'editar' => (bool)$usuario_actual['puede_editar'],
    'eliminar' => (bool)$usuario_actual['puede_eliminar'],
    'usuarios' => (bool)$usuario_actual['puede_gestionar_usuarios']
];

$permisos = $_SESSION['permisos'];
$rol = $_SESSION['rol'];
$nombre = $_SESSION['nombre'];
$nivel = $_SESSION['rol_nivel'];

// ============================================
// DETECCIÓN DE PROVEEDOR (con función centralizada)
// ============================================
$es_rol_proveedor = esRolProveedor($rol);
$proveedor_id = obtenerProveedorId($conn, $_SESSION['usuario_id'], $rol);

// Redirigir si es rol de tienda sin proveedor asociado
if ($es_rol_proveedor && $proveedor_id == 0) {
    session_destroy();
    header('Location: /login.php?error=sin_tienda');
    exit;
}

$es_proveedor_dueño = ($rol === 'Proveedor');
$es_inventario = ($rol === 'Inventario');
$es_vendedor = ($rol === 'Vendedor');
$es_empaquetador = ($rol === 'Empaquetador');
$es_transportista = ($rol === 'Transportista');

$nombre_tienda = '';
if ($proveedor_id > 0) {
    $nombre_tienda = obtenerNombreTienda($conn, $proveedor_id);
}

// ===== BADGE DE ROL =====
$colores_rol = [
    'Super Admin' => '#667eea',
    'Gerente' => '#4299e1',
    'Supervisor' => '#ecc94b',
    'Contador' => '#48bb78',
    'Proveedor' => '#ed8936',
    'Inventario' => '#38b2ac',
    'Vendedor' => '#ed64a6',
    'Empaquetador' => '#a0aec0',
    'Transportista' => '#975a16'
];
$color_rol = $colores_rol[$rol] ?? '#718096';

// ============================================
// PERMISOS POR ROL (SISTEMA)
// ============================================
// Roles globales con acceso a secciones de administración
$ver_productos = in_array($rol, ['Super Admin', 'Gerente', 'Supervisor', 'Contador']);
$ver_combos = in_array($rol, ['Super Admin', 'Gerente', 'Supervisor', 'Contador'], true);
$ver_clientes = in_array($rol, ['Super Admin', 'Gerente', 'Supervisor', 'Contador']);
$ver_carritos = in_array($rol, ['Super Admin', 'Gerente', 'Supervisor', 'Contador']);
$ver_pedidos = in_array($rol, ['Super Admin', 'Gerente', 'Supervisor', 'Contador']);
$ver_pagos = in_array($rol, ['Super Admin', 'Gerente', 'Supervisor', 'Contador']);
$ver_reportes = in_array($rol, ['Super Admin', 'Gerente', 'Supervisor', 'Contador']);
$ver_proveedores = in_array($rol, ['Super Admin', 'Gerente']);
$ver_usuarios = in_array($rol, ['Super Admin', 'Gerente', 'Proveedor'], true);
$ver_config = in_array($rol, ['Super Admin', 'Gerente']);

// ===== SECCIONES PERMITIDAS PARA EL MENÚ DE CONFIGURACIÓN =====
$secciones_permitidas = [];

// ===== SECCIONES PERMITIDAS PARA EL MENÚ DE CONFIGURACIÓN =====
$secciones_permitidas = [];

if ($rol === 'Super Admin') {
    $secciones_permitidas = ['generales', 'carrito', 'email', 'pagos', 'envios', 'tasas', 'monedas'];
} elseif ($rol === 'Gerente') {
    $secciones_permitidas = ['carrito', 'email', 'pagos', 'envios', 'tasas', 'monedas'];
} elseif ($rol === 'Contador') {
    $secciones_permitidas = ['pagos'];
} elseif ($rol === 'Proveedor') {
    $secciones_permitidas = ['carrito', 'email', 'pagos', 'envios', 'monedas']; // ← Sin 'tasas'
}

// Metadatos de las secciones de configuración (icono, título, URL)
$config_secciones_meta = [
    'generales' => ['icon' => '⚙️', 'titulo' => 'General',         'titulo_prov' => 'General',     'url' => '/configuracion/generales.php'],
    'carrito'   => ['icon' => '🛒', 'titulo' => 'Carrito',         'titulo_prov' => 'Mi Carrito',  'url' => '/configuracion/carrito.php'],
    'email'     => ['icon' => '📧', 'titulo' => 'Email',           'titulo_prov' => 'Mi Email',    'url' => '/configuracion/email.php'],
    'pagos'     => ['icon' => '💳', 'titulo' => 'Pagos',           'titulo_prov' => 'Mis Pagos',   'url' => '/configuracion/pagos.php'],
    'envios'    => ['icon' => '🚚', 'titulo' => 'Envíos',          'titulo_prov' => 'Mis Envíos',  'url' => '/configuracion/envios.php'],
    'tasas'     => ['icon' => '💱', 'titulo' => 'Tasas de Cambio', 'titulo_prov' => 'Mis Tasas',   'url' => '/configuracion/tasas.php'],
    'monedas'   => ['icon' => '🪙', 'titulo' => 'Monedas',         'titulo_prov' => 'Mis Monedas', 'url' => '/configuracion/monedas.php'],
];

// ===== CARGAR COLORES DESDE CONFIGURACIÓN =====
$stmt_color = $conn->prepare("SELECT valor FROM configuracion WHERE clave = 'color_primario'");
$stmt_color->execute();
$result_color = $stmt_color->get_result();
$color_primario = $result_color->num_rows > 0 ? $result_color->fetch_assoc()['valor'] : '#667eea';
$stmt_color->close();

$stmt_color2 = $conn->prepare("SELECT valor FROM configuracion WHERE clave = 'color_secundario'");
$stmt_color2->execute();
$result_color2 = $stmt_color2->get_result();
$color_secundario = $result_color2->num_rows > 0 ? $result_color2->fetch_assoc()['valor'] : '#764ba2';
$stmt_color2->close();

// ===== CARGAR LOGO DESDE CONFIGURACIÓN =====
$stmt_logo = $conn->prepare("SELECT valor FROM configuracion WHERE clave = 'logo_url'");
$stmt_logo->execute();
$result_logo = $stmt_logo->get_result();
$logo_url = $result_logo->num_rows > 0 ? $result_logo->fetch_assoc()['valor'] : '';
$stmt_logo->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Panel de Administración</title>
    <link rel="stylesheet" href="/estilo.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --primary: <?php echo htmlspecialchars($color_primario); ?>;
    --secondary: <?php echo htmlspecialchars($color_secundario); ?>;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Inter', sans-serif;
    background: #f8fafc;
    padding-top: 130px;
}

.admin-header {
    background: white;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    position: fixed;
    top: 0; left: 0; right: 0;
    z-index: 1000;
    width: 100%;
}

.header-container { max-width: 1400px; margin: 0 auto; padding: 0.3rem 1rem; }

.header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.7rem 0;
}

.logo-img { height: 40px; width: auto; max-height: 40px; object-fit: contain; }

.logo-area h1 {
    font-size: 1.2rem;
    font-weight: 700;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.user-area { display: flex; align-items: center; gap: 0.5rem; }
.user-icon { font-size: 2rem; }
.user-name { font-weight: 600; color: #2d3748; font-size: 1.5rem; }

.btn-logout {
    background: #f8fafc; color: #4a5568;
    padding: 0.6rem 0.8rem;
    border-radius: 30px;
    text-decoration: none;
    font-size: 1.2rem;
    font-weight: 500;
    transition: all 0.3s;
    border: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    gap: 0.2rem;
}

.admin-nav {
    display: flex; gap: 0.1rem;
    padding: 0.5rem 0;
    flex-wrap: wrap;
}

.admin-nav a,
.dropdown-toggle {
    padding: 0.3rem 0.6rem;
    border-radius: 30px;
    text-decoration: none;
    color: #4a5568;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    white-space: nowrap;
}

.admin-nav a:hover,
.dropdown-toggle:hover {
    background: #f7fafc;
    color: var(--primary);
}

.admin-nav a.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
}

.dropdown { position: relative; display: inline-block; }

.dropdown-menu {
    position: absolute;
    top: 100%; left: 0;
    background: white;
    min-width: 160px;
    border-radius: 10px;
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
    z-index: 9999;
    display: none;
}
.dropdown-menu.show { display: block; }
.dropdown-menu a {
    display: block;
    padding: 0.5rem 0.8rem;
    color: #4a5568;
    text-decoration: none;
    font-size: 0.9rem;
}
.dropdown-menu a:hover { background: #f7fafc; color: var(--primary); }

.main-content { max-width: 1400px; margin: 1rem auto; padding: 0 1rem; }

.logo-area { display: flex; align-items: center; gap: 0.8rem; }
.admin-nombre { font-size: 1.2rem; font-weight: 600; }

.dropdown-submenu { position: relative; }
.dropdown-submenu a {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.submenu-arrow { font-size: 0.7rem; margin-left: 8px; transition: transform 0.2s; }
.dropdown-submenu:hover .submenu-arrow { transform: rotate(90deg); }
.dropdown-submenu .submenu-content {
    display: none;
    position: absolute;
    left: 100%; top: 0;
    background: white;
    min-width: 160px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
    z-index: 100;
}
.dropdown-submenu:hover .submenu-content { display: block; }
.submenu-content a {
    padding: 8px 16px;
    display: block;
    color: #4a5568;
    text-decoration: none;
    white-space: nowrap;
}
.submenu-content a:hover { background: #f7fafc; color: var(--primary); }

/* ===== HEADER MÓVIL ===== */
.admin-mobile-header {
    display: none;
    flex-wrap: wrap;
    justify-content: center;
    padding: 0.3rem 0;
    border-top: 1px solid #e2e8f0;
}
.admin-mobile-header .admin-mobile-btn {
    background: #f1f5f9;
    border-radius: 30px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border: none;
    color: #4a5568;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    font-weight: 500;
}
.admin-mobile-header .admin-mobile-btn:hover,
.admin-mobile-header .admin-mobile-btn:active {
    background: var(--primary);
    color: white;
}
.admin-mobile-header .admin-mobile-icon { font-size: 1rem; }

@media (max-width: 998px) {
    .admin-mobile-header { display: flex !important; }
    .admin-nav { display: none !important; }
    body { padding-top: 130px !important; padding-bottom: 10px !important; }
}

@media (max-width: 768px) {
    body { padding-top: 90px; }
    .header-top {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
        padding: 0.3rem 0;
    }
    .logo-area { flex-shrink: 0 !important; }
.logo-area h1 { font-size: 0.75rem; white-space: nowrap; }
.logo-img { height: 28px; }
.admin-nombre { font-size: 0.75rem; }
    .user-area {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        gap: 0.3rem !important;
        flex-shrink: 0 !important;
    }
    .user-icon { font-size: 0.8rem; }
.user-name { font-size: 0.7rem; white-space: nowrap; }
.user-badge { display: none !important; }
.btn-logout { font-size: 0.7rem; padding: 0.2rem 0.5rem; white-space: nowrap; }
    .dropdown-menu {
        left: auto !important;
        right: 0 !important;
        min-width: 160px !important;
    }
    .dropdown { display: inline-block !important; flex-shrink: 0 !important; }
    .dropdown-submenu .submenu-content {
        left: 100% !important;
        top: 0 !important;
    }
    .dropdown-submenu .submenu-content a {
        display: block !important;
        white-space: nowrap !important;
        background: white !important;
    }
}

@media (min-width: 769px) and (max-width: 998px) {
    .admin-mobile-header { gap: 0.8rem !important; }
    .admin-mobile-btn { padding: 0.4rem 1rem !important; font-size: 0.8rem !important; }
}

@media (min-width: 380px) and (max-width: 768px) {
    .admin-mobile-header { gap: 0.3rem !important; }
    .admin-mobile-btn { padding: 0.3rem 0.6rem !important; font-size: 0.7rem !important; }
}

@media (min-width: 319px) and (max-width: 379px) {
    .admin-mobile-header { gap: 0.1rem !important; }
    .admin-mobile-btn { padding: 0.2rem 0.4rem !important; font-size: 0.5rem !important; }
}

@media (max-width: 318px) {
    .admin-mobile-header { gap: 0.15rem !important; justify-content: space-evenly !important; }
    .admin-mobile-btn { padding: 0.2rem 0.4rem !important; font-size: 0.6rem !important; }
    .admin-mobile-label { display: none !important; }
}

/* ===== MENÚS LATERALES ===== */
.gestion-overlay,
.config-overlay,
.reportes-overlay {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    display: none;
}
.gestion-overlay.show,
.config-overlay.show,
.reportes-overlay.show { display: block; }

.gestion-side-menu,
.config-side-menu,
.reportes-side-menu {
    position: fixed;
    top: 0;
    left: -280px;
    width: 250px;
    height: 100%;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: white;
    z-index: 10001;
    transition: left 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    box-shadow: 2px 0 20px rgba(0,0,0,0.3);
    overflow-y: auto;
}
.gestion-side-menu.show,
.config-side-menu.show,
.reportes-side-menu.show { left: 0; }

.gestion-side-header,
.config-side-header,
.reportes-side-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    font-size: 1.1rem;
    font-weight: 600;
    color: #4ade80;
}

.gestion-side-items,
.config-side-items,
.reportes-side-items {
    list-style: none;
    padding: 0;
    margin: 0;
}
.gestion-side-items li,
.config-side-items li,
.reportes-side-items li { border-bottom: 1px solid rgba(255,255,255,0.05); }
.gestion-side-items a,
.config-side-items a,
.reportes-side-items a {
    display: block;
    padding: 0.8rem 1.5rem;
    color: white;
    text-decoration: none;
    transition: all 0.25s;
    font-size: 0.9rem;
}
.gestion-side-items a:hover,
.config-side-items a:hover,
.reportes-side-items a:hover {
    background: rgba(255,255,255,0.1);
    padding-left: 1.8rem;
}

.reportes-divider {
    height: 1px;
    background: rgba(255,255,255,0.1);
    margin: 0.5rem 0;
}

@media (max-width: 800px) {
    .gestion-side-menu,
    .config-side-menu,
    .reportes-side-menu {
        width: 75% !important;
        left: -75% !important;
    }
    .gestion-side-menu.show,
    .config-side-menu.show,
    .reportes-side-menu.show { left: 0 !important; }
}
</style>
</head>
<body>
    <header class="admin-header">
        <div class="header-container">
            <div class="header-top">
                <div class="logo-area">
                    <?php if (!empty($logo_url)): ?>
                        <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="Logo" class="logo-img" style="height: 40px; vertical-align: middle; margin-left: 10px;">
                        <span class="admin-nombre">
                            <?php echo $nombre_tienda !== '' ? htmlspecialchars($nombre_tienda) : 'Mi Tienda Admin'; ?>
                        </span>
                    <?php else: ?>
                        <h1>📊 <?php echo $nombre_tienda !== '' ? htmlspecialchars($nombre_tienda) : 'Mi Tienda Admin'; ?></h1>
                    <?php endif; ?>
                </div>
                
                <div class="user-area">
    <span class="user-icon">👤</span>
    <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
    <span class="user-badge" style="background: <?php echo $color_rol; ?>; color: white; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; white-space: nowrap;">
        <?php echo htmlspecialchars($rol); ?>
    </span>
    <a href="/cerrar_sesion.php" class="btn-logout">🚪 Salir</a>
</div>
            </div>
            
            <nav class="admin-nav">
                <!-- PRODUCTOS - ADMIN GLOBAL -->
                <?php if ($ver_productos && !$es_rol_proveedor): ?>
                    <a href="/admin/stock/productos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/stock/productos.php') !== false ? 'active' : ''; ?>">
                        📦 <span class="nav-texto">Productos</span>
                    </a>
                <?php endif; ?>

                <!-- PRODUCTOS - PROVEEDOR / INVENTARIO / VENDEDOR -->
                <?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Inventario', 'Vendedor'])): ?>
                    <a href="/admin/stock/productos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/stock/productos.php') !== false ? 'active' : ''; ?>">
                        📦 <span class="nav-texto">Mis Productos</span>
                    </a>
                <?php endif; ?>

                <!-- COMBOS - ADMIN GLOBAL -->
                <?php if ($ver_combos && !$es_rol_proveedor): ?>
                    <a href="/admin/stock/combos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/stock/combos.php') !== false ? 'active' : ''; ?>">
                        🎁 <span class="nav-texto">Combos</span>
                    </a>
                <?php endif; ?>

                <!-- COMBOS - PROVEEDOR / INVENTARIO / VENDEDOR -->
<?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Inventario', 'Vendedor'])): ?>
    <a href="/admin/stock/combos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/stock/combos.php') !== false ? 'active' : ''; ?>">
        🎁 <span class="nav-texto">Mis Combos</span>
    </a>
<?php endif; ?>

                <!-- CLIENTES - ADMIN GLOBAL -->
                <?php if ($ver_clientes && !$es_rol_proveedor): ?>
                    <a href="/admin/clientes.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/clientes.php') !== false ? 'active' : ''; ?>">
                        👥 <span class="nav-texto">Clientes</span>
                    </a>
                <?php endif; ?>

                <!-- CARRITOS - ADMIN GLOBAL -->
<?php if ($ver_carritos && !$es_rol_proveedor): ?>
    <a href="/admin/carritos_gestion.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/carritos_gestion.php') !== false ? 'active' : ''; ?>">
        🛒 <span class="nav-texto">Carritos</span>
    </a>
<?php endif; ?>

<!-- CARRITOS - PROVEEDOR / INVENTARIO / VENDEDOR -->
<?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Inventario', 'Vendedor'], true)): ?>
    <a href="/admin/carritos_gestion.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/carritos_gestion.php') !== false ? 'active' : ''; ?>">
        🛒 <span class="nav-texto">Mis Carritos</span>
    </a>
<?php endif; ?>

                <!-- PEDIDOS - ADMIN GLOBAL -->
                <?php if ($ver_pedidos && !$es_rol_proveedor): ?>
                    <a href="/admin/pedidos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/pedidos.php') !== false ? 'active' : ''; ?>">
                        📦 <span class="nav-texto">Pedidos</span>
                    </a>
                <?php endif; ?>

                <!-- PEDIDOS - PROVEEDOR / VENDEDOR / EMPAQUETADOR / TRANSPORTISTA -->
                <?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Vendedor', 'Empaquetador', 'Transportista'])): ?>
                    <a href="/admin/pedidos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/pedidos.php') !== false ? 'active' : ''; ?>">
                        📦 <span class="nav-texto">Mis Pedidos</span>
                    </a>
                <?php endif; ?>

                <!-- PAGOS - ADMIN GLOBAL -->
                <?php if ($ver_pagos && !$es_rol_proveedor): ?>
                    <a href="/admin/pagos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/pagos.php') !== false ? 'active' : ''; ?>">
                        💰 <span class="nav-texto">Pagos</span>
                    </a>
                <?php endif; ?>

                <!-- PAGOS - PROVEEDOR / VENDEDOR -->
                <?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Vendedor'])): ?>
                    <a href="/admin/pagos.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/pagos.php') !== false ? 'active' : ''; ?>">
                        💰 <span class="nav-texto">Mis Pagos</span>
                    </a>
                <?php endif; ?>

                                                <!-- REPORTES - ADMIN GLOBAL -->
                <?php if ($ver_reportes && !$es_rol_proveedor): ?>
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" onclick="if(window.innerWidth <= 768){ event.preventDefault(); abrirReportesMenu(); }else{ toggleDropdown(event); }">
                            📊 <span class="nav-texto">Reportes</span> ▼
                        </a>
                        <div class="dropdown-menu">
                            <a href="/reportes.php?tab=productos">📦 Productos</a>
                            <a href="/reportes.php?tab=clientes">👥 Clientes</a>
                            <a href="/reportes.php?tab=carritos">🛒 Carritos</a>
                            <a href="/admin/admin_estadisticas.php">📈 Visitas</a>
                            <a href="/reportes/comentarios.php">💬 Comentarios</a>
                            <a href="/reportes.php?tab=dashboard">📊 Dashboard</a>
                            <div style="padding: 0.3rem 0.8rem; font-size: 0.75rem; color: #a0aec0; text-transform: uppercase; font-weight: 600; border-top: 1px solid #e2e8f0; margin-top: 0.3rem;">Ventas</div>
                            <a href="/reportes.php?tab=ventas&tipo_reporte=productos">💰 Ventas - Productos</a>
                            <a href="/reportes.php?tab=ventas&tipo_reporte=pedidos">💰 Ventas - Pedidos</a>
                            <a href="/reportes.php?tab=ventas&tipo_reporte=clientes">💰 Ventas - Clientes</a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- REPORTES - PROVEEDOR / INVENTARIO / VENDEDOR -->
<?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Inventario', 'Vendedor'], true)): ?>
    <div class="dropdown">
        <a href="#" class="dropdown-toggle" onclick="if(window.innerWidth <= 768){ event.preventDefault(); abrirReportesMenu(); }else{ toggleDropdown(event); }">
            📊 <span class="nav-texto">Mis Reportes</span> ▼
        </a>
        <div class="dropdown-menu">
            <a href="/reportes.php?tab=productos">📦 Mis Productos</a>

            <?php if (in_array($rol, ['Proveedor', 'Inventario'], true)): ?>
                <a href="/reportes.php?tab=carritos">🛒 Mis Carritos</a>
            <?php endif; ?>

            <?php if ($rol === 'Proveedor'): ?>
                <a href="/reportes.php?tab=dashboard">📊 Dashboard</a>
            <?php endif; ?>

            <?php if (in_array($rol, ['Proveedor', 'Vendedor'], true)): ?>
                <div style="padding: 0.3rem 0.8rem; font-size: 0.75rem; color: #a0aec0; text-transform: uppercase; font-weight: 600; border-top: 1px solid #e2e8f0; margin-top: 0.3rem;">Ventas</div>
                <a href="/reportes.php?tab=ventas&tipo_reporte=productos">💰 Ventas - Productos</a>
                <a href="/reportes.php?tab=ventas&tipo_reporte=pedidos">💰 Ventas - Pedidos</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

                <!-- PROVEEDORES - ADMIN GLOBAL -->
                <?php if ($ver_proveedores && !$es_rol_proveedor): ?>
                    <a href="/admin/proveedores/gestionar_proveedores.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'admin/proveedores/gestionar_proveedores.php') !== false ? 'active' : ''; ?>">
                        🏪 <span class="nav-texto">Proveedores</span>
                    </a>
                <?php endif; ?>

                <!-- USUARIOS - Super Admin y Proveedor (dueño) -->
                <?php if ($ver_usuarios): ?>
    <a href="/gestionar_usuarios.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'gestionar_usuarios.php') !== false ? 'active' : ''; ?>">
        👥 <span class="nav-texto">Usuarios</span>
    </a>
<?php endif; ?>

                <!-- CONFIGURACIÓN - ADMIN GLOBAL -->
                <?php if ($ver_config && !$es_rol_proveedor): ?>
                    <a href="/configuracion.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'configuracion.php') !== false ? 'active' : ''; ?>">
                        ⚙️ <span class="nav-texto">Config</span>
                    </a>
                <?php endif; ?>

                <!-- CONFIGURACIÓN - PROVEEDOR DUEÑO -->
                <?php if ($es_rol_proveedor && $rol === 'Proveedor'): ?>
                    <a href="/configuracion.php" class="<?php echo strpos($_SERVER['PHP_SELF'], 'configuracion.php') !== false ? 'active' : ''; ?>">
                        ⚙️ <span class="nav-texto">Configuración</span>
                    </a>
                <?php endif; ?>

                <!-- TIENDA - TODOS -->
                <a href="/index.php" target="_blank">
                    🌐 <span class="nav-texto">Tienda</span>
                </a>
            </nav>

            <!-- ===== MENÚ MÓVIL ===== -->
            <div class="admin-mobile-header">
                <!-- GESTIÓN - Admin global -->
                <?php if (!$es_rol_proveedor): ?>
                    <button class="admin-mobile-btn" onclick="abrirMenuGestion()">
                        <span class="admin-mobile-icon">📋</span>
                        <span class="admin-mobile-label">Gestión</span>
                    </button>
                <?php endif; ?>

                <!-- GESTIÓN - Proveedor dueño -->
                <?php if ($es_rol_proveedor && $rol === 'Proveedor'): ?>
                    <button class="admin-mobile-btn" onclick="abrirMenuGestion()">
                        <span class="admin-mobile-icon">📋</span>
                        <span class="admin-mobile-label">Mi Gestión</span>
                    </button>
                <?php endif; ?>

                <!-- REPORTES - Admin global -->
                <?php if ($ver_reportes && !$es_rol_proveedor): ?>
                    <button class="admin-mobile-btn" onclick="abrirReportesMenu()">
                        <span class="admin-mobile-icon">📊</span>
                        <span class="admin-mobile-label">Reportes</span>
                    </button>
                <?php endif; ?>

                <!-- CONFIGURACIÓN - Admin global -->
                <?php if ($ver_config && !$es_rol_proveedor): ?>
                    <button class="admin-mobile-btn" onclick="abrirMenuConfiguracion()">
                        <span class="admin-mobile-icon">⚙️</span>
                        <span class="admin-mobile-label">Config</span>
                    </button>
                <?php endif; ?>

                <!-- CONFIGURACIÓN - Proveedor dueño -->
                <?php if ($es_rol_proveedor && $rol === 'Proveedor'): ?>
                    <button class="admin-mobile-btn" onclick="abrirMenuConfiguracion()">
                        <span class="admin-mobile-icon">⚙️</span>
                        <span class="admin-mobile-label">Mi Config</span>
                    </button>
                <?php endif; ?>

                <!-- TIENDA - Todos -->
                <a href="/shop.php" target="_blank" class="admin-mobile-btn" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                    <span class="admin-mobile-icon">🌐</span>
                    <span class="admin-mobile-label">Tienda</span>
                </a>
            </div>
        </div>
    </header>

    <!-- ===== MENÚ LATERAL DE GESTIÓN ===== -->
    <div class="gestion-overlay" id="gestionOverlay" onclick="cerrarMenuGestion()"></div>
    <div class="gestion-side-menu" id="gestionSideMenu">
        <div class="gestion-side-header">
            <span>👇 📋 Gestión</span>
            <button onclick="cerrarMenuGestion()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">✕</button>
        </div>
        <ul class="gestion-side-items">
            <!-- PRODUCTOS - Admin global -->
            <?php if ($ver_productos && !$es_rol_proveedor): ?>
                <li><a href="/admin/stock/productos.php" onclick="cerrarMenuGestion()">📦 Productos</a></li>
            <?php endif; ?>

            <!-- PRODUCTOS - Proveedor / Inventario / Vendedor -->
            <?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Inventario', 'Vendedor'])): ?>
                <li><a href="/admin/stock/productos.php" onclick="cerrarMenuGestion()">📦 Mis Productos</a></li>
            <?php endif; ?>

            <!-- COMBOS - Admin global -->
            <?php if ($ver_combos && !$es_rol_proveedor): ?>
                <li><a href="/admin/stock/combos.php" onclick="cerrarMenuGestion()">🎁 Combos</a></li>
            <?php endif; ?>

            <!-- COMBOS - Proveedor / Inventario / Vendedor -->
<?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Inventario', 'Vendedor'])): ?>
    <li><a href="/admin/stock/combos.php" onclick="cerrarMenuGestion()">🎁 Mis Combos</a></li>
<?php endif; ?>

            <!-- CARRITOS - Admin global -->
<?php if ($ver_carritos && !$es_rol_proveedor): ?>
    <li><a href="/admin/carritos_gestion.php" onclick="cerrarMenuGestion()">🛒 Carritos</a></li>
<?php endif; ?>

<!-- CARRITOS - Proveedor / Inventario / Vendedor -->
<?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Inventario', 'Vendedor'], true)): ?>
    <li><a href="/admin/carritos_gestion.php" onclick="cerrarMenuGestion()">🛒 Mis Carritos</a></li>
<?php endif; ?>

            <!-- PEDIDOS - Admin global -->
            <?php if ($ver_pedidos && !$es_rol_proveedor): ?>
                <li><a href="/admin/pedidos.php" onclick="cerrarMenuGestion()">📦 Pedidos</a></li>
            <?php endif; ?>

            <!-- PEDIDOS - Proveedor / Vendedor / Empaquetador / Transportista -->
            <?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Vendedor', 'Empaquetador', 'Transportista'])): ?>
                <li><a href="/admin/pedidos.php" onclick="cerrarMenuGestion()">📦 Mis Pedidos</a></li>
            <?php endif; ?>

            <!-- PAGOS - Admin global -->
            <?php if ($ver_pagos && !$es_rol_proveedor): ?>
                <li><a href="/admin/pagos.php" onclick="cerrarMenuGestion()">💰 Pagos</a></li>
            <?php endif; ?>

            <!-- PAGOS - Proveedor / Vendedor -->
            <?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Vendedor'])): ?>
                <li><a href="/admin/pagos.php" onclick="cerrarMenuGestion()">💰 Mis Pagos</a></li>
            <?php endif; ?>

            <!-- CLIENTES - Admin global -->
            <?php if ($ver_clientes && !$es_rol_proveedor): ?>
                <li><a href="/admin/clientes.php" onclick="cerrarMenuGestion()">👥 Clientes</a></li>
            <?php endif; ?>

            <!-- PROVEEDORES - Admin global -->
            <?php if ($ver_proveedores && !$es_rol_proveedor): ?>
                <li><a href="/admin/proveedores/gestionar_proveedores.php" onclick="cerrarMenuGestion()">🏪 Proveedores</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- ===== MENÚ LATERAL DE CONFIGURACIÓN ===== -->
    <div class="config-overlay" id="configOverlay" onclick="cerrarMenuConfiguracion()"></div>
    <div class="config-side-menu" id="configSideMenu">
        <div class="config-side-header">
            <span>⚙️ Configuración</span>
            <button onclick="cerrarMenuConfiguracion()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">✕</button>
        </div>
                <ul class="config-side-items">
            <?php foreach ($secciones_permitidas as $sec): 
                if (!isset($config_secciones_meta[$sec])) continue;
                $meta = $config_secciones_meta[$sec];
                $titulo = $es_rol_proveedor ? $meta['titulo_prov'] : $meta['titulo'];
            ?>
                <li>
                    <a href="<?php echo htmlspecialchars($meta['url']); ?>" onclick="cerrarMenuConfiguracion()">
                        <?php echo $meta['icon']; ?> <?php echo htmlspecialchars($titulo); ?>
                    </a>
                </li>
            <?php endforeach; ?>

            <?php if ($_SESSION['rol'] === 'Super Admin' && !$es_rol_proveedor): ?>
                <li><a href="/gestionar_usuarios.php" onclick="cerrarMenuConfiguracion()">👥 Usuarios</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- ===== MENÚ LATERAL DE REPORTES ===== -->
    <div class="reportes-overlay" id="reportesOverlay" onclick="cerrarReportesMenu()"></div>
    <div class="reportes-side-menu" id="reportesSideMenu">
        <div class="reportes-side-header">
            <span>👇 📊 Reportes</span>
            <button onclick="cerrarReportesMenu()" style="background: none; border: none; color: white; font-size: 1.5rem; cursor: pointer;">✕</button>
        </div>
        <ul class="reportes-side-items">
                        <?php if ($ver_reportes && !$es_rol_proveedor): ?>
                <li><a href="/reportes.php?tab=productos" onclick="cerrarReportesMenu()">📦 Productos</a></li>
                <li><a href="/reportes.php?tab=clientes" onclick="cerrarReportesMenu()">👥 Clientes</a></li>
                <li><a href="/reportes.php?tab=carritos" onclick="cerrarReportesMenu()">🛒 Carritos</a></li>
                <li><a href="/admin/admin_estadisticas.php" onclick="cerrarReportesMenu()">📈 Visitas</a></li>
                <li><a href="/reportes/comentarios.php" onclick="cerrarReportesMenu()">💬 Comentarios</a></li>
                <li class="reportes-divider"></li>
                <li><strong style="color: #4ade80; padding: 0.5rem 0.8rem; display: block; font-size: 1.2rem;">👇 Reportes Ventas</strong></li>
                <li><a href="/reportes.php?tab=ventas&tipo_reporte=productos" onclick="cerrarReportesMenu()">📦 Productos</a></li>
                <li><a href="/reportes.php?tab=ventas&tipo_reporte=pedidos" onclick="cerrarReportesMenu()">📋 Pedidos</a></li>
                <li><a href="/reportes.php?tab=ventas&tipo_reporte=clientes" onclick="cerrarReportesMenu()">👥 Clientes</a></li>
            <?php endif; ?>

            <!-- REPORTES - Proveedor / Inventario / Vendedor -->
<?php if ($es_rol_proveedor && in_array($rol, ['Proveedor', 'Inventario', 'Vendedor'], true)): ?>
    <li><a href="/reportes.php?tab=productos" onclick="cerrarReportesMenu()">📦 Mis Productos</a></li>

    <?php if (in_array($rol, ['Proveedor', 'Inventario'], true)): ?>
        <li><a href="/reportes.php?tab=carritos" onclick="cerrarReportesMenu()">🛒 Mis Carritos</a></li>
    <?php endif; ?>

    <?php if ($rol === 'Proveedor'): ?>
        <li><a href="/reportes.php?tab=dashboard" onclick="cerrarReportesMenu()">📊 Dashboard</a></li>
    <?php endif; ?>

    <?php if (in_array($rol, ['Proveedor', 'Vendedor'], true)): ?>
        <li><strong style="color: #4ade80; padding: 0.5rem 0.8rem; display: block; font-size: 1.2rem;">💰 Mis Ventas</strong></li>
        <li><a href="/reportes.php?tab=ventas&tipo_reporte=productos" onclick="cerrarReportesMenu()">📦 Productos</a></li>
        <li><a href="/reportes.php?tab=ventas&tipo_reporte=pedidos" onclick="cerrarReportesMenu()">📋 Pedidos</a></li>
    <?php endif; ?>
<?php endif; ?>
        </ul>
    </div>

    <main class="main-content">

    <script>
        let tiempoRestante = <?php echo TIEMPO_MAXIMO_INACTIVIDAD; ?>;
        const timerElement = document.getElementById('sessionTimer');
        
        function actualizarTimer() {
            if (tiempoRestante <= 0) {
                window.location.href = '/cerrar_sesion.php?timeout=1';
            } else {
                const minutos = Math.floor(tiempoRestante / 60);
                const segundos = tiempoRestante % 60;
                const tiempoTexto = `⏱️ ${minutos}:${segundos.toString().padStart(2, '0')}`;
                
                if (timerElement) {
                    timerElement.textContent = tiempoTexto;
                    if (tiempoRestante < 60) {
                        timerElement.classList.add('warning');
                    } else {
                        timerElement.classList.remove('warning');
                    }
                }
                
                tiempoRestante--;
            }
        }
        
        setInterval(actualizarTimer, 1000);
        
        function resetearTimer() {
            tiempoRestante = <?php echo TIEMPO_MAXIMO_INACTIVIDAD; ?>;
        }
        
        document.addEventListener('mousemove', resetearTimer);
        document.addEventListener('keypress', resetearTimer);
        document.addEventListener('click', resetearTimer);
        
        function toggleDropdown(event) {
            event.preventDefault();
            const dropdown = event.currentTarget.closest('.dropdown');
            const menu = dropdown.querySelector('.dropdown-menu');
            menu.classList.toggle('show');
        }
        
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
        
        // ===== MANEJAR SUBMENÚS EN MÓVIL =====
        document.addEventListener('DOMContentLoaded', function() {
            function isMobile() {
                return window.innerWidth <= 768;
            }
            
            const submenus = document.querySelectorAll('.dropdown-submenu');
            
            submenus.forEach(function(submenu) {
                const link = submenu.querySelector('a');
                const content = submenu.querySelector('.submenu-content');
                
                if (link && content) {
                    link.removeEventListener('click', handleSubmenuClick);
                    link.removeEventListener('touchstart', handleSubmenuClick);
                    
                    if (isMobile()) {
                        link.addEventListener('click', handleSubmenuClick);
                        link.addEventListener('touchstart', handleSubmenuClick);
                    } else {
                        submenu.addEventListener('mouseleave', function() {
                            content.style.display = 'none';
                        });
                    }
                }
            });
            
            function handleSubmenuClick(e) {
                e.preventDefault();
                e.stopPropagation();
                const submenu = this.closest('.dropdown-submenu');
                const content = submenu.querySelector('.submenu-content');
                
                if (content) {
                    document.querySelectorAll('.dropdown-submenu .submenu-content').forEach(function(otherContent) {
                        if (otherContent !== content) {
                            otherContent.style.display = 'none';
                        }
                    });
                    
                    if (content.style.display === 'block') {
                        content.style.display = 'none';
                    } else {
                        content.style.display = 'block';
                    }
                }
            }
            
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown-submenu')) {
                    document.querySelectorAll('.dropdown-submenu .submenu-content').forEach(function(content) {
                        content.style.display = 'none';
                    });
                }
            });
            
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    document.querySelectorAll('.dropdown-submenu .submenu-content').forEach(function(content) {
                        content.style.display = '';
                    });
                }
            });
        });
        
        // ===== MENÚS LATERALES =====
        function abrirMenuGestion() {
            document.getElementById('gestionSideMenu').classList.add('show');
            document.getElementById('gestionOverlay').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function cerrarMenuGestion() {
            document.getElementById('gestionSideMenu').classList.remove('show');
            document.getElementById('gestionOverlay').classList.remove('show');
            document.body.style.overflow = '';
        }

        function abrirReportesMenu() {
            var menu = document.getElementById('reportesSideMenu');
            var overlay = document.getElementById('reportesOverlay');
            if (menu) menu.classList.add('show');
            if (overlay) overlay.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function cerrarReportesMenu() {
            var menu = document.getElementById('reportesSideMenu');
            var overlay = document.getElementById('reportesOverlay');
            if (menu) menu.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
            document.body.style.overflow = '';
        }

        function abrirMenuConfiguracion() {
            document.getElementById('configSideMenu').classList.add('show');
            document.getElementById('configOverlay').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function cerrarMenuConfiguracion() {
            document.getElementById('configSideMenu').classList.remove('show');
            document.getElementById('configOverlay').classList.remove('show');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                cerrarMenuGestion();
                cerrarMenuConfiguracion();
                cerrarReportesMenu();
            }
        });
    </script>
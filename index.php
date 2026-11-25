<?php
// ============================================
// DETECTAR PROVEEDOR DESDE URL AMIGABLE
// (ANTES DE LA REDIRECCIÓN INTELIGENTE)
// ============================================
$uri = $_SERVER['REQUEST_URI'];
$uri = trim($uri, '/');
$uri = explode('?', $uri)[0];

// Si la URL no está vacía y no es 'shop' ni 'index.php'
if (!empty($uri) && $uri !== 'shop' && $uri !== 'index.php' && !preg_match('/\.(php|html|css|js|png|jpg|jpeg|gif|webp|ico)$/i', $uri)) {
    // Verificar si existe en la base de datos antes de agregarlo
    require_once 'conexion.php';
    $stmt = $conn->prepare("SELECT id, activo FROM proveedores WHERE slug = ?");
    $stmt->bind_param("s", $uri);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $proveedor = $result->fetch_assoc();
        if ($proveedor['activo'] == 1) {
            // Solo si existe y está activo, lo agregamos a $_GET
            $_GET['proveedor'] = $uri;
        } else {
            // Si existe pero está inactivo, redirigir a shop.php
            header('Location: /shop.php');
            exit;
        }
    }
    // Si no existe, no hacer nada (dejar que la redirección maneje)
}

// ============================================
// REDIRECCIÓN INTELIGENTE - SOLO CUANDO ES NECESARIO
// ============================================

// 1. Filtrar parámetros que NO afectan el contenido de la tienda
$params_utiles = $_GET;

// ===== FORZAR QUE PROVEEDOR SEA UN PARÁMETRO ÚTIL =====
if (isset($_GET['proveedor']) && !empty($_GET['proveedor'])) {
    $params_utiles['proveedor'] = $_GET['proveedor'];
}

// --- FACEBOOK ---
unset($params_utiles['fbclid']);
unset($params_utiles['fb_action_ids']);
unset($params_utiles['fb_action_types']);
unset($params_utiles['fb_source']);
unset($params_utiles['fb_ref']);

// --- GOOGLE ADS ---
unset($params_utiles['gclid']);
unset($params_utiles['gclsrc']);
unset($params_utiles['_ga']);
unset($params_utiles['_gl']);

// --- MICROSOFT ADS ---
unset($params_utiles['msclkid']);

// --- CAMPAÑAS UTM ---
unset($params_utiles['utm_source']);
unset($params_utiles['utm_medium']);
unset($params_utiles['utm_campaign']);
unset($params_utiles['utm_term']);
unset($params_utiles['utm_content']);
unset($params_utiles['utm_id']);
unset($params_utiles['utm_reader']);
unset($params_utiles['utm_viz_id']);
unset($params_utiles['utm_pubreferrer']);

// --- LINKEDIN ---
unset($params_utiles['trk']);
unset($params_utiles['trkCampaign']);
unset($params_utiles['original_referer']);

// --- TWITTER / X ---
unset($params_utiles['s']);
unset($params_utiles['t']);

// --- PINTEREST ---
unset($params_utiles['epik']);
unset($params_utiles['_pinterest_cm']);
unset($params_utiles['_pinterest_sess']);

// --- TIKTOK ---
unset($params_utiles['_tt']);
unset($params_utiles['_t']);

// --- OTROS ---
unset($params_utiles['ref']);              // Referer general
unset($params_utiles['source']);           // Fuente general
unset($params_utiles['mc_cid']);           // Mailchimp
unset($params_utiles['mc_eid']);           // Mailchimp
unset($params_utiles['_hsmi']);            // HubSpot
unset($params_utiles['_hsenc']);           // HubSpot
unset($params_utiles['mkt_tok']);          // Marketo
unset($params_utiles['cid']);              // General campaign ID
unset($params_utiles['pk_source']);        // Piwik/Matomo
unset($params_utiles['pk_medium']);        // Piwik/Matomo
unset($params_utiles['pk_campaign']);      // Piwik/Matomo
unset($params_utiles['pk_keyword']);       // Piwik/Matomo
unset($params_utiles['pk_content']);       // Piwik/Matomo
unset($params_utiles['mtm_source']);       // Matomo
unset($params_utiles['mtm_medium']);       // Matomo
unset($params_utiles['mtm_campaign']);     // Matomo
unset($params_utiles['mtm_keyword']);      // Matomo
unset($params_utiles['mtm_content']);      // Matomo

// 2. Si NO hay parámetros útiles (o solo tiene fbclid), redirigir a shop.php
if (empty($params_utiles)) {
    // Redirección con múltiples métodos para compatibilidad
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Redirigiendo a la tienda...</title>
        <meta http-equiv="refresh" content="0; url=/shop.php">
        <script>
            window.location.href = "/shop.php";
            setTimeout(function() {
                window.location.replace("/shop.php");
            }, 100);
        </script>
    </head>
    <body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;background:#f8f9fa;margin:0;">
        <div style="text-align:center;padding:2rem;">
            <h2 style="color:#333;">🔄 Redirigiendo...</h2>
            <p style="color:#666;">Si no eres redirigido, <a href="/shop.php" style="color:#667eea;font-weight:bold;">haz clic aquí</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// SI HAY PARÁMETROS ÚTILES, CONTINÚA NORMALMENTE
// (categorías, búsquedas, filtros, etc.)
// ============================================

define('SEGURO', true);

// Configurar cookies de sesión para WebViews (SameSite Lax)
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'  // <-- Cambiado de Strict a Lax
]);

// ============================================
// ARCHIVOS REQUERIDOS
// ============================================
require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';
require_once 'includes/carrito_funciones.php';
require_once 'includes/moneda_funciones.php';
require_once 'includes/proveedor_utils.php';

// ============================================
// BUSCAR PROVEEDOR EN LA BASE DE DATOS
// ============================================
$proveedor_slug = isset($_GET['proveedor']) ? trim($_GET['proveedor']) : '';
$proveedor_id = 0;
$proveedor_nombre = '';
$proveedor_seleccionado = null;

if (!empty($proveedor_slug)) {
    $stmt_prov = $conn->prepare("SELECT id, nombre_tienda, activo FROM proveedores WHERE slug = ?");
    $stmt_prov->bind_param("s", $proveedor_slug);
    $stmt_prov->execute();
    $result_prov = $stmt_prov->get_result();
    
    if ($result_prov->num_rows > 0) {
        $proveedor = $result_prov->fetch_assoc();
        if ($proveedor['activo'] == 1) {
            $proveedor_id = $proveedor['id'];
            $proveedor_nombre = $proveedor['nombre_tienda'];
            $proveedor_seleccionado = $proveedor;
        } else {
            header('Location: /shop.php');
            exit;
        }
    } else {
        header('Location: /shop.php');
        exit;
    }
}

registrarVisita($conn);

// ============================================
// VERIFICAR MODO MANTENIMIENTO
// ============================================
$result_mantenimiento = $conn->query("SELECT valor FROM configuracion WHERE clave = 'modo_mantenimiento'");
if ($result_mantenimiento && $result_mantenimiento->num_rows > 0) {
    $row_mantenimiento = $result_mantenimiento->fetch_assoc();
    $modo_mantenimiento = $row_mantenimiento['valor'];
    
    if ($modo_mantenimiento == '1') {
        $es_admin = false;
        if (isset($_SESSION['usuario_id'])) {
            $stmt_admin = $conn->prepare("SELECT nivel FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.id = ?");
            $stmt_admin->bind_param("i", $_SESSION['usuario_id']);
            $stmt_admin->execute();
            $result_admin = $stmt_admin->get_result();
            if ($result_admin->num_rows > 0) {
                $admin_data = $result_admin->fetch_assoc();
                $es_admin = ($admin_data['nivel'] >= 2);
            }
        }
        if (!$es_admin) {
            include 'mantenimiento.php';
            exit;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cliente = obtenerClienteActual($conn);
$cliente_id = $cliente['id'];
$email_verified = $cliente['email_verified'] ?? 0;

if ($cliente_id > 0) {
    limpiarCarritosExpirados($conn);
}

$cliente_registrado = ($cliente_id > 0 && !empty($cliente['nombre']));

$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion");
while($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

$sitio_nombre = $config['sitio_nombre'] ?? 'TiendaVirtual';
$sitio_slogan = $config['sitio_slogan'] ?? 'Descubre productos increíbles';
$color_primario = $config['color_primario'] ?? '#667eea';
$color_secundario = $config['color_secundario'] ?? '#764ba2';

// ============================================
// CONTEXTO DEL PROVEEDOR
// La moneda la determina el header (usa $proveedor_contexto
// + carrito pendiente + moneda del cliente)
// ============================================
$proveedor_contexto = $proveedor_id; // 0 si no hay tienda específica

// ============================================
// OBTENER CATEGORÍAS PARA EL MENÚ
// ============================================
$categorias_menu = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");

// ============================================
// OBTENER PRODUCTOS CON FILTROS
// ============================================
$categoria_seleccionada = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$filtro_seleccionado = isset($_GET['filtro']) ? $_GET['filtro'] : '';
$termino_busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// ============================================
// VALIDAR Y SANITIZAR FILTROS (SEGURIDAD)
// ============================================

// 1. Validar filtro (solo permitir valores conocidos)
$filtros_permitidos = ['ofertas', 'nuevos', 'mas_vendidos', 'combos'];
if (!empty($filtro_seleccionado) && !in_array($filtro_seleccionado, $filtros_permitidos)) {
    // Si el filtro no es válido, redirigir a index sin filtro
    header('Location: /index.php');
    exit;
}

// 2. Validar categoría (verificar que existe en la BD)
if ($categoria_seleccionada > 0) {
    $check_cat = $conn->prepare("SELECT id FROM categorias WHERE id = ? AND activo = 1");
    $check_cat->bind_param("i", $categoria_seleccionada);
    $check_cat->execute();
    $result_cat = $check_cat->get_result();
    if ($result_cat->num_rows == 0) {
        // Si la categoría no existe, redirigir a index sin categoría
        header('Location: /index.php');
        exit;
    }
}

// 3. Sanitizar búsqueda (limitar longitud y eliminar HTML)
if (!empty($termino_busqueda)) {
    $termino_busqueda = substr($termino_busqueda, 0, 100); // Máximo 100 caracteres
    $termino_busqueda = strip_tags($termino_busqueda);     // Eliminar etiquetas HTML
    $termino_busqueda = htmlspecialchars($termino_busqueda, ENT_QUOTES, 'UTF-8');
}

// 4. Validar que la combinación de filtros sea segura
// Si hay búsqueda y filtro, solo usar la búsqueda (evita conflictos)
if (!empty($termino_busqueda) && !empty($filtro_seleccionado)) {
    $filtro_seleccionado = ''; // Priorizar búsqueda sobre filtros
}

// ============================================
// INICIALIZAR VARIABLES
// ============================================
$cargar_combos = false;  // ← Definir al inicio

// ============================================
// CONSTRUIR FILTRO SQL
// ============================================
$filtro_sql = '';
$order_sql = 'orden_rebaja ASC, total_vendidos DESC, orden_nuevo ASC, p.nombre ASC, p.id ASC';

if ($filtro_seleccionado == 'ofertas') {
    $filtro_sql = "AND p.porcentaje_rebaja > 0";
    $order_sql = "p.porcentaje_rebaja DESC, p.nombre ASC";
} elseif ($filtro_seleccionado == 'nuevos') {
    $filtro_sql = "AND p.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $order_sql = "p.fecha_creacion DESC, p.nombre ASC";
} elseif ($filtro_seleccionado == 'mas_vendidos') {
    $filtro_sql = "";
    $order_sql = "total_vendidos DESC, p.nombre ASC";
} elseif ($filtro_seleccionado == 'combos') {
    $filtro_sql = "";
    $order_sql = "orden_rebaja ASC, total_vendidos DESC, orden_nuevo ASC, p.nombre ASC, p.id ASC";
    // Marcar que estamos en modo combos (sin redirigir)
    $cargar_combos = true;
}

// Detectar dispositivo móvil
$es_movil = preg_match('/(Mobile|Android|iPhone|iPad|iPod|BlackBerry|Windows Phone)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
$productos_por_carga = $es_movil ? 6 : 16;

// ============================================
// CONSTRUIR FILTRO SQL PARA EL CONTADOR
// ============================================
$filtro_sql_count = '';
$es_filtro_combos = $cargar_combos === true;

if ($es_filtro_combos) {
    // Si es filtro de combos, el contador es 0
    $total_activos = 0;
    
} elseif ($filtro_seleccionado == 'ofertas') {
    $filtro_sql_count = "AND p.porcentaje_rebaja > 0";
} elseif ($filtro_seleccionado == 'nuevos') {
    $filtro_sql_count = "AND p.fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filtro_seleccionado == 'mas_vendidos') {
    $filtro_sql_count = "";
}

// Contar productos activos (solo si NO es filtro de combos)
if (!$es_filtro_combos) {
    if (!empty($termino_busqueda)) {
        $count_sql = "SELECT COUNT(*) as total FROM productos p WHERE p.activo = 1 
        				" . ($proveedor_id > 0 ? "AND p.proveedor_id = $proveedor_id " : "") . "
                      AND (p.nombre LIKE ? OR p.descripcion LIKE ? OR p.marca LIKE ?) $filtro_sql_count";
        $count_stmt = $conn->prepare($count_sql);
        $param = "%$termino_busqueda%";
        $count_stmt->bind_param("sss", $param, $param, $param);
        $count_stmt->execute();
        $total_activos = $count_stmt->get_result()->fetch_assoc()['total'];
    } elseif ($categoria_seleccionada > 0) {
        $count_sql = "SELECT COUNT(*) as total FROM productos p WHERE p.activo = 1 
        " . ($proveedor_id > 0 ? "AND p.proveedor_id = $proveedor_id " : "") . "
        AND p.categoria_id = ? $filtro_sql_count";
        $count_stmt = $conn->prepare($count_sql);
        $count_stmt->bind_param("i", $categoria_seleccionada);
        $count_stmt->execute();
        $total_activos = $count_stmt->get_result()->fetch_assoc()['total'];
    } else {
        $count_sql = "SELECT COUNT(*) as total FROM productos p WHERE p.activo = 1 
        " . ($proveedor_id > 0 ? "AND p.proveedor_id = $proveedor_id " : "") . "
        $filtro_sql_count";
        $total_result = $conn->query($count_sql);
        $total_activos = $total_result->fetch_assoc()['total'];
    }
}

// ============================================
// CONSULTA DE PRODUCTOS CON FILTROS
// ============================================
if ($es_filtro_combos) {
    // Si es filtro de combos, NO cargar productos
    $productos_todos = false;
    
} elseif (!empty($termino_busqueda)) {
    // ===== CONSULTA CON BÚSQUEDA (SEGURA) =====
    $sql = "SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono,
            pr.nombre_tienda,
            CASE WHEN p.porcentaje_rebaja > 0 THEN 1 ELSE 4 END as orden_rebaja,
            COALESCE((SELECT SUM(cp.cantidad) FROM carrito_productos cp 
                      JOIN carritos ca ON cp.carrito_id = ca.id 
                      WHERE cp.producto_id = p.id AND ca.estado = 'comprado'), 0) as total_vendidos,
            CASE WHEN TIMESTAMPDIFF(HOUR, p.fecha_creacion, NOW()) <= 48 THEN 2 ELSE 3 END as orden_nuevo
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
            WHERE p.activo = 1 ";

    // ===== FILTRO DE PROVEEDOR (primero) =====
    if ($proveedor_id > 0) {
        $sql .= " AND p.proveedor_id = " . intval($proveedor_id) . " ";
    }

    // ===== BÚSQUEDA =====
    $sql .= " AND (p.nombre LIKE '%" . $conn->real_escape_string($termino_busqueda) . "%' 
                   OR p.descripcion LIKE '%" . $conn->real_escape_string($termino_busqueda) . "%' 
                   OR p.marca LIKE '%" . $conn->real_escape_string($termino_busqueda) . "%') ";

    // ===== FILTROS ADICIONALES =====
    if (!empty($filtro_sql)) {
        $sql .= " $filtro_sql ";
    }

    // ===== ORDEN Y LÍMITE =====
    $sql .= " ORDER BY $order_sql LIMIT " . intval($productos_por_carga);

    $productos_todos = $conn->query($sql);
    
} elseif ($categoria_seleccionada > 0) {
    $sql = "SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono,
            pr.nombre_tienda,
            CASE WHEN p.porcentaje_rebaja > 0 THEN 1 ELSE 4 END as orden_rebaja,
            COALESCE((SELECT SUM(cp.cantidad) FROM carrito_productos cp 
                      JOIN carritos ca ON cp.carrito_id = ca.id 
                      WHERE cp.producto_id = p.id AND ca.estado = 'comprado'), 0) as total_vendidos,
            CASE WHEN TIMESTAMPDIFF(HOUR, p.fecha_creacion, NOW()) <= 48 THEN 2 ELSE 3 END as orden_nuevo
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
            WHERE p.activo = 1 
            " . ($proveedor_id > 0 ? "AND p.proveedor_id = $proveedor_id " : "") . "
            AND p.categoria_id = ? $filtro_sql
            ORDER BY $order_sql
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $categoria_seleccionada, $productos_por_carga);
    $stmt->execute();
    $productos_todos = $stmt->get_result();
    
} else {
    $sql = "SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono,
            pr.nombre_tienda,
            CASE WHEN p.porcentaje_rebaja > 0 THEN 1 ELSE 4 END as orden_rebaja,
            COALESCE((SELECT SUM(cp.cantidad) FROM carrito_productos cp 
                      JOIN carritos ca ON cp.carrito_id = ca.id 
                      WHERE cp.producto_id = p.id AND ca.estado = 'comprado'), 0) as total_vendidos,
            CASE WHEN TIMESTAMPDIFF(HOUR, p.fecha_creacion, NOW()) <= 48 THEN 2 ELSE 3 END as orden_nuevo
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
            WHERE p.activo = 1 
            " . ($proveedor_id > 0 ? "AND p.proveedor_id = $proveedor_id " : "") . "
            $filtro_sql
            ORDER BY $order_sql
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productos_por_carga);
    $stmt->execute();
    $productos_todos = $stmt->get_result();
}

// ============================================
// COMBOS (solo si no hay filtros O si es filtro=combos)
// ============================================
$combos_primero = null;
$sin_filtros = ($categoria_seleccionada == 0 && empty($termino_busqueda) && empty($filtro_seleccionado));
$es_filtro_combos = isset($cargar_combos) && $cargar_combos === true;

if ($sin_filtros || $es_filtro_combos) {
    $sql_combos_primero = "SELECT c.*, pr.nombre_tienda,
                          (SELECT COUNT(*) FROM combo_productos WHERE combo_id = c.id) as productos_distintos,
                          (SELECT SUM(cantidad) FROM combo_productos WHERE combo_id = c.id) as unidades_totales
                          FROM combos c 
                          LEFT JOIN proveedores pr ON c.proveedor_id = pr.id
                          WHERE c.activo = 1 
                          " . ($proveedor_id > 0 ? "AND c.proveedor_id = $proveedor_id " : "") . "
                          ORDER BY c.fecha_creacion DESC
                          LIMIT 12";
    $combos_primero = $conn->query($sql_combos_primero);
}

// ============================================
// PASAR VARIABLES A JAVASCRIPT
// ============================================
echo '<script>';
echo 'window.proveedor_actual = "' . addslashes($proveedor_slug) . '";';
echo '</script>';

// ============================================
// INCLUIR HEADER (YA TIENE TODO)
// ============================================
include 'index_modales/carrito.php';
include 'header_tienda.php';
?>

<!-- ============================================ -->
<!-- CONTENIDO DE INDEX.PHP (SOLO PRODUCTOS)     -->
<!-- ============================================ -->
<main class="container">
    <section class="productos-section">
        
       <!-- TÍTULO Y CONTADOR -->
<h2 class="section-title" style="margin-top: 2rem; margin-bottom: 0.5rem;">
    <div style="line-height: 1.2; margin-bottom: 0.5rem;">
        <?php if ($proveedor_id > 0 && !empty($proveedor_nombre)): ?>
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.3rem; text-align: center; padding-top: 10px;" 
                 class="titulo-tienda-container">
                
                                <!-- Fila 1: Nombre tienda -->
                <span style="font-size: 2rem; font-weight: 700;" class="titulo-tienda-nombre">🏪 <?php echo htmlspecialchars($proveedor_nombre); ?></span>
                
                <!-- Fila 1.5: Selector de moneda (solo si aplica) -->
                <?php include 'includes/proveedores/selector_moneda.php'; ?>
                
                <!-- Fila 2: Botón Otras tiendas -->
                <a href="directorio_proveedores.php"  
                   style="display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 1rem; background: #e2e8f0; color: #1f2937; border-radius: 30px; text-decoration: none; font-size: 0.8rem; font-weight: 500; transition: all 0.3s ease; border: 1px solid #d1d5db; white-space: nowrap;"
                   class="btn-todas-tiendas"
                   onmouseover="this.style.background='var(--primary)'; this.style.color='white'; this.style.borderColor='var(--primary)';"
                   onmouseout="this.style.background='#e2e8f0'; this.style.color='#1f2937'; this.style.borderColor='#d1d5db';"
                   onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                    🏪 Otras tiendas →
                </a>
                
                <!-- Fila 3: Enlace "Todos los productos" (controlado por JavaScript) -->
<a id="btnTodosProductos" 
   href="index.php?proveedor=<?php echo urlencode($proveedor_slug); ?>" 
   style="font-size: 0.85rem; color: #6b7280; font-weight: 400; margin-top: 0.2rem; text-decoration: none; transition: color 0.3s; display: none;"
   onmouseover="this.style.color='var(--primary)'; this.style.textDecoration='underline';"
   onmouseout="this.style.color='#6b7280'; this.style.textDecoration='none';"
   onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
    📦 Ver todos los productos
</a>
            </div>
            
            <style>
                @media (max-width: 768px) {
                    .titulo-tienda-container {
                        flex-direction: column !important;
                        gap: 0.3rem !important;
                        margin-top: 0rem !important;
                    }
                    .titulo-tienda-nombre {
                        font-size: 1.1rem !important;
                    }
                    .btn-todas-tiendas {
                        white-space: normal !important;
                        font-size: 0.75rem !important;
                    }
                    .titulo-principal {
                        margin-top: 0rem !important;
                    }
                }
            </style>
        <?php else: ?>
            <div style="text-align: center;">
                🛍️ Nuestros Productos
            </div>
        <?php endif; ?>
    </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
    <!-- Buscador -->
    <div class="buscador-wrapper" style="display: flex; align-items: center;">
        <div class="buscador-container" style="position: relative; display: inline-block;">
            <input type="text" id="buscarInputTitulo" value="<?php echo htmlspecialchars($termino_busqueda); ?>" placeholder="Buscar..." autocomplete="off" 
                   style="padding: 0.4rem 3.5rem 0.4rem 0.8rem; border-radius: 30px; border: 1px solid #e2e8f0; width: 100%; max-width: 220px; font-size: 0.85rem;">
            <span id="btnLimpiarBusqueda" style="position: absolute; right: 35px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #ef4444; font-size: 1rem; display: none;">✕</span>
            <button id="btnLupaBuscar" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1rem; cursor: pointer; color: #6b7280; padding: 0.2rem;">
                🔍
            </button>
        </div>
    </div>
                
                <!-- Contador -->
                <div id="contador-productos" style="font-size: 0.85rem; color: #6b7280; font-weight: normal;">
                    <?php if (!empty($termino_busqueda)): ?>
                        <span>🔍 Resultados para: "<?php echo htmlspecialchars($termino_busqueda); ?>"</span>
                        <a href="#" onclick="limpiarBusquedaCompleta(); return false;" style="display: inline-block; font-size: 0.75rem; background: #ef4444; color: white; padding: 0.2rem 0.6rem; border-radius: 30px; text-decoration: none; margin-left: 0.5rem;">✕ Limpiar</a>
                    <?php elseif ($categoria_seleccionada > 0): ?>
                        <?php
                        $stmt_cat = $conn->prepare("SELECT nombre, icono FROM categorias WHERE id = ?");
                        $stmt_cat->bind_param("i", $categoria_seleccionada);
                        $stmt_cat->execute();
                        $categoria_info = $stmt_cat->get_result()->fetch_assoc();
                        $cat_nombre = $categoria_info['nombre'] ?? 'Categoría';
                        $cat_icono = $categoria_info['icono'] ?? '📁';
                        ?>
                        <span>(<?php echo $total_activos; ?> <?php echo $cat_icono; ?> <?php echo htmlspecialchars($cat_nombre); ?>)</span>
                    <?php else: ?>
                        <span>(<?php echo $total_activos; ?> productos)</span>
                    <?php endif; ?>
                </div>
            </div>
        </h2>

                <!-- ===== COMBOS (solo si no hay filtros O si es filtro=combos) ===== -->
        <?php if (($sin_filtros || $es_filtro_combos) && $combos_primero && $combos_primero->num_rows > 0): ?>
            <div id="combosContainer" class="combos-section">
                <div class="productos-grid" id="combos-principal-grid">
                    <?php while($combo = $combos_primero->fetch_assoc()): 
                        // ============================================
                        // INCREMENTAR CONTADOR DE VISITAS DEL COMBO
                        // ============================================
                        $conn->query("UPDATE combos SET vistas = vistas + 1 WHERE id = " . $combo['id']);
                        $productos_distintos = intval($combo['productos_distintos'] ?? 0);
                        $unidades_totales = intval($combo['unidades_totales'] ?? 0);
                        include 'combo_card.php';
                    endwhile; ?>
                </div>
            </div>

            <!-- Botón "Ver todos los productos" debajo del grid de combos (solo si es filtro=combos) -->
            <?php if ($es_filtro_combos): ?>
            <div id="verTodosCombosBtn" style="text-align: center; padding: 1.5rem 1rem;">
                <a href="<?php echo htmlspecialchars(($proveedor_id > 0 && !empty($proveedor_slug)) ? 'index.php?proveedor=' . urlencode($proveedor_slug) : 'shop.php'); ?>" 
                   style="display: inline-block; padding: 0.7rem 1.5rem; background: #e2e8f0; color: #1f2937; border-radius: 30px; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all 0.3s;"
                   onmouseover="this.style.background='var(--primary)'; this.style.color='white';"
                   onmouseout="this.style.background='#e2e8f0'; this.style.color='#1f2937';"
                   onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                    📦 Ver todos los productos<?php echo ($proveedor_id > 0 && !empty($proveedor_nombre)) ? ' de ' . htmlspecialchars($proveedor_nombre) : ''; ?>
                </a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        
       <!-- ===== PRODUCTOS (GRID CON SCROLL INFINITO) ===== -->
        <?php if (!$es_filtro_combos && $productos_todos && $productos_todos->num_rows > 0): ?>
            <div class="productos-grid" id="productos-grid" 
                 data-categoria="<?php echo $categoria_seleccionada; ?>" 
                 data-buscar="<?php echo htmlspecialchars($termino_busqueda); ?>"
                 data-offset="<?php echo $productos_por_carga; ?>" 
                 data-total="<?php echo $total_activos; ?>" 
                 data-cargando="false">
                <?php while($producto = $productos_todos->fetch_assoc()): 
// Incrementar contador de visitas
    $conn->query("UPDATE productos SET vistas = vistas + 1 WHERE id = " . $producto['id']);
    $dias_antiguedad = floor((time() - strtotime($producto['fecha_creacion'])) / 86400);
    $es_nuevo = $dias_antiguedad < 7;
    $tiene_rebaja = $producto['porcentaje_rebaja'] > 0;
    
    // Precios base en USD (tal como están en BD)
    $precio_original_usd = $tiene_rebaja ? $producto['precio_original'] : $producto['precio'];
    $precio_final_usd = $producto['precio'];
    
    // Proveedor del producto (para tasa personalizada si aplica)
    $prov_id_producto = intval($producto['proveedor_id'] ?? 0);
    
        // Si estamos en global (sin proveedor), calcular moneda por producto
    if ($proveedor_contexto == 0) {
        $moneda_producto = getMonedaParaProveedor($conn, $prov_id_producto, $cliente, null);
        $simbolo_producto = getSimboloPorMoneda($moneda_producto);
    } else {
        $moneda_producto = $moneda_actual;
        $simbolo_producto = $moneda_simbolo;
    }
    
    if ($moneda_producto !== 'USD') {
        $precio_original = convertirMoneda($precio_original_usd, 'USD', $moneda_producto, $conn, $prov_id_producto);
        $precio_final = convertirMoneda($precio_final_usd, 'USD', $moneda_producto, $conn, $prov_id_producto);
    } else {
        $precio_original = $precio_original_usd;
        $precio_final = $precio_final_usd;
    }
?>
                    <div class="producto-card" data-producto-id="<?php echo $producto['id']; ?>" onclick="abrirModalProductoTienda(<?php echo $producto['id']; ?>)">
                        <div class="producto-etiquetas">
                            <?php if ($es_nuevo): ?>
                                <span class="etiqueta-nuevo">NUEVO</span>
                            <?php endif; ?>
                            <?php if ($tiene_rebaja): ?>
                                <span class="etiqueta-rebaja">-<?php echo $producto['porcentaje_rebaja']; ?>%</span>
                            <?php endif; ?>
                            <?php if ($producto['cantidad'] <= 0): ?>
                                <span class="etiqueta-agotado">AGOTADO</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="producto-imagen-wrapper">
    <?php 
    $ruta_img = $producto['imagen_url'] ?? '';
    
    // Si la ruta está vacía, usar imagen por defecto
    if (empty($ruta_img)) {
        $ruta_img_completa = '/uploads/productos/sin-imagen.png';
    } else {
        // Si NO tiene barra al inicio, agregarla
        $ruta_img_completa = (strpos($ruta_img, '/') === 0) ? $ruta_img : '/' . $ruta_img;
        // Limpiar doble barra si existe
        $ruta_img_completa = preg_replace('#^/+#', '/', $ruta_img_completa);
    }
    
    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_img_completa;
    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
    ?>
    <img src="<?php echo htmlspecialchars($ruta_img_completa) . '?v=' . $version; ?>" 
         alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
         class="producto-imagen"
         loading="lazy"
         onclick="verImagenGrande(this.src)"
         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22 viewBox=%220 0 200 200%22%3E%3Crect width=%22200%22 height=%22200%22 fill=%22%23e2e8f0%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 font-family=%22Arial%22 font-size=%2216%22 fill=%22%23999%22 text-anchor=%22middle%22 dy=%22.3em%22%3ESin imagen%3C/text%3E%3C/svg%3E'">
</div>
                        
                        <div class="producto-info">
                            <div class="producto-marca">
    <?php 
    if (!empty($producto['nombre_tienda'])) {
        echo '🏪 ' . htmlspecialchars($producto['nombre_tienda']);
    } elseif (!empty($producto['proveedor_id'])) {
        echo '🏪 ' . htmlspecialchars(obtenerNombreTienda($conn, $producto['proveedor_id']));
    } else {
        echo htmlspecialchars($sitio_nombre);
    }
    ?>
</div>
                            
                            <h3 class="producto-nombre"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
                            
                            <div class="producto-descripcion">
                                <?php 
                                $desc = $producto['descripcion'] ?? '';
                                echo htmlspecialchars(substr($desc, 0, 80));
                                if (strlen($desc) > 80) echo '...';
                                ?>
                            </div>
                            
                            <?php if (!empty($producto['categoria_nombre'])): ?>
                                <span class="producto-categoria">
                                    <?php echo $producto['categoria_icono'] ?? '🏷️'; ?> 
                                    <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
                                </span>
                            <?php endif; ?>
                            
                                                        <div class="producto-precio">
                                <?php if ($tiene_rebaja): ?>
                                    <span class="precio-original">
                                        <?php echo $simbolo_producto; ?><?php echo number_format($precio_original, 2); ?>
                                    </span>
                                    <span class="precio-final">
                                        <?php echo $simbolo_producto; ?><?php echo number_format($precio_final, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="precio-normal">
                                        <?php echo $simbolo_producto; ?><?php echo number_format($precio_final, 2); ?>
                                    </span>
                                <?php endif; ?>
                                <small><?php echo $moneda_producto; ?></small>
                            </div>
                            
                            <?php if ($producto['cantidad'] <= 0): ?>
                                <div class="producto-acciones" style="opacity: 0.5; pointer-events: none;">
                            <?php else: ?>
                                <div class="producto-acciones">
                            <?php endif; ?>
                                <div class="cantidad-selector" data-producto-id="<?php echo $producto['id']; ?>">
                                    <button class="cantidad-btn" onclick="event.stopPropagation(); event.preventDefault(); cambiarCantidad(this, -1)">−</button>
                                    <input type="text" class="cantidad-input" value="1" maxlength="3" readonly>
                                    <button class="cantidad-btn" onclick="event.stopPropagation(); event.preventDefault(); cambiarCantidad(this, 1)">+</button>
                                </div>
                                <button class="btn-carrito" onclick="event.stopPropagation(); agregarAlCarrito(<?php echo $producto['id']; ?>, this)">
                                    <span class="carrito-icono">🛒</span> Añadir
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <div id="loader-mas-productos" style="text-align: center; padding: 2rem; display: none;">
                <div style="display: inline-block; width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <p style="margin-top: 0.5rem; color: #718096;">Cargando más productos...</p>
            </div>
            
                        <?php
            // Destino del enlace "Ver todos" / "Recargar tienda"
            // Si hay proveedor → index.php?proveedor={slug} (todos los productos y combos de esa tienda)
            // Si es global → shop.php (dashboard)
            $url_ver_todos = ($proveedor_id > 0 && !empty($proveedor_slug))
                ? 'index.php?proveedor=' . urlencode($proveedor_slug)
                : 'shop.php';
            ?>
            <div id="fin-productos" style="text-align: center; padding: 2rem; color: #718096; display: none;">
                ✅ Has visto todos los productos
                <?php if (!empty($termino_busqueda)): ?>
                    <br><br>
                    <a href="<?php echo htmlspecialchars($url_ver_todos); ?>" style="display: inline-block; padding: 0.6rem 1.2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 30px; text-decoration: none; font-size: 0.85rem; margin-top: 0.5rem;" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                        🔄 Ver todos los productos
                    </a>
                <?php else: ?>
                    <br><br>
                    <a href="<?php echo htmlspecialchars($url_ver_todos); ?>" style="display: inline-block; padding: 0.6rem 1.2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 30px; text-decoration: none; font-size: 0.85rem; margin-top: 0.5rem;" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                        🔄 Recargar tienda
                    </a>
                <?php endif; ?>
            </div>
            
                        <div id="cargarMasBtn" style="text-align: center; padding: 1rem; display: none;">
                <button onclick="cargarMasManual()" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 30px; font-size: 0.85rem; cursor: pointer;">
                    ➕ Cargar más productos
                </button>
            </div>

            <!-- Botón "Ver todos los productos" debajo de Cargar más (solo si hay filtro activo) -->
            <div id="verTodosBtn" style="text-align: center; padding: 1rem; display: none;">
                <a href="<?php echo htmlspecialchars($url_ver_todos); ?>" 
                   style="display: inline-block; padding: 0.6rem 1.2rem; background: #e2e8f0; color: #1f2937; border-radius: 30px; text-decoration: none; font-size: 0.85rem; font-weight: 500; transition: all 0.3s;"
                   onmouseover="this.style.background='var(--primary)'; this.style.color='white';"
                   onmouseout="this.style.background='#e2e8f0'; this.style.color='#1f2937';"
                   onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                    📦 Ver todos los productos<?php echo ($proveedor_id > 0 && !empty($proveedor_nombre)) ? ' de ' . htmlspecialchars($proveedor_nombre) : ''; ?>
                </a>
            </div>
            
            <style>
                @keyframes spin { to { transform: rotate(360deg); } }
            </style>
            
        <?php elseif (!$es_filtro_combos): ?>
    <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg);">
        <?php if (!empty($termino_busqueda)): ?>
            <h3>📭 No hay productos activos disponibles</h3>
            <p style="margin-top: 1rem;">No se encontraron resultados para "<strong><?php echo htmlspecialchars($termino_busqueda); ?></strong>"</p>
            <?php if ($proveedor_id > 0 && !empty($proveedor_slug)): ?>
                <p style="margin-top: 1rem;">
                    <a href="index.php?proveedor=<?php echo urlencode($proveedor_slug); ?>" 
                       style="color: var(--primary); font-weight: 600; text-decoration: none;"
                       onmouseover="this.style.textDecoration='underline';"
                       onmouseout="this.style.textDecoration='none';"
                       onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                        📦 Ver todos los productos de <?php echo htmlspecialchars($proveedor_nombre); ?>
                    </a>
                </p>
            <?php else: ?>
                <p><a href="index.php" style="color: var(--primary);">← Volver a todos los productos</a></p>
            <?php endif; ?>
        <?php else: ?>
            <h3>📭 No hay productos activos disponibles</h3>
            <?php if ($proveedor_id > 0 && !empty($proveedor_slug)): ?>
                <p style="margin-top: 1rem;">
                    <a href="index.php?proveedor=<?php echo urlencode($proveedor_slug); ?>" 
                       style="color: var(--primary); font-weight: 600; text-decoration: none;"
                       onmouseover="this.style.textDecoration='underline';"
                       onmouseout="this.style.textDecoration='none';"
                       onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                        📦 Ver todos los productos de <?php echo htmlspecialchars($proveedor_nombre); ?>
                    </a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
    </section>
</main>

<!-- ============================================ -->
<!-- SCRIPTS ESPECÍFICOS DE INDEX.PHP            -->
<!-- ============================================ -->
<script>
// ============================================
// FUNCIONES DEL BUSCADOR
// ============================================
const inputBuscar = document.getElementById('buscarInputTitulo');
if (inputBuscar) {
    function ejecutarBusqueda() {
        const termino = inputBuscar.value.trim();
        if (termino !== '') {
            toggleCarrusel(true);
            buscarProductosAJAX(termino);
        }
    }
    
    const btnLupa = document.getElementById('btnLupaBuscar');
    if (btnLupa) {
        btnLupa.addEventListener('click', function(e) {
            e.preventDefault();
            ejecutarBusqueda();
        });
    }
    
    const btnLimpiar = document.getElementById('btnLimpiarBusqueda');
    if (btnLimpiar) {
        btnLimpiar.addEventListener('click', function(e) {
            e.preventDefault();
            if (inputBuscar) {
                inputBuscar.value = '';
            }
            this.style.display = 'none';
            window.busquedaActiva = '';
            filtrarCategoria(0);
            toggleCarrusel(false);
        });
    }
    
    inputBuscar.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            ejecutarBusqueda();
        }
    });
}

// ============================================
// SCROLL INFINITO
// ============================================
window.addEventListener('scroll', function() {
    if (window.busquedaActiva) return;
    if (window.scrollInfinitoHabilitado === false) return;
    
    const scrollPos = window.innerHeight + window.scrollY;
    const alturaTotal = document.body.scrollHeight;
    var esMovil = window.innerWidth <= 768;
    var sensibilidad = esMovil ? 700 : 400;
    
    if (scrollPos >= alturaTotal - sensibilidad) {
        cargarMasProductos();
    }
});

// ============================================
// INICIALIZAR
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(ajustarTamanosTarjetas, 50);
    actualizarContadorCarrito();
});

window.addEventListener('resize', function() {
    ajustarTamanosTarjetas();
});

// Verificar carrito pendiente
// verificarCarritoPendiente();

// ============================================
// MOSTRAR/OCULTAR "TODOS LOS PRODUCTOS" SEGÚN FILTRO
// ============================================
function actualizarBotonTodosProductos() {
    var hayFiltro = false;
    
    // 1. Verificar si hay búsqueda activa (por AJAX)
    if (window.busquedaActiva && window.busquedaActiva !== '') {
        hayFiltro = true;
    }
    
    // 2. Verificar si hay filtro de categoría (por el grid)
    var grid = document.getElementById('productos-grid');
    if (grid) {
        var categoria = grid.getAttribute('data-categoria');
        if (categoria && categoria !== '0') {
            hayFiltro = true;
        }
        var filtro = grid.getAttribute('data-filtro');
        if (filtro && filtro !== '') {
            hayFiltro = true;
        }
    }
    
    // 3. Verificar filtros desde la URL (nuevos, mas_vendidos, ofertas, combos, categoria, buscar)
    var params = new URLSearchParams(window.location.search);
    if (params.get('filtro') || params.get('categoria') || params.get('buscar')) {
        hayFiltro = true;
    }
    
    // 4. Mostrar u ocultar el enlace "Todos los productos" (arriba, junto al título)
    var btnTodos = document.getElementById('btnTodosProductos');
    if (btnTodos) {
        btnTodos.style.display = hayFiltro ? 'inline-block' : 'none';
    }
    
    // 5. Mostrar u ocultar el botón "Ver todos los productos" (debajo de Cargar más)
    var verTodosBtn = document.getElementById('verTodosBtn');
    if (verTodosBtn) {
        verTodosBtn.style.display = hayFiltro ? 'block' : 'none';
    }
}

// Ejecutar al cargar y después de cada búsqueda/filtro
document.addEventListener('DOMContentLoaded', function() {
    actualizarBotonTodosProductos();
    
    // Observar cambios en el grid (para cuando se carguen productos)
    var observer = new MutationObserver(function() {
        actualizarBotonTodosProductos();
    });
    var grid = document.getElementById('productos-grid');
    if (grid) {
        observer.observe(grid, { childList: true, subtree: true });
    }
});

// Modificar buscarProductosAJAX para que actualice el botón
var buscarProductosAJAXOriginal = buscarProductosAJAX;
buscarProductosAJAX = function(termino) {
    buscarProductosAJAXOriginal(termino);
    setTimeout(actualizarBotonTodosProductos, 300);
};

// Modificar filtrarCategoria para que actualice el botón
var filtrarCategoriaOriginal = filtrarCategoria;
filtrarCategoria = function(categoriaId) {
    filtrarCategoriaOriginal(categoriaId);
    setTimeout(actualizarBotonTodosProductos, 300);
};

// Modificar los filtros (nuevos, mas_vendidos, ofertas, combos)
var filtros = ['filtrarNuevos', 'filtrarMasVendidos', 'filtrarOfertas', 'cargarCombos'];
filtros.forEach(function(nombre) {
    if (typeof window[nombre] === 'function') {
        var original = window[nombre];
        window[nombre] = function() {
            original();
            setTimeout(actualizarBotonTodosProductos, 300);
        };
    }
});
</script>

<?php $conn->close(); ?>
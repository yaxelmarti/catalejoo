<?php
// ============================================
// shop.php - PÁGINA DE BIENVENIDA/DASHBOARD
// ============================================

if (!defined('SEGURO')) {
    define('SEGURO', true);
}

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';
require_once 'includes/carrito_funciones.php';
require_once 'includes/moneda_funciones.php';
require_once 'includes/proveedor_utils.php';

registrarVisita($conn);

// ============================================
// FUNCIÓN PARA CORREGIR RUTAS DE IMÁGENES
// ============================================
function corregirRutaImagen($ruta) {
    if (empty($ruta)) return '';
    // Eliminar espacios al inicio/final
    $ruta = trim($ruta);
    // Si no tiene barra al inicio, agregarla
    $ruta = (strpos($ruta, '/') === 0) ? $ruta : '/' . $ruta;
    // Limpiar doble barra
    $ruta = preg_replace('#^/+#', '/', $ruta);
    return $ruta;
}

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
$cliente_id = $cliente['id'] ?? 0;
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
// Shop es el dashboard general: sin proveedor específico.
// El header resolverá moneda del cliente o carrito pendiente.
// ============================================
$proveedor_contexto = 0;

// ============================================
// OBTENER SLUG DE LA TIENDA INSIGNIA (id 1)
// Se lee de la BD para que sea escalable:
// si cambia el nombre/slug, el enlace se actualiza solo.
// ============================================
$stmt_tienda_insignia = $conn->prepare("SELECT slug FROM proveedores WHERE id = 1 AND activo = 1");
$stmt_tienda_insignia->execute();
$result_tienda_insignia = $stmt_tienda_insignia->get_result();
$tienda_insignia = $result_tienda_insignia->fetch_assoc();
$tienda_insignia_slug = $tienda_insignia['slug'] ?? '';
$stmt_tienda_insignia->close();

$sql_categorias = "SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre";
$categorias_barra = $conn->query($sql_categorias);

// ============================================
// OBTENER IMÁGENES PARA TARJETAS DE ACCESO RÁPIDO
// ============================================
$sql_ofertas_img = "SELECT imagen_url, nombre, porcentaje_rebaja 
                    FROM productos 
                    WHERE activo = 1 AND porcentaje_rebaja > 0 
                    ORDER BY porcentaje_rebaja DESC 
                    LIMIT 4";
$ofertas_img = $conn->query($sql_ofertas_img);

$sql_combos_img = "SELECT imagen_url, nombre, precio 
                   FROM combos 
                   WHERE activo = 1 
                   ORDER BY precio DESC 
                   LIMIT 4";
$combos_img = $conn->query($sql_combos_img);

$sql_nuevos_img = "SELECT imagen_url, nombre, fecha_creacion 
                   FROM productos 
                   WHERE activo = 1 
                   ORDER BY fecha_creacion DESC 
                   LIMIT 4";
$nuevos_img = $conn->query($sql_nuevos_img);

$sql_mas_vendidos_img = "SELECT p.imagen_url, p.nombre, 
                         COALESCE(SUM(cp.cantidad), 0) as total_vendidos
                         FROM productos p
                         LEFT JOIN carrito_productos cp ON cp.producto_id = p.id
                         LEFT JOIN carritos c ON cp.carrito_id = c.id AND c.estado = 'comprado'
                         WHERE p.activo = 1
                         GROUP BY p.id
                         ORDER BY total_vendidos DESC
                         LIMIT 4";
$mas_vendidos_img = $conn->query($sql_mas_vendidos_img);

$proveedor1_img = [
    ['imagen_url' => '/uploads/shop/proveedor1/imagen1.webp', 'nombre' => 'Producto Ejemplo 1'],
    ['imagen_url' => '/uploads/shop/proveedor1/imagen2.webp', 'nombre' => 'Producto Ejemplo 2'],
    ['imagen_url' => '/uploads/shop/proveedor1/imagen3.webp', 'nombre' => 'Producto Ejemplo 3'],
    ['imagen_url' => '/uploads/shop/proveedor1/imagen4.webp', 'nombre' => 'Producto Ejemplo 4']
];

$proveedor2_img = [
    ['imagen_url' => '/uploads/shop/proveedor2/imagen1.webp', 'nombre' => 'Producto Ejemplo 1'],
    ['imagen_url' => '/uploads/shop/proveedor2/imagen2.webp', 'nombre' => 'Producto Ejemplo 2'],
    ['imagen_url' => '/uploads/shop/proveedor2/imagen3.webp', 'nombre' => 'Producto Ejemplo 3'],
    ['imagen_url' => '/uploads/shop/proveedor2/imagen4.webp', 'nombre' => 'Producto Ejemplo 4']
];

// ============================================
// PRODUCTOS Y COMBOS DESTACADOS (MÁS VISTOS)
// ============================================

// 1. Obtener productos más vistos (solo tienda 1)
$sql_productos = "SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono, 'producto' as tipo
                  FROM productos p
                  LEFT JOIN categorias c ON p.categoria_id = c.id
                  WHERE p.activo = 1 AND p.proveedor_id = 1
                  ORDER BY p.vistas DESC
                  LIMIT 15";
$productos_destacados = $conn->query($sql_productos);

// 2. Obtener combos más vistos (solo tienda 1)
$sql_combos = "SELECT c.*, NULL as categoria_nombre, NULL as categoria_icono, 'combo' as tipo
                FROM combos c
                WHERE c.activo = 1 AND c.proveedor_id = 1
                ORDER BY c.vistas DESC
                LIMIT 15";
$combos_destacados = $conn->query($sql_combos);

// 3. Combinar ambos resultados en un solo array
$items_destacados = [];

if ($productos_destacados && $productos_destacados->num_rows > 0) {
    while($row = $productos_destacados->fetch_assoc()) {
        $items_destacados[] = $row;
    }
}

if ($combos_destacados && $combos_destacados->num_rows > 0) {
    while($row = $combos_destacados->fetch_assoc()) {
        $items_destacados[] = $row;
    }
}

// 4. Ordenar por vistas (de mayor a menor)
usort($items_destacados, function($a, $b) {
    return ($b['vistas'] ?? 0) - ($a['vistas'] ?? 0);
});

// 5. Tomar solo los primeros 15
$items_destacados = array_slice($items_destacados, 0, 15);

// ============================================
// PRODUCTOS MÁS VISTOS POR TIENDA (3 POR TIENDA, LÍMITE 60)
// ============================================
$sql_tiendas_productos = "
    SELECT 
        p.id,
        p.nombre,
        p.imagen_url,
        p.precio,
        p.vistas,
        p.proveedor_id,
        pr.nombre_tienda,
        pr.slug
    FROM productos p
    INNER JOIN proveedores pr ON p.proveedor_id = pr.id
    WHERE p.activo = 1 
      AND pr.activo = 1
      AND p.proveedor_id IS NOT NULL
      AND p.proveedor_id >= 2
    ORDER BY p.vistas DESC
";

$result_tiendas = $conn->query($sql_tiendas_productos);
$productos_por_tienda = [];
$tiendas_limite = [];

if ($result_tiendas && $result_tiendas->num_rows > 0) {
    while ($row = $result_tiendas->fetch_assoc()) {
        $tienda_id = $row['proveedor_id'];
        
        // Si la tienda ya tiene 3 productos, saltar
        if (!isset($tiendas_limite[$tienda_id])) {
            $tiendas_limite[$tienda_id] = 0;
        }
        
        if ($tiendas_limite[$tienda_id] < 3) {
            $productos_por_tienda[] = $row;
            $tiendas_limite[$tienda_id]++;
        }
    }
}

// Ordenar globalmente por vistas (de mayor a menor)
usort($productos_por_tienda, function($a, $b) {
    return ($b['vistas'] ?? 0) - ($a['vistas'] ?? 0);
});

// Limitar a 60 productos
$productos_por_tienda = array_slice($productos_por_tienda, 0, 60);

// ============================================
// INCLUIR HEADER (YA TIENE <html> y <body>)
// El header se encarga de definir window.clienteRegistrado y window.monedaSimbolo
// ============================================
include 'index_modales/carrito.php';
include 'header_tienda.php';
?>

<!-- ============================================ -->
<!-- CONTENIDO DE SHOP.PHP                        -->
<!-- ============================================ -->
<main class="container">
    <!-- 1. CARRUSEL -->
    <div class="amazon-carrusel">
        <div class="container">
            <?php include 'index_modales/marketing.php'; ?>
        </div>
    </div>

    <!-- 2. BARRA DE CATEGORÍAS -->
    <?php if ($categorias_barra && $categorias_barra->num_rows > 0): ?>
    <div class="categorias-barra-blanca">
        <div class="container">
            <div class="categorias-barra-wrapper">
                <button class="categoria-barra-btn categoria-barra-btn-izq" id="catBtnIzq">❮</button>
                <div class="categorias-barra-scroll" id="categoriasScroll">
                    <?php while($cat = $categorias_barra->fetch_assoc()): ?>
                        <a href="index.php?categoria=<?php echo $cat['id']; ?>" class="categoria-barra-item" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                            <span class="categoria-barra-icono"><?php echo $cat['icono'] ?? '📁'; ?></span>
                            <span class="categoria-barra-nombre"><?php echo htmlspecialchars($cat['nombre']); ?></span>
                        </a>
                    <?php endwhile; ?>
                </div>
                <button class="categoria-barra-btn categoria-barra-btn-der" id="catBtnDer">❯</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
         <!-- 2.5 BUSCADOR RÁPIDO -->
<div class="container">
    <div class="buscador-shop-container" style="text-align: center; margin: 1.5rem 0 1rem 0;">
        <form id="buscadorShopForm" action="resultados_busqueda_shop.php" method="GET" style="display: flex; max-width: 600px; width: 100%; margin: 0 auto; gap: 0.5rem; align-items: center;">
            <input type="text" name="buscar" id="buscadorShopInput" placeholder="🔍 Buscar productos..." 
                   style="flex: 1; padding: 0.7rem 1rem; border: 2px solid #e2e8f0; border-radius: 30px; font-size: 1rem; outline: none; transition: border-color 0.3s, box-shadow 0.3s; min-width: 0;">
            <button type="submit" style="padding: 0.7rem 1.2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border: none; border-radius: 30px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; white-space: nowrap; box-shadow: 0 4px 15px rgba(102,126,234,0.4);">
                🔍 Buscar
            </button>
        </form>
                <p style="font-size: 0.8rem; color: #718096; margin-top: 0.3rem;">Encuentra lo que necesitas en nuestra tienda</p>
    </div>
</div>

<!-- AVISO DE MONEDA (comentado)
<div class="container">
    <div style="background: #f0f4ff; border: 1px solid #667eea; border-radius: 12px; padding: 0.8rem 1rem; margin: 1rem auto; max-width: 800px; text-align: center; font-size: 0.85rem; color: #4a5568;">
        💵 <strong>Precios en USD</strong> (moneda base del marketplace). Al entrar en una tienda, verás los precios en la moneda que acepte el proveedor.
    </div>
</div>
-->
    
<!-- ============================================ -->
<!-- 2. ANUNCIOS CON EFECTO FADE                 -->
<!-- ============================================ -->
<div class="anuncios-fade-container" style="margin: 1rem 0 2rem 0; position: relative; width: 100%; max-width: 1200px; height: clamp(200px, 50vw, 580px); overflow: hidden; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <div class="anuncio-slide" id="anuncio1" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 1; transition: opacity 1.5s ease-in-out; background: url('/uploads/anuncios/anuncio1.webp') center/cover no-repeat;">
    </div>
    <div class="anuncio-slide" id="anuncio2" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 1.5s ease-in-out; background: url('/uploads/anuncios/anuncio2.webp') center/cover no-repeat;">
    </div>
    <div class="anuncio-slide" id="anuncio3" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 1.5s ease-in-out; background: url('/uploads/anuncios/anuncio3.webp') center/cover no-repeat;">
    </div>
</div>

    <!-- 3. TARJETAS DE ACCESO RÁPIDO -->
    <div class="container">
        <div class="amazon-seccion">
            <h2 class="amazon-titulo-seccion">⚡ Acceso rápido</h2>
            <div class="amazon-tarjetas-grid">
                <!-- TARJETA 1: OFERTAS -->
<div class="amazon-tarjeta" onclick="window.location.href='index.php?proveedor=<?php echo urlencode($tienda_insignia_slug); ?>&filtro=ofertas&registrar_visita=1'; sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                    <div class="amazon-tarjeta-imagen" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                        <div class="amazon-tarjeta-overlay">
                            <span class="amazon-tarjeta-badge">🔥 Hasta 50%</span>
                            <div class="amazon-tarjeta-header">
                                <span class="amazon-tarjeta-icono">💰</span>
                                <h3 class="amazon-tarjeta-titulo">Ofertas</h3>
                            </div>
                            <div class="amazon-tarjeta-miniaturas">
                                <?php if ($ofertas_img && $ofertas_img->num_rows > 0): ?>
                                    <?php while($img = $ofertas_img->fetch_assoc()): 
    $ruta_corregida = corregirRutaImagen($img['imagen_url']);
    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_corregida;
    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
?>
    <img src="<?php echo $ruta_corregida . '?v=' . $version; ?>" 
                                             alt="<?php echo htmlspecialchars($img['nombre']); ?>" 
                                             class="tarjeta-miniatura"
                                             loading="lazy">
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                <?php endif; ?>
                            </div>
                            <div class="amazon-tarjeta-boton">Ver ofertas →</div>
                        </div>
                    </div>
                </div>

                <!-- TARJETA 2: COMBOS -->
<div class="amazon-tarjeta" onclick="window.location.href='index.php?proveedor=<?php echo urlencode($tienda_insignia_slug); ?>&filtro=combos&registrar_visita=1'; sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                    <div class="amazon-tarjeta-imagen" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">
                        <div class="amazon-tarjeta-overlay">
                            <span class="amazon-tarjeta-badge">🎁 Ahorra más</span>
                            <div class="amazon-tarjeta-header">
                                <span class="amazon-tarjeta-icono">📦</span>
                                <h3 class="amazon-tarjeta-titulo">Combos</h3>
                            </div>
                            <div class="amazon-tarjeta-miniaturas">
                                <?php if ($combos_img && $combos_img->num_rows > 0): ?>
                                    <?php while($img = $combos_img->fetch_assoc()): 
    $ruta_corregida = corregirRutaImagen($img['imagen_url']);
    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_corregida;
    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
?>
    <img src="<?php echo $ruta_corregida . '?v=' . $version; ?>" 
                                             alt="<?php echo htmlspecialchars($img['nombre']); ?>" 
                                             class="tarjeta-miniatura"
                                             loading="lazy">
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                <?php endif; ?>
                            </div>
                            <div class="amazon-tarjeta-boton">Ver combos →</div>
                        </div>
                    </div>
                </div>

                <!-- TARJETA 3: NUEVOS -->
<div class="amazon-tarjeta" onclick="window.location.href='index.php?proveedor=<?php echo urlencode($tienda_insignia_slug); ?>&filtro=nuevos&registrar_visita=1'; sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                    <div class="amazon-tarjeta-imagen" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                        <div class="amazon-tarjeta-overlay">
                            <span class="amazon-tarjeta-badge">🆕 Recién llegados</span>
                            <div class="amazon-tarjeta-header">
                                <span class="amazon-tarjeta-icono">✨</span>
                                <h3 class="amazon-tarjeta-titulo">Nuevos</h3>
                            </div>
                            <div class="amazon-tarjeta-miniaturas">
                                <?php if ($nuevos_img && $nuevos_img->num_rows > 0): ?>
                                    <?php while($img = $nuevos_img->fetch_assoc()): 
    $ruta_corregida = corregirRutaImagen($img['imagen_url']);
    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_corregida;
    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
?>
    <img src="<?php echo $ruta_corregida . '?v=' . $version; ?>" 
                                             alt="<?php echo htmlspecialchars($img['nombre']); ?>" 
                                             class="tarjeta-miniatura"
                                             loading="lazy">
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                <?php endif; ?>
                            </div>
                            <div class="amazon-tarjeta-boton">Ver nuevos →</div>
                        </div>
                    </div>
                </div>

               <!-- TARJETA 4: MÁS VENDIDOS -->
<div class="amazon-tarjeta" onclick="window.location.href='index.php?proveedor=<?php echo urlencode($tienda_insignia_slug); ?>&filtro=mas_vendidos&registrar_visita=1'; sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                    <div class="amazon-tarjeta-imagen" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">
                        <div class="amazon-tarjeta-overlay">
                            <span class="amazon-tarjeta-badge">⭐ Top ventas</span>
                            <div class="amazon-tarjeta-header">
                                <span class="amazon-tarjeta-icono">🏆</span>
                                <h3 class="amazon-tarjeta-titulo">Más vendidos</h3>
                            </div>
                            <div class="amazon-tarjeta-miniaturas">
                                <?php if ($mas_vendidos_img && $mas_vendidos_img->num_rows > 0): ?>
                                    <?php while($img = $mas_vendidos_img->fetch_assoc()): 
    $ruta_corregida = corregirRutaImagen($img['imagen_url']);
    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_corregida;
    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
?>
    <img src="<?php echo $ruta_corregida . '?v=' . $version; ?>" 
                                             alt="<?php echo htmlspecialchars($img['nombre']); ?>" 
                                             class="tarjeta-miniatura"
                                             loading="lazy">
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                    <div class="tarjeta-miniatura-placeholder">📦</div>
                                <?php endif; ?>
                            </div>
                            <div class="amazon-tarjeta-boton">Ver más vendidos →</div>
                        </div>
                    </div>
                </div>

             <?php
// Obtener las 3 tiendas más visitadas
$sql_top_tiendas = "SELECT id, nombre_tienda, imagen_url, slug, vistas 
                    FROM proveedores 
                    WHERE activo = 1 
                    ORDER BY vistas DESC 
                    LIMIT 3";
$top_tiendas = $conn->query($sql_top_tiendas);

$tiendas_mostrar = [];
if ($top_tiendas && $top_tiendas->num_rows > 0) {
    while($tienda = $top_tiendas->fetch_assoc()) {
        $tiendas_mostrar[] = $tienda;
    }
}
while(count($tiendas_mostrar) < 3) {
    $tiendas_mostrar[] = [
        'imagen_url' => null,
        'nombre_tienda' => 'Tienda Destacada',
        'slug' => '#'
    ];
}
?>

<!-- TARJETA 5: DIRECTORIO DE TIENDAS -->
<div class="amazon-tarjeta" onclick="window.location.href='directorio_proveedores.php'; sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
    <div class="amazon-tarjeta-imagen" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
        <div class="amazon-tarjeta-overlay">
            <span class="amazon-tarjeta-badge">🏪 Tiendas destacadas</span>
            <div class="amazon-tarjeta-header">
                <span class="amazon-tarjeta-icono">🛍️</span>
                <h3 class="amazon-tarjeta-titulo">Tiendas</h3>
            </div>
            <div class="amazon-tarjeta-miniaturas">
                <?php foreach ($tiendas_mostrar as $tienda): ?>
                    <?php if (!empty($tienda['imagen_url'])): 
                        $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $tienda['imagen_url'];
                        $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
                    ?>
                        <img src="<?php echo htmlspecialchars($tienda['imagen_url']) . '?v=' . $version; ?>" 
                             alt="<?php echo htmlspecialchars($tienda['nombre_tienda']); ?>" 
                             class="tarjeta-miniatura"
                             loading="lazy"
                             title="<?php echo htmlspecialchars($tienda['nombre_tienda']); ?>">
                    <?php else: ?>
                        <div class="tarjeta-miniatura-placeholder" title="<?php echo htmlspecialchars($tienda['nombre_tienda']); ?>">
                            🏬
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="amazon-tarjeta-boton">Ver todas las tiendas →</div>
        </div>
    </div>
</div>
             
                <!-- TARJETA 6: PROVEEDOR 2 -->          
                <div class="amazon-tarjeta amazon-tarjeta-proximamente ocultar-tarjeta-6" onclick="mostrarProximamente();">
                    <div class="amazon-tarjeta-imagen" style="background: linear-gradient(135deg, var(--secondary), var(--primary));">
                        <div class="amazon-tarjeta-overlay">
                            <span class="amazon-tarjeta-badge">🚧 Próximamente</span>
                            <div class="amazon-tarjeta-header">
                                <span class="amazon-tarjeta-icono">🏪</span>
                                <h3 class="amazon-tarjeta-titulo">Tienda "Ejemplo 2"</h3>
                            </div>
                            <div class="amazon-tarjeta-miniaturas">
                                <?php foreach ($proveedor2_img as $img): ?>
                                    <img src="<?php echo $img['imagen_url']; ?>" 
                                         alt="<?php echo htmlspecialchars($img['nombre']); ?>" 
                                         class="tarjeta-miniatura"
                                         loading="lazy">
                                <?php endforeach; ?>
                            </div>
                            <div class="amazon-tarjeta-boton" style="opacity:0.6;">Próximamente</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
          
<!-- 4. PRODUCTOS DESTACADOS (CARRUSEL SIN CARRITO) -->
<?php if (!empty($items_destacados)): ?>
<div class="container">
    <div class="amazon-seccion">
        <div class="carrusel-header">
            <h2 class="amazon-titulo-seccion">⭐⭐ Más vistos</h2>
            <a href="index.php" class="ver-todos-link" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">Ver todos →</a>
        </div>
        
        <!-- ===== CARRUSEL SIN CARRITO ===== -->
        <div class="carrusel-wrapper-productos carrusel-siempre" id="productosCarruselWrapper">
            <button class="carrusel-btn-productos carrusel-btn-productos-izq" id="prodBtnIzq">❮</button>
            <div class="carrusel-productos" id="productosCarrusel">
                <?php foreach($items_destacados as $item): 
    // Proveedor del ítem (para tasa personalizada)
    $prov_id_item = intval($item['proveedor_id'] ?? 0);
    
    // Determinar si es producto o combo
    if ($item['tipo'] == 'combo') {
        $dias_antiguedad = 0;
        $es_nuevo = false;
        $tiene_rebaja = $item['porcentaje_rebaja'] > 0;
        
        // Precios base USD
        $precio_original_usd = $item['precio'];
        $precio_final_usd = $tiene_rebaja ? ($item['precio'] * (1 - $item['porcentaje_rebaja'] / 100)) : $item['precio'];
        
        $nombre = $item['nombre'];
        $imagen_url = $item['imagen_url'];
        $marca = '🎁 Combo';
        $categoria_nombre = 'Combo';
        $categoria_icono = '🎁';
        $id = $item['id'];
        $tipo_url = 'proveedor=' . urlencode($tienda_insignia_slug) . '&filtro=combos';
        $es_combo = true;
        $cantidad = $item['stock_combo'] ?? 0;
    } else {
        $dias_antiguedad = floor((time() - strtotime($item['fecha_creacion'])) / 86400);
        $es_nuevo = $dias_antiguedad < 7;
        $tiene_rebaja = $item['porcentaje_rebaja'] > 0;
        
        // Precios base USD
        $precio_original_usd = $tiene_rebaja ? $item['precio_original'] : $item['precio'];
        $precio_final_usd = $item['precio'];
        
        $nombre = $item['nombre'];
        $imagen_url = $item['imagen_url'];
        $marca = !empty($item['marca']) ? $item['marca'] : $sitio_nombre;
        $categoria_nombre = $item['categoria_nombre'] ?? '';
        $categoria_icono = $item['categoria_icono'] ?? '🏷️';
        $id = $item['id'];
        $primera_palabra = explode(' ', trim($item['nombre']))[0];
        $tipo_url = 'proveedor=' . urlencode($tienda_insignia_slug) . '&buscar=' . urlencode($primera_palabra);
        $es_combo = false;
        $cantidad = $item['cantidad'] ?? 0;
    }
    
        // Precios siempre en USD (moneda base del marketplace)
    $precio_original = $precio_original_usd;
    $precio_final = $precio_final_usd;
?>
                    <div class="producto-card-sin-carrito" onclick="window.location.href='index.php?<?php echo $tipo_url; ?>'">
                        <div class="producto-etiquetas">
                            <?php if ($es_nuevo && !$es_combo): ?>
                                <span class="etiqueta-nuevo">NUEVO</span>
                            <?php endif; ?>
                            <?php if ($tiene_rebaja): ?>
                                <span class="etiqueta-rebaja">-<?php echo $item['porcentaje_rebaja']; ?>%</span>
                            <?php endif; ?>
                            <?php if ($cantidad <= 0): ?>
                                <span class="etiqueta-agotado">AGOTADO</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="producto-imagen-wrapper">
                            <?php 
$ruta_corregida = corregirRutaImagen($imagen_url);
$ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_corregida;
$version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
?>
<img src="<?php echo htmlspecialchars($ruta_corregida) . '?v=' . $version; ?>" 
                                 alt="<?php echo htmlspecialchars($nombre); ?>" 
                                 class="producto-imagen"
                                 loading="lazy">
                        </div>
                        
                        <div class="producto-info-sin-carrito">
                            <div class="producto-marca">
                                <?php echo htmlspecialchars($marca); ?>
                            </div>
                            
                            <h3 class="producto-nombre"><?php echo htmlspecialchars($nombre); ?></h3>
                            
                            <?php if (!empty($categoria_nombre) && !$es_combo): ?>
                                <span class="producto-categoria" style="font-size: 0.6rem; display: inline-block; background: #eef2ff; color: #4f46e5; padding: 0.1rem 0.5rem; border-radius: 20px; margin-bottom: 0.3rem; align-self: flex-start;">
                                    <?php echo $categoria_icono; ?> <?php echo htmlspecialchars($categoria_nombre); ?>
                                </span>
                            <?php endif; ?>
                            
                                                        <div class="producto-precio">
                                <?php if ($tiene_rebaja): ?>
                                    <span class="precio-original">
                                        $<?php echo number_format($precio_original, 2); ?>
                                    </span>
                                    <span class="precio-final">
                                        $<?php echo number_format($precio_final, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="precio-normal">
                                        $<?php echo number_format($precio_final, 2); ?>
                                    </span>
                                <?php endif; ?>
                                <small>USD</small>
                            </div>
                            
                            <div class="producto-ver">
                                <span class="ver-producto">Ver <?php echo $es_combo ? 'combo' : 'producto'; ?> →</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carrusel-btn-productos carrusel-btn-productos-der" id="prodBtnDer">❯</button>
        </div>
        
    </div>
</div>
<?php endif; ?>
    
<!-- ============================================ -->
<!-- PRODUCTOS MÁS VISTOS POR TIENDA              -->
<!-- ============================================ -->
<?php if (!empty($productos_por_tienda)): ?>
<div class="container">
    <div class="amazon-seccion">
        <div class="carrusel-header">
            <h2 class="amazon-titulo-seccion">⭐⭐ Más vistos por tienda</h2>
        </div>
        
        <!-- ===== CARRUSEL ===== -->
        <div class="carrusel-wrapper-productos carrusel-siempre" id="productosTiendaCarruselWrapper">
            <button class="carrusel-btn-productos carrusel-btn-productos-izq" id="prodTiendaBtnIzq">❮</button>
            <div class="carrusel-productos" id="productosTiendaCarrusel">
                <?php foreach ($productos_por_tienda as $item): 
    $ruta_corregida = corregirRutaImagen($item['imagen_url']);
    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_corregida;
    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
    $tienda_slug = $item['slug'];
    $tienda_nombre = htmlspecialchars($item['nombre_tienda']);
    
    // Precio siempre en USD (moneda base del marketplace)
    $precio_item = floatval($item['precio']);
?>
                                <a href="index.php?proveedor=<?php echo urlencode($tienda_slug); ?>&registrar_visita=1" 
                   class="producto-card-sin-carrito"  
                   style="text-decoration: none; color: inherit;"
                   onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.12)';"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                    
                    <div class="producto-imagen-wrapper">
                        <img src="<?php echo htmlspecialchars($ruta_corregida) . '?v=' . $version; ?>" 
                             alt="<?php echo htmlspecialchars($item['nombre']); ?>" 
                             class="producto-imagen"
                             loading="lazy">
                    </div>
                    
                    <div class="producto-info-sin-carrito">
                        <div class="producto-marca" style="font-size: 0.6rem; color: var(--primary);">
                            🏪 <?php echo $tienda_nombre; ?>
                        </div>
                        
                        <h3 class="producto-nombre"><?php echo htmlspecialchars($item['nombre']); ?></h3>
                        
                        <div class="producto-ver">
                            <span class="ver-producto">Ver tienda →</span>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <button class="carrusel-btn-productos carrusel-btn-productos-der" id="prodTiendaBtnDer">❯</button>
        </div>
        
    </div>
</div>
<?php endif; ?>

    <!-- 5. BOTÓN VER TODOS -->
    <div class="container">
        <div style="text-align: center; margin: 2rem 0 3rem;">
            <a href="/index.php?categoria=0" class="amazon-btn-ver-todos" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                🛍️ Todos los productos
            </a>
        </div>
    </div>
</main>

<!-- ============================================ -->
<!-- MODALES                                      -->
<!-- ============================================ -->
<?php include 'index_modales/registro.php'; ?>
<?php include 'index_modales/verificar_password.php'; ?>
<?php include 'index_modales/carrito_pendiente.php'; ?>
<?php include 'index_modales/sincronizar.php'; ?>
<?php include 'index_modales/password_cliente.php'; ?>
<?php include 'index_modales/editar_cliente.php'; ?>
<?php include 'index_modales/password_compra.php'; ?>
<?php include 'index_modales/datos_entrega.php'; ?>
<?php include 'index_modales/producto_tienda.php'; ?>
<?php include 'index_modales/combo_tienda.php'; ?>
<?php include 'index_modales/bienvenida.php'; ?>
<?php include 'index_modales/comentarios.php'; ?>

<!-- ============================================ -->
<!-- FOOTER                                       -->
<!-- ============================================ -->
<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>✨ <?php echo htmlspecialchars($sitio_nombre); ?></h4>
                <p><?php echo htmlspecialchars($sitio_slogan); ?></p>
                <p>© <?php echo date('Y'); ?></p>
            </div>
            <div class="footer-col">
                <h4>📞 Contacto</h4>
                <ul class="footer-contacto">
                    <?php if (!empty($config['sitio_telefono'])): ?>
                    <li><span>📱</span> <a href="tel:<?php echo htmlspecialchars($config['sitio_telefono']); ?>"><?php echo htmlspecialchars($config['sitio_telefono']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($config['sitio_email'])): ?>
                    <li><span>📧</span> <a href="mailto:<?php echo htmlspecialchars($config['sitio_email']); ?>"><?php echo htmlspecialchars($config['sitio_email']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($config['horario_atencion'])): ?>
                    <li><span>⏰</span> <span><?php echo htmlspecialchars($config['horario_atencion']); ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-col">
                <h4>🌐 Redes</h4>
                <div class="footer-social">
                    <?php if (!empty($config['facebook_url'])): ?>
                    <a href="<?php echo htmlspecialchars($config['facebook_url']); ?>" target="_blank" class="social-icon" title="Facebook">📘</a>
                    <?php endif; ?>
                    <?php if (!empty($config['instagram_url'])): ?>
                    <a href="<?php echo htmlspecialchars($config['instagram_url']); ?>" target="_blank" class="social-icon" title="Instagram">📷</a>
                    <?php endif; ?>
                    <?php if (!empty($config['twitter_url'])): ?>
                    <a href="<?php echo htmlspecialchars($config['twitter_url']); ?>" target="_blank" class="social-icon" title="Twitter">🐦</a>
                    <?php endif; ?>
                    <?php if (!empty($config['whatsapp_numero'])): ?>
                    <a href="https://wa.me/<?php echo htmlspecialchars($config['whatsapp_numero']); ?>" target="_blank" class="social-icon" title="WhatsApp">💬</a>
                    <?php endif; ?>
                    <?php if (!empty($config['tiktok_url'])): ?>
                    <a href="<?php echo htmlspecialchars($config['tiktok_url']); ?>" target="_blank" class="social-icon" title="TikTok">🎵</a>
                    <?php endif; ?>
                    <?php if (!empty($config['youtube_url'])): ?>
                    <a href="<?php echo htmlspecialchars($config['youtube_url']); ?>" target="_blank" class="social-icon" title="YouTube">▶️</a>
                    <?php endif; ?>
                    <?php if (!empty($config['telegram_url'])): ?>
                    <a href="<?php echo htmlspecialchars($config['telegram_url']); ?>" target="_blank" class="social-icon" title="Telegram">✈️</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="footer-col">
                <h4>🔗 Enlaces</h4>
                <ul class="footer-enlaces">
                    <li><a href="/directorio_proveedores.php">🏪 Directorio de tiendas</a></li>
                    <li><a href="/ayuda.php">❓ Ayuda</a></li>
                    <li><a href="index.php">🛍️ Tienda</a></li>
                    <?php if ($cliente_registrado): ?>
                    <li><a href="/mis_pedidos.php">📋 Mis Compras</a></li>
                    <li><a href="#" onclick="abrirPanelCliente()">👤 Mi cuenta</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Footer para móvil (dos líneas fijo abajo) -->
    <div class="footer-movil">
        <div class="footer-movil-redes">
            <?php if (!empty($config['facebook_url'])): ?>
                <a href="<?php echo htmlspecialchars($config['facebook_url']); ?>" target="_blank" class="social-icon-movil">📘</a>
            <?php endif; ?>
            <?php if (!empty($config['instagram_url'])): ?>
                <a href="<?php echo htmlspecialchars($config['instagram_url']); ?>" target="_blank" class="social-icon-movil">📷</a>
            <?php endif; ?>
            <?php if (!empty($config['twitter_url'])): ?>
                <a href="<?php echo htmlspecialchars($config['twitter_url']); ?>" target="_blank" class="social-icon-movil">🐦</a>
            <?php endif; ?>
            <?php if (!empty($config['whatsapp_numero'])): ?>
                <a href="https://wa.me/<?php echo htmlspecialchars($config['whatsapp_numero']); ?>" target="_blank" class="social-icon-movil">💬</a>
            <?php endif; ?>
            <?php if (!empty($config['tiktok_url'])): ?>
                <a href="<?php echo htmlspecialchars($config['tiktok_url']); ?>" target="_blank" class="social-icon-movil">🎵</a>
            <?php endif; ?>
            <?php if (!empty($config['youtube_url'])): ?>
                <a href="<?php echo htmlspecialchars($config['youtube_url']); ?>" target="_blank" class="social-icon-movil">▶️</a>
            <?php endif; ?>
            <?php if (!empty($config['telegram_url'])): ?>
                <a href="<?php echo htmlspecialchars($config['telegram_url']); ?>" target="_blank" class="social-icon-movil">✈️</a>
            <?php endif; ?>
        </div>               <div class="footer-movil-contacto" style="display: flex; flex-direction: row; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px; padding: 0 10px; width: 100%; box-sizing: border-box;">
            <?php if (!empty($config['sitio_telefono'])): ?>
                <a href="tel:<?php echo htmlspecialchars($config['sitio_telefono']); ?>" class="footer-movil-tel" style="color: white; text-decoration: none; font-size: 0.7rem; white-space: nowrap; display: inline-block;">📱 <?php echo htmlspecialchars($config['sitio_telefono']); ?></a>
            <?php endif; ?>
            
            <?php if (!empty($config['sitio_email'])): ?>
                <a href="mailto:<?php echo htmlspecialchars($config['sitio_email']); ?>" class="footer-movil-email" style="color: white; text-decoration: none; font-size: 0.7rem; white-space: nowrap; display: inline-block;">📧 <?php echo htmlspecialchars($config['sitio_email']); ?></a>
            <?php endif; ?>
        </div>
    </div>
</footer>
<!-- ============================================ -->
<!-- ESTILOS                                     -->
<!-- ============================================ -->
<style>
.categorias-barra-blanca {
    background: white;
    padding: 0.5rem 0;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    position: relative;
}

.categorias-barra-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.categorias-barra-scroll {
    display: flex;
    gap: 1.5rem;
    overflow-x: auto;
    padding: 0.5rem 0.3rem;
    scroll-behavior: smooth;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
    cursor: grab;
    flex: 1;
}

.categorias-barra-scroll::-webkit-scrollbar {
    display: none;
}

.categoria-barra-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #1f2937;
    text-decoration: none;
    font-size: 0.85rem;
    padding: 0.4rem 0.8rem;
    border-radius: 30px;
    white-space: nowrap;
    transition: all 0.3s ease;
    font-weight: 500;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.categoria-barra-item:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102,126,234,0.3);
}

.categoria-barra-icono {
    font-size: 1.5rem;
}

.categoria-barra-nombre {
    font-size: 1.1rem;
}

.categoria-barra-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    font-size: 1.2rem;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1f2937;
}

.categoria-barra-btn:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(102,126,234,0.3);
}

.categoria-barra-btn-izq {
    left: -10px;
}

.categoria-barra-btn-der {
    right: -10px;
}

.amazon-carrusel {
    margin: 0 0 0.5rem 0;
}

.amazon-seccion {
    margin: 2rem 0;
}

.amazon-titulo-seccion {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1.2rem;
    padding-bottom: 0.3rem;
    border-bottom: 2px solid #e5e7eb;
}

.amazon-tarjetas-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
}

.amazon-tarjeta {
    cursor: pointer;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    position: relative;
    aspect-ratio: 16 / 10;
}

.amazon-tarjeta:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 40px rgba(0,0,0,0.2);
}

.amazon-tarjeta-imagen {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    position: relative;
    transition: transform 0.6s ease;
}

.amazon-tarjeta:hover .amazon-tarjeta-imagen {
    transform: scale(1.05);
}

.amazon-tarjeta-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    text-align: center;
    color: white;
    transition: all 0.3s ease;
}

.amazon-tarjeta:hover .amazon-tarjeta-overlay {
    backdrop-filter: blur(2px);
}

.amazon-tarjeta-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    color: white;
    padding: 0.15rem 0.7rem;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.3);
    letter-spacing: 0.5px;
}

.amazon-tarjeta-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    margin-bottom: 0.2rem;
}

.amazon-tarjeta-icono {
    font-size: 2.5rem;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.amazon-tarjeta-titulo {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.amazon-tarjeta-miniaturas {
    display: flex;
    gap: 0.5rem;
    margin: 0.3rem 0 0.6rem;
    justify-content: center;
    flex-wrap: nowrap;
}

.tarjeta-miniatura {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3);
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    background: rgba(255,255,255,0.1);
    flex-shrink: 0;
}

.tarjeta-miniatura:hover {
    transform: scale(1.15);
    border-color: white;
}

.tarjeta-miniatura-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border: 2px dashed rgba(255,255,255,0.3);
    flex-shrink: 0;
}

.amazon-tarjeta-boton {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    color: white;
    padding: 0.3rem 1.2rem;
    border-radius: 30px;
    font-size: 1.1rem;
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s ease;
    cursor: pointer;
    text-shadow: 0 1px 4px rgba(0,0,0,0.2);
}

.amazon-tarjeta:hover .amazon-tarjeta-boton {
    background: white;
    color: #1f2937;
    transform: scale(1.05);
}

.amazon-tarjeta-proximamente {
    opacity: 0.8;
}

.amazon-tarjeta-proximamente:hover {
    opacity: 1;
}

.amazon-tarjeta-proximamente .amazon-tarjeta-boton {
    background: rgba(255,255,255,0.1);
    cursor: default;
}

.amazon-productos-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.2rem;
}

.amazon-producto-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.amazon-producto-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    border-color: var(--primary);
}

.amazon-producto-imagen {
    padding: 1rem;
    background: #fafbfc;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 180px;
}

.amazon-producto-imagen img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.amazon-producto-info {
    padding: 0.8rem 1rem 1rem;
}

.amazon-producto-nombre {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.3rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.3;
}

.amazon-producto-precio {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0.3rem 0;
}

.amazon-precio-original {
    font-size: 0.75rem;
    color: #ef4444;
    text-decoration: line-through;
}

.amazon-precio-final {
    font-size: 1rem;
    font-weight: 700;
    color: #10b981;
}

.amazon-precio-normal {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
}

.amazon-producto-rating {
    font-size: 0.75rem;
    color: #6b7280;
}

.amazon-btn-ver-todos {
    display: inline-block;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 0.8rem 2.5rem;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
}

.amazon-btn-ver-todos:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102,126,234,0.5);
}

.notificacion-proximamente {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0,0,0,0.85);
    color: white;
    padding: 2rem 3rem;
    border-radius: 16px;
    font-size: 1.5rem;
    font-weight: 600;
    z-index: 9999;
    text-align: center;
    animation: fadeInUp 0.4s ease;
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.2);
    cursor: pointer;
}

.notificacion-proximamente span {
    display: block;
    font-size: 3rem;
    margin-bottom: 0.5rem;
}

.notificacion-proximamente small {
    display: block;
    font-size: 0.8rem;
    opacity: 0.7;
    margin-top: 0.5rem;
}
    
/* Ocultar solo la tarjeta 6 */
.ocultar-tarjeta-6 {
    display: none !important;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translate(-50%, -30%); }
    to { opacity: 1; transform: translate(-50%, -50%); }
}

@media (max-width: 992px) {
    .amazon-tarjetas-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .amazon-productos-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Menores de 750px - 2 columnas, imágenes pequeñas y pegadas */
@media (max-width: 750px) {
    .amazon-tarjetas-grid {
        grid-template-columns: repeat(2, 1fr);
        max-width: 100% !important;
        padding: 0 0.1rem;
        gap: 0.3rem !important;
    }
    
    .amazon-tarjeta {
        aspect-ratio: 16 / 9;
        border-radius: 12px;
    }
    
    .amazon-tarjeta-icono {
        font-size: 2rem;
    }
    
    .amazon-tarjeta-titulo {
        font-size: 1.2rem;
    }
    
    /* UNIFICADO: ambas clases mismo tamaño */
    .tarjeta-miniatura,
    .tarjeta-miniatura-placeholder {
        width: 70px !important;
        height: 75px !important;
        flex-shrink: 0 !important;
        border-radius: 50% !important;
    }
    
    /* Mantener estilos específicos */
    .tarjeta-miniatura {
        object-fit: cover !important;
    }
    
    .tarjeta-miniatura-placeholder {
        font-size: 2rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        border: 2px dashed rgba(255,255,255,0.3) !important;
    }
    
    .amazon-tarjeta-badge {
    font-size: 0.6rem !important;
    padding: 0.05rem 0.4rem !important;
    top: 5px !important;
    right: 5px !important;
}
    
    .amazon-tarjeta-boton {
        font-size: 1rem;
        padding: 0.15rem 0.8rem;
    }
    
    .amazon-tarjeta-miniaturas {
        gap: 0.2rem;
    }
}
    
/* Menores de 670px - 2 columnas, imágenes pequeñas */
@media (max-width: 670px) {
    .categoria-barra-btn {
        display: none;
    }
    .categorias-barra-scroll {
        gap: 0.8rem;
        padding: 0.3rem 0.2rem;
    }
    .categoria-barra-item {
        font-size: 1rem;
        padding: 0.3rem 0.6rem;
        gap: 0.3rem;
    }
    .categoria-barra-icono {
        font-size: 1rem;
    }
    .categoria-barra-nombre {
        font-size: 0.8rem;
    }
    
    /* TARJETAS - 2 COLUMNAS */
    .amazon-tarjetas-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        max-width: 100% !important;
        padding: 0 0.3rem !important;
        gap: 0.4rem !important;
        margin: 0 auto !important;
    }
    
    .amazon-tarjeta {
        aspect-ratio: 16 / 10;
        max-width: 100% !important;
        width: 100% !important;
        border-radius: 10px;
    }
    
    .amazon-tarjeta-icono {
        font-size: 2.2rem;
    }
    .amazon-tarjeta-titulo {
        font-size: 1.5rem;
    }
    
    .amazon-tarjeta-miniaturas {
        gap: 0.2rem !important;
    }
    
    /* UNIFICADO: miniaturas y placeholders mismo tamaño */
    .tarjeta-miniatura,
    .tarjeta-miniatura-placeholder {
        width: 65px !important;
        height: 70px !important;
        flex-shrink: 0 !important;
        border-radius: 50% !important;
    }
    
    .tarjeta-miniatura {
        object-fit: cover !important;
    }
    
    .tarjeta-miniatura-placeholder {
        font-size: 2rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    .amazon-tarjeta-badge {
        font-size: 0.5rem !important;
        padding: 0.05rem 0.4rem;
        top: 5px;
        right: 5px;
    }
    .amazon-tarjeta-boton {
        font-size: 0.7rem;
        padding: 0.15rem 0.6rem;
    }
    .amazon-productos-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.8rem;
    }
    .amazon-producto-imagen {
        height: 120px;
        padding: 0.5rem;
    }
    .amazon-producto-nombre {
        font-size: 0.75rem;
    }
    .amazon-precio-final,
    .amazon-precio-normal {
        font-size: 0.85rem;
    }
    .amazon-titulo-seccion {
        font-size: 1.2rem;
    }
}
    
/* Menores de 599px - 1 columnas, imágenes pequeñas y pegadas */
@media (max-width: 599px) {
     .categoria-barra-btn {
        display: none;
    }
    .categorias-barra-scroll {
        gap: 0.8rem;
        padding: 0.3rem 0.2rem;
    }
    .categoria-barra-item {
        font-size: 1rem;
        padding: 0.3rem 0.6rem;
        gap: 0.3rem;
    }
    .categoria-barra-icono {
        font-size: 1rem;
    }
    .categoria-barra-nombre {
        font-size: 0.8rem;
    }
    
    /* TARJETAS - 1 COLUMNA */
    .amazon-tarjetas-grid {
        grid-template-columns: 1fr !important;
        max-width: 100% !important;
        padding: 0 0.5rem !important;
        gap: 0.8rem !important;
        margin: 0 auto;
    }
    
    .amazon-tarjeta {
        aspect-ratio: 16 / 10;
        max-width: 100%;
    }
    
    .amazon-tarjeta-icono {
        font-size: 2.8rem;
    }
    .amazon-tarjeta-titulo {
        font-size: 2rem;
    }
    
    .amazon-tarjeta-miniaturas {
        gap: 0.3rem !important;  /* ← Aumenta este valor */
    }
    
    /* UNIFICADO: miniaturas y placeholders mismo tamaño */
    .tarjeta-miniatura,
    .tarjeta-miniatura-placeholder {
        width: 100px !important;
        height: 110px !important;
        flex-shrink: 0 !important;
        border-radius: 50% !important;
    }
    
    .tarjeta-miniatura {
        object-fit: cover !important;
    }
    
    .tarjeta-miniatura-placeholder {
        font-size: 2.6rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    .amazon-tarjeta-badge {
        font-size: 1rem !important;
        padding: 0.1rem 0.5rem;
        top: 8px;
        right: 8px;
    }
    .amazon-tarjeta-boton {
        font-size: 1rem;
        padding: 0.25rem 1rem;
    }
    .amazon-productos-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.8rem;
    }
    .amazon-producto-imagen {
        height: 120px;
        padding: 0.5rem;
    }
    .amazon-producto-nombre {
        font-size: 0.75rem;
    }
    .amazon-precio-final,
    .amazon-precio-normal {
        font-size: 0.85rem;
    }
    .amazon-titulo-seccion {
        font-size: 1.2rem;
    }
}

/* Menores de 499px - 1 columnas, imágenes pequeñas y pegadas */
@media (max-width: 480px) {
    .categorias-barra-scroll {
        gap: 0.5rem;
    }
    .categoria-barra-item {
        font-size: 1rem;
        padding: 0.25rem 0.5rem;
    }
    .categoria-barra-icono {
        font-size: 0.8rem;
    }
    .categoria-barra-nombre {
        font-size: 0.6rem;
    }
    
    /* TARJETAS - 1 COLUMNA */
    .amazon-tarjetas-grid {
        grid-template-columns: 1fr !important;
        max-width: 100% !important;
        padding: 0 0.5rem !important;
        gap: 0.8rem !important;
    }
    
    .amazon-tarjeta {
        aspect-ratio: 16 / 9;
        max-width: 100%;
    }
    
    .amazon-tarjeta-miniaturas {
        gap: 0.3rem;
    }
    
    /* UNIFICADO: miniaturas y placeholders mismo tamaño */
    .tarjeta-miniatura,
    .tarjeta-miniatura-placeholder {
        width: 65px !important;
        height: 65px !important;
        flex-shrink: 0 !important;
        border-radius: 50% !important;
    }
    
    .tarjeta-miniatura {
        object-fit: cover !important;
    }
    
    .tarjeta-miniatura-placeholder {
        font-size: 2rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    .amazon-tarjeta-icono {
        font-size: 2.5rem;
    }
    .amazon-tarjeta-titulo {
        font-size: 1.6rem;
    }
    .amazon-tarjeta-badge {
        font-size: 0.7rem !important;
        padding: 0.1rem 0.4rem;
        top: 6px;
        right: 6px;
    }
    .amazon-tarjeta-boton {
        font-size: 0.9rem;
        padding: 0.2rem 0.8rem;
    }
    .amazon-productos-grid {
        grid-template-columns: 1fr;
    }
    .amazon-producto-imagen {
        height: 150px;
    }
}

/* Menores de 390px - 1 columna, imágenes pequeñas y más ancho */
@media (max-width: 390px) {
    .categorias-barra-scroll {
        gap: 0.5rem;
    }
    .categoria-barra-item {
        font-size: 1rem;
        padding: 0.25rem 0.5rem;
    }
    .categoria-barra-icono {
        font-size: 0.8rem;
    }
    .categoria-barra-nombre {
        font-size: 0.6rem;
    }
    
    /* TARJETAS - 1 COLUMNA, MÁS ANCHO */
    .amazon-tarjetas-grid {
        grid-template-columns: 1fr !important;
        max-width: 100% !important;
        padding: 0 0.1rem !important;  /* ← Mantiene margen */
        gap: 0.6rem !important;
        width: 100% !important;
        margin: 0 auto !important;
    }
    
    .amazon-tarjeta {
        aspect-ratio: 20 / 16;
        max-width: 100% !important;
        width: 100% !important;
        border-radius: 12px;
    }
    
    .amazon-tarjeta-miniaturas {
        gap: 0.3rem;
    }
    
    /* UNIFICADO: miniaturas y placeholders mismo tamaño */
    .tarjeta-miniatura,
    .tarjeta-miniatura-placeholder {
        width: 60px !important;
        height: 65px !important;
        flex-shrink: 0 !important;
        border-radius: 50% !important;
    }
    
    .tarjeta-miniatura {
        object-fit: cover !important;
    }
    
    .tarjeta-miniatura-placeholder {
        font-size: 2rem !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }
    
    .amazon-tarjeta-icono {
        font-size: 2rem;
    }
    .amazon-tarjeta-titulo {
        font-size: 1.1rem;
    }
    .amazon-tarjeta-badge {
        font-size: 0.9rem !important;
        padding: 0.1rem 0.4rem;
        top: 6px;
        right: 6px;
    }
    .amazon-tarjeta-boton {
        font-size: 0.9rem;
        padding: 0.2rem 0.8rem;
    }
    .amazon-productos-grid {
        grid-template-columns: 1fr;
    }
    .amazon-producto-imagen {
        height: 150px;
    }
}
    
    
@media (max-width: 768px) {
    .amazon-productos-grid {
        display: none !important;
    }
    .carrusel-wrapper-productos {
        display: block !important;
    }
    .carrusel-btn-productos {
        display: none !important;
    }
    .amazon-producto-card-carrusel {
        width: 150px;
        flex-shrink: 0;
    }
    .amazon-producto-imagen-carrusel {
        height: 120px;
        padding: 0.5rem;
    }
    .amazon-producto-nombre-carrusel {
        font-size: 0.7rem;
    }
    .amazon-producto-precio-carrusel .amazon-precio-final,
    .amazon-producto-precio-carrusel .amazon-precio-normal {
        font-size: 0.8rem;
    }
}

@media (max-width: 400px) {
    .amazon-producto-card-carrusel {
        width: 130px;
    }
    .amazon-producto-imagen-carrusel {
        height: 100px;
        padding: 0.4rem;
    }
    .amazon-producto-nombre-carrusel {
        font-size: 0.65rem;
    }
    .amazon-producto-precio-carrusel .amazon-precio-final,
    .amazon-producto-precio-carrusel .amazon-precio-normal {
        font-size: 0.7rem;
    }
}

.carrusel-wrapper-productos {
    position: relative;
    margin: 0 -0.5rem;
    display: none;
}

.carrusel-productos {
    display: flex;
    gap: 1rem;
    overflow-x: auto;
    scroll-behavior: smooth;
    padding: 0.5rem 0.5rem 1rem;
    scrollbar-width: none;
    -ms-overflow-style: none;
    cursor: grab;
}

.carrusel-productos::-webkit-scrollbar {
    display: none;
}

.carrusel-btn-productos {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.95);
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    font-size: 1.2rem;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1f2937;
}

.carrusel-btn-productos:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.carrusel-btn-productos-izq {
    left: -5px;
}

.carrusel-btn-productos-der {
    right: -5px;
}

.amazon-producto-card-carrusel {
    flex: 0 0 auto;
    width: 180px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
}

.amazon-producto-card-carrusel:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    border-color: var(--primary);
}

.amazon-producto-imagen-carrusel {
    padding: 0.8rem;
    background: #fafbfc;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 150px;
}

.amazon-producto-imagen-carrusel img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.amazon-producto-info-carrusel {
    padding: 0.5rem 0.8rem 0.8rem;
}

.amazon-producto-nombre-carrusel {
    font-size: 0.8rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0 0 0.3rem 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.3;
}

.amazon-producto-precio-carrusel {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.amazon-producto-precio-carrusel .amazon-precio-original {
    font-size: 0.7rem;
    color: #ef4444;
    text-decoration: line-through;
}

.amazon-producto-precio-carrusel .amazon-precio-final {
    font-size: 0.9rem;
    font-weight: 700;
    color: #10b981;
}

.amazon-producto-precio-carrusel .amazon-precio-normal {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--primary);
}

.site-header,
header {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    z-index: 99999 !important;
}

.side-menu {
    z-index: 100002 !important;
}

.categorias-right-menu {
    z-index: 100004 !important;
}

.cliente-bottom-menu {
    z-index: 100003 !important;
}

.menu-overlay {
    z-index: 100001 !important;
}

.right-overlay {
    z-index: 100003 !important;
}

.bottom-overlay {
    z-index: 100002 !important;
}

.side-menu *,
.categorias-right-menu *,
.cliente-bottom-menu *,
.menu-overlay *,
.right-overlay *,
.bottom-overlay * {
    z-index: auto !important;
}

main,
.container,
.categorias-barra-blanca,
.amazon-seccion,
.amazon-tarjetas-grid,
.amazon-productos-grid,
.carrusel-wrapper-productos,
.carrusel-productos,
.productos-grid,
.productos-section,
.dashboard-section {
    position: relative !important;
}

body {
    padding-top: 90px !important;
    overflow-x: hidden !important;
}
    
    /* ============================================ */
/* CARRUSEL SIEMPRE VISIBLE (PC Y MÓVIL)       */
/* ============================================ */

/* El carrusel siempre visible */
.carrusel-siempre {
    display: block !important;
}

/* Botones de navegación visibles en PC, ocultos en móvil */
.carrusel-btn-productos {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.95);
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    font-size: 1.2rem;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1f2937;
}

.carrusel-btn-productos:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.carrusel-btn-productos-izq {
    left: -5px;
}

.carrusel-btn-productos-der {
    right: -5px;
}

/* Ajustes para PC */
@media (min-width: 769px) {
    .amazon-producto-card-carrusel {
        width: 200px;
    }
    
    .amazon-producto-imagen-carrusel {
        height: 160px;
    }
}

/* Ajustes para móvil */
@media (max-width: 768px) {
    .amazon-producto-card-carrusel {
        width: 150px;
    }
    
    .amazon-producto-imagen-carrusel {
        height: 120px;
        padding: 0.5rem;
    }
    
    .amazon-producto-nombre-carrusel {
        font-size: 0.7rem;
    }
    
    .amazon-producto-precio-carrusel .amazon-precio-final,
    .amazon-producto-precio-carrusel .amazon-precio-normal {
        font-size: 0.8rem;
    }
}

@media (max-width: 400px) {
    .amazon-producto-card-carrusel {
        width: 130px;
    }
    .amazon-producto-imagen-carrusel {
        height: 100px;
        padding: 0.4rem;
    }
    .amazon-producto-nombre-carrusel {
        font-size: 0.65rem;
    }
    .amazon-producto-precio-carrusel .amazon-precio-final,
    .amazon-producto-precio-carrusel .amazon-precio-normal {
        font-size: 0.7rem;
    }
}

/* ============================================ */
/* CARRUSEL SIN CARRITO (TARJETAS DESTACADAS)  */
/* ============================================ */

/* El carrusel siempre visible */
.carrusel-siempre {
    display: block !important;
}

/* Contenedor del scroll horizontal */
.carrusel-productos {
    display: flex !important;
    gap: 1rem;
    overflow-x: auto !important;
    overflow-y: visible !important;
    scroll-behavior: smooth;
    padding: 0.5rem 0.5rem 1rem;
    scrollbar-width: none;
    -ms-overflow-style: none;
    cursor: grab;
}

.carrusel-productos::-webkit-scrollbar {
    display: none;
}

/* ===== TARJETA SIN CARRITO ===== */
.producto-card-sin-carrito {
    flex: 0 0 auto;
    width: 200px;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    border: 1px solid #e5e7eb;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    position: relative;
    display: flex;
    flex-direction: column;
}

.producto-card-sin-carrito:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.12);
    border-color: var(--primary);
}

/* Imagen */
.producto-card-sin-carrito .producto-imagen-wrapper {
    padding: 1rem;
    background: #fafbfc;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 150px;
}

.producto-card-sin-carrito .producto-imagen {
    max-width: 100%;
    max-height: 120px;
    object-fit: contain;
}

/* Info */
.producto-info-sin-carrito {
    padding: 0.8rem 1rem 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.producto-info-sin-carrito .producto-marca {
    font-size: 0.65rem;
    color: #6b7280;
    text-transform: uppercase;
    margin-bottom: 0.3rem;
}

.producto-info-sin-carrito .producto-nombre {
    font-size: 0.85rem;
    font-weight: 600;
    color: #1f2937;
    line-height: 1.3;
    margin-bottom: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.producto-info-sin-carrito .producto-precio {
    margin-bottom: 0.5rem;
}

/* Enlace "Ver producto" */
.producto-ver {
    margin-top: auto;
    padding-top: 0.5rem;
}

.ver-producto {
    display: inline-block;
    color: var(--primary);
    font-weight: 600;
    font-size: 0.8rem;
    transition: all 0.3s ease;
}

.producto-card-sin-carrito:hover .ver-producto {
    color: var(--secondary);
    transform: translateX(5px);
}

/* ===== BOTONES DE NAVEGACIÓN ===== */
.carrusel-btn-productos {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.95);
    border: 1px solid #e5e7eb;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    font-size: 1.2rem;
    cursor: pointer;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1f2937;
}

.carrusel-btn-productos:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.carrusel-btn-productos-izq {
    left: -5px;
}

.carrusel-btn-productos-der {
    right: -5px;
}

/* ============================================ */
/* RESPONSIVE                                   */
/* ============================================ */

/* PC: botones visibles */
@media (min-width: 769px) {
    .carrusel-btn-productos {
        display: flex !important;
    }
    .producto-card-sin-carrito {
        width: 200px;
    }
}

/* Móvil: botones más pequeños y visibles */
@media (max-width: 768px) {
    .carrusel-btn-productos {
        display: flex !important;
        width: 28px !important;
        height: 28px !important;
        font-size: 0.8rem !important;
        background: rgba(255,255,255,0.9) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important;
    }
    .carrusel-btn-productos-izq {
        left: 2px !important;
    }
    .carrusel-btn-productos-der {
        right: 2px !important;
    }
}

@media (max-width: 400px) {
    .carrusel-btn-productos {
        width: 24px !important;
        height: 24px !important;
        font-size: 0.65rem !important;
    }
    .carrusel-btn-productos-izq {
        left: 0px !important;
    }
    .carrusel-btn-productos-der {
        right: 0px !important;
    }
}
    
/* Móvil: botones ocultos (arrastre) */
@media (max-width: 768px) {
    .producto-card-sin-carrito {
        width: 150px;
    }
    .producto-card-sin-carrito .producto-imagen-wrapper {
        min-height: 110px;
        padding: 0.5rem;
        width: 100% !important;  /* ← NUEVO: ocupa todo el ancho */
    }
    .producto-card-sin-carrito .producto-imagen {
        max-height: 90px;
        width: 100% !important;  /* ← NUEVO: ocupa todo el ancho */
        object-fit: contain;
    }
    .producto-info-sin-carrito .producto-nombre {
        font-size: 0.75rem;
    }
    .producto-info-sin-carrito .producto-precio .precio-final,
    .producto-info-sin-carrito .producto-precio .precio-normal {
        font-size: 0.85rem;
    }
}

@media (max-width: 400px) {
    .producto-card-sin-carrito {
        width: 130px;
    }
    .producto-card-sin-carrito .producto-imagen-wrapper {
        min-height: 90px;
        padding: 0.4rem;
        width: 100% !important;  /* ← NUEVO: ocupa todo el ancho */
    }
    .producto-card-sin-carrito .producto-imagen {
        max-height: 70px;
        width: 100% !important;  /* ← NUEVO: ocupa todo el ancho */
        object-fit: contain;
    }
    .ver-producto {
        font-size: 0.7rem;
    }
}
    
    /* ============================================
   BUSCADOR SHOP - RESPONSIVE
   ============================================ */
@media (max-width: 768px) {
    #buscadorShopForm {
        gap: 0.4rem !important;
    }
    
    #buscadorShopInput {
        font-size: 0.85rem !important;
        padding: 0.5rem 0.8rem !important;
        border-radius: 25px !important;
    }
    
    #buscadorShopForm button {
        font-size: 0.75rem !important;
        padding: 0.5rem 0.9rem !important;
        border-radius: 25px !important;
        white-space: nowrap !important;
    }
}

@media (max-width: 400px) {
    #buscadorShopForm button {
        font-size: 0.65rem !important;
        padding: 0.4rem 0.7rem !important;
    }
    
    #buscadorShopInput {
        font-size: 0.75rem !important;
        padding: 0.4rem 0.6rem !important;
    }
}
</style>

<!-- ============================================ -->
<!-- SCRIPTS ESPECÍFICOS DE SHOP.PHP              -->
<!-- ============================================ -->
<script>
// ============================================
// CARRUSEL DE CATEGORÍAS (SOLO PARA SHOP.PHP)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const scrollContainer = document.getElementById('categoriasScroll');
    const btnIzq = document.getElementById('catBtnIzq');
    const btnDer = document.getElementById('catBtnDer');
    
    if (!scrollContainer) return;
    
    function desplazar(direccion) {
        const scrollAmount = scrollContainer.clientWidth * 0.7;
        const target = direccion === 'izq' 
            ? scrollContainer.scrollLeft - scrollAmount 
            : scrollContainer.scrollLeft + scrollAmount;
        scrollContainer.scrollTo({ left: target, behavior: 'smooth' });
    }
    
    if (btnIzq) {
        btnIzq.addEventListener('click', function(e) {
            e.preventDefault();
            desplazar('izq');
        });
    }
    
    if (btnDer) {
        btnDer.addEventListener('click', function(e) {
            e.preventDefault();
            desplazar('der');
        });
    }
    
    let isDown = false;
    let startX;
    let scrollLeft;
    
    scrollContainer.addEventListener('mousedown', function(e) {
        isDown = true;
        startX = e.pageX - scrollContainer.offsetLeft;
        scrollLeft = scrollContainer.scrollLeft;
        scrollContainer.style.cursor = 'grabbing';
    });
    
    scrollContainer.addEventListener('mouseleave', function() {
        isDown = false;
        scrollContainer.style.cursor = 'grab';
    });
    
    scrollContainer.addEventListener('mouseup', function() {
        isDown = false;
        scrollContainer.style.cursor = 'grab';
    });
    
    scrollContainer.addEventListener('mousemove', function(e) {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - scrollContainer.offsetLeft;
        const walk = (x - startX) * 1.5;
        scrollContainer.scrollLeft = scrollLeft - walk;
    });
    
    let touchStartX = 0;
    let touchScrollLeft = 0;
    
    scrollContainer.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].pageX - scrollContainer.offsetLeft;
        touchScrollLeft = scrollContainer.scrollLeft;
    }, { passive: true });
    
    scrollContainer.addEventListener('touchmove', function(e) {
        const x = e.touches[0].pageX - scrollContainer.offsetLeft;
        const walk = (x - touchStartX) * 1.5;
        scrollContainer.scrollLeft = touchScrollLeft - walk;
    }, { passive: true });
    
    function actualizarVisibilidadBotones() {
        if (!btnIzq || !btnDer) return;
        const maxScroll = scrollContainer.scrollWidth - scrollContainer.clientWidth;
        
        if (maxScroll <= 0) {
            btnIzq.style.display = 'none';
            btnDer.style.display = 'none';
            return;
        }
        
        if (window.innerWidth <= 768) {
            btnIzq.style.display = 'none';
            btnDer.style.display = 'none';
            return;
        }
        
        btnIzq.style.display = 'flex';
        btnDer.style.display = 'flex';
        
        btnIzq.style.opacity = scrollContainer.scrollLeft <= 10 ? '0.3' : '1';
        btnDer.style.opacity = scrollContainer.scrollLeft >= maxScroll - 10 ? '0.3' : '1';
    }
    
    scrollContainer.addEventListener('scroll', actualizarVisibilidadBotones);
    window.addEventListener('resize', actualizarVisibilidadBotones);
    
    setTimeout(actualizarVisibilidadBotones, 100);
});

// ============================================
// PRÓXIMAMENTE (SOLO PARA SHOP.PHP)
// ============================================
function mostrarProximamente() {
    if (document.querySelector('.notificacion-proximamente')) {
        return;
    }
    
    const notif = document.createElement('div');
    notif.className = 'notificacion-proximamente';
    notif.innerHTML = `
        <span>🚧</span>
        Próximamente
        <small>Estamos preparando esta tienda para ti</small>
    `;
    document.body.appendChild(notif);
    
    notif.addEventListener('click', function() {
        this.remove();
    });
    
    setTimeout(function() {
        if (notif.parentNode) {
            notif.remove();
        }
    }, 3000);
}

// ============================================
// CARRUSEL DE PRODUCTOS DESTACADOS (SHOP.PHP)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const carrusel = document.getElementById('productosCarrusel');
    const btnIzq = document.getElementById('prodBtnIzq');
    const btnDer = document.getElementById('prodBtnDer');
    
    if (!carrusel) return;
    
    function desplazarProductos(direccion) {
        const scrollAmount = carrusel.clientWidth * 0.7;
        const target = direccion === 'izq' 
            ? carrusel.scrollLeft - scrollAmount 
            : carrusel.scrollLeft + scrollAmount;
        carrusel.scrollTo({ left: target, behavior: 'smooth' });
    }
    
    if (btnIzq) {
        btnIzq.addEventListener('click', function(e) {
            e.preventDefault();
            desplazarProductos('izq');
        });
    }
    
    if (btnDer) {
        btnDer.addEventListener('click', function(e) {
            e.preventDefault();
            desplazarProductos('der');
        });
    }
    
    let isDown = false;
    let startX;
    let scrollLeft;
    
    carrusel.addEventListener('mousedown', function(e) {
        if (window.innerWidth > 768) return;
        isDown = true;
        startX = e.pageX - carrusel.offsetLeft;
        scrollLeft = carrusel.scrollLeft;
        carrusel.style.cursor = 'grabbing';
    });
    
    carrusel.addEventListener('mouseleave', function() {
        isDown = false;
        carrusel.style.cursor = 'grab';
    });
    
    carrusel.addEventListener('mouseup', function() {
        isDown = false;
        carrusel.style.cursor = 'grab';
    });
    
    carrusel.addEventListener('mousemove', function(e) {
        if (!isDown || window.innerWidth > 768) return;
        e.preventDefault();
        const x = e.pageX - carrusel.offsetLeft;
        const walk = (x - startX) * 1.5;
        carrusel.scrollLeft = scrollLeft - walk;
    });
    
    let touchStartX = 0;
    let touchScrollLeft = 0;
    
    carrusel.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].pageX - carrusel.offsetLeft;
        touchScrollLeft = carrusel.scrollLeft;
    }, { passive: true });
    
    carrusel.addEventListener('touchmove', function(e) {
        const x = e.touches[0].pageX - carrusel.offsetLeft;
        const walk = (x - touchStartX) * 1.5;
        carrusel.scrollLeft = touchScrollLeft - walk;
    }, { passive: true });
});
            // ===== AUTOPLAY PARA CARRUSEL "MÁS VISTOS" =====
    let autoplayIntervalMasVistos;
    const autoplayDelayMasVistos = 1500; // 3 segundos
    
    function iniciarAutoplayMasVistos() {
        if (autoplayIntervalMasVistos) clearInterval(autoplayIntervalMasVistos);
        autoplayIntervalMasVistos = setInterval(function() {
            const c = document.getElementById('productosCarrusel');
            if (c) {
                // Calcular el ancho de una tarjeta
                let scrollAmount;
                const primeraTarjeta = c.querySelector('.producto-card-sin-carrito, .amazon-producto-card-carrusel');
                if (primeraTarjeta) {
                    scrollAmount = primeraTarjeta.offsetWidth + 16; // ancho + gap
                } else {
                    scrollAmount = window.innerWidth <= 768 ? 150 + 16 : 200 + 16;
                }
                
                const maxScroll = c.scrollWidth - c.clientWidth;
                
                if (c.scrollLeft >= maxScroll - 10) {
                    c.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    c.scrollTo({ left: c.scrollLeft + scrollAmount, behavior: 'smooth' });
                }
            }
        }, autoplayDelayMasVistos);
    }
    
    function detenerAutoplayMasVistos() {
        if (autoplayIntervalMasVistos) {
            clearInterval(autoplayIntervalMasVistos);
            autoplayIntervalMasVistos = null;
        }
    }
    
    // Iniciar autoplay
    const carruselMasVistos = document.getElementById('productosCarrusel');
    if (carruselMasVistos) {
        iniciarAutoplayMasVistos();
        
        // Pausar al pasar el mouse
        carruselMasVistos.addEventListener('mouseenter', detenerAutoplayMasVistos);
        carruselMasVistos.addEventListener('mouseleave', iniciarAutoplayMasVistos);
        
        // Pausar en móvil (touch)
        carruselMasVistos.addEventListener('touchstart', detenerAutoplayMasVistos);
        carruselMasVistos.addEventListener('touchend', function() {
            setTimeout(iniciarAutoplayMasVistos, 5000);
        });
    }
    
// ============================================
// CARRUSEL DE PRODUCTOS POR TIENDA CON AUTOPLAY
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const carrusel = document.getElementById('productosTiendaCarrusel');
    const btnIzq = document.getElementById('prodTiendaBtnIzq');
    const btnDer = document.getElementById('prodTiendaBtnDer');
    
    if (!carrusel) return;
    
    let autoplayInterval;
    const autoplayDelay = 1500; // 3 segundos
    
    // ===== FUNCIÓN PARA DESPLAZAR =====
    function desplazarTienda(direccion) {
        const scrollAmount = carrusel.clientWidth * 0.7;
        const target = direccion === 'izq' 
            ? carrusel.scrollLeft - scrollAmount 
            : carrusel.scrollLeft + scrollAmount;
        carrusel.scrollTo({ left: target, behavior: 'smooth' });
    }
    
       // ===== FUNCIÓN PARA INICIAR AUTOPLAY =====
    function iniciarAutoplayTienda() {
        if (autoplayInterval) clearInterval(autoplayInterval);
        autoplayInterval = setInterval(function() {
            if (carrusel) {
                // Calcular el ancho de una tarjeta
                let scrollAmount;
                const primeraTarjeta = carrusel.querySelector('.producto-card-sin-carrito');
                if (primeraTarjeta) {
                    scrollAmount = primeraTarjeta.offsetWidth + 16; // ancho + gap
                } else {
                    scrollAmount = window.innerWidth <= 768 ? 150 + 16 : 200 + 16;
                }
                
                const maxScroll = carrusel.scrollWidth - carrusel.clientWidth;
                
                // Si llegamos al final, volver al principio
                if (carrusel.scrollLeft >= maxScroll - 10) {
                    carrusel.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    carrusel.scrollTo({ left: carrusel.scrollLeft + scrollAmount, behavior: 'smooth' });
                }
            }
        }, autoplayDelay);
    }
    
    // ===== FUNCIÓN PARA DETENER AUTOPLAY =====
    function detenerAutoplayTienda() {
        if (autoplayInterval) {
            clearInterval(autoplayInterval);
            autoplayInterval = null;
        }
    }
    
    // ===== EVENTOS DE LOS BOTONES =====
    if (btnIzq) {
        btnIzq.addEventListener('click', function(e) {
            e.preventDefault();
            detenerAutoplayTienda();
            desplazarTienda('izq');
            setTimeout(iniciarAutoplayTienda, 5000);
        });
    }
    
    if (btnDer) {
        btnDer.addEventListener('click', function(e) {
            e.preventDefault();
            detenerAutoplayTienda();
            desplazarTienda('der');
            setTimeout(iniciarAutoplayTienda, 5000);
        });
    }
    
    // ===== INICIAR AUTOPLAY =====
    iniciarAutoplayTienda();
    
    // ===== PAUSAR AL PASAR EL MOUSE =====
    carrusel.addEventListener('mouseenter', detenerAutoplayTienda);
    carrusel.addEventListener('mouseleave', iniciarAutoplayTienda);
    
    // ===== PAUSAR EN MÓVIL (TOUCH) =====
    carrusel.addEventListener('touchstart', detenerAutoplayTienda);
    carrusel.addEventListener('touchend', function() {
        setTimeout(iniciarAutoplayTienda, 5000);
    });
    
    // ===== ARRASTRE CON MOUSE =====
    let isDown = false;
    let startX;
    let scrollLeft;
    
    carrusel.addEventListener('mousedown', function(e) {
        if (window.innerWidth > 768) return;
        detenerAutoplayTienda();
        isDown = true;
        startX = e.pageX - carrusel.offsetLeft;
        scrollLeft = carrusel.scrollLeft;
        carrusel.style.cursor = 'grabbing';
    });
    
    carrusel.addEventListener('mouseleave', function() {
        if (isDown) {
            isDown = false;
            carrusel.style.cursor = 'grab';
            setTimeout(iniciarAutoplayTienda, 5000);
        }
    });
    
    carrusel.addEventListener('mouseup', function() {
        if (isDown) {
            isDown = false;
            carrusel.style.cursor = 'grab';
            setTimeout(iniciarAutoplayTienda, 5000);
        }
    });
    
    carrusel.addEventListener('mousemove', function(e) {
        if (!isDown || window.innerWidth > 768) return;
        e.preventDefault();
        const x = e.pageX - carrusel.offsetLeft;
        const walk = (x - startX) * 1.5;
        carrusel.scrollLeft = scrollLeft - walk;
    });
    
    // ===== ARRASTRE TÁCTIL =====
    let touchStartX = 0;
    let touchScrollLeft = 0;
    
    carrusel.addEventListener('touchstart', function(e) {
        detenerAutoplayTienda();
        touchStartX = e.touches[0].pageX - carrusel.offsetLeft;
        touchScrollLeft = carrusel.scrollLeft;
    }, { passive: true });
    
    carrusel.addEventListener('touchmove', function(e) {
        const x = e.touches[0].pageX - carrusel.offsetLeft;
        const walk = (x - touchStartX) * 1.5;
        carrusel.scrollLeft = touchScrollLeft - walk;
    }, { passive: true });
    
    carrusel.addEventListener('touchend', function() {
        setTimeout(iniciarAutoplayTienda, 5000);
    }, { passive: true });
});
    
    // ============================================
// ANUNCIOS CON EFECTO FADE
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const slides = document.querySelectorAll('.anuncio-slide');
    let currentIndex = 0;
    
    if (slides.length === 0) return;
    
    function mostrarSiguiente() {
        // Ocultar todos
        slides.forEach(slide => {
            slide.style.opacity = '0';
        });
        
        // Mostrar el siguiente
        currentIndex = (currentIndex + 1) % slides.length;
        slides[currentIndex].style.opacity = '1';
    }
    
    // Cambiar cada 5 segundos
    setInterval(mostrarSiguiente, 5000);
});
    
    // ============================================
// BUSCADOR EN SHOP (REDIRIGE A INDEX)
// ============================================
function buscarEnIndex(event) {
    event.preventDefault();
    const input = document.getElementById('buscadorShopInput');
    const termino = input.value.trim();
    if (termino !== '') {
        window.location.href = 'resultados_busqueda_shop.php?buscar=' + encodeURIComponent(termino);
    } else {
        input.focus();
        input.style.borderColor = '#ef4444';
        input.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.2)';
        setTimeout(() => {
            input.style.borderColor = '#e2e8f0';
            input.style.boxShadow = 'none';
        }, 1500);
    }
}

// Permitir búsqueda con Enter (ya lo maneja el formulario)
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('buscadorShopInput');
    if (input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('buscadorShopForm').dispatchEvent(new Event('submit'));
            }
        });
    }
});
</script>

<?php $conn->close(); ?>
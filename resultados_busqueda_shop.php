<?php
// ============================================
// RESULTADOS DE BÚSQUEDA GLOBAL (DESDE SHOP)
// ============================================

define('SEGURO', true);

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
require_once 'includes/moneda_funciones.php';

$termino = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

if (empty($termino)) {
    header('Location: /shop.php');
    exit;
}

// ============================================
// SANITIZAR TÉRMINO DE BÚSQUEDA (SEGURIDAD)
// ============================================
$termino_raw = $termino;
$termino = htmlspecialchars($termino, ENT_QUOTES, 'UTF-8');
$termino = substr($termino, 0, 100);
$termino_like = '%' . $conn->real_escape_string($termino_raw) . '%';

// ============================================
// OBTENER CONFIGURACIÓN
// ============================================
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion");
while($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

$sitio_nombre = $config['sitio_nombre'] ?? 'TiendaVirtual';
$color_primario = $config['color_primario'] ?? '#667eea';
$color_secundario = $config['color_secundario'] ?? '#764ba2';

$cliente = obtenerClienteActual($conn);
$cliente_id = $cliente['id'];
$cliente_registrado = ($cliente_id > 0 && !empty($cliente['nombre']));

// ============================================
// DETERMINAR MONEDA DEL CLIENTE
// ============================================
if (!empty($cliente['moneda'])) {
    $moneda_actual = $cliente['moneda'];
} else {
    $ahora = time();
    $tiempo_cache = 300;
    if (!isset($_SESSION['pais_detectado']) || 
        !isset($_SESSION['pais_detectado_time']) || 
        ($ahora - $_SESSION['pais_detectado_time']) > $tiempo_cache) {
        $_SESSION['pais_detectado'] = getPaisPorIP();
        $_SESSION['pais_detectado_time'] = $ahora;
    }
    $moneda_actual = getMonedaPorPais($_SESSION['pais_detectado']);
}
$moneda_simbolo = getSimboloPorMoneda($moneda_actual);
$moneda_codigo = $moneda_actual;

// ============================================
// BUSCAR PRODUCTOS (PREPARED STATEMENT)
// ============================================
$sql_productos = "SELECT p.*, pr.nombre_tienda, pr.slug
                  FROM productos p
                  LEFT JOIN proveedores pr ON p.proveedor_id = pr.id
                  WHERE p.activo = 1 
                  AND (pr.activo = 1 OR p.proveedor_id IS NULL)
                  AND (p.nombre LIKE ? 
                       OR p.descripcion LIKE ? 
                       OR p.marca LIKE ?)
                  ORDER BY p.vistas DESC
                  LIMIT 50";
$stmt = $conn->prepare($sql_productos);
$stmt->bind_param("sss", $termino_like, $termino_like, $termino_like);
$stmt->execute();
$productos = $stmt->get_result();

// ============================================
// BUSCAR TIENDAS (PREPARED STATEMENT)
// ============================================
$sql_tiendas = "SELECT id, nombre_tienda, slug, imagen_url, descripcion
                FROM proveedores
                WHERE activo = 1 
                AND (nombre_tienda LIKE ? 
                     OR descripcion LIKE ?)
                ORDER BY vistas DESC
                LIMIT 20";
$stmt_tiendas = $conn->prepare($sql_tiendas);
$stmt_tiendas->bind_param("ss", $termino_like, $termino_like);
$stmt_tiendas->execute();
$tiendas = $stmt_tiendas->get_result();

include 'header_tienda.php';
?>

<!-- ============================================ -->
<!-- ESTILOS RESPONSIVE (dentro de la página)    -->
<!-- ============================================ -->
<style>
.resultados-shop {
    max-width: 1200px;
    margin: 0 auto;
    padding: 100px 1rem 2rem;
}

.resultados-header-shop {
    background: linear-gradient(135deg, <?php echo $color_primario; ?>, <?php echo $color_secundario; ?>);
    color: white;
    padding: 2rem 1.5rem;
    border-radius: 20px;
    margin-bottom: 2rem;
    text-align: center;
}

.resultados-header-shop h1 {
    font-size: 2rem;
    margin: 0 0 0.5rem 0;
}

.resultados-header-shop p {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0;
}

.seccion-titulo-shop {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1f2937;
    margin: 2rem 0 0.5rem 0;
    padding-bottom: 0.3rem;
    border-bottom: 2px solid #e5e7eb;
}

/* ===== TIENDAS ===== */
.tiendas-lista-shop {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin-top: 1rem;
}

.tienda-card-shop {
    background: white;
    border-radius: 12px;
    padding: 1.2rem;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.tienda-card-shop:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: <?php echo $color_primario; ?>;
}

.tienda-img-shop {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    background: #f1f5f9;
    flex-shrink: 0;
}

.tienda-info-shop {
    flex: 1;
}

.tienda-nombre-shop {
    font-weight: 600;
    color: #1f2937;
    font-size: 1rem;
}

.tienda-desc-shop {
    font-size: 0.8rem;
    color: #6b7280;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.tienda-boton-shop {
    font-size: 0.8rem;
    color: <?php echo $color_primario; ?>;
    font-weight: 600;
    white-space: nowrap;
}

/* ===== PRODUCTOS ===== */
.productos-grid-shop {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.producto-card-shop {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
    display: block;
}

.producto-card-shop:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    border-color: <?php echo $color_primario; ?>;
}

.producto-img-shop {
    padding: 1rem;
    background: #fafbfc;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 160px;
    overflow: hidden;
}

.producto-img-shop img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.producto-info-shop {
    padding: 0.8rem 1rem 1rem;
}

.producto-tienda-shop {
    font-size: 0.7rem;
    color: <?php echo $color_primario; ?>;
    font-weight: 500;
    margin-bottom: 0.3rem;
}

.producto-nombre-shop {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1f2937;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 0.3rem;
    line-height: 1.3;
}

.producto-precio-shop {
    font-size: 1rem;
    font-weight: 700;
    color: #10b981;
}

.producto-precio-shop .precio-original {
    text-decoration: line-through;
    color: #9ca3af;
    font-size: 0.8rem;
    margin-right: 0.3rem;
}

.producto-boton-shop {
    display: inline-block;
    margin-top: 0.5rem;
    padding: 0.3rem 0.8rem;
    background: <?php echo $color_primario; ?>;
    color: white;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
    transition: background 0.3s;
}

.producto-card-shop:hover .producto-boton-shop {
    background: <?php echo $color_secundario; ?>;
}

.sin-resultados-shop {
    text-align: center;
    padding: 4rem 2rem;
    color: #6b7280;
}

.sin-resultados-shop .icono {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.sin-resultados-shop h2 {
    font-size: 1.5rem;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.btn-volver-shop {
    display: inline-block;
    padding: 0.7rem 2rem;
    background: #e5e7eb;
    color: #1f2937;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.3s;
    text-align: center;
}

.btn-volver-shop:hover {
    background: #d1d5db;
}

.btn-volver-shop-white {
    display: inline-block;
    margin-top: 1rem;
    padding: 0.6rem 1.5rem;
    background: <?php echo $color_primario; ?>;
    color: white;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
}

/* ============================================ */
/* RESPONSIVE                                   */
/* ============================================ */
@media (max-width: 768px) {
    .resultados-shop {
        padding: 90px 0.8rem 1.5rem;
    }
    
    .resultados-header-shop {
        padding: 1.5rem 1rem;
        border-radius: 16px;
    }
    
    .resultados-header-shop h1 {
        font-size: 1.5rem;
    }
    
    .resultados-header-shop p {
        font-size: 0.9rem;
    }
    
    .productos-grid-shop {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.8rem;
    }
    
    .producto-img-shop {
        height: 120px;
        padding: 0.5rem;
    }
    
    .producto-nombre-shop {
        font-size: 0.8rem;
    }
    
    .producto-precio-shop {
        font-size: 0.9rem;
    }
    
    .tienda-card-shop {
        padding: 1rem;
        flex-wrap: wrap;
    }
    
    .tienda-img-shop {
        width: 50px;
        height: 50px;
    }
}

@media (max-width: 500px) {
    .productos-grid-shop {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.6rem;
    }
    
    .producto-img-shop {
        height: 100px;
    }
    
    .producto-nombre-shop {
        font-size: 0.7rem;
    }
    
    .producto-precio-shop {
        font-size: 0.8rem;
    }
    
    .producto-info-shop {
        padding: 0.5rem 0.6rem 0.8rem;
    }
    
    .producto-boton-shop {
        font-size: 0.6rem;
        padding: 0.2rem 0.6rem;
    }
    
    .tienda-card-shop {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .tienda-boton-shop {
        align-self: flex-start;
    }
}

@media (max-width: 370px) {
    .productos-grid-shop {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    
    .producto-img-shop {
        height: 80px;
        padding: 0.3rem;
    }
}
</style>

<!-- ============================================ -->
<!-- CONTENIDO                                   -->
<!-- ============================================ -->
<div class="resultados-shop">

    <!-- HEADER -->
    <div class="resultados-header-shop">
        <h1>🔍 Resultados para: "<?php echo htmlspecialchars($termino); ?>"</h1>
        <p>Encuentra productos y tiendas que coinciden con tu búsqueda</p>
    </div>

    <?php
    $total_productos = $productos->num_rows;
    $total_tiendas = $tiendas->num_rows;
    $total_resultados = $total_productos + $total_tiendas;
    ?>

    <?php if ($total_resultados == 0): ?>
        <div class="sin-resultados-shop">
            <div class="icono">🔍</div>
            <h2>No se encontraron resultados</h2>
            <p>Intenta con otras palabras o revisa la ortografía</p>
            <a href="/shop.php" class="btn-volver-shop-white">← Volver a la tienda</a>
        </div>
    <?php else: ?>

        <!-- ===== TIENDAS ===== -->
        <?php if ($total_tiendas > 0): ?>
            <h2 class="seccion-titulo-shop">🏪 Tiendas encontradas (<?php echo $total_tiendas; ?>)</h2>
            <div class="tiendas-lista-shop">
                <?php while($tienda = $tiendas->fetch_assoc()): 
                    $ruta_img = !empty($tienda['imagen_url']) ? $tienda['imagen_url'] : '/uploads/proveedores/default.png';
                    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_img;
                    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
                ?>
                <a href="/<?php echo htmlspecialchars($tienda['slug']); ?>" class="tienda-card-shop">
                    <img src="<?php echo htmlspecialchars($ruta_img) . '?v=' . $version; ?>" 
                         alt="<?php echo htmlspecialchars($tienda['nombre_tienda']); ?>" 
                         class="tienda-img-shop" 
                         onerror="this.src='/uploads/proveedores/default.png'">
                    <div class="tienda-info-shop">
                        <div class="tienda-nombre-shop"><?php echo htmlspecialchars($tienda['nombre_tienda']); ?></div>
                        <div class="tienda-desc-shop"><?php echo htmlspecialchars($tienda['descripcion'] ?? 'Visita nuestra tienda'); ?></div>
                    </div>
                    <span class="tienda-boton-shop">Visitar tienda →</span>
                </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <!-- ===== PRODUCTOS ===== -->
        <?php if ($total_productos > 0): ?>
            <h2 class="seccion-titulo-shop" style="margin-top: <?php echo $total_tiendas > 0 ? '2rem' : '0'; ?>;">
                📦 Productos encontrados (<?php echo $total_productos; ?>)
            </h2>
            <div class="productos-grid-shop">
                <?php while($producto = $productos->fetch_assoc()): 
                    $ruta_img = !empty($producto['imagen_url']) ? $producto['imagen_url'] : '/uploads/productos/sin-imagen.png';
                    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_img;
                    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
                    $slug_tienda = $producto['slug'] ?? '';
                    $nombre_tienda = $producto['nombre_tienda'] ?? 'Tienda';
                    $tiene_rebaja = $producto['porcentaje_rebaja'] > 0;
                    $precio_original = $tiene_rebaja ? $producto['precio_original'] : $producto['precio'];
                    $precio_final = $producto['precio'];
                    $enlace_tienda = !empty($slug_tienda) ? '/index.php?proveedor=' . urlencode($slug_tienda) . '&buscar=' . urlencode($termino_raw) . '&registrar_visita=1' : '/shop.php';
                ?>
                <a href="<?php echo $enlace_tienda; ?>" class="producto-card-shop">
                    <div class="producto-img-shop">
                        <img src="<?php echo htmlspecialchars($ruta_img) . '?v=' . $version; ?>" 
                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                             loading="lazy" 
                             onerror="this.src='/uploads/productos/sin-imagen.png'">
                    </div>
                    <div class="producto-info-shop">
                        <div class="producto-tienda-shop">🏪 <?php echo htmlspecialchars($nombre_tienda); ?></div>
                        <div class="producto-nombre-shop"><?php echo htmlspecialchars($producto['nombre']); ?></div>
                        <div class="producto-precio-shop">
                            <?php if ($tiene_rebaja): ?>
                                <span class="precio-original"><?php echo $moneda_simbolo; ?><?php echo number_format($precio_original, 2); ?></span>
                                <?php echo $moneda_simbolo; ?><?php echo number_format($precio_final, 2); ?>
                            <?php else: ?>
                                <?php echo $moneda_simbolo; ?><?php echo number_format($precio_final, 2); ?>
                            <?php endif; ?>
                            <small><?php echo $moneda_codigo; ?></small>
                        </div>
                        <span class="producto-boton-shop">Ver en tienda →</span>
                    </div>
                </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>

        <!-- BOTÓN VOLVER -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="/shop.php" class="btn-volver-shop">← Volver a la tienda</a>
        </div>

    <?php endif; ?>
</div>

<?php include 'footer_tienda.php'; ?>
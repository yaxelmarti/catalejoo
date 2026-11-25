<?php
define('SEGURO', true);
// Configurar cookies de sesión seguras ANTES de session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';
require_once 'includes/carrito_funciones.php';

registrarVisita($conn);

// ============================================
// VERIFICAR MODO MANTENIMIENTO
// ============================================
$result_mantenimiento = $conn->query("SELECT valor FROM configuracion WHERE clave = 'modo_mantenimiento'");
if ($result_mantenimiento && $result_mantenimiento->num_rows > 0) {
    $row_mantenimiento = $result_mantenimiento->fetch_assoc();
    $modo_mantenimiento = $row_mantenimiento['valor'];
    
    if ($modo_mantenimiento == '1') {
        // Verificar si es administrador
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
        
        // Si no es admin, mostrar página de mantenimiento
        if (!$es_admin) {
            include 'mantenimiento.php';
            exit;
        }
    }
}

session_start();

// Obtener o crear cliente actual
$cliente = obtenerClienteActual($conn);
$cliente_id = $cliente['id'];
$email_verified = $cliente['email_verified'] ?? 0;

// ===== LIMPIAR CARRITOS EXPIRADOS =====
if ($cliente_id > 0) {
    limpiarCarritosExpirados($conn);
}

// ===== NUEVA VARIABLE: Cliente registrado (tiene nombre) =====
$cliente_registrado = ($cliente_id > 0 && !empty($cliente['nombre']));

// Obtener configuración
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion");
while($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

// Valores por defecto
$sitio_nombre = $config['sitio_nombre'] ?? 'TiendaVirtual';
$sitio_slogan = $config['sitio_slogan'] ?? 'Descubre productos increíbles';
$moneda_simbolo = $config['moneda_simbolo'] ?? '$';
$moneda_codigo = $config['moneda_codigo'] ?? 'USD';
$color_primario = $config['color_primario'] ?? '#667eea';
$color_secundario = $config['color_secundario'] ?? '#764ba2';

// Obtener categorías para el menú lateral
$categorias_menu = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");

// ============================================
// OBTENER PRODUCTOS ACTIVOS (CON FILTRO DE CATEGORÍA O BÚSQUEDA)
// ============================================
$categoria_seleccionada = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$termino_busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Detectar dispositivo móvil para carga inicial
$es_movil = preg_match('/(Mobile|Android|iPhone|iPad|iPod|BlackBerry|Windows Phone)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
$productos_por_carga = $es_movil ? 6 : 16;

// Obtener el total de productos activos (para el contador)
if (!empty($termino_busqueda)) {
    $count_sql = "SELECT COUNT(*) as total FROM productos p WHERE p.activo = 1 
                  AND (p.nombre LIKE ? OR p.descripcion LIKE ? OR p.marca LIKE ?)";
    $count_stmt = $conn->prepare($count_sql);
    $param = "%$termino_busqueda%";
    $count_stmt->bind_param("sss", $param, $param, $param);
    $count_stmt->execute();
    $total_activos = $count_stmt->get_result()->fetch_assoc()['total'];
} elseif ($categoria_seleccionada > 0) {
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM productos WHERE activo = 1 AND categoria_id = ?");
    $count_stmt->bind_param("i", $categoria_seleccionada);
    $count_stmt->execute();
    $total_activos = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE activo = 1");
    $total_activos = $total_result->fetch_assoc()['total'];
}

// Consulta para los primeros 16 productos
if (!empty($termino_busqueda)) {
    $sql = "SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono,
            CASE 
                WHEN p.porcentaje_rebaja > 0 THEN 1 
                ELSE 4 
            END as orden_rebaja,
            COALESCE((SELECT SUM(cp.cantidad) FROM carrito_productos cp 
                      JOIN carritos ca ON cp.carrito_id = ca.id 
                      WHERE cp.producto_id = p.id AND ca.estado = 'comprado'), 0) as total_vendidos,
            CASE 
                WHEN TIMESTAMPDIFF(HOUR, p.fecha_creacion, NOW()) <= 48 THEN 2
                ELSE 3
            END as orden_nuevo
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.activo = 1 
            AND (p.nombre LIKE ? OR p.descripcion LIKE ? OR p.marca LIKE ?)
            ORDER BY orden_rebaja ASC, total_vendidos DESC, orden_nuevo ASC, p.nombre ASC, p.id ASC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $param = "%$termino_busqueda%";
    $stmt->bind_param("sssi", $param, $param, $param, $productos_por_carga);
    $stmt->execute();
    $productos_todos = $stmt->get_result();
    
} elseif ($categoria_seleccionada > 0) {
    $sql = "SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono,
            CASE 
                WHEN p.porcentaje_rebaja > 0 THEN 1 
                ELSE 4 
            END as orden_rebaja,
            COALESCE((SELECT SUM(cp.cantidad) FROM carrito_productos cp 
                      JOIN carritos ca ON cp.carrito_id = ca.id 
                      WHERE cp.producto_id = p.id AND ca.estado = 'comprado'), 0) as total_vendidos,
            CASE 
                WHEN TIMESTAMPDIFF(HOUR, p.fecha_creacion, NOW()) <= 48 THEN 2
                ELSE 3
            END as orden_nuevo
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.activo = 1 AND p.categoria_id = ? 
            ORDER BY orden_rebaja ASC, total_vendidos DESC, orden_nuevo ASC, p.nombre ASC, p.id ASC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $categoria_seleccionada, $productos_por_carga);
    $stmt->execute();
    $productos_todos = $stmt->get_result();
} else {
    $sql = "SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono,
            CASE 
                WHEN p.porcentaje_rebaja > 0 THEN 1 
                ELSE 4 
            END as orden_rebaja,
            COALESCE((SELECT SUM(cp.cantidad) FROM carrito_productos cp 
                      JOIN carritos ca ON cp.carrito_id = ca.id 
                      WHERE cp.producto_id = p.id AND ca.estado = 'comprado'), 0) as total_vendidos,
            CASE 
                WHEN TIMESTAMPDIFF(HOUR, p.fecha_creacion, NOW()) <= 48 THEN 2
                ELSE 3
            END as orden_nuevo
            FROM productos p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.activo = 1 
            ORDER BY orden_rebaja ASC, total_vendidos DESC, orden_nuevo ASC, p.nombre ASC, p.id ASC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productos_por_carga);
    $stmt->execute();
    $productos_todos = $stmt->get_result();
}

// INCLUIR MODALES
include 'index_modales/carrito.php';
include 'header_tienda.php';
?>
<!DOCTYPE html>

<main class="container">
    <section class="productos-section">
       <!-- Carrusel con superposición de imagen (ventana) -->
<div id="carruselContainer">
    <div class="carrusel-back">
        <?php include 'index_modales/marketing.php'; ?>
    </div>
    <div class="carrusel-overlay" id="carruselOverlay">
        <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/config/marco_carrucel.webp')): ?>
            <img src="/uploads/config/marco_carrucel.webp" class="ventana-imagen" alt="Marco ventana">
        <?php elseif (file_exists($_SERVER['DOCUMENT_ROOT'] . '/uploads/config/marco_carrucel.png')): ?>
            <img src="/uploads/config/marco_carrucel.png" class="ventana-imagen" alt="Marco ventana">
        <?php endif; ?>
    </div>
</div>

<h2 class="section-title" style="margin-bottom: 0.5rem;">
    <div style="line-height: 1.2; margin-bottom: 0.5rem;">🛍️ Nuestros Productos</div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <!-- Buscador a la izquierda -->
               <div class="buscador-wrapper" style="display: flex; align-items: center;">
    <div class="buscador-container" style="position: relative; display: inline-block;">
        <input type="text" name="buscar" id="buscarInputTitulo" value="<?php echo htmlspecialchars($termino_busqueda); ?>" placeholder="Buscar..." autocomplete="off" 
       style="padding: 0.4rem 3.5rem 0.4rem 0.8rem; border-radius: 30px; border: 1px solid #e2e8f0; width: 100%; max-width: 220px; font-size: 0.85rem;">
        
        <!-- X dentro del input (a la izquierda de la lupa) -->
        <span id="btnLimpiarBusqueda" style="position: absolute; right: 35px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #ef4444; font-size: 1rem; display: none;">✕</span>
        
        <!-- Lupa dentro del input (a la derecha) -->
        <button id="btnLupaBuscar" style="position: absolute; right: 5px; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 1rem; cursor: pointer; color: #6b7280; padding: 0.2rem;">
            🔍
        </button>
    </div>
</div>
                
                <!-- Contador a la derecha -->
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
        
                <?php
        // ============================================
        // MOSTRAR COMBOS PRIMERO (si existen)
        // ============================================
        $sql_combos_primero = "SELECT c.*, 
                                      (SELECT COUNT(*) FROM combo_productos WHERE combo_id = c.id) as productos_distintos,
                                      (SELECT SUM(cantidad) FROM combo_productos WHERE combo_id = c.id) as unidades_totales
                               FROM combos c 
                               WHERE c.activo = 1 
                               ORDER BY c.fecha_creacion DESC
                               LIMIT 12";
        $combos_primero = $conn->query($sql_combos_primero);
        $sin_filtros = ($categoria_seleccionada == 0 && empty($termino_busqueda) && !isset($_GET['filtro']));
        if ($sin_filtros && $combos_primero && $combos_primero->num_rows > 0):
        ?>
            <div id="combosContainer" class="combos-section">
                <div class="productos-grid" id="combos-principal-grid">
                    <?php while($combo = $combos_primero->fetch_assoc()): 
                        $productos_distintos = intval($combo['productos_distintos'] ?? 0);
                        $unidades_totales = intval($combo['unidades_totales'] ?? 0);
                        include 'combo_card.php';
                    endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
     
        <?php if ($total_activos > 0): ?>
            <div class="productos-grid" id="productos-grid" 
                 data-categoria="<?php echo $categoria_seleccionada; ?>" 
                 data-buscar="<?php echo htmlspecialchars($termino_busqueda); ?>"
                 data-offset="<?php echo $productos_por_carga; ?>" 
                 data-total="<?php echo $total_activos; ?>" 
                 data-cargando="false">
                <?php while($producto = $productos_todos->fetch_assoc()): 
                    $dias_antiguedad = floor((time() - strtotime($producto['fecha_creacion'])) / 86400);
                    $es_nuevo = $dias_antiguedad < 7;
                    $tiene_rebaja = $producto['porcentaje_rebaja'] > 0;
                    $precio_original = $tiene_rebaja ? $producto['precio_original'] : $producto['precio'];
                    $precio_final = $producto['precio'];
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
                            $ruta_img = $producto['imagen_url'];
                            $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_img;
                            $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
                            ?>
                            <img src="<?php echo htmlspecialchars($ruta_img) . '?v=' . $version; ?>" 
                                 alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                 class="producto-imagen"
                                 loading="lazy"
                                 onclick="verImagenGrande(this.src)">
                        </div>
                        
                        <div class="producto-info">
                            <div class="producto-marca">
                                <?php echo !empty($producto['marca']) ? htmlspecialchars($producto['marca']) : htmlspecialchars($sitio_nombre); ?>
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
                                        <?php echo $moneda_simbolo; ?><?php echo number_format($precio_original, 2); ?>
                                    </span>
                                    <span class="precio-final">
                                        <?php echo $moneda_simbolo; ?><?php echo number_format($precio_final, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="precio-normal">
                                        <?php echo $moneda_simbolo; ?><?php echo number_format($precio_final, 2); ?>
                                    </span>
                                <?php endif; ?>
                                <small><?php echo $moneda_codigo; ?></small>
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
            
            <div id="fin-productos" style="text-align: center; padding: 2rem; color: #718096; display: none;">
    ✅ Has visto todos los productos
    <?php if (!empty($termino_busqueda)): ?>
        <br><br>
        <a href="index.php" style="display: inline-block; padding: 0.6rem 1.2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 30px; text-decoration: none; font-size: 0.85rem; margin-top: 0.5rem;" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
            🔄 Ver todos los productos
        </a>
    <?php else: ?>
        <br><br>
        <a href="index.php" style="display: inline-block; padding: 0.6rem 1.2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 30px; text-decoration: none; font-size: 0.85rem; margin-top: 0.5rem;" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
            🔄 Recargar tienda
        </a>
    <?php endif; ?>
</div>
        <!-- Botón cargar más para móvil (visible solo cuando se alcanza el límite) -->
<div id="cargarMasBtn" style="text-align: center; padding: 1rem; display: none;">
    <button onclick="cargarMasManual()" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 30px; font-size: 0.85rem; cursor: pointer;">
        ➕ Cargar más productos
    </button>
</div>            
            <style>
                @keyframes spin { to { transform: rotate(360deg); } }
            </style>
            
        <?php else: ?>
            <div style="text-align: center; padding: 4rem; background: white; border-radius: var(--radius-lg);">
                <h3>📭 No hay productos activos disponibles</h3>
                <?php if (!empty($termino_busqueda)): ?>
                    <p style="margin-top: 1rem;">No se encontraron resultados para "<strong><?php echo htmlspecialchars($termino_busqueda); ?></strong>"</p>
                    <p><a href="index.php" style="color: var(--primary);">← Volver a todos los productos</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</main>

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
                </div>
            </div>
            
            <div class="footer-col">
                <h4>🔗 Enlaces</h4>
                <ul class="footer-enlaces">
                    <li><a href="/ayuda.php">❓ Ayuda</a></li>
                    <?php if ($cliente_registrado): ?>
                    <li><a href="/mis_pedidos.php">📋 Mis Compras</a></li>
                    <li><a href="#" onclick="abrirPanelCliente()">👤 Mi cuenta</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</footer>

<?php include 'index_modales/registro.php'; ?>
<?php include 'index_modales/verificar_password.php'; ?>
<?php include 'index_modales/carrito_pendiente.php'; ?>
<?php include 'index_modales/sincronizar.php'; ?>
<?php include 'index_modales/password_cliente.php'; ?>
<?php include 'index_modales/editar_cliente.php'; ?>
<?php include 'index_modales/password_compra.php'; ?>
<?php include 'index_modales/datos_entrega.php'; ?>
<?php include 'index_modales/producto_tienda.php'; ?>
<?php include 'index_modales/bienvenida.php'; ?>
<?php include 'index_modales/comentarios.php'; ?>

<script>
// ============================================
// VARIABLES GLOBALES
// ============================================
<?php if ($cliente['id'] == 0): ?>
    window.clienteRegistrado = false;
<?php elseif (empty($cliente['nombre'])): ?>
    window.clienteRegistrado = false;
<?php else: ?>
    window.clienteRegistrado = true;
<?php endif; ?>

window.carritoPendienteId = null;
window.productoPendiente = null;
window.busquedaActiva = '';
window.scrollInfinitoHabilitado = true;

// CONTROL DE SCROLL INFINITO EN MÓVIL
window.totalCargado = 0;        // Productos cargados actualmente
window.limiteScroll = 30;       // Límite actual (30, 60, 90...)
window.scrollActivo = true;     // Si el scroll infinito está activo
window.cargaPendiente = false;  // Evita cargas múltiples
window.limiteScrollPC = 48;
    
function abrirRegistro() {
    document.getElementById('modalRegistro').style.display = 'block';
}

// ============================================
// FUNCIONES DEL CARRITO
// ============================================
window.cambiarCantidad = function(btn, delta) {
    const selector = btn.closest('.cantidad-selector');
    const input = selector.querySelector('.cantidad-input');
    let valor = parseInt(input.value) || 1;
    valor = Math.max(1, Math.min(99, valor + delta));
    input.value = valor;
}

window.agregarAlCarrito = function(productoId, btn) {
    const selector = btn.closest('.producto-card').querySelector('.cantidad-selector');
    const cantidad = parseInt(selector.querySelector('.cantidad-input').value) || 1;
    
    if (!window.clienteRegistrado) {
        document.getElementById('modalRegistro').style.display = 'block';
        window.productoPendiente = { id: productoId, cantidad: cantidad };
        return;
    }
    
    fetch('includes/carrito_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=agregar&producto_id=' + productoId + '&cantidad=' + cantidad
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion('✅ Producto agregado', 'exito');
            actualizarContadorCarrito();
        } else if (data.requiere_registro) {
            document.getElementById('modalRegistro').style.display = 'block';
            window.productoPendiente = { id: productoId, cantidad: cantidad };
        } else {
            mostrarNotificacion('❌ ' + data.mensaje, 'error');
        }
    })
    .catch(error => {
        console.error('Error en fetch:', error);
        mostrarNotificacion('❌ Error al conectar con el servidor', 'error');
    });
}

window.actualizarContadorCarrito = function() {
    fetch('includes/carrito_ajax.php?accion=obtener_carrito')
    .then(response => response.json())
    .then(data => {
        const contadorHeader = document.getElementById('carritoContadorHeader');
        if (data.success && !data.vacio) {
            const totalItems = data.items.reduce((sum, item) => sum + item.cantidad, 0);
            if (contadorHeader) {
                contadorHeader.textContent = totalItems;
                contadorHeader.style.display = 'flex';
            }
        } else {
            if (contadorHeader) contadorHeader.style.display = 'none';
        }
        
        const sideContador = document.getElementById('sideMenuCarritoContador');
        if (sideContador && data.success && !data.vacio) {
            const totalItems = data.items.reduce((sum, item) => sum + item.cantidad, 0);
            sideContador.textContent = totalItems;
            sideContador.style.display = 'inline-flex';
        } else if (sideContador) {
            sideContador.style.display = 'none';
        }
    });
}

function verificarCarritoPendiente() {
    if (sessionStorage.getItem('no_mostrar_carrito_pendiente') === 'true') {
        sessionStorage.removeItem('no_mostrar_carrito_pendiente');
        return;
    }
    
    fetch('includes/carrito_ajax.php?accion=verificar_carrito_pendiente')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.tiene_carrito) {
            mostrarModalCarritoPendiente(data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function mostrarModalCarritoPendiente(data) {
    const info = document.getElementById('infoCarritoPendiente');
    if (!info) return;
    
    const totalItems = data.items.reduce((sum, item) => sum + item.cantidad, 0);
    const moneda = '<?php echo $moneda_simbolo; ?>';
    
    info.innerHTML = `
        <p class="cantidad">📦 ${totalItems} producto${totalItems !== 1 ? 's' : ''}</p>
        <p class="total">💰 ${moneda}${data.total.toFixed(2)}</p>
        <p class="tiempo">⏰ Expira en: ${data.horas_restantes} horas</p>
    `;
    
    window.carritoPendienteId = data.carrito_id;
    document.getElementById('modalCarritoPendiente').style.display = 'block';
}

// ============================================
// FUNCIONES DE CLIENTE
// ============================================
function abrirPanelCliente() {
    cerrarSideMenu();
    document.getElementById('modalPasswordCliente').style.display = 'block';
}

function reenviarVerificacionDesdeCliente() {
    fetch('includes/carrito_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=reenviar_verificacion'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion('✅ Nuevo enlace enviado a tu email', 'exito');
        } else {
            mostrarNotificacion('❌ ' + data.mensaje, 'error');
        }
    });
}

// ============================================
// FUNCIONES PARA MENÚ LATERAL IZQUIERDO
// ============================================
function toggleSideMenu() {
    window.pasoAtras = 0;
    const menu = document.getElementById('sideMenu');
    const overlay = document.getElementById('menuOverlay');
    const isOpen = menu.classList.contains('show');
    
    if (isOpen) {
        menu.classList.remove('show');
        overlay.classList.remove('show');
        // Limpiar el hash de la URL si existe
        if (window.location.hash.includes('menu-side')) {
            window.history.back();
        }
    } else {
        // Agregar hash a la URL sin recargar
        if (!window.location.hash.includes('menu-side')) {
            window.history.pushState({ menu: 'side' }, '', window.location.pathname + window.location.search + '#menu-side');
        }
        menu.classList.add('show');
        overlay.classList.add('show');
        actualizarContadorCarrito();
    }
}
    
function cerrarSideMenu() {
    const menu = document.getElementById('sideMenu');
    const overlay = document.getElementById('menuOverlay');
    if (menu) menu.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
}

document.getElementById('menuOverlay').addEventListener('click', cerrarSideMenu);

function cerrarSesion() {
    if (confirm('¿Cerrar sesión?')) {
        window.location.href = 'logout.php';
    }
}

// ============================================
// FLECHA VOLVER ARRIBA
// ============================================
const scrollTopBtn = document.getElementById('scrollTopBtn');

window.addEventListener('scroll', function() {
    if (window.scrollY > 300) {
        scrollTopBtn.classList.add('show');
    } else {
        scrollTopBtn.classList.remove('show');
    }
});

if (scrollTopBtn) {
    scrollTopBtn.addEventListener('click', function() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================
function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function mostrarNotificacion(mensaje, tipo = 'exito') {
    const notif = document.createElement('div');
    notif.className = 'notificacion ' + (tipo === 'error' ? 'error' : tipo === 'warning' ? 'warning' : '');
    notif.textContent = mensaje;
    document.body.appendChild(notif);
    setTimeout(() => notif.remove(), 3000);
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
}

// ============================================
// SCROLL INFINITO
// ============================================
function cargarMasProductos() {
    const grid = document.getElementById('productos-grid');
    if (!grid) return;
    
   // === NUEVO: variables para móvil ===
const esMovil = window.innerWidth <= 768;
let productosPorCarga = esMovil ? 6 : 16;

if (esMovil && window.scrollActivo === false) return;
if (esMovil && window.totalCargado >= window.limiteScroll) {
    document.getElementById('cargarMasBtn').style.display = 'block';
    return;
}

// === NUEVO: límite para PC ===
if (!esMovil && window.totalCargado >= window.limiteScrollPC) {
    document.getElementById('cargarMasBtn').style.display = 'block';
    return;
}
    // === FIN NUEVO ===
    
    // NUEVA LÍNEA 1 (ya la tenías)
    if (window.scrollInfinitoHabilitado === false) return;
    
    const cargando = grid.getAttribute('data-cargando') === 'true';
    const offset = parseInt(grid.getAttribute('data-offset') || 0);
    const total = parseInt(grid.getAttribute('data-total') || 0);
    const categoria = parseInt(grid.getAttribute('data-categoria') || 0);
    const buscar = grid.getAttribute('data-buscar') || '';
    
    if (cargando) return;
    if (offset >= total) {
        document.getElementById('fin-productos').style.display = 'block';
        window.scrollInfinitoHabilitado = false;
        return;
    }
    
    grid.setAttribute('data-cargando', 'true');
    document.getElementById('loader-mas-productos').style.display = 'block';

    // === NUEVO: agregar &limite a la URL ===
let url;
const filtro = grid.getAttribute('data-filtro');

if (filtro === 'combos') {
    url = 'includes/cargar_mas_combos.php?offset=' + offset + '&limite=' + productosPorCarga;
} else {
    url = 'includes/cargar_mas_productos.php?offset=' + offset + '&categoria=' + categoria + '&limite=' + productosPorCarga;
    if (buscar) {
        url += '&buscar=' + encodeURIComponent(buscar);
    }
    if (filtro) {
        url += '&filtro=' + filtro;
    }
}
// === FIN NUEVO ===
    
    fetch(url)
        .then(response => response.text())
        .then(html => {
            if (html.trim()) {
                grid.insertAdjacentHTML('beforeend', html);
                
                const nuevosProductos = document.querySelectorAll('.producto-card').length;
                grid.setAttribute('data-offset', nuevosProductos);
                
                // === NUEVO: actualizar contador en móvil ===
                window.totalCargado = nuevosProductos;
                // === FIN NUEVO ===
                
                ajustarTamanosTarjetas();
            }
            grid.setAttribute('data-cargando', 'false');
            document.getElementById('loader-mas-productos').style.display = 'none';
            
            // === NUEVO: verificar límite en móvil ===
if (esMovil && window.totalCargado >= window.limiteScroll) {
    document.getElementById('cargarMasBtn').style.display = 'block';
    window.scrollActivo = false;
    return;
}

// === NUEVO: verificar límite en PC ===
if (!esMovil && window.totalCargado >= window.limiteScrollPC && window.scrollActivo === true) {
    document.getElementById('cargarMasBtn').style.display = 'block';
    window.scrollActivo = false;
    return;
}
           
        })
        .catch(error => {
            console.error('Error al cargar más productos:', error);
            grid.setAttribute('data-cargando', 'false');
            document.getElementById('loader-mas-productos').style.display = 'none';
        });
}
    
 function cargarMasManual() {
    const esMovil = window.innerWidth <= 768;
    
    if (esMovil) {
        window.limiteScroll += 30;
    } else {
        window.limiteScrollPC += 48;
    }
    
    // Actualizar el total de productos cargados y el offset
    const grid = document.getElementById('productos-grid');
    if (grid) {
        const nuevosProductos = document.querySelectorAll('.producto-card').length;
        window.totalCargado = nuevosProductos;
        grid.setAttribute('data-offset', nuevosProductos);
    }
    
    window.scrollActivo = true;
    document.getElementById('cargarMasBtn').style.display = 'none';
    cargarMasProductos();
}

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
// HEARTBEAT
// ============================================
function enviarHeartbeat() {
    fetch('heartbeat_cliente.php')
        .then(response => response.json())
        .then(data => console.log('Heartbeat:', data))
        .catch(error => console.log('Heartbeat error:', error));
}

// ============================================
// FUNCIONES PARA MENÚ CLIENTE
// ============================================
function toggleClienteBottomMenu() {
    window.pasoAtras = 0;
    const menu = document.getElementById('clienteBottomMenu');
    const overlay = document.getElementById('bottomOverlay');
    const isOpen = menu.classList.contains('show');
    
    if (isOpen) {
        menu.classList.remove('show');
        overlay.classList.remove('show');
        if (window.location.hash.includes('menu-user')) {
            window.history.back();
        }
    } else {
        if (!window.location.hash.includes('menu-user')) {
            window.history.pushState({ menu: 'user' }, '', window.location.pathname + window.location.search + '#menu-user');
        }
        menu.classList.add('show');
        overlay.classList.add('show');
    }
}
    
function cerrarClienteBottomMenu() {
    const menu = document.getElementById('clienteBottomMenu');
    const overlay = document.getElementById('bottomOverlay');
    menu.classList.remove('show');
    overlay.classList.remove('show');
}

// ============================================
// FUNCIONES PARA MENÚ CATEGORÍAS
// ============================================
function toggleCarrusel(ocultar) {
    const carrusel = document.getElementById('carruselContainer');
    if (carrusel) {
        carrusel.style.display = ocultar ? 'none' : 'block';
    }
}    
    
function toggleCategoriasRightMenu() {
    window.pasoAtras = 0;
    const menu = document.getElementById('categoriasRightMenu');
    const overlay = document.getElementById('rightOverlay');
    const isOpen = menu.classList.contains('show');
    
    if (isOpen) {
        menu.classList.remove('show');
        overlay.classList.remove('show');
        if (window.location.hash.includes('menu-cat')) {
            window.history.back();
        }
    } else {
        if (!window.location.hash.includes('menu-cat')) {
            window.history.pushState({ menu: 'cat' }, '', window.location.pathname + window.location.search + '#menu-cat');
        }
        menu.classList.add('show');
        overlay.classList.add('show');
    }
}

function cerrarCategoriasRightMenu() {
    const menu = document.getElementById('categoriasRightMenu');
    const overlay = document.getElementById('rightOverlay');
    menu.classList.remove('show');
    overlay.classList.remove('show');
}

function filtrarCategoria(categoriaId) {
    document.getElementById('combosContainer').style.display = 'none';
   // Reiniciar scroll infinito (móvil y PC)
window.totalCargado = 0;
window.limiteScroll = 30;
window.limiteScrollPC = 48;
window.scrollActivo = true;
window.scrollInfinitoHabilitado = true;
document.getElementById('cargarMasBtn').style.display = 'none';
document.getElementById('fin-productos').style.display = 'none';

// Reiniciar el offset del grid
const grid = document.getElementById('productos-grid');
if (grid) {
    grid.setAttribute('data-offset', '16');
}
    toggleCarrusel(true);
    const url = new URL(window.location.href);
    if (categoriaId > 0) {
        url.searchParams.set('categoria', categoriaId);
    } else {
        url.searchParams.delete('categoria');
    }
    url.searchParams.delete('buscar');
    window.history.pushState({}, '', url);
    
    grid.innerHTML = '<div style="text-align: center; padding: 4rem;">🔄 Cargando productos...</div>';
    
    fetch('includes/filtrar_productos.php?categoria=' + categoriaId + '&offset=0')
    .then(response => response.text())
    .then(html => {
        grid.innerHTML = html;
        
       if (categoriaId == 0) {
    const combosContainer = document.getElementById('combosContainer');
    if (combosContainer) combosContainer.style.display = '';  // vacío para que retome el CSS original
}
        
        // Actualizar total de productos cargados después del filtro
window.totalCargado = document.querySelectorAll('.producto-card').length;
        
        // Obtener el total real desde el servidor
        fetch('includes/total_filtro.php?categoria=' + categoriaId)
        .then(r => r.json())
        .then(data => {
            const totalReal = data.total;
            const contadorDiv = document.getElementById('contador-productos');
            if (contadorDiv) {
                if (categoriaId > 0) {
                    fetch('includes/obtener_categoria.php?id=' + categoriaId)
                    .then(r => r.json())
                    .then(cat => { 
                        grid.setAttribute('data-total', totalReal);
                        contadorDiv.innerHTML = `<span><span id="btnLimpiarFiltro" style="cursor: pointer; color: #ef4444; margin-right: 0.5rem; display: inline-block;">✕</span>(${totalReal} ${cat.icono} ${cat.nombre})</span>`;
                    });
                } else {
                    grid.setAttribute('data-total', totalReal);
                    contadorDiv.innerHTML = `<span>(${totalReal} productos)</span>`;
                }
            }
        });
        
        grid.setAttribute('data-categoria', categoriaId);
        grid.setAttribute('data-buscar', '');
        grid.setAttribute('data-offset', '16');
        grid.setAttribute('data-cargando', 'false');
        document.getElementById('loader-mas-productos').style.display = 'none';
        document.getElementById('fin-productos').style.display = 'none';
        ajustarTamanosTarjetas();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    })
    .catch(error => {
        console.error('Error:', error);
        grid.innerHTML = '<div style="text-align: center; padding: 4rem;"><h3>❌ Error al cargar productos</h3></div>';
    });
}
function filtrarNuevos() {
    document.getElementById('combosContainer').style.display = 'none';
       // Reiniciar scroll infinito (móvil y PC)
window.totalCargado = 0;
window.limiteScroll = 30;
window.limiteScrollPC = 48;
window.scrollActivo = true;
window.scrollInfinitoHabilitado = true;
document.getElementById('cargarMasBtn').style.display = 'none';
document.getElementById('fin-productos').style.display = 'none';

// Reiniciar el offset del grid
const grid = document.getElementById('productos-grid');
if (grid) {
    grid.setAttribute('data-offset', '16');
}
    
    toggleCarrusel(true);
    window.busquedaActiva = '';
    grid.innerHTML = '<div style="text-align: center; padding: 4rem;">🔄 Cargando productos nuevos...</div>';
    
    fetch('includes/filtrar_avanzado.php?nuevos=1&offset=0')
    .then(response => response.text())
    .then(html => {
        grid.innerHTML = html;
        
        // Actualizar total de productos cargados después del filtro
window.totalCargado = document.querySelectorAll('.producto-card').length;
        
        fetch('includes/total_filtro.php?nuevos=1')
        .then(r => r.json())
        .then(data => {
            const totalReal = data.total;
            grid.setAttribute('data-total', totalReal);
            const contadorDiv = document.getElementById('contador-productos');
            if (contadorDiv) {
                contadorDiv.innerHTML = `<span><span id="btnLimpiarFiltro" style="cursor: pointer; color: #ef4444; margin-right: 0.5rem; display: inline-block;">✕</span>(${totalReal} nuevos)</span>`;
            }
        });
        
        grid.setAttribute('data-filtro', 'nuevos');
        grid.setAttribute('data-offset', '16');
        grid.setAttribute('data-cargando', 'false');
        
        const url = new URL(window.location.href);
        url.searchParams.set('filtro', 'nuevos');
        url.searchParams.delete('buscar');
        window.history.pushState({}, '', url);
        ajustarTamanosTarjetas();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    })
    .catch(error => {
        console.error('Error:', error);
        grid.innerHTML = '<div style="text-align: center; padding: 4rem;"><h3>❌ Error al cargar productos</h3></div>';
    });
}
function filtrarMasVendidos() {
    document.getElementById('combosContainer').style.display = 'none';
       // Reiniciar scroll infinito (móvil y PC)
window.totalCargado = 0;
window.limiteScroll = 30;
window.limiteScrollPC = 48;
window.scrollActivo = true;
window.scrollInfinitoHabilitado = true;
document.getElementById('cargarMasBtn').style.display = 'none';
document.getElementById('fin-productos').style.display = 'none';

// Reiniciar el offset del grid
const grid = document.getElementById('productos-grid');
if (grid) {
    grid.setAttribute('data-offset', '16');
}
    
    toggleCarrusel(true);
    grid.innerHTML = '<div style="text-align: center; padding: 4rem;">🔄 Cargando productos más vendidos...</div>';
    
    const esMovil = window.innerWidth <= 768;
let productosPorCarga = esMovil ? 6 : 16;

fetch('includes/filtrar_avanzado.php?mas_vendidos=1&offset=0&limite=' + productosPorCarga)
.then(response => response.text())
.then(html => {
    grid.innerHTML = html;
        
        // Actualizar total de productos cargados y el offset después del filtro
const nuevosProductos = document.querySelectorAll('.producto-card').length;
window.totalCargado = nuevosProductos;
grid.setAttribute('data-offset', nuevosProductos);
        
        fetch('includes/total_filtro.php?mas_vendidos=1')
        .then(r => r.json())
        .then(data => {
            const totalReal = data.total;
            grid.setAttribute('data-total', totalReal);
            const contadorDiv = document.getElementById('contador-productos');
            if (contadorDiv) {
                contadorDiv.innerHTML = `<span><span id="btnLimpiarFiltro" style="cursor: pointer; color: #ef4444; margin-right: 0.5rem; display: inline-block;">✕</span>(${totalReal} más vendidos)</span>`;
            }
        });
        
        grid.setAttribute('data-filtro', 'mas_vendidos');
        grid.setAttribute('data-cargando', 'false');
        
        const url = new URL(window.location.href);
        url.searchParams.set('filtro', 'mas_vendidos');
        url.searchParams.delete('buscar');
        window.history.pushState({}, '', url);
        ajustarTamanosTarjetas();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    })
    .catch(error => {
        console.error('Error:', error);
        grid.innerHTML = '<div style="text-align: center; padding: 4rem;"><h3>❌ Error al cargar productos</h3></div>';
    });
}

function filtrarOfertas() {
    document.getElementById('combosContainer').style.display = 'none';
        // Reiniciar scroll infinito (móvil y PC)
window.totalCargado = 0;
window.limiteScroll = 30;
window.limiteScrollPC = 48;
window.scrollActivo = true;
window.scrollInfinitoHabilitado = true;
document.getElementById('cargarMasBtn').style.display = 'none';
document.getElementById('fin-productos').style.display = 'none';

// Reiniciar el offset del grid
const grid = document.getElementById('productos-grid');
if (grid) {
    grid.setAttribute('data-offset', '16');
}
    
    toggleCarrusel(true);
    grid.innerHTML = '<div style="text-align: center; padding: 4rem;">🔄 Cargando productos en oferta...</div>';
    
    fetch('includes/filtrar_avanzado.php?ofertas=1&offset=0')
    .then(response => response.text())
    .then(html => {
        grid.innerHTML = html;
        
        // Actualizar total de productos cargados después del filtro
window.totalCargado = document.querySelectorAll('.producto-card').length;
        
        fetch('includes/total_filtro.php?ofertas=1')
        .then(r => r.json())
        .then(data => {
            const totalReal = data.total;
            grid.setAttribute('data-total', totalReal);
            const contadorDiv = document.getElementById('contador-productos');
            if (contadorDiv) {
                contadorDiv.innerHTML = `<span><span id="btnLimpiarFiltro" style="cursor: pointer; color: #ef4444; margin-right: 0.5rem; display: inline-block;">✕</span>(${totalReal} en oferta)</span>`;
            }
        });
        
        grid.setAttribute('data-filtro', 'ofertas');
        grid.setAttribute('data-offset', '16');
        grid.setAttribute('data-cargando', 'false');
        
        const url = new URL(window.location.href);
        url.searchParams.set('filtro', 'ofertas');
        url.searchParams.delete('buscar');
        window.history.pushState({}, '', url);
        ajustarTamanosTarjetas();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    })
    .catch(error => {
        console.error('Error:', error);
        grid.innerHTML = '<div style="text-align: center; padding: 4rem;"><h3>❌ Error al cargar productos</h3></div>';
    });
}
    // Limpiar filtro (categoría, nuevos, mas vendidos, ofertas) - SIN recargar página
document.addEventListener('click', function(e) {
    if (e.target.id === 'btnLimpiarFiltro') {
        e.preventDefault();
        
        // Limpiar el input de búsqueda si tiene algo
        const inputBuscar = document.getElementById('buscarInputTitulo');
        if (inputBuscar) {
            inputBuscar.value = '';
        }
        
        // Ocultar la X del buscador si estaba visible
        const btnLimpiarBusqueda = document.getElementById('btnLimpiarBusqueda');
        if (btnLimpiarBusqueda) {
            btnLimpiarBusqueda.style.display = 'none';
        }
        
        // Recargar todos los productos (sin filtro)
        filtrarCategoria(0);
        toggleCarrusel(false);
    }
});
    
    function cargarCombos() {
        document.getElementById('combosContainer').style.display = 'none';
    // Reiniciar variables de scroll
    window.totalCargado = 0;
    window.limiteScroll = 60;
    window.scrollActivo = true;
    window.scrollInfinitoHabilitado = true;
    document.getElementById('cargarMasBtn').style.display = 'none';
    document.getElementById('fin-productos').style.display = 'none';
    
    // Ocultar carrusel
    const carrusel = document.getElementById('carruselContainer');
    if (carrusel) carrusel.style.display = 'none';
    
    const grid = document.getElementById('productos-grid');
    if (!grid) return;
    
    grid.innerHTML = '<div style="text-align: center; padding: 4rem;">🎁 Cargando combos...</div>';
    
    const esMovil = window.innerWidth <= 768;
    let combosPorCarga = esMovil ? 6 : 12;
    
    fetch('includes/cargar_combos.php?offset=0&limite=' + combosPorCarga)
        .then(response => response.text())
        .then(html => {
            grid.innerHTML = html;
            
            // Actualizar contador
            const contadorDiv = document.getElementById('contador-productos');
            if (contadorDiv) {
                fetch('includes/total_combos.php')
                    .then(r => r.json())
                    .then(data => {
                        contadorDiv.innerHTML = `<span><span id="btnLimpiarFiltro" style="cursor: pointer; color: #ef4444; margin-right: 0.5rem; display: inline-block;">✕</span>(${data.total} 🎁 Combos)</span>`;
                        grid.setAttribute('data-total', data.total);
                    });
            }
            
            grid.setAttribute('data-filtro', 'combos');
            grid.setAttribute('data-offset', combosPorCarga);
            window.totalCargado = combosPorCarga;
            
            if (typeof ajustarTamanosTarjetas === 'function') ajustarTamanosTarjetas();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        })
        .catch(error => {
            console.error('Error:', error);
            grid.innerHTML = '<div style="text-align: center; padding: 4rem;"><h3>❌ Error al cargar combos</h3></div>';
        });
}
    
    // ============================================
// MODAL COMBO TIENDA
// ============================================
window.modalComboAbierto = false;

function abrirModalComboTienda(comboId) {
    if (!comboId || isNaN(Number(comboId))) return;
    if (window.modalComboAbierto) return;
    
    window.history.pushState({ modalCombo: true }, '', window.location.pathname + window.location.search + '#modal-combo');
    
    const modal = document.getElementById('modalComboTienda');
    const contenedor = document.getElementById('contenidoModalComboTienda');
    
    contenedor.innerHTML = `<div style="text-align: center; padding: 2rem;"><div class="spinner-modal"></div><p>Cargando combo...</p></div>`;
    modal.style.display = 'block';
    window.modalComboAbierto = true;
    
    fetch('includes/get_combo_modal.php?id=' + comboId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                contenedor.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div style="text-align: center;">
                            <div class="producto-etiquetas">
                                ${data.combo.es_nuevo ? '<span class="etiqueta-nuevo">NUEVO</span>' : ''}
                                ${data.combo.porcentaje_rebaja > 0 ? `<span class="etiqueta-rebaja">-${data.combo.porcentaje_rebaja}%</span>` : ''}
                            </div>
                            <img src="${data.combo.imagen_url}" style="max-width: 100%; max-height: 250px; object-fit: contain; border-radius: 8px;">
                        </div>
                        <div>
                            <h3 style="font-size: 1.5rem;">${data.combo.nombre}</h3>
                            <div style="margin: 0.5rem 0;">${data.combo.descripcion}</div>
                            <div style="font-size: 0.85rem;">🎁 ${data.combo.total_productos} productos - ${data.combo.total_unidades} unidades</div>
                            <div style="margin: 0.5rem 0;">
                                ${data.combo.porcentaje_rebaja > 0 ? `
                                    <span style="text-decoration: line-through;">${data.moneda_simbolo}${data.combo.precio.toFixed(2)}</span>
                                    <strong style="font-size: 1.5rem; color: #10b981;">${data.moneda_simbolo}${data.combo.precio_final.toFixed(2)}</strong>
                                ` : `<strong style="font-size: 1.5rem;">${data.moneda_simbolo}${data.combo.precio.toFixed(2)}</strong>`}
                                <small>${data.moneda_codigo}</small>
                            </div>
                            <div style="display: flex; align-items: center; gap: 1rem; ${data.combo.stock_combo <= 0 ? 'opacity: 0.5; pointer-events: none;' : ''}">
                                <div class="cantidad-selector">
                                    <button class="cantidad-btn" onclick="cambiarCantidadComboModal(-1, ${data.combo.id})">−</button>
                                    <input type="text" id="cantidadComboModal_${data.combo.id}" class="cantidad-input" value="1" readonly style="width: 50px;">
                                    <button class="cantidad-btn" onclick="cambiarCantidadComboModal(1, ${data.combo.id})">+</button>
                                </div>
                                <button class="btn-carrito" onclick="agregarComboModalAlCarrito(${data.combo.id})" style="${data.combo.stock_combo <= 0 ? 'display: none;' : ''}">
                                    🛒 Agregar combo
                                </button>
                            </div>
                            ${data.combo.stock_combo <= 0 ? '<div style="color: #ef4444; text-align: center; margin-top: 1rem;">❌ Combo agotado</div>' : ''}
                        </div>
                    </div>
                `;
            } else {
                contenedor.innerHTML = `<div style="text-align: center; color: #ef4444;">❌ ${data.error}</div>`;
            }
        })
        .catch(error => {
            contenedor.innerHTML = `<div style="text-align: center; color: #ef4444;">❌ Error al cargar</div>`;
        });
}

function cerrarModalComboTienda(reemplazarHash = true) {
    if (reemplazarHash && window.location.hash.includes('modal-combo')) {
        const urlLimpia = window.location.pathname + window.location.search;
        window.history.replaceState({}, '', urlLimpia);
    }
    const modal = document.getElementById('modalComboTienda');
    if (modal) modal.style.display = 'none';
    window.modalComboAbierto = false;
}

function cambiarCantidadComboModal(delta, comboId) {
    const input = document.getElementById(`cantidadComboModal_${comboId}`);
    if (input) {
        let valor = parseInt(input.value) || 1;
        valor = Math.max(1, Math.min(99, valor + delta));
        input.value = valor;
    }
}

function agregarComboModalAlCarrito(comboId) {
    const input = document.getElementById(`cantidadComboModal_${comboId}`);
    const cantidad = input ? parseInt(input.value) : 1;
    
    if (!window.clienteRegistrado) {
        cerrarModalComboTienda();
        document.getElementById('modalRegistro').style.display = 'block';
        window.productoPendiente = { combo: true, id: comboId, cantidad: cantidad };
        return;
    }
    
    fetch('includes/carrito_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=agregar_combo&combo_id=' + comboId + '&cantidad=' + cantidad
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion('✅ Combo agregado al carrito', 'exito');
            actualizarContadorCarrito();
            cerrarModalComboTienda();
        } else {
            mostrarNotificacion('❌ ' + data.mensaje, 'error');
        }
    });
}

document.addEventListener('click', function(event) {
    if (event.target === document.getElementById('modalComboTienda')) {
        cerrarModalComboTienda();
    }
});
    
    // ============================================
// FUNCIONES PARA TARJETAS DE COMBO
// ============================================
function cambiarCantidadCombo(btn, delta) {
    const selector = btn.closest('.cantidad-selector');
    const input = selector.querySelector('.cantidad-input');
    let valor = parseInt(input.value) || 1;
    valor = Math.max(1, Math.min(99, valor + delta));
    input.value = valor;
}

function agregarComboAlCarrito(comboId, btn) {
    const card = btn.closest('.producto-card');
    const selector = card.querySelector('.cantidad-selector');
    const cantidad = parseInt(selector.querySelector('.cantidad-input').value) || 1;
    
    if (!window.clienteRegistrado) {
        const modalRegistro = document.getElementById('modalRegistro');
        if (modalRegistro) modalRegistro.style.display = 'block';
        window.productoPendiente = { combo: true, id: comboId, cantidad: cantidad };
        return;
    }
    
    fetch('includes/carrito_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=agregar_combo&combo_id=' + comboId + '&cantidad=' + cantidad
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            mostrarNotificacion('✅ Combo agregado al carrito', 'exito');
            if (typeof actualizarContadorCarrito === 'function') actualizarContadorCarrito();
        } else {
            mostrarNotificacion('❌ ' + data.mensaje, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarNotificacion('❌ Error al conectar con el servidor', 'error');
    });
}
    
/*
    window.addEventListener('popstate', function() {
    const params = new URLSearchParams(window.location.search);
    const categoria = params.get('categoria') || 0;
    const buscar = params.get('buscar') || '';
    if (buscar) {
        window.location.reload();
    } else {
        filtrarCategoria(categoria);
    }
});
*/
setInterval(enviarHeartbeat, 30000);
    
    function limpiarFiltroAjax() {
    const grid = document.getElementById('productos-grid');
    const combosContainer = document.getElementById('combosContainer');
    if (!grid) return;

    // Mostrar combos si estaban ocultos
    if (combosContainer) combosContainer.style.display = 'block';

    // Mostrar carga
    grid.innerHTML = '<div style="text-align: center; padding: 4rem;">🔄 Limpiando filtro...</div>';

    // Cargar productos sin filtro (categoría 0)
    fetch('includes/filtrar_productos.php?categoria=0&offset=0')
        .then(response => response.text())
        .then(html => {
            grid.innerHTML = html;

            // Actualizar contador de productos
            const totalProductos = document.querySelectorAll('.producto-card').length;
            const contadorDiv = document.getElementById('contador-productos');
            if (contadorDiv) {
                contadorDiv.innerHTML = `<span>(${totalProductos} productos)</span>`;
            }

            // Restablecer atributos del grid
            grid.setAttribute('data-categoria', '0');
            grid.setAttribute('data-buscar', '');
            grid.setAttribute('data-filtro', '');
            grid.setAttribute('data-offset', '16');
            grid.setAttribute('data-total', totalProductos);
            grid.setAttribute('data-cargando', 'false');

            // Ajustar tarjetas
            if (typeof ajustarTamanosTarjetas === 'function') ajustarTamanosTarjetas();

            // Mostrar carrusel (si estaba oculto)
            const carrusel = document.getElementById('carruselContainer');
            if (carrusel) carrusel.style.display = 'block';

            // Marcar que ya se ha limpiado el filtro
            window.pasoAtras = 1;
        })
        .catch(error => {
            console.error('Error al limpiar filtro:', error);
            grid.innerHTML = '<div style="text-align: center; padding: 4rem;"><h3>❌ Error al limpiar filtro</h3></div>';
        });
}
</script>

<script>
function ajustarTamanosTarjetas() {
    var anchoPantalla = window.innerWidth;
    var esMovil = anchoPantalla <= 768;
    
    if (esMovil) {
        document.querySelectorAll('.producto-marca').forEach(el => el.style.setProperty('font-size', '10px', 'important'));
        document.querySelectorAll('.producto-nombre').forEach(el => {
            el.style.setProperty('font-size', '13px', 'important');
        });
        document.querySelectorAll('.producto-categoria').forEach(el => el.style.setProperty('font-size', '9px', 'important'));
        document.querySelectorAll('.producto-descripcion').forEach(el => el.style.setProperty('font-size', '11px', 'important'));
        document.querySelectorAll('.producto-precio').forEach(el => el.style.removeProperty('color'));
        
        document.querySelectorAll('.producto-nombre').forEach(el => el.style.setProperty('margin-bottom', '4px', 'important'));
        document.querySelectorAll('.producto-descripcion').forEach(el => el.style.setProperty('margin-bottom', '4px', 'important'));
        document.querySelectorAll('.producto-categoria').forEach(el => el.style.setProperty('margin-bottom', '6px', 'important'));
    } else {
        document.querySelectorAll('.producto-marca').forEach(el => el.style.setProperty('font-size', '0.7rem', 'important'));
        document.querySelectorAll('.producto-nombre').forEach(el => {
            el.style.setProperty('font-size', '0.9rem', 'important');
        });
        document.querySelectorAll('.producto-categoria').forEach(el => el.style.setProperty('font-size', '0.65rem', 'important'));
        document.querySelectorAll('.producto-descripcion').forEach(el => el.style.setProperty('font-size', '0.75rem', 'important'));
        document.querySelectorAll('.producto-precio').forEach(el => el.style.removeProperty('color'));
        
        document.querySelectorAll('.producto-nombre').forEach(el => el.style.setProperty('margin-bottom', '4px', 'important'));
        document.querySelectorAll('.producto-descripcion').forEach(el => el.style.setProperty('margin-bottom', '4px', 'important'));
        document.querySelectorAll('.producto-categoria').forEach(el => el.style.setProperty('margin-bottom', '6px', 'important'));
    }
}
    
 // ===== FUNCIONES DEL BUSCADOR =====
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
        
        // Limpiar el input
        if (inputBuscar) {
            inputBuscar.value = '';
        }
        
        // Ocultar la X
        this.style.display = 'none';
        
        // Limpiar búsqueda activa
        window.busquedaActiva = '';
        
        // Recargar todos los productos (sin recargar página)
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

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(ajustarTamanosTarjetas, 50);
    actualizarContadorCarrito();
});

window.addEventListener('resize', function() {
    ajustarTamanosTarjetas();
});
    
    if (window.location.search.includes('abrir_editar=1')) {
    sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');
    setTimeout(function() {
        // Abrir modal de edición directamente
        if (typeof cargarDatosCliente === 'function') {
            cargarDatosCliente();  // Esta función carga datos y abre el modal
        } else {
            const modalEditar = document.getElementById('modalEditarCliente');
            if (modalEditar) modalEditar.style.display = 'block';
        }
        window.history.replaceState({}, document.title, window.location.pathname);
    }, 500);
}
verificarCarritoPendiente();
</script>    
    
    <!-- MODAL COMBO TIENDA -->
<div id="modalComboTienda" class="modal-overlay" style="display: none;">
    <div class="modal-contenido" style="max-width: 900px; width: 90%; padding: 1.5rem;">
        <span class="close-modal" onclick="cerrarModalComboTienda()">&times;</span>
        <div id="contenidoModalComboTienda">
            <div style="text-align: center; padding: 2rem;">
                <div class="spinner-modal"></div>
                <p>Cargando combo...</p>
            </div>
        </div>
    </div>
</div>
    
</body>
</html>
<?php $conn->close(); ?>
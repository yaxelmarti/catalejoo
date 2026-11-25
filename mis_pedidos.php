<?php
// ============================================
// mis_pedidos.php - Mis Compras
// ============================================

if (!defined('SEGURO')) {
    define('SEGURO', true);
}
    
// Configurar cookies de sesión seguras ANTES de session_start()
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
require_once 'includes/proveedor_utils.php';

// Obtener cliente actual
$cliente = obtenerClienteActual($conn);
$cliente_id = $cliente['id'] ?? 0;

if ($cliente_id == 0) {
    header('Location: /index.php?error=login');
    exit;
}

// Obtener pedidos del cliente
$stmt = $conn->prepare("SELECT p.*, 
                               (SELECT COUNT(*) FROM pedido_detalles WHERE pedido_id = p.id) as total_productos,
                               (SELECT SUM(cantidad) FROM pedido_detalles WHERE pedido_id = p.id) as total_items
                        FROM pedidos p 
                        WHERE p.cliente_id = ? 
                        ORDER BY p.fecha_pedido DESC");
$stmt->bind_param("i", $cliente_id);
$stmt->execute();
$pedidos = $stmt->get_result();

// Obtener configuración
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion");
while($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

$sitio_nombre = $config['sitio_nombre'] ?? 'Mi Tienda';
$sitio_email = $config['sitio_email'] ?? 'contacto@mitienda.com';
$sitio_telefono = $config['sitio_telefono'] ?? '+53 55551234';
$horario = $config['horario_atencion'] ?? 'Lunes a Viernes 9am - 6pm';
$color_primario = $config['color_primario'] ?? '#667eea';
$color_secundario = $config['color_secundario'] ?? '#764ba2';

// ============================================
// CONTEXTO DEL PROVEEDOR
// Mis Pedidos no es tienda específica:
// el header resolverá moneda del cliente o carrito pendiente.
// ============================================
$proveedor_contexto = 0;

// Categorías para el menú
$categorias_menu = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");

// Incluir header (define $moneda_simbolo, $moneda_codigo, $cliente_registrado)
include 'header_tienda.php';

// ============================================
// FUNCIÓN: Obtener nombre y URL del método de pago desde BD
// ============================================
function getMetodoPagoData($conn, $slug) {
    static $cache = [];
    if (isset($cache[$slug])) return $cache[$slug];
    
    $stmt = $conn->prepare("SELECT nombre, url_oficial FROM pasarelas WHERE slug = ? LIMIT 1");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $datos = [
        'nombre' => $res['nombre'] ?? ucfirst(str_replace('_', ' ', $slug)),
        'url' => $res['url_oficial'] ?? ''
    ];
    
    $cache[$slug] = $datos;
    return $datos;
}
?>

<!-- ===== CONTENIDO DE MIS PEDIDOS ===== -->
<style>
    :root {
        --primary: <?php echo $color_primario; ?>;
        --secondary: <?php echo $color_secundario; ?>;
        --success: #48bb78;
        --warning: #ecc94b;
        --danger: #f56565;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background: #f8fafc;
        line-height: 1.6;
        color: #2d3748;
    }
    
    .welcome-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 3rem 1rem;
        text-align: center;
        margin-bottom: 2rem;
        border-radius: 0 0 30px 30px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .welcome-header h1 {
        font-size: clamp(2rem, 6vw, 3rem);
        margin-bottom: 1rem;
    }
    
    .welcome-header p {
        font-size: clamp(1rem, 4vw, 1.3rem);
        max-width: 600px;
        margin: 0 auto;
        opacity: 0.95;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem 3rem;
    }
    
    .compras-list {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .compra-item {
        background: white;
        border-radius: 16px;
        padding: 1rem 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
        border: 1px solid #e5e7eb;
        flex-wrap: wrap;
        gap: 1rem;
    }
    
    .compra-item:hover {
        box-shadow: 0 4px 12px rgba(102,126,234,0.15);
        transform: translateX(5px);
        border-color: var(--primary);
    }
    
    .compra-info {
        display: flex;
        align-items: center;
        gap: 2rem;
        flex-wrap: wrap;
        flex: 1;
    }
    
    .compra-numero {
        font-weight: 700;
        color: #2d3748;
        min-width: 100px;
    }
    
    .compra-fecha {
        color: #718096;
        font-size: 0.9rem;
        min-width: 120px;
    }
    
    .compra-total {
        font-weight: 600;
        color: var(--primary);
        min-width: 100px;
    }
    
    .compra-total small {
        font-size: 0.7rem;
        color: #718096;
        font-weight: normal;
    }
    
    .compra-estado {
        padding: 0.3rem 1rem;
        border-radius: 30px;
        font-size: 0.85rem;
        font-weight: 600;
        min-width: 130px;
        text-align: center;
    }
    
    .compra-metodo {
        display: flex;
        flex-direction: column;
        min-width: 150px;
    }
    
    .compra-metodo .metodo-nombre {
        font-size: 0.85rem;
        font-weight: 500;
        color: #4a5568;
    }
    
    .compra-metodo .metodo-link {
        font-size: 0.7rem;
        color: var(--primary);
        text-decoration: none;
    }
    
    .compra-metodo .metodo-link:hover {
        text-decoration: underline;
    }
    
    .estado-finalizado { background: #bee3f8; color: #2c5282; }
    .estado-empaquetado { background: #feebc8; color: #c05621; }
    .estado-transporte { background: #e9d8fd; color: #6b46c1; }
    .estado-entregado { background: #c6f6d5; color: #22543d; }
    .estado-cancelado { background: #fed7d7; color: #c53030; }
    .estado-incidencia { background: #fed7d7; color: #c05621; }
    .estado-pendiente { background: #fef5e7; color: #744210; }
    .estado-pagado { background: #c6f6d5; color: #22543d; }
    .estado-pendiente_parcial { background: #fef5e7; color: #744210; }
    .estado-pagado_parcial { background: #c6f6d5; color: #22543d; }
    .estado-pendiente_entrega_pago { background: #fef5e7; color: #744210; }
    
    .btn-ver {
        background: var(--primary);
        color: white;
        border: none;
        padding: 0.5rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 0.9rem;
    }
    
    .btn-ver:hover {
        background: var(--secondary);
        transform: translateY(-2px);
    }
    
    .btn-cancelar-solicitud {
        background: var(--danger);
        color: white;
        border: none;
        padding: 0.5rem 1.2rem;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 0.8rem;
    }
    
    .btn-cancelar-solicitud:hover {
        background: #c53030;
        transform: translateY(-2px);
    }
    
    .acciones {
        display: flex;
        gap: 0.8rem;
        align-items: center;
    }
    
    .sin-compras {
        text-align: center;
        padding: 4rem;
        background: white;
        border-radius: 20px;
        color: #718096;
        border: 1px solid #e5e7eb;
    }
    
    .sin-compras h2 {
        font-size: 2rem;
        margin-bottom: 1rem;
        color: #2d3748;
    }
    
    .sin-compras p {
        margin-bottom: 2rem;
    }
    
    .btn-comprar {
        display: inline-block;
        padding: 1rem 2rem;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-comprar:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 20px rgba(102,126,234,0.3);
    }
    
    /* Notificación */
    .toast-notificacion {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: #2d3748;
        color: white;
        padding: 12px 24px;
        border-radius: 50px;
        font-size: 14px;
        z-index: 3000;
        animation: fadeInUp 0.3s ease;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateX(-50%) translateY(20px); }
        to { opacity: 1; transform: translateX(-50%) translateY(0); }
    }
    
    /* Botones flotantes */
    .btn-flotante-volver {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(102,126,234,0.4);
        z-index: 1000;
        transition: all 0.3s;
        border: none;
        text-decoration: none;
    }
    
    .btn-flotante-volver:hover {
        transform: translateY(-5px);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .welcome-header {
            padding: 2rem 1rem;
            border-radius: 0 0 20px 20px;
        }
        
        .compra-item {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .compra-info {
            width: 100%;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.8rem;
        }
        
        .compra-numero, .compra-fecha, .compra-total, .compra-estado, .compra-metodo {
            min-width: auto;
        }
        
        .acciones {
            width: 100%;
            justify-content: flex-end;
        }
        
        .btn-flotante-volver {
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 480px) {
        .compra-item {
            padding: 0.8rem 1rem;
        }
        
        .sin-compras {
            padding: 2rem;
        }
        
        .sin-compras h2 {
            font-size: 1.5rem;
        }
    }
    
    .btn-pdf {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        background: #ef4444;
        color: white;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.75rem;
        font-weight: 500;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }
    
    .btn-pdf:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }
    
    .btn-pagar-saldo {
        display: inline-block;
        padding: 0.5rem 1.2rem;
        background: #f59e0b;
        color: white;
        border-radius: 8px;
        text-decoration: none;
        font-size: 0.8rem;
        font-weight: 500;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }
    
    .btn-pagar-saldo:hover {
        background: #d97706;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
    }
</style>

<main>
    <?php
    // ===== MENSAJE FLASH (sesión) =====
    $flash_mensaje = $_SESSION['mensaje'] ?? '';
    $flash_tipo = $_SESSION['tipo_mensaje'] ?? 'info';
    
    unset($_SESSION['mensaje'], $_SESSION['tipo_mensaje']);
    
    if (!empty($flash_mensaje)):
        $flash_colores = [
            'error'   => ['bg' => '#fed7d7', 'border' => '#f56565', 'color' => '#c53030', 'icon' => '⚠️'],
            'success' => ['bg' => '#c6f6d5', 'border' => '#48bb78', 'color' => '#22543d', 'icon' => '✅'],
            'warning' => ['bg' => '#feebc8', 'border' => '#ed8936', 'color' => '#7b341e', 'icon' => '⚠️'],
            'info'    => ['bg' => '#bee3f8', 'border' => '#4299e1', 'color' => '#2c5282', 'icon' => 'ℹ️'],
        ];
        $fc = $flash_colores[$flash_tipo] ?? $flash_colores['info'];
    ?>
    <div style="max-width: 1200px; margin: 1rem auto 0; padding: 0 1rem;">
        <div style="
            background: <?php echo $fc['bg']; ?>;
            border-left: 5px solid <?php echo $fc['border']; ?>;
            color: <?php echo $fc['color']; ?>;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-size: 0.95rem;
            line-height: 1.5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 0.8rem;
        ">
            <span style="font-size: 1.5rem; flex-shrink: 0;"><?php echo $fc['icon']; ?></span>
            <div><?php echo htmlspecialchars($flash_mensaje); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="welcome-header">
        <h1>📋 Mis Compras</h1>
        <p>Consulta el estado y detalles de tus compras</p>
    </div>
    
    <div class="container">
        <?php if ($pedidos->num_rows > 0): ?>
            <div class="compras-list">
                <?php while($p = $pedidos->fetch_assoc()): 
                    // Obtener nombre y URL del método de pago desde BD
                    $metodo_data = getMetodoPagoData($conn, $p['tipo_pago']);
                    $nombre_metodo = $metodo_data['nombre'];
                    $url_metodo = $metodo_data['url'];
                    
                    // Estados que NO permiten cancelación
                    $estados_cancelables = ['pendiente_pago', 'pendiente_parcial'];
                    $puede_cancelar = in_array($p['estado_actual'], $estados_cancelables);
                    
                    // Determinar si mostrar botón de pagar saldo pendiente
                    $monto_pendiente_pedido = floatval($p['monto_pendiente'] ?? 0);
                    $mostrar_pago_saldo = ($monto_pendiente_pedido > 0 && $p['estado_actual'] != 'cancelado');
                    
                    $estado_actual = $p['estado_actual'];
                    $estados = [
                        'pendiente' => 'Pendiente',
                        'pendiente_parcial' => 'Pendiente parcial',
                        'pendiente_entrega_pago' => 'Pendiente pago entrega',
                        'pagado' => 'Pagado',
                        'pagado_parcial' => 'Pagado parcial',
                        'finalizado' => 'Finalizado',
                        'empaquetado' => 'Empaquetado',
                        'transporte' => 'En transporte',
                        'entregado' => 'Entregado',
                        'cancelado' => 'Cancelado',
                        'incidencia' => 'Incidencia'
                    ];
                    $estado_texto = $estados[$estado_actual] ?? ucfirst($estado_actual);
                    
                    // Moneda histórica del pedido
                    $pedido_moneda = !empty($p['moneda']) ? $p['moneda'] : 'USD';
                    $pedido_simbolo = getSimboloPorMoneda($pedido_moneda);
                    $pedido_total = (!empty($p['total_convertido'])) 
                        ? floatval($p['total_convertido']) 
                        : floatval($p['total']);
                ?>
                <div class="compra-item" id="compra-<?php echo $p['id']; ?>">
                    <div class="compra-info">
                        <span class="compra-numero"><?php echo $p['codigo']; ?></span>
                        <span class="compra-fecha"><?php echo date('d/m/Y H:i', strtotime($p['fecha_pedido'])); ?></span>
                        <span class="compra-total">
                            <?php echo htmlspecialchars($pedido_simbolo); ?><?php echo number_format($pedido_total, 2); ?> <?php echo htmlspecialchars($pedido_moneda); ?>
                        </span>
                        <span class="compra-estado estado-<?php echo $estado_actual; ?>">
                            <?php echo $estado_texto; ?>
                        </span>
                        <div class="compra-metodo">
                            <span class="metodo-nombre"><?php echo htmlspecialchars($nombre_metodo); ?></span>
                            <?php if (!empty($url_metodo)): ?>
                                <a href="<?php echo $url_metodo; ?>" target="_blank" class="metodo-link">Ir al sitio oficial →</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="acciones">
                        <?php if ($puede_cancelar): ?>
                            <button onclick="solicitarCancelacion(<?php echo $p['id']; ?>)" class="btn-cancelar-solicitud" id="btn-cancelar-<?php echo $p['id']; ?>">
                                ✖ Solicitar cancelación
                            </button>
                        <?php endif; ?>
                        
                        <?php if ($mostrar_pago_saldo): ?>
                            <a href="/checkout/pagar_saldo_pendiente.php?pedido=<?php echo $p['id']; ?>" class="btn-pagar-saldo">
                                💰 Pagar <?php echo htmlspecialchars($pedido_simbolo); ?><?php echo number_format($monto_pendiente_pedido, 2); ?> <?php echo htmlspecialchars($pedido_moneda); ?>
                            </a>
                        <?php endif; ?>
                        
                        <a href="/checkout/confirmacion.php?pedido=<?php echo $p['id']; ?>" class="btn-ver" style="text-decoration: none; display: inline-block;">
                            👁️ Ver detalle
                        </a>
                         
                        <a href="/descargar_pdf_pedidos.php?id=<?php echo $p['id']; ?>" 
                           class="btn-pdf" 
                           title="Descargar PDF del pedido"
                           style="text-decoration: none; display: inline-block;">
                            📄 PDF
                        </a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="sin-compras">
                <h2>📭 No tienes compras aún</h2>
                <p>Explora nuestra tienda y realiza tu primera compra</p>
                <a href="index.php" class="btn-comprar">🛍️ Comenzar a Comprar</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
    function volverTienda() {
        sessionStorage.setItem('volviendo_de_compras', 'true');
        window.location.href = 'index.php';
    }
    
    function solicitarCancelacion(pedidoId) {
        if (confirm('¿Estás seguro de que deseas solicitar la cancelación de esta compra? Una vez cancelada no podrás recuperarla.')) {
            const btn = document.getElementById('btn-cancelar-' + pedidoId);
            btn.disabled = true;
            btn.textContent = '⏳ Enviando...';
            
            fetch('solicitar_cancelacion.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'pedido_id=' + pedidoId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarNotificacion('✅ Solicitud de cancelación enviada. El administrador la revisará.', 'success');
                    const btn = document.getElementById('btn-cancelar-' + pedidoId);
                    btn.textContent = '✖ Cancelación solicitada';
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                } else {
                    mostrarNotificacion('❌ Error: ' + data.mensaje, 'error');
                    const btn = document.getElementById('btn-cancelar-' + pedidoId);
                    btn.disabled = false;
                    btn.textContent = '✖ Solicitar cancelación';
                }
            })
            .catch(error => {
                mostrarNotificacion('❌ Error de conexión', 'error');
                const btn = document.getElementById('btn-cancelar-' + pedidoId);
                btn.disabled = false;
                btn.textContent = '✖ Solicitar cancelación';
            });
        }
    }
    
    function mostrarNotificacion(mensaje, tipo) {
        const toast = document.createElement('div');
        toast.className = 'toast-notificacion';
        toast.textContent = mensaje;
        if (tipo === 'error') {
            toast.style.background = '#f56565';
        } else {
            toast.style.background = '#48bb78';
        }
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }
</script>

<?php
include 'footer.php';
$conn->close();
?>
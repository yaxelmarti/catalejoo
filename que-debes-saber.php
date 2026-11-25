<?php
// ============================================
// que-debes-saber.php - Información importante
// ============================================

define('SEGURO', true);

// Configurar cookies de sesión seguras
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

session_start();
require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';

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

// Obtener cliente actual
$cliente = obtenerClienteActual($conn);
$cliente_id = $cliente['id'] ?? 0;
$cliente_registrado = ($cliente_id > 0 && !empty($cliente['nombre']));

// Categorías para el menú
$categorias_menu = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");

// Pasar variables a JavaScript
echo '<script>';
echo 'window.clienteRegistrado = ' . ($cliente_registrado ? 'true' : 'false') . ';';
echo 'window.monedaSimbolo = "' . ($config['moneda_simbolo'] ?? '$') . '";';
echo '</script>';

// Incluir header
include 'header_tienda.php';

// ============================================
// MÉTODOS DE PAGO ACTIVOS
// ============================================
$metodos_pago = [
    ['clave' => 'paypal_activo', 'icono' => '💛', 'nombre' => 'PayPal', 'desc' => 'Pago internacional con tu cuenta PayPal'],
    ['clave' => 'stripe_activo', 'icono' => '💳', 'nombre' => 'Stripe', 'desc' => 'Pago con tarjeta de crédito/débito'],
    ['clave' => 'square_activo', 'icono' => '🟩', 'nombre' => 'Square', 'desc' => 'Pago con Square'],
    ['clave' => 'amazon_activo', 'icono' => '📦', 'nombre' => 'Amazon Pay', 'desc' => 'Pago con tu cuenta de Amazon'],
    ['clave' => 'mercadopago_activo', 'icono' => '🛒', 'nombre' => 'Mercado Pago', 'desc' => 'Pago a través de Mercado Pago'],
    ['clave' => 'bizum_activo', 'icono' => '📱', 'nombre' => 'Bizum', 'desc' => 'Pago con Bizum desde tu móvil'],
    ['clave' => 'tropipay_activo', 'icono' => '🌍', 'nombre' => 'TropiPay', 'desc' => 'Pago a través de TropiPay'],
    ['clave' => 'transferencia_usd_activo', 'icono' => '🏦', 'nombre' => 'Transferencia USD', 'desc' => 'Para cuentas bancarias en USD'],
    ['clave' => 'transferencia_clasica_activo', 'icono' => '💳', 'nombre' => 'Transferencia Clásica', 'desc' => 'Para tarjetas clásicas cubanas'],
    ['clave' => 'zelle_activo', 'icono' => '💸', 'nombre' => 'Zelle', 'desc' => 'Pago con Zelle (email o teléfono registrado)'],
    ['clave' => 'qvapay_activo', 'icono' => '💳', 'nombre' => 'QvaPay', 'desc' => 'Pago a través de QvaPay'],
];

$metodos_activos = array_filter($metodos_pago, function($metodo) use ($config) {
    return isset($config[$metodo['clave']]) && $config[$metodo['clave']] == '1';
});

// ============================================
// PAGO CON ENTREGA (según configuración real)
// ============================================
$pago_entrega_activo = isset($config['pago_con_entrega_activo']) && $config['pago_con_entrega_activo'] == '1';
$porcentaje_entrega = intval($config['porcentaje_con_entrega'] ?? 0);

// Determinar método de pago
if (!$pago_entrega_activo) {
    $metodo_pago_titulo = '💳 Pago por adelantado';
    $metodo_pago_texto = 'Usted paga por la pasarela deseada y comienza a procesar su pedido.';
    $metodo_pago_color = '#ebf8ff';
    $metodo_pago_border = '#3182ce';
} elseif ($pago_entrega_activo && $porcentaje_entrega < 100) {
    $adelanto = 100 - $porcentaje_entrega;
    $metodo_pago_titulo = '💰 Pago parcial';
    $metodo_pago_texto = "Usted paga por adelantado el <strong>{$adelanto}%</strong> y en el momento de la entrega paga el resto <strong>{$porcentaje_entrega}%</strong>.";
    $metodo_pago_color = '#fef3c7';
    $metodo_pago_border = '#f59e0b';
} elseif ($pago_entrega_activo && $porcentaje_entrega == 100) {
    $metodo_pago_titulo = '📦 Pago contra entrega';
    $metodo_pago_texto = 'Usted será contactado por un administrador para coordinar el proceso, y paga el 100% del importe en el momento de la entrega.';
    $metodo_pago_color = '#f0fff4';
    $metodo_pago_border = '#38a169';
} else {
    $metodo_pago_titulo = '💳 Pago por adelantado';
    $metodo_pago_texto = 'Usted paga por la pasarela deseada y comienza a procesar su pedido.';
    $metodo_pago_color = '#ebf8ff';
    $metodo_pago_border = '#3182ce';
}
?>

<!-- ===== CONTENIDO DE QUE DEBES SABER ===== -->
<style>
    .welcome-header {
        background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
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
        animation: fadeInDown 0.8s;
    }
    
    .welcome-header p {
        font-size: clamp(1rem, 4vw, 1.3rem);
        max-width: 600px;
        margin: 0 auto;
        opacity: 0.95;
        animation: fadeInUp 0.8s;
    }
    
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .info-nav {
        background: white;
        border-radius: 50px;
        max-width: 900px;
        margin: -2rem auto 2rem;
        padding: 0.8rem;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 0.5rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        position: relative;
        z-index: 10;
    }
    
    .info-nav a {
        padding: 0.8rem 1.2rem;
        border-radius: 40px;
        text-decoration: none;
        color: #4a5568;
        font-weight: 600;
        font-size: clamp(0.8rem, 3vw, 0.95rem);
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 0.3rem;
    }
    
    .info-nav a:hover {
        background: <?php echo $color_primario; ?>;
        color: white;
        transform: translateY(-2px);
    }
    
    .info-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 1rem 3rem;
    }
    
    .seccion {
        background: white;
        border-radius: 30px;
        padding: clamp(1.5rem, 5vw, 2rem);
        margin-bottom: 2rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        scroll-margin-top: 100px;
    }
    
    .seccion h2 {
        font-size: clamp(1.5rem, 5vw, 2rem);
        color: #2d3748;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 3px solid <?php echo $color_primario; ?>;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .seccion h3 {
        font-size: clamp(1.1rem, 4vw, 1.3rem);
        color: #4a5568;
        margin: 1.5rem 0 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }
    
    .seccion p {
        margin-bottom: 1rem;
        color: #4a5568;
        line-height: 1.7;
        font-size: clamp(0.9rem, 3vw, 1rem);
    }
    
    .seccion ul, .seccion ol {
        margin: 1rem 0 1.5rem 1.5rem;
    }
    
    .seccion li {
        margin-bottom: 0.5rem;
        color: #4a5568;
        font-size: clamp(0.9rem, 3vw, 1rem);
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }
    
    .info-card {
        background: #f8fafc;
        border-radius: 20px;
        padding: 1.8rem 1.5rem;
        text-align: center;
        transition: transform 0.3s;
        border-left: 4px solid <?php echo $color_primario; ?>;
    }
    
    .info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(102,126,234,0.15);
    }
    
    .info-card .icono {
        font-size: 2.5rem;
        margin-bottom: 0.8rem;
    }
    
    .info-card h4 {
        color: #2d3748;
        margin-bottom: 0.5rem;
        font-size: 1.1rem;
    }
    
    .info-card p {
        font-size: 0.95rem;
        color: #718096;
        margin-bottom: 0;
    }
    
    .info-card .destacado {
        font-weight: 700;
        color: <?php echo $color_primario; ?>;
        font-size: 1.2rem;
    }
    
    .metodos-pago {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin: 1.5rem 0;
    }
    
    .metodo-item {
        flex: 1 1 180px;
        background: #f8fafc;
        border-radius: 15px;
        padding: 1.2rem;
        text-align: center;
        border-left: 4px solid <?php echo $color_primario; ?>;
    }
    
    .metodo-item h4 {
        margin-bottom: 0.5rem;
        color: #2d3748;
        font-size: 1rem;
    }
    
    .metodo-item p {
        font-size: 0.85rem;
        margin-bottom: 0;
    }
    
    .contacto-info {
        background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
        border-radius: 20px;
        padding: 2rem;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-around;
        gap: 2rem;
        text-align: center;
    }
    
    .contacto-item {
        flex: 1 1 180px;
    }
    
    .contacto-icono {
        font-size: clamp(1.8rem, 6vw, 2rem);
        margin-bottom: 0.5rem;
    }
    
    .contacto-item h4 {
        font-size: 1rem;
        margin-bottom: 0.5rem;
    }
    
    .contacto-item p {
        font-size: 0.85rem;
        margin-bottom: 0;
        word-break: break-word;
    }
    
    .contacto-item a {
        color: <?php echo $color_primario; ?>;
        text-decoration: none;
        word-break: break-all;
    }
    
    .contacto-item a:hover {
        text-decoration: underline;
    }
    
    .btn-volver-tienda {
        display: inline-block;
        padding: 1rem 2rem;
        background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
        color: white;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(102,126,234,0.3);
        transition: all 0.3s;
    }
    
    .btn-volver-tienda:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102,126,234,0.4);
    }
    
    .btn-volver-tienda-flotante {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
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
    
    .btn-volver-tienda-flotante:hover {
        transform: translateY(-5px);
    }
    
    @media (max-width: 768px) {
        .welcome-header {
            padding: 2rem 1rem;
            border-radius: 0 0 20px 20px;
        }
        
        .info-nav {
            margin: -1.5rem 1rem 2rem;
            border-radius: 30px;
        }
        
        .info-nav a {
            padding: 0.5rem 0.8rem;
        }
        
        .seccion {
            padding: 1.2rem;
            border-radius: 20px;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .info-card {
            padding: 1rem;
        }
        
        .metodos-pago {
            flex-direction: column;
            gap: 0.8rem;
        }
        
        .metodo-item {
            flex: 1;
            padding: 1rem;
        }
        
        .contacto-info {
            flex-direction: column;
            gap: 1.5rem;
            padding: 1.5rem;
        }
        
        .btn-volver-tienda-flotante {
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 480px) {
        .info-nav a {
            flex: 1 1 calc(50% - 0.5rem);
            justify-content: center;
            padding: 0.5rem 0.6rem;
            font-size: 0.75rem;
        }
        
        .seccion h2 {
            font-size: 1.3rem;
        }
    }
</style>

<main>
    <div class="welcome-header">
        <h1>📋 Qué debes saber</h1>
        <p>Toda la información clave para comprar con confianza en <?php echo htmlspecialchars($sitio_nombre); ?></p>
    </div>
    
   <nav class="info-nav">
    <a href="#tiendas">🏪 Tiendas</a>
    <a href="#envios">🚚 Envíos</a>
    <a href="#transportacion">🚛 Costos</a>
    <a href="#pagos">💳 Pagos</a>
    <a href="#contacto">📞 Contacto</a>
</nav>
    
    <div class="info-container">
        <!-- ============================================ -->
        <!-- SECCIÓN: ENTREGA DE PEDIDOS                   -->
        <!-- ============================================ -->
        <section id="envios" class="seccion">
            <h2>🚚 Entrega de pedidos</h2>
            
            <div class="info-grid">
                <div class="info-card">
                    <div class="icono">🇨🇺</div>
                    <h4>Entregas en toda Cuba</h4>
                    <p><span class="destacado">72 horas</span> o menos</p>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem;">Desde el momento en que se confirma tu pedido</p>
                </div>
                
                <div class="info-card" style="border-left-color: <?php echo $color_secundario; ?>;">
                    <div class="icono">🏙️</div>
                    <h4>Entregas en Santa Clara</h4>
                    <p><span class="destacado">48 horas</span> o menos</p>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem;">Entrega prioritaria en la ciudad</p>
                </div>
            </div>
            
            <h3>📦 ¿Cómo funciona el envío?</h3>
            <p>Una vez que confirmes tu compra y el pago sea verificado, tu pedido será preparado y enviado a la dirección que indicaste en el formulario de entrega.</p>
            <ul>
                <li><strong>Preparación:</strong> 24 horas hábiles</li>
                <li><strong>Tránsito:</strong> 24-48 horas dependiendo del destino</li>
                <li><strong>Total estimado:</strong> 48-72 horas en toda Cuba</li>
                <li><strong>Santa Clara:</strong> Entrega en 48 horas por ubicación local</li>
            </ul>
        </section>

<!-- ============================================ -->
<!-- SECCIÓN: TIENDAS Y CARRITO                   -->
<!-- ============================================ -->
<section id="tiendas" class="seccion">
    <h2>🏪 Tiendas y carrito</h2>
    
    <div class="info-grid">
        <div class="info-card">
            <div class="icono">🛒</div>
            <h4>Una tienda por carrito</h4>
            <p>Tu carrito solo puede contener productos de <span class="destacado">una tienda</span> a la vez.</p>
            <p style="font-size: 0.85rem; margin-top: 0.5rem;">Esto asegura que tu pedido llegue completo y de un solo vendedor.</p>
        </div>
        
        <div class="info-card" style="border-left-color: <?php echo $color_secundario; ?>;">
            <div class="icono">🔄</div>
            <h4>¿Cambiaste de tienda?</h4>
            <p>Si intentas agregar productos de otra tienda, el sistema te dará <span class="destacado">3 opciones</span>:</p>
            <ul style="text-align: left; margin-top: 0.5rem; font-size: 0.85rem;">
                <li>🏪 Regresar a la tienda actual</li>
                <li>💳 Pagar tu carrito actual</li>
                <li>🗑️ Cancelar y empezar nuevo</li>
            </ul>
        </div>
    </div>
    
    <div style="background: #fef5e7; border-radius: 15px; padding: 1.2rem; border-left: 4px solid #ed8936; margin-top: 1.5rem;">
        <h4 style="margin: 0 0 0.3rem 0; color: #744210;">💡 Consejo práctico</h4>
        <p style="margin: 0; font-size: 0.95rem; color: #744210;">Si necesitas comprar en <strong>varias tiendas</strong>, realiza una compra por cada tienda. Así cada pedido llega desde su respectivo vendedor.</p>
    </div>
</section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: COSTO DE TRANSPORTACIÓN             -->
        <!-- ============================================ -->
        <section id="transportacion" class="seccion">
            <h2>🚚 Costo de transportación del pedido según localidad</h2>
            <p>El costo de envío se calcula automáticamente al seleccionar tu provincia y municipio al finalizar la compra.</p>
            
            <div style="overflow-x: auto; margin: 1.5rem 0;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <thead>
                        <tr style="background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%); color: white;">
                            <th style="padding: 0.8rem 1rem; text-align: left;">Provincia</th>
                            <th style="padding: 0.8rem 1rem; text-align: left;">Municipios</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Obtener provincias con sus municipios activos y costos
                        $sql = "SELECT p.id as provincia_id, p.nombre as provincia_nombre, 
                                       c.id as costo_id, c.municipio, c.costo, c.activo as municipio_activo
                                FROM provincias p
                                LEFT JOIN costos_envio c ON p.id = c.provincia_id
                                WHERE p.activo = 1 AND (c.activo = 1 OR c.id IS NULL)
                                ORDER BY p.nombre, c.municipio";
                        $result = $conn->query($sql);
                        
                        $provincias = [];
                        while ($row = $result->fetch_assoc()) {
                            $provincia_id = $row['provincia_id'];
                            if (!isset($provincias[$provincia_id])) {
                                $provincias[$provincia_id] = [
                                    'nombre' => $row['provincia_nombre'],
                                    'municipios' => []
                                ];
                            }
                            if ($row['municipio']) {
                                $provincias[$provincia_id]['municipios'][] = [
                                    'nombre' => $row['municipio'],
                                    'costo' => $row['costo']
                                ];
                            }
                        }
                        
                        if (count($provincias) > 0):
                            foreach ($provincias as $provincia):
                        ?>
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 0.8rem 1rem; font-weight: 600; vertical-align: top;">
                                    <?php echo htmlspecialchars($provincia['nombre']); ?>
                                </td>
                                <td style="padding: 0.8rem 1rem; vertical-align: top;">
                                    <?php if (count($provincia['municipios']) > 0): ?>
                                        <?php 
                                        $municipios_con_costo = [];
                                        foreach ($provincia['municipios'] as $m):
                                            $costo_texto = ($m['costo'] > 0) ? ' (<strong>$' . number_format($m['costo'], 2) . '</strong>)' : '';
                                            $municipios_con_costo[] = htmlspecialchars($m['nombre']) . $costo_texto;
                                        endforeach;
                                        echo implode(', ', $municipios_con_costo);
                                        ?>
                                    <?php else: ?>
                                        <span style="color: #718096;">Todos los municipios</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="2" style="padding: 2rem; text-align: center; color: #718096;">
                                    No hay provincias configuradas actualmente.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <p style="font-size: 0.85rem; color: #718096; margin-top: 0.5rem;">
                💡 El costo de transportación se calcula automáticamente al seleccionar tu municipio en el formulario de entrega.
            </p>
        </section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: MÉTODOS DE PAGO                     -->
        <!-- ============================================ -->
        <section id="pagos" class="seccion">
            <h2>💳 Métodos de pago</h2>
            <p>Aceptamos múltiples métodos de pago para tu comodidad. Todos son seguros y verificados.</p>
            
            <!-- ============================================ -->
            <!-- MÉTODO DE PAGO SEGÚN CONFIGURACIÓN           -->
            <!-- ============================================ -->
            <div style="background: <?php echo $metodo_pago_color; ?>; border-radius: 15px; padding: 1.5rem; margin: 1.5rem 0; border-left: 5px solid <?php echo $metodo_pago_border; ?>;">
                <h3 style="margin: 0 0 0.5rem 0; color: #2d3748;"><?php echo $metodo_pago_titulo; ?></h3>
                <p style="margin: 0; font-size: 1rem;"><?php echo $metodo_pago_texto; ?></p>
            </div>
            
            <!-- ============================================ -->
            <!-- PASARELAS DE PAGO ACTIVAS                    -->
            <!-- ============================================ -->
            <h3 style="margin-top: 1.5rem;">Pasarelas de pago disponibles</h3>
            <div class="metodos-pago">
                <?php if (count($metodos_activos) > 0): ?>
                    <?php foreach ($metodos_activos as $metodo): ?>
                        <div class="metodo-item">
                            <h4><?php echo $metodo['icono']; ?> <?php echo $metodo['nombre']; ?></h4>
                            <p><?php echo $metodo['desc']; ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color: #718096;">No hay pasarelas de pago configuradas actualmente.</p>
                <?php endif; ?>
            </div>
            
            <h3 style="margin-top: 2rem;">🔄 ¿Cómo se configuran los pagos?</h3>
            <p>Los métodos de pago se gestionan desde el panel de administración de la tienda. Si necesitas añadir o modificar algún método, contacta con el administrador.</p>
            
            <p style="font-size: 0.9rem; color: #718096; margin-top: 0.5rem;">
                💡 Los datos de pago se mostrarán al finalizar tu compra, una vez que hayas completado el formulario de entrega.
            </p>
        </section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: CONTACTO                           -->
        <!-- ============================================ -->
        <section id="contacto" class="seccion">
            <h2>📞 Contacto</h2>
            <p>¿Tienes alguna duda sobre envíos, pagos o tu pedido? Contáctanos por cualquiera de estos medios:</p>
            
            <div class="contacto-info">
                <div class="contacto-item">
                    <div class="contacto-icono">📧</div>
                    <h4>Email</h4>
                    <p><a href="mailto:<?php echo $sitio_email; ?>"><?php echo $sitio_email; ?></a></p>
                </div>
                
                <div class="contacto-item">
                    <div class="contacto-icono">📱</div>
                    <h4>Teléfono</h4>
                    <p><a href="tel:<?php echo $sitio_telefono; ?>"><?php echo $sitio_telefono; ?></a></p>
                </div>
                
                <div class="contacto-item">
                    <div class="contacto-icono">⏰</div>
                    <h4>Horario</h4>
                    <p><?php echo $horario; ?></p>
                </div>
            </div>
        </section>
        
        <!-- ============================================ -->
        <!-- BOTÓN VOLVER A LA TIENDA                    -->
        <!-- ============================================ -->
        <div style="text-align: center; margin: 2rem 0;">
            <a href="javascript:void(0)" class="btn-volver-tienda" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true'); window.location.href='index.php';">
                🏠 Volver a la tienda
            </a>
        </div>
    </div>
</main>

<script>
    document.querySelectorAll('.info-nav a').forEach(enlace => {
        enlace.addEventListener('click', function(e) {
            e.preventDefault();
            const destino = document.querySelector(this.getAttribute('href'));
            if (destino) {
                destino.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>

<?php
// Incluir footer
include 'footer.php';
$conn->close();
?>>
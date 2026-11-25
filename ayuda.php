<?php
// ============================================
// ayuda.php - Centro de Ayuda
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
$carrito_expiracion_horas = $config['carrito_expiracion_horas'] ?? 48;

// Obtener cliente actual
$cliente = obtenerClienteActual($conn);
$cliente_registrado = ($cliente['id'] ?? 0) > 0 && !empty($cliente['nombre']);

// Categorías para el menú
$categorias_menu = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");

// Pasar variables a JavaScript
echo '<script>';
echo 'window.clienteRegistrado = ' . ($cliente_registrado ? 'true' : 'false') . ';';
echo 'window.monedaSimbolo = "' . ($config['moneda_simbolo'] ?? '$') . '";';
echo '</script>';

// Incluir header
include 'header_tienda.php';
?>

<!-- ===== CONTENIDO DE AYUDA ===== -->
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
    
    .ayuda-nav {
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
    
    .ayuda-nav a {
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
    
    .ayuda-nav a:hover {
        background: <?php echo $color_primario; ?>;
        color: white;
        transform: translateY(-2px);
    }
    
    .ayuda-container {
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
    
    .pasos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }
    
    .paso-card {
        background: #f8fafc;
        border-radius: 20px;
        padding: 1.5rem;
        text-align: center;
        transition: transform 0.3s;
    }
    
    .paso-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(102,126,234,0.2);
    }
    
    .paso-numero {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: 700;
        margin: 0 auto 1rem;
    }
    
    .paso-card h4 {
        color: #2d3748;
        margin-bottom: 0.5rem;
        font-size: 1.1rem;
    }
    
    .paso-card p {
        font-size: 0.9rem;
        color: #718096;
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
    
    .faq-item {
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 0;
    }
    
    .faq-item:last-child {
        border-bottom: none;
    }
    
    .faq-pregunta {
        font-weight: 600;
        color: #2d3748;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        font-size: clamp(0.95rem, 3.5vw, 1rem);
    }
    
    .faq-pregunta:hover {
        color: <?php echo $color_primario; ?>;
    }
    
    .faq-respuesta {
        margin-top: 0.5rem;
        color: #718096;
        padding-left: 1rem;
        border-left: 2px solid <?php echo $color_primario; ?>;
        font-size: clamp(0.85rem, 3vw, 0.95rem);
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
        bottom: 100px;
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
    
    .aviso-importante {
        background: #fef5e7;
        border-left: 5px solid #ed8936;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin: 1.5rem 0;
    }
    
    .aviso-importante h4 {
        color: #744210;
        margin-bottom: 0.5rem;
    }
    
    .aviso-importante p {
        color: #744210;
        margin-bottom: 0;
    }
    
    .aviso-peligro {
        background: #fed7d7;
        border-left: 5px solid #f56565;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin: 1.5rem 0;
    }
    
    .aviso-peligro h4 {
        color: #c53030;
        margin-bottom: 0.5rem;
    }
    
    .aviso-peligro p {
        color: #c53030;
        margin-bottom: 0;
    }
    
    @media (max-width: 768px) {
        .welcome-header {
            padding: 2rem 1rem;
            border-radius: 0 0 20px 20px;
        }
        
        .ayuda-nav {
            margin: -1.5rem 1rem 2rem;
            border-radius: 30px;
        }
        
        .ayuda-nav a {
            padding: 0.5rem 0.8rem;
        }
        
        .seccion {
            padding: 1.2rem;
            border-radius: 20px;
        }
        
        .pasos-grid {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        
        .paso-card {
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
            bottom: 90px;
            right: 20px;
            width: 45px;
            height: 45px;
            font-size: 1.3rem;
        }
    }
    
    @media (max-width: 480px) {
        .ayuda-nav a {
            flex: 1 1 calc(50% - 0.5rem);
            justify-content: center;
            padding: 0.5rem 0.6rem;
            font-size: 0.75rem;
        }
        
        .seccion h2 {
            font-size: 1.3rem;
        }
        
        .paso-numero {
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
        }
    }
</style>

<main>
    <div class="welcome-header">
        <h1>👋 ¡Hola! ¿Necesitas ayuda?</h1>
        <p>Estamos aquí para que tu experiencia de compra sea fácil y segura.</p>
    </div>
    
    <nav class="ayuda-nav">
        <a href="#registro">📝 Registro</a>
        <a href="#compra">🛒 Compra</a>
        <a href="#tienda">🏪 Tiendas</a>
        <a href="#monedas">💱 Monedas</a>
        <a href="#correos">📧 Correos</a>
        <a href="#faqs">❓ FAQs</a>
        <a href="#contacto">📞 Contacto</a>
    </nav>
    
    <div class="ayuda-container">
        
        <!-- ============================================ -->
        <!-- SECCIÓN: REGISTRO                            -->
        <!-- ============================================ -->
        <section id="registro" class="seccion">
            <h2>📝 Cómo registrarse</h2>
            
            <div class="aviso-importante">
                <h4>📱 ¡Importante!</h4>
                <p>Al registrarte, debes usar <strong>tu propio número de móvil</strong> (el que usas a diario). Los números de móvil de otras personas (familiares, amigos, beneficiarios) se ingresan más adelante, en la sección de <strong>"Datos de entrega"</strong> cuando finalices tu compra.</p>
            </div>
            
            <div class="pasos-grid">
                <div class="paso-card">
                    <div class="paso-numero">1</div>
                    <h4>Agrega un producto</h4>
                    <p>Al agregar cualquier producto al carrito, aparecerá automáticamente el formulario de registro.</p>
                </div>
                
                <div class="paso-card">
                    <div class="paso-numero">2</div>
                    <h4>Completa tus datos</h4>
                    <p>Nombre completo, email, <strong>tu móvil personal</strong> y contraseña (mínimo 6 caracteres).</p>
                </div>
                
                <div class="paso-card">
                    <div class="paso-numero">3</div>
                    <h4>Verifica tu email</h4>
                    <p>Revisa tu bandeja de entrada y haz clic en el enlace de verificación que te enviamos.</p>
                </div>
                
                <div class="paso-card">
                    <div class="paso-numero">4</div>
                    <h4>¡Listo!</h4>
                    <p>Ya puedes comprar. Tu carrito se guardará aunque cierres el navegador.</p>
                </div>
            </div>
            
            <h3>📱 Dispositivos múltiples</h3>
            <p>Si ya tienes cuenta y entras desde otro dispositivo (computadora, tablet o teléfono diferente), el sistema te pedirá tu contraseña para vincular el nuevo equipo. Puedes tener hasta <strong>3 dispositivos vinculados</strong> a tu cuenta.</p>
            
            <div class="aviso-importante">
                <h4>💡 Recuerda:</h4>
                <p>El número de móvil que ingresas al registrarte es el <strong>tuyo propio</strong>. Si quieres que el pedido llegue a otra persona (regalo, familiar, etc.), eso lo especificarás en la sección <strong>"Datos de entrega"</strong> al finalizar la compra.</p>
            </div>
        </section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: COMPRA                              -->
        <!-- ============================================ -->
        <section id="compra" class="seccion">
            <h2>🛒 Cómo comprar paso a paso</h2>
            
            <h3>1. Explorar productos</h3>
            <p>Navega por nuestras categorías usando el botón de <strong>9 puntos</strong> (☰) en la parte superior derecha, o el botón de menú en la parte superior izquierda para más opciones.</p>
            
            <h3>2. Agregar al carrito</h3>
            <p>Selecciona la cantidad deseada y haz clic en <strong>"🛒 Añadir"</strong>. El producto se reserva por <strong><?php echo $carrito_expiracion_horas; ?> horas</strong> mientras completas tu compra.</p>
            
            <div class="aviso-importante">
                <h4>⚠️ Una tienda a la vez</h4>
                <p>El carrito solo permite productos de <strong>una tienda</strong> a la vez. Si intentas agregar productos de otra tienda, el sistema te mostrará un aviso con opciones.</p>
            </div>
            
            <h3>3. Gestionar carrito</h3>
            <p>Accede a tu carrito desde el botón <strong>🛒</strong> en la parte superior derecha (fondo negro). Puedes:</p>
            <ul>
                <li>✅ Modificar cantidades de productos</li>
                <li>🗑️ Eliminar productos o combos</li>
                <li>⏰ Ver el tiempo restante antes de que expire tu carrito</li>
            </ul>
            
            <h3>4. Finalizar compra</h3>
            <p>Dentro del carrito, haz clic en <strong>"✅ Finalizar Compra"</strong> e ingresa tu contraseña para confirmar que eres tú quien está realizando la compra.</p>
            
            <h3>5. Datos de entrega</h3>
            <p>Completa el formulario con la información de envío. Aquí puedes especificar:</p>
            <ul>
                <li>📱 <strong>El móvil de la persona que recibirá el pedido</strong> (si es para ti, pon el tuyo; si es para otra persona, pon el de esa persona)</li>
                <li>📍 Dirección completa (calle, número, municipio, provincia)</li>
            </ul>
            <p>El <strong>costo de envío</strong> se calcula automáticamente según el municipio que selecciones.</p>
            
            <h3>6. Formas de pago</h3>
            <p>Elige la forma que más te convenga. Cada una tiene sus ventajas:</p>
            
            <div class="aviso-importante">
                <h4>💡 Importante</h4>
                <p>No todas las tiendas ofrecen todas las formas de pago. <strong>Cada tienda decide qué formas acepta</strong>. Si no ves alguna, es porque esa tienda no la tiene activa.</p>
            </div>
            
            <h3>💳 Pago todo por adelantado</h3>
            <p>Pagas el <strong>total</strong> de tu compra al confirmar el pedido.</p>
            <ul>
                <li>✅ <strong>Ventaja</strong>: tu pedido se procesa de inmediato. <strong>No tienes que volver a la tienda</strong> ni hacer más pagos después. Ideal si compras para otra persona y quieres que todo quede resuelto desde el principio.</li>
                <li>💡 <strong>Ideal si</strong>: confías en la tienda y quieres terminar la compra en un solo paso.</li>
            </ul>
            
            <h3>📦 Pago después de la entrega (contra entrega)</h3>
            <p>Pagas <strong>cuando la persona que indicaste en "Datos de entrega" recibe el pedido</strong>.</p>
            <ul>
                <li>✅ <strong>Ventaja</strong>: muy seguro. <strong>No pagas hasta que tu familiar o amigo tenga el pedido en sus manos</strong>.</li>
                <li>⚠️ <strong>Ten en cuenta</strong>: te contactaremos varias veces para coordinar la entrega y el pago. Deberás <strong>volver a la tienda</strong> para completar el pago después de que se haya entregado la compra.</li>
                <li>💡 <strong>Ideal si</strong>: prefieres asegurarte de que la entrega se realizó antes de pagar.</li>
            </ul>
            
            <h3>💰 Pago parcial</h3>
            <p>Pagas un <strong>porcentaje ahora</strong> y el resto <strong>después de la entrega</strong>.</p>
            <ul>
                <li>✅ <strong>Ventaja</strong>: combina lo mejor de las dos opciones anteriores. Pagas una parte por adelantado y el resto cuando la persona que indicaste reciba el pedido.</li>
                <li>⚠️ <strong>Ten en cuenta</strong>: al igual que el pago después de la entrega, te contactaremos para coordinar el pago final y deberás <strong>volver a la tienda</strong> para completarlo.</li>
                <li>💡 <strong>Ideal si</strong>: quieres asegurar tu pedido sin pagar todo de una vez.</li>
            </ul>
            
            <h3>🔵 Pago automático (API)</h3>
            <p>El pago se confirma al instante y tu pedido pasa a procesarse automáticamente.</p>
            <ul>
                <li><strong>PayPal</strong> (pago con tu cuenta PayPal).</li>
                <li><strong>Stripe</strong> (pago con tarjeta, sin cuenta).</li>
                <li><strong>PayPal Tarjetas</strong> (pago con tarjeta, sin cuenta).</li>
                <li><strong>Otros métodos internacionales</strong> (según la tienda).</li>
            </ul>
            
            <h3>🟢 Pago manual</h3>
            <p>Realizas el pago y el equipo de la tienda lo confirma manualmente.</p>
            <ul>
                <li><strong>Transferencia USD</strong> (cuenta bancaria en USD).</li>
                <li><strong>Transferencia Clásica</strong> (tarjeta clásica cubana).</li>
            </ul>
            
            <div class="aviso-importante">
                <h4>💡 Recuerda</h4>
                <p>Cada tienda decide qué métodos acepta. Si no ves alguno, es porque esa tienda no lo tiene activo.</p>
            </div>
            
            <h3>💡 Importante</h3>
            <ul>
                <li>El monto que pagas es <strong>solo el precio de tu pedido más el costo de envío</strong> según la dirección de la persona que recibe la compra.</li>
                <li>Las comisiones de la pasarela las asume la tienda, <strong>no tú</strong>.</li>
                <li>Una vez confirmado el pago, recibirás un correo y podrás seguir tu pedido.</li>
            </ul>
            
            <h3>🔄 ¿Pago fallido o pendiente?</h3>
            <p>Si tu pago no se completó o necesitas volver a intentarlo, puedes hacerlo desde <strong>Mis compras</strong> cuando quieras.</p>
            
            <div style="text-align: center; margin: 1.5rem 0;">
                <a href="/mis_pedidos.php" class="btn-volver-tienda" style="padding: 0.7rem 1.5rem; font-size: 0.9rem;">
                    🛒 Ir a mis compras
                </a>
            </div>
            
            <h3>7. Realizar la compra</h3>
            <p>Revisa el resumen de tu pedido (productos + envío) y elige el método de pago que prefieras. Se te mostrarán los datos para realizar el pago.</p>
            
            <div class="aviso-importante">
                <h4>💡 Recomendación</h4>
                <p>Primero haz clic en <strong>"Ir a Pagar"</strong> para ver los datos de pago, luego realiza el pago y finalmente haz clic en <strong>"Finalizar la compra"</strong> para confirmar tu pedido.</p>
            </div>
            
            <p>El pedido quedará en estado <strong>pendiente de pago</strong> hasta que el administrador confirme tu pago.</p>
            
            <div class="aviso-peligro">
                <h4>⏰ Aviso importante</h4>
                <p>Los pedidos creados que no sean pagados en un plazo de <strong>48 horas</strong> serán cancelados automáticamente por el sistema. Esto libera el stock para que otros clientes puedan comprar. Si tu pedido se canceló, puedes volver a realizarlo cuando quieras.</p>
            </div>
            
            <h3>8. Acceder a tu cuenta</h3>
            <p>Usa el botón <strong>👤</strong> en la parte superior derecha para:</p>
            <ul>
                <li>👤 Ver tu perfil</li>
                <li>📋 Ver tus compras anteriores</li>
                <li>🔐 Cambiar tu contraseña</li>
                <li>🚪 Cerrar sesión</li>
            </ul>
        </section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: TIENDAS                             -->
        <!-- ============================================ -->
        <section id="tienda" class="seccion">
            <h2>🏪 Cómo funcionan las tiendas</h2>
            
            <h3>¿Qué es una tienda?</h3>
            <p>En nuestro marketplace, cada vendedor tiene su propia <strong>tienda</strong> dentro de la plataforma. Cada tienda tiene su propio nombre, logo y productos.</p>
            
            <h3>📋 Regla importante: una tienda por carrito</h3>
            <p>Para garantizar que tu pedido llegue completo y de un solo vendedor, <strong>cada carrito solo puede tener productos de UNA tienda</strong>.</p>
            
            <div class="aviso-importante">
                <h4>🏪 ¿Qué pasa si cambio de tienda?</h4>
                <p>Si ya tienes productos de la <strong>Tienda A</strong> en tu carrito e intentas agregar un producto de la <strong>Tienda B</strong>, aparecerá un aviso con las siguientes opciones:</p>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin: 1.5rem 0;">
                <div style="background: #ebf8ff; padding: 1rem; border-radius: 12px; border-left: 4px solid #4299e1;">
                    <h4 style="margin-bottom: 0.3rem;">🏪 Regresar a la tienda</h4>
                    <p style="font-size: 0.9rem; margin-bottom: 0;">Vuelve a la tienda donde tienes tu carrito para seguir comprando allí.</p>
                </div>
                <div style="background: #f0fff4; padding: 1rem; border-radius: 12px; border-left: 4px solid #48bb78;">
                    <h4 style="margin-bottom: 0.3rem;">💳 Pagar carrito actual</h4>
                    <p style="font-size: 0.9rem; margin-bottom: 0;">Finaliza tu compra en la tienda actual y ve al checkout.</p>
                </div>
                <div style="background: #fff5f5; padding: 1rem; border-radius: 12px; border-left: 4px solid #f56565;">
                    <h4 style="margin-bottom: 0.3rem;">🗑️ Cancelar y empezar nuevo</h4>
                    <p style="font-size: 0.9rem; margin-bottom: 0;">Vacía tu carrito y agrega el producto de la nueva tienda.</p>
                </div>
                <div style="background: #f7fafc; padding: 1rem; border-radius: 12px; border-left: 4px solid #a0aec0;">
                    <h4 style="margin-bottom: 0.3rem;">Seguir navegando</h4>
                    <p style="font-size: 0.9rem; margin-bottom: 0;">Cierra el aviso sin hacer cambios y sigue mirando productos.</p>
                </div>
            </div>
            
            <div class="aviso-importante">
                <h4>💡 Consejo práctico</h4>
                <p>Si necesitas comprar productos de <strong>varias tiendas diferentes</strong>, deberás realizar una compra por cada tienda. Es la única forma de que cada pedido llegue correctamente desde su respectivo vendedor.</p>
            </div>
            
            <h3>📱 Datos de entrega: ¿qué pongo?</h3>
            <p>Cuando llegues a la sección de <strong>Datos de entrega</strong> (en el checkout), aquí es donde especificas:</p>
            <ul>
                <li><strong>📱 Móvil del destinatario:</strong> Si el pedido es para ti, pon <strong>tu móvil</strong>. Si es un regalo o para otra persona, pon el <strong>móvil de esa persona</strong>.</li>
                <li><strong>📍 Dirección:</strong> La dirección completa donde debe llegar el pedido.</li>
                <li><strong>🏙️ Municipio y provincia:</strong> Para calcular el costo de envío.</li>
            </ul>
            <p><strong>Nota:</strong> El móvil que pusiste al registrarte es el <strong>tuyo propio</strong> para la cuenta. El móvil que pones en Datos de entrega es a <strong>quien va dirigido el pedido</strong> (puede ser el tuyo o el de otra persona).</p>
        </section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: MONEDAS (NUEVA)                     -->
        <!-- ============================================ -->
        <section id="monedas" class="seccion">
            <h2>💱 Monedas y precios</h2>
            
            <h3>¿En qué moneda veo los precios?</h3>
            <p>El sistema detecta automáticamente tu ubicación y muestra los precios en la moneda de tu país (USD, EUR, CUP, etc.), siempre que la tienda acepte esa moneda. Si la tienda no la acepta, verás los precios en USD.</p>
            
            <h3>🌍 Detección automática</h3>
            <p>Al entrar a la tienda, el sistema reconoce tu país y te muestra los precios en tu moneda. Por tu seguridad, esta detección se actualiza cada 5 minutos para evitar suplantaciones y proteger tu compra.</p>
            
            <div class="aviso-importante">
                <h4>💡 Si usas VPN</h4>
                <p>Si cambias de VPN, el sistema tardará unos minutos en detectar el cambio. Es una medida de seguridad para proteger tu cuenta y tus compras.</p>
            </div>
            
            <h3>🔄 Cambiar la moneda manualmente</h3>
            <p>Si estás registrado, verás un selector de moneda junto al nombre de la tienda. Puedes elegir entre las monedas que la tienda acepte (USD, EUR, CUP, etc.).</p>
            
            <div class="aviso-importante">
                <h4>⚠️ Carrito activo</h4>
                <p>Si tienes un carrito con productos de otra tienda, el selector se bloquea hasta que vacíes el carrito o realices la compra del carrito (solo se pueden comprar productos en una tienda a la vez).</p>
            </div>
            
            <h3>👤 ¿Y si no estoy registrado?</h3>
            <p>Verás los precios en la moneda detectada por tu ubicación. El selector de moneda solo está disponible para clientes registrados.</p>
        </section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: CORREOS                             -->
        <!-- ============================================ -->
        <section id="correos" class="seccion">
            <h2>📧 Correos que recibirás</h2>
            
            <p>Te enviaremos un correo en cada etapa de tu pedido para que siempre sepas en qué estado está:</p>
            
            <div style="margin: 1rem 0; display: grid; grid-template-columns: 1fr; gap: 0.8rem;">
                <div style="background: #f7fafc; padding: 0.8rem 1.2rem; border-radius: 10px; border-left: 4px solid #4299e1;">
                    <strong>✅ Pedido creado</strong> - Confirmación de que tu pedido se registró correctamente (estado: pendiente de pago).
                </div>
                <div style="background: #f0fff4; padding: 0.8rem 1.2rem; border-radius: 10px; border-left: 4px solid #48bb78;">
                    <strong>💰 Pago confirmado</strong> - El equipo de la tienda verificó tu pago. El pedido pasa a procesarse.
                </div>
                <div style="background: #ebf8ff; padding: 0.8rem 1.2rem; border-radius: 10px; border-left: 4px solid #3182ce;">
                    <strong>📦 Pedido empaquetado</strong> - Tus productos están listos para ser enviados.
                </div>
                <div style="background: #fef5e7; padding: 0.8rem 1.2rem; border-radius: 10px; border-left: 4px solid #ed8936;">
                    <strong>🚚 Pedido en transporte</strong> - Tu pedido está en camino. Entrega estimada: <strong>1-3 días hábiles</strong>.
                </div>
                <div style="background: #f0fff4; padding: 0.8rem 1.2rem; border-radius: 10px; border-left: 4px solid #38a169;">
                    <strong>✅ Pedido entregado</strong> - Confirmación de que la persona que indicaste recibió tu pedido.
                </div>
            </div>
            
            <p>Todos los correos incluyen el detalle completo de tu pedido, el costo de envío y la fecha estimada de entrega.</p>
            
            <div class="aviso-importante">
                <h4>📱 ¿No recibes nuestros correos?</h4>
                <p>Si tienes problemas para recibir los correos, contacta con <strong>soporte por WhatsApp</strong> para informar el problema. Te ayudaremos a resolverlo lo antes posible.</p>
            </div>
        </section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: FAQS                                -->
        <!-- ============================================ -->
        <section id="faqs" class="seccion">
            <h2>❓ Preguntas frecuentes</h2>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>⏱️ ¿Cuánto tarda la entrega?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Una vez confirmado el pago, el tiempo de entrega es de <strong>1 a 3 días hábiles</strong>.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>🔍 ¿Cómo sé si mi pago fue recibido?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Recibirás un correo de <strong>"Pago confirmado"</strong> y podrás ver el estado en <a href="/mis_pedidos.php">Mis compras</a>.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>❌ ¿Puedo cancelar un pedido?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Sí, desde <strong>Mis Compras</strong>, solo si el pedido está en estado <strong>Pendiente de pago</strong> (aún no has realizado el pago). Una vez confirmado el pago, contacta al administrador.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>📋 ¿Cómo veo mis compras?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Haz clic en el botón <strong>👤</strong> (superior derecha) y selecciona <strong>"Mis Compras"</strong> o <a href="/mis_pedidos.php">clic aquí</a>.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>🔐 ¿Olvidé mi contraseña?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    En el panel de cliente (👤), haz clic en <strong>"¿Olvidaste tu contraseña?"</strong> y te enviaremos una nueva por correo.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>⏰ ¿Qué pasa si mi carrito expira?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Los carritos expiran a las <strong><?php echo $carrito_expiracion_horas; ?> horas</strong> y el stock se libera automáticamente para que otros clientes puedan comprar.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>📱 ¿Qué móvil debo poner al registrarme?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Al registrarte, debes poner <strong>tu propio número de móvil</strong>. Si el pedido es para otra persona (regalo, familiar), el móvil de esa persona lo especificarás en la sección <strong>"Datos de entrega"</strong> al finalizar la compra.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>📦 ¿Puedo enviar un pedido a otra persona?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Sí, puedes enviar un pedido a cualquier persona. En la sección <strong>"Datos de entrega"</strong>, especifica el nombre, móvil y dirección de la persona que recibirá el pedido. El pago lo realizas tú con tu cuenta.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>💱 ¿Puedo cambiar la moneda?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Sí, si estás registrado. Verás un selector de moneda junto al nombre de la tienda. Solo puedes cambiar entre las monedas que la tienda acepte. Si tienes un carrito con productos de otra tienda, el selector se bloquea hasta que vacíes el carrito o realices la compra.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>🌍 ¿Por qué a veces veo los precios en otra moneda?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    El sistema detecta tu ubicación automáticamente para mostrarte los precios en tu moneda. Por seguridad, esta detección se actualiza cada 5 minutos. Si cambias de VPN, el sistema tardará unos minutos en detectar el cambio.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>🏪 ¿Puedo comprar productos de varias tiendas en un solo carrito?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    No. Cada carrito solo permite productos de <strong>una tienda</strong> a la vez. Si necesitas productos de varias tiendas, deberás realizar una compra por cada tienda.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>📱 ¿Cuántos dispositivos puedo vincular a mi cuenta?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Hasta <strong>3 dispositivos</strong>. Por seguridad, te recomendamos usar <strong>siempre el mismo dispositivo</strong> para iniciar sesión. Así evitas que otras personas que tengan acceso a un dispositivo vinculado puedan entrar a tu cuenta y usar tu identidad.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>📧 ¿Por qué no recibo los correos?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Si tienes problemas para recibir nuestros correos, contacta con <strong>soporte por WhatsApp</strong> para informar el problema. Te ayudaremos a resolverlo lo antes posible.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>🔄 ¿Qué hago si mi pago falló?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Puedes reintentar el pago desde <strong>Mis compras</strong> cuando quieras. También puedes cambiar de método de pago si el que elegiste no funcionó.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>💰 ¿Puedo pagar después de la entrega?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Sí, si la tienda lo permite. En ese caso, pagas cuando la persona que indicaste en <strong>Datos de entrega</strong> recibe el pedido. Te contactaremos para coordinar el pago.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>💳 ¿Las comisiones de pago las pago yo?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    No. Las comisiones de la pasarela las asume la tienda. Tú pagas <strong>solo el precio de tu pedido más el costo de envío</strong> según la dirección de la persona que recibe la compra.
                </div>
            </div>
            
            <div class="faq-item">
                <div class="faq-pregunta" onclick="toggleFaq(this)">
                    <span>⏰ ¿Qué pasa si no pago mi pedido?</span>
                    <span>▼</span>
                </div>
                <div class="faq-respuesta" style="display: none;">
                    Los pedidos no pagados se <strong>cancelan automáticamente tras 48 horas</strong>. Esto libera el stock para otros clientes. Si tu pedido se canceló, puedes volver a realizarlo cuando quieras.
                </div>
            </div>
        </section>
        
        <!-- ============================================ -->
        <!-- SECCIÓN: CONTACTO                            -->
        <!-- ============================================ -->
        <section id="contacto" class="seccion">
            <h2>📞 Contacto</h2>
            
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
        
        <div style="text-align: center; margin: 2rem 0;">
            <a href="javascript:void(0)" class="btn-volver-tienda" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true'); window.location.href='index.php';">
                🏠 Volver a la tienda
            </a>
        </div>
    </div>
</main>

<script>
    function toggleFaq(elemento) {
        const respuesta = elemento.nextElementSibling;
        const flecha = elemento.querySelector('span:last-child');
        
        if (respuesta.style.display === 'none' || respuesta.style.display === '') {
            respuesta.style.display = 'block';
            flecha.textContent = '▲';
        } else {
            respuesta.style.display = 'none';
            flecha.textContent = '▼';
        }
    }
    
    document.querySelectorAll('.ayuda-nav a').forEach(enlace => {
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
?>
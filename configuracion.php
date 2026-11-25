<?php
// ============================================
// PANEL DE CONFIGURACIÓN - CENTRALIZADO
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'header.php';
require_once 'conexion.php';
require_once 'includes/proveedor_utils.php';

// ============================================
// VERIFICAR PERMISOS CON FUNCIONES CENTRALIZADAS
// ============================================
$rol_usuario = $_SESSION['rol'] ?? '';
$es_proveedor = esRolProveedor($rol_usuario);
$es_admin = in_array($rol_usuario, ['Super Admin', 'Gerente']);

// Si no tiene ningún rol válido, redirigir
if (!$es_admin && !$es_proveedor) {
    header('Location: admin.php?error=permiso');
    exit;
}

// Obtener proveedor_id si es proveedor
$proveedor_id = 0;
$nombre_tienda = '';
if ($es_proveedor) {
    $proveedor_id = obtenerProveedorId($conn, $_SESSION['usuario_id'] ?? 0, $rol_usuario);
    $nombre_tienda = obtenerNombreTienda($conn, $proveedor_id);
}

// Definir secciones permitidas según rol
$secciones_permitidas = [];

if ($rol_usuario === 'Super Admin') {
    $secciones_permitidas = ['generales', 'carrito', 'email', 'pagos', 'envios', 'tasas', 'monedas'];
} elseif ($rol_usuario === 'Gerente') {
    $secciones_permitidas = ['carrito', 'email', 'pagos', 'envios', 'tasas', 'monedas'];
} elseif ($es_proveedor) {
    $secciones_permitidas = ['carrito', 'email', 'pagos', 'envios', 'monedas'];
}

// ============================================
// MENSAJES
// ============================================
$mensaje = '';
$tipo_mensaje = '';

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipo_mensaje = $_SESSION['tipo_mensaje'] ?? 'info';
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipo_mensaje']);
}
?>
<!-- ============================================
     ESTILOS ESPECÍFICOS DE CONFIGURACIÓN
     ============================================ -->
<style>
.config-container {
    width: 100%;
    max-width: 1200px;
    margin: 1rem auto;
    padding: 0 1rem;
}

@media (min-width: 768px) {
    .config-container {
        margin: 2rem auto;
        padding: 0 2rem;
    }
}

.config-header {
    text-align: center;
    margin-bottom: 1.5rem;
}

@media (min-width: 768px) {
    .config-header {
        margin-bottom: 2rem;
    }
}

.config-header h2 {
    font-size: 1.5rem;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

@media (min-width: 768px) {
    .config-header h2 {
        font-size: 2rem;
    }
}

.config-header p {
    color: #718096;
    font-size: 0.85rem;
}

.config-header .tienda-badge {
    display: inline-block;
    background: #dbeafe;
    color: #1e40af;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    margin-top: 0.3rem;
}

.config-dashboard {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}

@media (min-width: 640px) {
    .config-dashboard {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.25rem;
    }
}

@media (min-width: 1024px) {
    .config-dashboard {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
    }
}

.config-card {
    background: white;
    border-radius: 16px;
    padding: 1.25rem;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 1px solid #edf2f7;
}

@media (min-width: 768px) {
    .config-card {
        padding: 1.5rem;
    }
}

.config-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    border-color: var(--primary);
}

.config-card .icon {
    font-size: 2.5rem;
    margin-bottom: 0.75rem;
}

@media (min-width: 768px) {
    .config-card .icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
}

.config-card h3 {
    font-size: 1rem;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

@media (min-width: 768px) {
    .config-card h3 {
        font-size: 1.1rem;
    }
}

.config-card p {
    font-size: 0.7rem;
    color: #718096;
}

@media (min-width: 768px) {
    .config-card p {
        font-size: 0.75rem;
    }
}

.mensaje {
    position: fixed;
    top: 20px;
    right: 20px;
    left: 20px;
    padding: 0.8rem 1rem;
    border-radius: 10px;
    z-index: 1000;
    animation: slideIn 0.3s ease;
    word-break: break-word;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    font-size: 0.85rem;
}

@media (min-width: 768px) {
    .mensaje {
        top: 20px;
        right: 20px;
        left: auto;
        padding: 1rem 1.5rem;
        max-width: 400px;
    }
}

.mensaje.exito {
    background: #f0fff4;
    color: #22543d;
    border-left: 4px solid #48bb78;
}

.mensaje.error {
    background: #fff5f5;
    color: #742a2a;
    border-left: 4px solid #f56565;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.btn-volver {
    display: inline-block;
    margin-top: 1.5rem;
    padding: 0.6rem 1.2rem;
    background: #edf2f7;
    color: #4a5568;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    text-align: center;
}

.btn-volver:hover {
    background: #e2e8f0;
    transform: translateY(-2px);
}
</style>

<div class="config-container">
    <div class="config-header">
        <h2>⚙️ Panel de Configuración</h2>
        <p>
            Selecciona la sección que deseas configurar
            <?php if ($es_proveedor && !empty($nombre_tienda)): ?>
                <br><span class="tienda-badge">🏪 <?php echo htmlspecialchars($nombre_tienda); ?></span>
            <?php endif; ?>
        </p>
    </div>
    
    <?php if ($mensaje): ?>
        <div class="mensaje <?php echo $tipo_mensaje; ?>"><?php echo $mensaje; ?></div>
    <?php endif; ?>
    
        <div class="config-dashboard">
        <?php 
        // Metadatos de las secciones (misma fuente que header.php)
        $config_secciones_meta = [
            'generales' => [
                'icon' => '⚙️', 'titulo' => 'General', 'titulo_prov' => 'General',
                'url' => 'configuracion/generales.php',
                'desc' => 'Nombre, colores, redes sociales, contacto', 'desc_prov' => 'Nombre, colores, redes sociales, contacto',
            ],
            'carrito' => [
                'icon' => '🛒', 'titulo' => 'Carrito', 'titulo_prov' => 'Mi Carrito',
                'url' => 'configuracion/carrito.php',
                'desc' => 'Tiempo de expiración', 'desc_prov' => 'Tiempo de expiración',
            ],
            'email' => [
                'icon' => '📧', 'titulo' => 'Email', 'titulo_prov' => 'Mi Email',
                'url' => 'configuracion/email.php',
                'desc' => 'Configuración SMTP', 'desc_prov' => 'Configuración SMTP',
            ],
            'pagos' => [
                'icon' => '💳', 'titulo' => 'Pagos', 'titulo_prov' => 'Mis Pagos',
                'url' => 'configuracion/pagos.php',
                'desc' => 'PayPal, Transferencias', 'desc_prov' => 'PayPal, Transferencias',
            ],
            'envios' => [
                'icon' => '🚚', 'titulo' => 'Envíos', 'titulo_prov' => 'Mis Envíos',
                'url' => 'configuracion/envios.php',
                'desc' => 'Costos por municipio', 'desc_prov' => 'Costos por municipio',
            ],
            'tasas' => [
                'icon' => '💱', 'titulo' => 'Tasas de Cambio', 'titulo_prov' => 'Mis Tasas',
                'url' => 'configuracion/tasas.php',
                'desc' => 'Configuración de tasas', 'desc_prov' => 'Configuración de tasas',
            ],
            'monedas' => [
                'icon' => '🪙', 'titulo' => 'Monedas', 'titulo_prov' => 'Mis Monedas',
                'url' => 'configuracion/monedas.php',
                'desc' => 'Crear, editar, activar/desactivar monedas', 'desc_prov' => 'Activar monedas y configurar tasas',
            ],
        ];

        foreach ($secciones_permitidas as $sec):
            if (!isset($config_secciones_meta[$sec])) continue;
            $meta = $config_secciones_meta[$sec];
            $titulo = $es_proveedor ? $meta['titulo_prov'] : $meta['titulo'];
            $desc   = $es_proveedor ? $meta['desc_prov']   : $meta['desc'];
        ?>
            <div class="config-card" onclick="location.href='<?php echo htmlspecialchars($meta['url']); ?>'">
                <div class="icon"><?php echo $meta['icon']; ?></div>
                <h3><?php echo htmlspecialchars($titulo); ?></h3>
                <p><?php echo htmlspecialchars($desc); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div style="text-align: center; margin-top: 2rem;">
        <a href="admin.php" class="btn-volver">← Volver al Panel</a>
    </div>
</div>

<script>
setTimeout(function() {
    const mensaje = document.querySelector('.mensaje');
    if (mensaje) {
        mensaje.style.transition = 'opacity 0.5s';
        mensaje.style.opacity = '0';
        setTimeout(function() {
            if (mensaje) mensaje.remove();
        }, 500);
    }
}, 5000);
</script>

<?php
$conn->close();
?>
<?php
// ============================================
// ARCHIVOS REQUERIDOS PARA TODAS LAS PÁGINAS
// ============================================
require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';
require_once 'includes/carrito_funciones.php';
require_once 'includes/moneda_funciones.php';

if (!defined('SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

// ============================================
// VARIABLES QUE EL HEADER NECESITA (AUTOSUFICIENTE)
// ============================================

// 1. Obtener configuración del sitio (si no está definida)
if (!isset($config) || empty($config)) {
    $config = [];
    $result = $conn->query("SELECT clave, valor FROM configuracion");
    while($row = $result->fetch_assoc()) {
        $config[$row['clave']] = $row['valor'];
    }
}

// 2. Variables de configuración (valores por defecto si no existen)
if (!isset($sitio_nombre)) $sitio_nombre = $config['sitio_nombre'] ?? 'TiendaVirtual';
if (!isset($sitio_slogan)) $sitio_slogan = $config['sitio_slogan'] ?? 'Descubre productos increíbles';
if (!isset($color_primario)) $color_primario = $config['color_primario'] ?? '#667eea';
if (!isset($color_secundario)) $color_secundario = $config['color_secundario'] ?? '#764ba2';
if (!isset($logo_url)) $logo_url = $config['logo_url'] ?? '';

// (eliminadas — el cálculo real va más abajo, después de identificar al cliente)
// 3. Obtener cliente actual (si no está definido)
if (!isset($cliente)) {
    $cliente = obtenerClienteActual($conn);
}
$cliente_id = $cliente['id'] ?? 0;
$cliente_registrado = ($cliente_id > 0 && !empty($cliente['nombre']));

// ============================================
// DETERMINAR MONEDA DEL CLIENTE/PROVEEDOR
// ============================================
if (!isset($moneda_simbolo) || !isset($moneda_codigo)) {
    
    // 1. Detectar proveedor del contexto
    $proveedor_contexto = $proveedor_contexto ?? $proveedor_id ?? 0;
    
            // 2. Si la página no pasó el carrito → consultarlo aquí
    if (!isset($carrito_moneda_actual) && $cliente_registrado && function_exists('iniciarCarrito')) {
        $carrito_pendiente = iniciarCarrito($conn, $cliente_id);
        if ($carrito_pendiente && !empty($carrito_pendiente['moneda'])) {
            $carrito_prov = $carrito_pendiente['proveedor_id'] ?? 0;
            
            // Solo usar la moneda del carrito si:
            // - La página NO tiene proveedor (dashboard, mis_pedidos, etc.) → $proveedor_contexto == 0
            // - O el carrito es del MISMO proveedor que la página
            if ($proveedor_contexto == 0 || $carrito_prov == $proveedor_contexto) {
                $carrito_moneda_actual = $carrito_pendiente['moneda'];
                $carrito_proveedor_actual = $carrito_prov;
            }
        }
    }
    
        // 2.5. Leer moneda del SELECTOR (si aplica)
    $moneda_selector = null;
    if ($proveedor_contexto > 0 && $cliente_registrado) {
        $moneda_selector = $_SESSION['moneda_proveedor_' . $proveedor_contexto] ?? null;
    }
    
    // 2.6. ✅ Calcular SIEMPRE el país por IP (para el selector de prefijo telefónico)
    // Se calcula aquí para que $_SESSION['pais_detectado'] esté disponible
    // en todos los archivos, incluso si el cliente está registrado.
    $ahora_pais = time();
    $tiempo_cache_pais = 1200; // 20 minutos
    if (!isset($_SESSION['pais_detectado']) || 
        !isset($_SESSION['pais_detectado_time']) || 
        ($ahora_pais - $_SESSION['pais_detectado_time']) > $tiempo_cache_pais) {
        $_SESSION['pais_detectado'] = getPaisPorIP();
        $_SESSION['pais_detectado_time'] = $ahora_pais;
    }
    
    // 3. Determinar moneda con la nueva función
    $moneda_actual = getMonedaParaProveedor(
        $conn, 
        $proveedor_contexto, 
        $cliente, 
        $carrito_moneda_actual ?? $moneda_selector
    );
    
    $moneda_simbolo = getSimboloPorMoneda($moneda_actual);
    $moneda_codigo = $moneda_actual;
}

// 4. Obtener categorías para el menú (si no están definidas)
if (!isset($categorias_menu) || $categorias_menu === null) {
    $categorias_menu = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");
}

// 5. Pasar variables a JavaScript (solo una vez)
if (!isset($js_variables_pasadas)) {
    echo '<script>';
    echo 'window.clienteRegistrado = ' . ($cliente_registrado ? 'true' : 'false') . ';';
    echo 'window.monedaSimbolo = "' . $moneda_simbolo . '";';
    echo 'window.monedaCodigo = "' . $moneda_codigo . '";';
    echo 'window.proveedorActual = ' . intval($proveedor_contexto ?? 0) . ';';
    echo '</script>';
    $js_variables_pasadas = true;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sitio_nombre); ?> - <?php echo htmlspecialchars($sitio_slogan); ?></title>
    <link rel="stylesheet" href="estilo.css?v=<?php echo filemtime('estilo.css'); ?>">
    <!-- Archivo de funciones JavaScript -->
<script src="/includes/funciones_tienda.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/includes/funciones_tienda.js'); ?>"></script>
      <style>
        :root {
            --primary: <?php echo $color_primario; ?>;
            --secondary: <?php echo $color_secundario; ?>;
            --danger: #f56565;
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --radius-md: 8px;
            --radius-lg: 16px;
            --radius-full: 9999px;
        }
      
        body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
}       
        /* Botón carrito negro */
        .header-btn-carrito {
            background: #1a1a2e;
        }
        
        .header-btn-carrito:hover {
            background: #000000;
        }
        
        /* Contador del carrito */
        .carrito-contador-header {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            font-weight: bold;
            border: 2px solid white;
        }
        
        /* Botón de 9 puntos */
        .btn-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3px;
            width: 20px;
            height: 20px;
            margin: 0 auto;
        }
        
        .btn-grid span {
            width: 4px;
            height: 4px;
            background: white;
            border-radius: 50%;
            display: block;
        }
        
        /* ===== BUSCADOR ===== */
        .buscador-pc {
            display: block;
        }
        
        .buscador-pc form {
            margin: 0;
        }
        
        .buscador-pc input {
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 30px;
            padding: 0.5rem 1rem;
            color: white;
            font-size: 0.85rem;
            width: 180px;
            transition: all 0.3s ease;
        }
        
        .buscador-pc input::placeholder {
            color: rgba(255,255,255,0.7);
        }
        
        .buscador-pc input:focus {
            outline: none;
            background: rgba(255,255,255,0.3);
            width: 220px;
        }
        
        .btn-buscador-movil {
            display: none;
        }
        
        /* ===== MENÚ CLIENTE (DESDE ABAJO) ===== */
        .cliente-bottom-menu {
            position: fixed;
            bottom: -100%;
            left: 0;
            right: 0;
            width: 100%;
            background: white;
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
            z-index: 1003;
            transition: bottom 0.3s ease;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .cliente-bottom-menu.show {
            bottom: 0;
        }
        
        .cliente-bottom-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
            border-radius: 20px 20px 0 0;
        }
        
        .cliente-bottom-nombre {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .cliente-bottom-email {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-top: 0.2rem;
        }
        
        .cliente-bottom-items {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .cliente-bottom-items li {
            border-bottom: 1px solid #edf2f7;
            width: 100%;
            text-align: center;
        }
        
        .cliente-bottom-items a, .cliente-bottom-items .menu-item {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #4a5568;
            font-size: 1rem;
            cursor: pointer;
            background: none;
            border: none;
            width: 100%;
            text-align: center;
        }
        
        .cliente-bottom-items a:hover, .cliente-bottom-items .menu-item:hover {
            background: #f7fafc;
        }
        
        .bottom-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1002;
            display: none;
        }
        
        .bottom-overlay.show {
            display: block;
        }
        
        /* ===== MENÚ CATEGORÍAS LATERAL DERECHO ===== */
        .categorias-right-menu {
            position: fixed;
            top: 0;
            right: -320px;
            width: 300px;
            height: 100%;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            z-index: 1004;
            transition: right 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            box-shadow: -2px 0 20px rgba(0,0,0,0.3);
            overflow-y: auto;
        }
        
        .categorias-right-menu.show {
            right: 0;
        }
        
        .categorias-right-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .categorias-right-items {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .categorias-right-items li {
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .categorias-right-items a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            color: white;
            transition: all 0.25s;
            font-size: 0.9rem;
        }
        
        .categorias-right-items a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 1.8rem;
        }
        
        .right-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1003;
            display: none;
        }
        
        .right-overlay.show {
            display: block;
        }
        
        /* ===== MENÚ LATERAL IZQUIERDO (EXISTENTE) ===== */
        .side-menu {
            position: fixed;
            top: 0;
            left: -320px;
            width: 300px;
            height: 100%;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: white;
            z-index: 1002;
            transition: left 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            box-shadow: 2px 0 20px rgba(0,0,0,0.3);
            overflow-y: auto;
        }
        
        .side-menu.show {
            left: 0;
        }
        
        .side-menu-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1rem;
        }
        
        .side-menu-avatar {
            font-size: 3rem;
            margin-bottom: 0.5rem;
        }
        
        .side-menu-nombre {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .side-menu-email {
            font-size: 0.7rem;
            opacity: 0.7;
        }
        
        .side-menu-verificacion {
            background: #f56565;
            color: white;
            font-size: 0.65rem;
            padding: 0.2rem 0.5rem;
            border-radius: 20px;
            display: inline-block;
            margin-top: 0.5rem;
            cursor: pointer;
        }
        
        .side-menu-items {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .side-menu-items li {
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        
        .side-menu-items a, .side-menu-items .menu-item {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1.5rem;
            text-decoration: none;
            color: white;
            transition: all 0.25s;
            font-size: 0.9rem;
            cursor: pointer;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
        }
        
        .side-menu-items a:hover, .side-menu-items .menu-item:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 1.8rem;
        }
        
        .menu-icon {
            font-size: 1.2rem;
            width: 28px;
        }
        
        .menu-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 0.5rem 0;
        }
        
        .menu-title {
            padding: 0.8rem 1.5rem;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
        }
        
        .side-menu-footer {
            padding: 1rem;
            text-align: center;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 1rem;
            font-size: 0.7rem;
            opacity: 0.5;
        }
        
        .side-menu-footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
        }
        
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(3px);
            z-index: 1001;
            display: none;
        }
        
        .menu-overlay.show {
            display: block;
        }
        
        /* ===== FLECHA VOLVER ARRIBA ===== */
        .scroll-top-btn {
            position: fixed;
            bottom: 50px;
            right: 50px;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 5rem;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
            border: 2px solid white;
        }
        
        .scroll-top-btn.show {
            opacity: 1;
            visibility: visible;
        }
        
        .scroll-top-btn:hover {
            transform: scale(1.1);
        }
        
          /* Animación de rebote suave para la flecha */
@keyframes bounceArrow {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

/* Aplicar la animación al botón (solo si está visible) */
.scroll-top-btn.show {
    animation: bounceArrow 1.5s ease-in-out infinite;
}
          /* Centrar botón volver arriba en pantallas de PC */
@media (min-width: 769px) {
    .scroll-top-btn {
        left: 85% !important;
        transform: translateX(-50%) !important;
        right: auto !important;
        /* bottom: 30px;  mantén la misma distancia inferior o ajústala */
    }
}
        /* ===== MODALES ===== */
        .modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
    z-index: 2000;
    animation: fadeIn 0.3s ease;
    justify-content: center;    /* ← AGREGA ESTO */
    align-items: center;        /* ← AGREGA ESTO */
}
        
        .modal-contenido {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { transform: translate(-50%, -30%); opacity: 0; }
            to { transform: translate(-50%, -50%); opacity: 1; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 2rem;
            cursor: pointer;
            color: #a0aec0;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: #2d3748;
        }
        
       /* Notificaciones - solo texto, fondo transparente */
.notificacion {
    position: fixed;
    top: 30px;                /* Muy arriba, casi pegada al borde superior */
    right: 20px;              /* Alineada a la derecha */
    background: transparent !important;
    color: #000000 !important;
    padding: 0.5rem 1rem;
    border-radius: 0;
    box-shadow: none;
    z-index: 3000;
    animation: slideInRight 0.3s ease;
    text-shadow: 0 0 15px white, 0 0 10px rgba(255,255,255,0.6);
    font-weight: bold;
    font-size: 1rem;
}

.notificacion.error {
    background: transparent !important;
    color: #ffcccc;   /* Rojo claro para error, opcional */
}

.notificacion.warning {
    background: transparent !important;
    color: #ffeb99;   /* Amarillo claro para advertencia, opcional */
}
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        /* ===== PRODUCTOS ===== */
        .productos-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        @media (max-width: 1200px) {
            .productos-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .productos-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .productos-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .scroll-top-btn {
                width: 60px;
                height: 60px;
                font-size: 4rem;
                bottom: 30px;
                right: 30px;
            }
            
            body {
                padding-top: 80px;
            }
            
            .header-logo h1 {
                font-size: 1rem;
            }
            
            .header-btn {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
            }
            
            .header-buttons {
                gap: 0.5rem;
            }
            
            .buscador-pc {
                display: none;
            }
            
            .btn-buscador-movil {
                display: flex;
            }
        }
        
        /* Tarjetas de productos */
        .producto-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .producto-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -12px rgba(0,0,0,0.15);
        }
        
        .producto-etiquetas {
            position: absolute;
            top: 12px;
            left: 12px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .etiqueta-nuevo {
    background: #eab308;
    color: black;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
}

.etiqueta-rebaja {
    background: #84cc16;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
}

.etiqueta-agotado {
    background: #b91c1c;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
}
        
        .producto-imagen-wrapper {
            padding: 1rem;
            background: #fafbfc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 160px;
        }
        
        .producto-imagen {
            max-width: 100%;
            max-height: 140px;
            object-fit: contain;
        }
        
        .producto-info {
            padding: 0 1rem 1rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .producto-marca {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }
        
        .producto-nombre {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
            line-height: 1.3;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .producto-descripcion {
            color: #718096;
            font-size: 0.95rem;
            margin-bottom: 1.2rem;
            flex: 1;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }
        
        .producto-categoria {
            display: inline-block;
            background: #eef2ff;
            color: #4f46e5;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            margin-bottom: 0.8rem;
            align-self: flex-start;
        }
        
        .producto-precio {
            margin-bottom: 0.8rem;
        }
        
        .precio-original {
            font-size: 0.85rem;
            color: #ef4444;
            text-decoration: line-through;
            margin-right: 0.5rem;
        }
        
        .precio-final {
            font-size: 1.2rem;
            font-weight: 700;
            color: #10b981;
        }
        
        .precio-normal {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .producto-precio small {
            font-size: 0.7rem;
            color: #9ca3af;
        }
        
        .producto-acciones {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            margin-top: auto;
        }
        
        .cantidad-selector {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            background: #f3f4f6;
            border-radius: 30px;
            padding: 0.15rem;
        }
        
        .cantidad-btn {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            border: none;
            background: white;
            color: #374151;
            font-weight: bold;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cantidad-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        .cantidad-input {
            width: 29px;
            text-align: center;
            border: none;
            background: transparent;
            font-weight: 600;
            font-size: 0.85rem;
            color: #1f2937;
        }
        
        .btn-carrito {
            background: #10b981; 
            color: white;
            padding: 0.4rem 0.9rem;
            border-radius: 30px;
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn-carrito:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .producto-card {
                display: flex;
                flex-direction: row;
                align-items: stretch;
                min-height: 137px;
            }
            
            .producto-imagen-wrapper {
                width: 40%;
                padding: 0.5rem;
                min-height: 100%;
            }
            
            .producto-imagen {
                max-height: 105px;
            }
            
            .producto-info {
                width: 60%;
                padding: 0.6rem 0.8rem 0.6rem 0;
            }
            
            .precio-final, .precio-normal {
                font-size: 1rem;
            }
            
            .cantidad-btn {
                width: 22px;
                height: 22px;
                font-size: 0.8rem;
            }
            
            .cantidad-input {
                width: 26px;
                font-size: 0.75rem;
            }
            
            .btn-carrito {
                padding: 0.3rem 0.7rem;
                font-size: 0.7rem;
            }
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .section-title {
            text-align: center;
            margin: 2rem 0 1rem;
            color: #1f2937;
            font-size: 1.5rem;
        }
        
        /* Footer */
        .site-footer {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 1rem 0 0.8rem;
            margin-top: 2rem;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        
        .footer-col h4 {
            color: white;
            font-size: 0.85rem;
            margin-bottom: 0.6rem;
            padding-bottom: 0.2rem;
            border-bottom: 2px solid rgba(255,255,255,0.2);
        }
        
        .footer-col p, .footer-contacto li, .footer-enlaces li {
            color: rgba(255,255,255,0.8);
            font-size: 0.75rem;
            line-height: 1.4;
        }
        
        .footer-contacto, .footer-enlaces {
            list-style: none;
            padding: 0;
        }
        
        .footer-contacto li, .footer-enlaces li {
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .footer-social {
            display: flex;
            gap: 0.6rem;
            flex-wrap: wrap;
        }
        
        .social-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: rgba(255,255,255,0.15);
            border-radius: 50%;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.9rem;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .social-icon:hover {
            background: var(--primary);
            transform: translateY(-2px);
        }
        
        @media (max-width: 992px) {
            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
       @media (max-width: 768px) {
    .footer-grid {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .footer-contacto li, .footer-enlaces li {
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.3rem;
    }
    
    .footer-social {
        justify-content: center;
    }
    
    .footer-contacto a,
    .footer-enlaces a {
        word-break: break-all;
        overflow-wrap: anywhere;
        font-size: 0.7rem;
    }
}
        
        @media (max-width: 768px) {
            .categorias-right-menu {
                width: 60% !important;
                right: -60% !important;
            }
            
            .categorias-right-menu.show {
                right: 0 !important;
            }
        }
        
        @media (max-width: 768px) {
            .side-menu {
                width: 60% !important;
                left: -60% !important;
            }
            
            .side-menu.show {
                left: 0 !important;
            }
        }
        
        /* Menú lateral más compacto */
        .side-menu-items li a,
        .side-menu-items .menu-item {
            padding: 0.5rem 1.5rem;
        }
        
        .side-menu-header {
            padding: 1rem;
        }
        
        .menu-title {
            padding: 0.5rem 1.5rem;
        }
        
        .menu-divider {
            margin: 0.3rem 0;
        }
        
        .side-menu-footer {
            padding: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .side-menu-items li a,
            .side-menu-items .menu-item {
                padding: 0.4rem 1.2rem;
                font-size: 0.85rem;
            }
            
            .side-menu-header {
                padding: 0.8rem;
            }
            
            .menu-title {
                padding: 0.4rem 1.2rem;
                font-size: 0.65rem;
            }
            
            .side-menu-nombre {
                font-size: 0.85rem;
            }
            
            .side-menu-email {
                font-size: 0.65rem;
            }
        }
        
        /* Menú derecho más compacto */
        .categorias-right-header {
            padding: 0.8rem;
            font-size: 1.1rem;
        }
        
        .categorias-right-items li a {
            padding: 0.4rem 1.2rem;
            font-size: 1.0rem;
            gap: 0.6rem;
        }
        
        .categorias-right-items .menu-icon {
            font-size: 1.1rem;
            width: 24px;
        }
        
        @media (max-width: 768px) {
            .categorias-right-header {
                padding: 0.6rem;
                font-size: 1.1rem;
            }
            
            .categorias-right-items li a {
                padding: 0.35rem 1rem;
                font-size: 1.0rem;
                gap: 0.5rem;
            }
        }
        
        .buscador-pc input {
            pointer-events: auto !important;
            opacity: 1 !important;
            z-index: 101;
            position: relative;
        }
        
        /* Buscador más corto en móvil */
@media (max-width: 768px) {
    #buscarInputTitulo {
        width: 140px !important;
        font-size: 0.75rem !important;
        padding: 0.25rem 0.6rem !important;
    }
}
        
        /* Buscador más corto en móvil */
@media (max-width: 768px) {
    #buscarInputTitulo {
        width: 150px !important;
        font-size: 0.85rem !important;
        padding: 0.4rem 0.6rem !important;
    }
}
        
        /* Tamaños para PC (pantallas mayores a 768px) */
@media (min-width: 769px) {
    #buscarInputTitulo {
        width: 280px !important;
        font-size: 0.95rem !important;
        padding: 0.5rem 1rem !important;
    }
    
    #contador-productos {
        font-size: 1rem !important;
    }
}
         /* Círculo azul marino detrás del carrito */
.btn-carrito .carrito-icono {
    background: #1a2a4f;  /* Azul marino */
    display: inline-block;
    width: 24px;
    height: 24px;
    line-height: 24px;
    text-align: center;
    border-radius: 50%;
    font-size: 0.9rem;
    margin-left: -0.2rem;  /* Mueve el círculo a la izquierda */
    position: relative;
    left: -0.1rem;  /* Opcional: desplazamiento adicional */
}

/* Para móvil un poco más grande */
@media (max-width: 768px) {
    .btn-carrito .carrito-icono {
        width: 28px;
        height: 28px;
        line-height: 28px;
        font-size: 1rem;
        margin-left: -0.25rem;
    }
}
          @media (max-width: 768px) {
    .producto-card .btn-carrito {
        padding: 0.2rem 0.7rem !important;
        font-size: 0.7rem !important;
    }
}  
          
            @media (max-width: 768px) {
    #modalComboTienda .modal-contenido > div > div {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 1rem !important;
    }
}

/* Botón flotante de comentarios */
.btn-comentarios-flotante {
    position: fixed;
    bottom: 20px;
    left: 20px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-comentarios-flotante:hover {
    transform: scale(1.1);
}

@media (max-width: 768px) {
    .btn-comentarios-flotante {
        width: 45px;
        height: 45px;
        font-size: 1.3rem;
        bottom: 15px;
        left: 15px;
    }
}
 
          /* ============================================
   ETIQUETAS PARA TODAS LAS TARJETAS
   ============================================ */
.producto-card .etiqueta-agotado,
.producto-card-sin-carrito .etiqueta-agotado,
.producto-card-dashboard .etiqueta-agotado {
    background: #b91c1c;
    color: white;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
}

.producto-card .etiqueta-rebaja,
.producto-card-sin-carrito .etiqueta-rebaja,
.producto-card-dashboard .etiqueta-rebaja {
    background: #84cc16;
    color: #1e3a8a;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
}

.producto-card .etiqueta-nuevo,
.producto-card-sin-carrito .etiqueta-nuevo,
.producto-card-dashboard .etiqueta-nuevo {
    background: #eab308;
    color: black;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 0.2rem 0.8rem;
    border-radius: 20px;
}
          
 /* ============================================
   MENÚ CARRITO LATERAL DERECHO
   ============================================ */
/* ===== CONTENEDOR PRINCIPAL ===== */
.carrito-right-menu {
    position: fixed;
    top: 0;
    right: -360px;
    width: 360px;
    height: 100vh;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    color: white;
    z-index: 100006 !important;
    transition: right 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1);
    box-shadow: -2px 0 20px rgba(0,0,0,0.3);
    display: flex;
    flex-direction: column;
}

.carrito-right-menu.show {
    right: 0;
}

/* ===== HEADER FIJO ===== */
.carrito-right-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.2rem 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: #16213e;
    flex-shrink: 0;
}

.carrito-right-header span {
    font-size: 1.2rem;
    font-weight: 700;
    color: white;
}

.carrito-close-btn {
    background: none;
    border: none;
    font-size: 1.8rem;
    cursor: pointer;
    color: rgba(255,255,255,0.6);
    transition: color 0.3s;
    padding: 0 0.5rem;
    line-height: 1;
}

.carrito-close-btn:hover {
    color: #ef4444;
}

/* ===== CONTENEDOR DE PRODUCTOS ===== */
#contenidoCarritoLateral {
    flex: 1;
    overflow-y: auto;
    padding: 0 1.5rem 1rem 1.5rem;
    min-height: 0;
}

/* ===== FOOTER FIJO CON FONDO SÓLIDO ===== */
.carrito-footer {
    border-top: 1px solid rgba(255,255,255,0.1);
    padding: 1.2rem 1.5rem;
    background: #16213e;
    flex-shrink: 0;
    position: sticky;
    bottom: 0;
    z-index: 10;
    width: 100%;
    box-sizing: border-box;
}

.carrito-total {
    display: flex;
    justify-content: space-between;
    font-size: 1.2rem;
    font-weight: 700;
    color: white;
    margin-bottom: 0.8rem;
}

.carrito-total-monto {
    color: #10b981;
}

.carrito-item {
    display: flex;
    gap: 1rem;
    padding: 0.8rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    align-items: center;
}

.carrito-item-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 8px;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.1);
    flex-shrink: 0;
}

.carrito-item-info {
    flex: 1;
    min-width: 0;
}

.carrito-item-nombre {
    font-weight: 600;
    font-size: 0.9rem;
    color: white;
    margin-bottom: 0.2rem;
}

.carrito-item-precio {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.6);
}

.carrito-item-acciones {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-top: 0.3rem;
}

.carrito-item-subtotal {
    font-weight: 700;
    color: #10b981;
    font-size: 0.9rem;
    min-width: 70px;
    text-align: right;
    flex-shrink: 0;
}

#contenidoCarritoLateral .btn-cantidad {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.3);
    background: rgba(255,255,255,0.1);
    color: white;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

#contenidoCarritoLateral .btn-cantidad:hover {
    background: rgba(255,255,255,0.2);
    border-color: white;
}

#contenidoCarritoLateral .btn-eliminar {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid rgba(239,68,68,0.3);
    background: rgba(239,68,68,0.1);
    color: #ef4444;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
}

#contenidoCarritoLateral .btn-eliminar:hover {
    background: #ef4444;
    color: white;
}

.cantidad-texto {
    min-width: 30px;
    text-align: center;
    color: white;
    font-weight: 500;
}

.carrito-btn-checkout {
    width: 100%;
    padding: 0.8rem;
    border: none;
    border-radius: 10px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 0.5rem;
}

.carrito-btn-checkout:last-child {
    margin-bottom: 0;
}

.carrito-btn-checkout-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.carrito-btn-checkout-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(16,185,129,0.4);
}

.carrito-btn-checkout-secondary {
    background: rgba(255,255,255,0.1);
    color: white;
    border: 1px solid rgba(255,255,255,0.2);
}

.carrito-btn-checkout-secondary:hover {
    background: rgba(255,255,255,0.2);
}

.carrito-btn-checkout-danger {
    background: rgba(239,68,68,0.2);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
}

.carrito-btn-checkout-danger:hover {
    background: rgba(239,68,68,0.3);
}

.carrito-vacio {
    text-align: center;
    padding: 3rem 1rem;
}

.carrito-vacio-icono {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.carrito-vacio h3 {
    color: white;
    margin-bottom: 0.5rem;
}

.carrito-vacio p {
    color: rgba(255,255,255,0.6);
    margin-bottom: 1.5rem;
}

#carritoOverlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 100005 !important;
    display: none;
}

#carritoOverlay.show {
    display: block;
}

@media (max-width: 768px) {
    .carrito-right-menu {
        width: 75% !important;
        right: -75% !important;
    }
    .carrito-right-menu.show {
        right: 0 !important;
    }
    .carrito-item-img {
        width: 45px;
        height: 45px;
    }
    .carrito-item-nombre {
        font-size: 0.8rem;
    }
    .carrito-item-subtotal {
        font-size: 0.8rem;
        min-width: 60px;
    }
    #contenidoCarritoLateral .btn-cantidad {
        width: 24px;
        height: 24px;
        font-size: 0.7rem;
    }
    #contenidoCarritoLateral .btn-eliminar {
        width: 24px;
        height: 24px;
        font-size: 0.7rem;
    }
    .carrito-btn-checkout {
        font-size: 0.85rem;
        padding: 0.6rem;
    }
}

@media (max-width: 480px) {
    .carrito-right-menu {
        width: 82% !important;
        right: -82% !important;
    }
}
          
          /* ===== BOTONES EN LÍNEA ===== */
.carrito-botones-linea {
    display: flex;
    gap: 0.5rem;
    width: 100%;
}

.carrito-botones-linea .carrito-btn-checkout {
    flex: 1;
    padding: 0.5rem 0.8rem;
    border: none;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
    margin: 0;
}

.carrito-botones-linea .carrito-btn-checkout-primary {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.carrito-botones-linea .carrito-btn-checkout-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(16,185,129,0.4);
}

.carrito-botones-linea .carrito-btn-checkout-danger {
    background: rgba(239,68,68,0.2);
    color: #ef4444;
    border: 1px solid rgba(239,68,68,0.3);
}

.carrito-botones-linea .carrito-btn-checkout-danger:hover {
    background: rgba(239,68,68,0.3);
}
          
          /* Asegurar que los modales estén por encima del header */
.modal-overlay {
    z-index: 100000 !important; /* Mayor que header (99999) */
}

.modal-contenido {
    z-index: 100001 !important;
}
          
/* ===== HEADER CON BLOQUE CENTRADO ===== */
.site-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 0.3rem 0;
}

body {
    padding-top: 75px;
}

.header-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1rem;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 60px;
}

/* ===== BLOQUE CENTRADO (logo + contenido) ===== */
.header-bloque {
    display: flex;
    align-items: center;
    gap: 1.8rem;
    width: auto;
    flex-shrink: 0;
}

/* Logo */
.header-logo {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header-logo img {
    height: 90px;
    max-height: 100px;
    width: auto;
    object-fit: contain;
}

.header-logo span {
    font-size: 1.3rem;
    font-weight: 700;
    white-space: nowrap;
}

.header-logo h1 {
    font-size: 1.3rem;
    margin: 0;
    font-weight: 700;
    white-space: nowrap;
}

/* Columna derecha */
.header-derecha {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.1rem;
    flex-shrink: 0;
}

/* Línea 1 */
.header-fila-superior {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    flex-wrap: wrap;
}

.header-nombre-tienda {
    font-size: 1.6rem;
    font-weight: 600;
    white-space: nowrap;
}

/* Línea 2 - Estilos base (PC grande) */
.header-fila-inferior {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.2rem;
    flex-wrap: wrap;
}

/* Botones base */
.header-btn {
    background: rgba(255,255,255,0.15);
    border: none;
    color: white;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    border-radius: 14px;
    text-decoration: none;
    flex-shrink: 0;
}

.header-btn:hover {
    background: rgba(255,255,255,0.3);
    transform: scale(1.05);
}

.header-btn-carrito {
    background: rgba(0,0,0,0.3);
}

.header-btn-carrito:hover {
    background: rgba(0,0,0,0.5);
}

/* Botones con texto */
.header-btn-texto {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0 1.1rem;
    width: auto;
    height: 60px;
    background: rgba(255,255,255,0.1);
    border-radius: 15px;
    font-weight: 500;
}

.header-btn-texto:hover {
    background: rgba(255,255,255,0.25);
}

.header-btn-icono {
    font-size: 1.6rem;
}

.header-btn-label {
    font-size: 1rem;
    white-space: nowrap;
    display: inline;
}

/* Contador del carrito */
.carrito-contador-header {
    position: absolute;
    top: -4px;
    right: -4px;
    background: var(--danger);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.55rem;
    font-weight: bold;
    border: 2px solid white;
}

.btn-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2px;
    width: 16px;
    height: 16px;
}

.btn-grid span {
    width: 5px;
    height: 5px;
    background: white;
    border-radius: 50%;
    display: block;
}

/* ============================================ */
/* RESPONSIVE - RANGOS DESCENDENTES             */
/* ============================================ */

/* Tablets grandes (≤1100px) */
@media (max-width: 1100px) {
    body { padding-top: 65px; }
    
    .header-container { 
        min-height: 60px; 
        padding: 0 0.8rem; 
    }
    
    .header-bloque { 
        gap: 2rem; 
    }
    
    .header-logo img { 
        height: 65px; 
        max-height: 70px; 
    }
    
    .header-nombre-tienda { 
        font-size: 1.2rem; 
    }
    
    .header-fila-inferior { 
        gap: 0.5rem; 
    }
    
    .header-btn { 
        width: 50px; 
        height: 40px; 
        font-size: 1.6rem; 
        border-radius: 12px;
    }
    
    .header-btn-texto {
        width: auto;
        height: 40px;
        padding: 0 1rem;
        gap: 0.5rem;
        border-radius: 12px;
        font-size: 0.9rem;
    }
    
    .header-btn-icono {
        font-size: 1.3rem;
    }
    
    .header-btn-label {
        font-size: 0.8rem;
        display: inline;
    }
    
    .btn-grid { 
        width: 16px; 
        height: 16px; 
        gap: 2px;
    }
    
    .btn-grid span { 
        width: 3.5px; 
        height: 3.5px; 
    }
}

/* Tablets pequeñas y móviles grandes (≤800px) */
@media (max-width: 800px) {
    body { padding-top: 65px; }
    
    .header-container { 
        min-height: 58px; 
        padding: 0 0.6rem; 
    }
    
    .header-bloque { 
        gap: 0.8rem; 
    }
    
    .header-logo img { 
        height: 60px; 
        max-height: 65px; 
    }
    
    .header-nombre-tienda { 
        font-size: 1.1rem; 
    }
    
    .header-fila-inferior { 
        gap: 0.3rem; 
    }
    
    .header-btn { 
        width: 40px; 
        height: 40px; 
        font-size: 1.3rem; 
        border-radius: 10px;
    }
    
    .header-btn-texto {
        width: auto;
        height: 40px;
        padding: 0 0.8rem;
        gap: 0.3rem;
        border-radius: 10px;
        font-size: 0.75rem;
    }
    
    .header-btn-icono {
        font-size: 1.1rem;
    }
    
    .header-btn-label {
        font-size: 0.65rem;
        display: inline;
    }
    
    .btn-grid { 
        width: 14px; 
        height: 14px; 
        gap: 1.5px;
    }
    
    .btn-grid span { 
        width: 3px; 
        height: 3px; 
    }
}

/* Móviles medianos (≤600px) */
@media (max-width: 600px) {
    body { padding-top: 60px; }
    
    .header-container { 
        min-height: 52px; 
        padding: 0 0.5rem; 
    }
    
    .header-bloque { 
        gap: 0.6rem; 
    }
    
    .header-logo img { 
        height: 50px; 
        max-height: 55px; 
    }
    
    .header-nombre-tienda { 
        font-size: 1rem; 
    }
    
    .header-fila-inferior { 
        gap: 0.3rem; 
    }
    
    .header-btn { 
        width: 42px; 
        height: 42px; 
        font-size: 1.1rem; 
        border-radius: 10px;
    }
    
    .header-btn-texto {
        width: auto;
        height: 36px;
        padding: 0 0.6rem;
        gap: 0.2rem;
        border-radius: 8px;
        font-size: 0.65rem;
    }
    
    .header-btn-icono {
        font-size: 1rem;
    }
    
    .header-btn-label {
        font-size: 0.55rem;
        display: inline;
    }
    
    .btn-grid { 
        width: 13px; 
        height: 13px; 
        gap: 1.5px;
    }
    
    .btn-grid span { 
        width: 2.5px; 
        height: 2.5px; 
    }
}

/* Móviles pequeños (≤480px) - SIN TEXTO */
@media (max-width: 490px) {
    body { padding-top: 55px; }
    
    .header-container { 
        min-height: 48px; 
        padding: 0 0.4rem; 
    }
    
    .header-bloque { 
        gap: 0.4rem; 
    }
    
    .header-logo img { 
        height: 50px; 
        max-height: 55px; 
    }
    
    .header-nombre-tienda { 
        font-size: 0.9rem; 
    }
    
    .header-fila-inferior { 
        gap: 0.4rem; 
    }
    
    .header-btn-label {
        display: none;
    }
    
    .header-btn { 
        width: 38px; 
        height: 38px; 
        font-size: 1rem; 
        border-radius: 10px;
    }
    
    .header-btn-texto {
        width: 40px;
        height: 34px;
        padding: 0;
        justify-content: center;
        border-radius: 10px;
        background: rgba(255,255,255,0.15);
        font-size: 0;
    }
    
    .header-btn-icono {
        font-size: 1.1rem;
    }
    
    .btn-grid { 
        width: 15px; 
        height: 15px; 
        gap: 1.5px;
    }
    
    .btn-grid span { 
        width: 3.2px; 
        height: 3.2px; 
    }
    
    .carrito-contador-header { 
        width: 14px; 
        height: 14px; 
        font-size: 0.45rem; 
        top: -2px;
        right: -2px;
        border-width: 1.5px;
    }
}

/* Móviles muy pequeños (≤380px) */
@media (max-width: 380px) {
    body { padding-top: 52px; }
    
    .header-container { 
        min-height: 44px; 
        padding: 0 0.3rem; 
    }
    
    .header-bloque { 
        gap: 0.3rem; 
    }
    
    .header-logo img { 
        height: 46px; 
        max-height: 50px; 
    }
    
    .header-nombre-tienda { 
        font-size: 0.8rem; 
    }
    
    .header-fila-inferior { 
        gap: 0.4rem; 
    }
    
    .header-btn { 
        width: 32px; 
        height: 32px; 
        font-size: 0.85rem; 
        border-radius: 8px;
    }
    
    .header-btn-texto {
        width: 34px;
        height: 30px;
        border-radius: 8px;
    }
    
    .header-btn-icono {
        font-size: 1rem;
    }
    
    .btn-grid { 
        width: 15px; 
        height: 15px; 
        gap: 1px;
    }
    
    .btn-grid span { 
        width: 3px; 
        height: 3px; 
    }
    
    .carrito-contador-header { 
        width: 12px; 
        height: 12px; 
        font-size: 0.4rem; 
        top: -2px;
        right: -2px;
        border-width: 1.5px;
    }
}

/* Fondo blanco circular detrás del icono del carrito en el header */
.header-btn-carrito .header-btn-icono {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: #E5E4E2;
    border-radius: 50%;
    font-size: 1.1rem;
    line-height: 1;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
}

/* Ajustes por tamaño de pantalla */
@media (max-width: 800px) {
    .header-btn-carrito .header-btn-icono {
        width: 28px;
        height: 28px;
        font-size: 0.95rem;
    }
}

@media (max-width: 600px) {
    .header-btn-carrito .header-btn-icono {
        width: 30px;
        height: 30px;
        font-size: 1rem;
    }
}

@media (max-width: 490px) {
    .header-btn-carrito .header-btn-icono {
        width: 32px;
        height: 32px;
        font-size: 1.1rem;
    }
}

@media (max-width: 380px) {
    .header-btn-carrito .header-btn-icono {
        width: 28px;
        height: 28px;
        font-size: 0.95rem;
    }
}
    </style>
</head>
<body>
<?php include 'includes/skeleton_loader.php'; ?>
<!-- HEADER CON LOGO IZQUIERDA Y 2 LÍNEAS -->
<header class="site-header">
    <div class="header-container">
        <!-- BLOQUE CENTRADO (logo + contenido) -->
        <div class="header-bloque">
            <!-- LOGO -->
            <div class="header-logo">
                <?php
                if (!empty($logo_url)): 
                    $ruta_fisica_logo = $_SERVER['DOCUMENT_ROOT'] . $logo_url;
                    $version_logo = file_exists($ruta_fisica_logo) ? filemtime($ruta_fisica_logo) : time();
                ?>
                    <img src="<?php echo htmlspecialchars($logo_url) . '?v=' . $version_logo; ?>" alt="<?php echo htmlspecialchars($sitio_nombre); ?>">
                <?php else: ?>
                    <span><?php echo htmlspecialchars($sitio_nombre); ?></span>
                <?php endif; ?>
            </div>
            
            <!-- CONTENIDO DERECHA (2 líneas) -->
            <div class="header-derecha">
                <!-- Línea 1: Nombre -->
                <div class="header-fila-superior">
                    <span class="header-nombre-tienda"><?php echo htmlspecialchars($sitio_nombre); ?></span>
                </div>
                
                <!-- Línea 2: Botones -->
                <div class="header-fila-inferior">
                    <button class="header-btn header-btn-texto" onclick="toggleSideMenu()" title="Categorías">
                        <span class="header-btn-icono">☰</span>
                        <span class="header-btn-label">Menu</span>
                    </button>
                    <a href="/index.php" class="header-btn header-btn-texto" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
                        <span class="header-btn-icono">🏠</span>
                        <span class="header-btn-label">Inicio</span>
                    </a>
                    <a href="/ayuda.php" class="header-btn header-btn-texto">
                        <span class="header-btn-icono">❓</span>
                        <span class="header-btn-label">Ayuda</span>
                    </a>
                    <button class="header-btn header-btn-texto header-btn-carrito" onclick="window.abrirCarritoLateral()" title="Mi carrito" id="btnCarritoHeader">
                        <span class="header-btn-icono">🛒</span>
                        <span class="header-btn-label">Carrito</span>
                        <span class="carrito-contador-header" id="carritoContadorHeader" style="display: none;">0</span>
                    </button>
                    <button class="header-btn header-btn-texto" onclick="toggleClienteBottomMenu()" title="Mi cuenta">
                        <span class="header-btn-icono">👤</span>
                        <span class="header-btn-label">Cuenta</span>
                    </button>
                    <button class="header-btn header-btn-texto" onclick="toggleCategoriasRightMenu()" title="Categorías">
                        <span class="header-btn-icono">
                            <div class="btn-grid">
                                <span></span><span></span><span></span>
                                <span></span><span></span><span></span>
                                <span></span><span></span><span></span>
                            </div>
                        </span>
                        <span class="header-btn-label">Categorías</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>
    
<!-- MODAL BUSCADOR MÓVIL -->
<div class="modal-overlay" id="modalBuscadorMovil" style="display: none;">
    <div class="modal-contenido" style="max-width: 350px;">
        <span class="close-modal" onclick="cerrarModalBuscador()">&times;</span>
        <h3 style="margin-bottom: 1rem;">🔍 Buscar productos</h3>
        <form id="formBuscarMovil" onsubmit="buscarProductosAJAX(this.buscar.value); return false;">
            <input type="text" name="buscar" id="buscarInputMovil" placeholder="Escribe lo que deseas buscar..." 
                   style="width: 100%; padding: 0.8rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; margin-bottom: 1rem;">
            <button type="submit" style="width: 100%; padding: 0.8rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer;">
                🔍 Buscar
            </button>
        </form>
    </div>
</div>

<!-- MENÚ CLIENTE (DESDE ABAJO) -->
<div class="bottom-overlay" id="bottomOverlay" onclick="toggleClienteBottomMenu()"></div>
<div class="cliente-bottom-menu" id="clienteBottomMenu">
    <div class="cliente-bottom-header">
        <?php if ($cliente_registrado): ?>
            <div class="cliente-bottom-nombre"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellidos']); ?></div>
            <div class="cliente-bottom-email"><?php echo htmlspecialchars($cliente['email']); ?></div>
        <?php else: ?>
            <div class="cliente-bottom-nombre">Visitante</div>
            <div class="cliente-bottom-email">Regístrate para comprar</div>
        <?php endif; ?>
    </div>
    <ul class="cliente-bottom-items">
        <?php if ($cliente_registrado): ?>
            <li><div class="menu-item" onclick="cerrarClienteBottomMenu(); abrirPanelCliente();">👤 Mi Perfil</div></li>
            <li><div class="menu-item" onclick="cerrarClienteBottomMenu(); abrirCarrito();">🛒 Mi Carrito</div></li>
            <li><a href="/mis_pedidos.php" class="menu-item" onclick="cerrarClienteBottomMenu()">📋 Mis Compras</a></li>
            <li><a href="/ayuda.php" class="menu-item" onclick="cerrarClienteBottomMenu()">❓ Ayuda</a></li>
        <?php else: ?>
            <li><div class="menu-item" onclick="cerrarClienteBottomMenu(); abrirRegistro();">📝 Registrarse</div></li>
        <?php endif; ?>
    </ul>
</div>

<!-- MENÚ CATEGORÍAS LATERAL DERECHO -->
<div class="right-overlay" id="rightOverlay" onclick="toggleCategoriasRightMenu()"></div>
<div class="categorias-right-menu" id="categoriasRightMenu">
    <div class="categorias-right-header">
        📂 Categorías
    </div>
    <ul class="categorias-right-items">
        <li>
    <a href="/index.php?categoria=0<?php echo !empty($proveedor_slug) ? '&proveedor=' . urlencode($proveedor_slug) : ''; ?>" onclick="cerrarCategoriasRightMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        🏷️ Todos los productos
    </a>
</li>
<li>
    <a href="/index.php?filtro=combos<?php echo !empty($proveedor_slug) ? '&proveedor=' . urlencode($proveedor_slug) : ''; ?>" onclick="cerrarCategoriasRightMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        🎁 Combos especiales
    </a>
</li>
<?php 
if ($categorias_menu && $categorias_menu->num_rows > 0):
    $categorias_menu->data_seek(0);
    while($categoria = $categorias_menu->fetch_assoc()): 
?>
<li>
    <a href="/index.php?categoria=<?php echo $categoria['id']; ?><?php echo !empty($proveedor_slug) ? '&proveedor=' . urlencode($proveedor_slug) : ''; ?>" onclick="cerrarCategoriasRightMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        <?php echo $categoria['icono']; ?> <?php echo htmlspecialchars($categoria['nombre']); ?>
    </a>
</li>
<?php 
    endwhile;
endif; 
?>
    </ul>
</div>
    
    <!-- MENÚ CARRITO LATERAL DERECHO -->
<div id="carritoOverlay" class="carrito-overlay" onclick="window.cerrarCarritoLateral()"></div>
<div class="carrito-right-menu" id="carritoRightMenu">
    <div class="carrito-right-header">
        <span>🛒 Mi Carrito</span>
        <button class="carrito-close-btn" onclick="window.cerrarCarritoLateral()">✕</button>
    </div>
    <div id="contenidoCarritoLateral">
        <div style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.6);">
            <div class="spinner-modal" style="border-color: rgba(255,255,255,0.2); border-top-color: #10b981;"></div>
            <p>Cargando carrito...</p>
        </div>
    </div>
</div>

<!-- MENÚ LATERAL IZQUIERDO (EXISTENTE) -->
<div class="side-menu" id="sideMenu">
    <!-- Cliente -->
    <div class="side-menu-header" onclick="cerrarSideMenu(); toggleClienteBottomMenu();" style="cursor: pointer;">
        <div style="flex: 1;">
            <?php if ($cliente_registrado): ?>
                <div class="side-menu-nombre" style="font-size: 0.9rem;"><?php echo htmlspecialchars($cliente['nombre'] . ' ' . ($cliente['apellidos'] ?? '')); ?></div>
                <div class="side-menu-email" style="font-size: 0.7rem; opacity: 0.7;"><?php echo htmlspecialchars($cliente['email']); ?></div>
            <?php else: ?>
                <div class="side-menu-nombre" style="font-size: 0.9rem;">Visitante</div>
                <div class="side-menu-email" style="font-size: 0.7rem; opacity: 0.7;">Regístrate para comprar</div>
            <?php endif; ?>
        </div>
    </div>
    
    <ul class="side-menu-items">
        <!-- Separador -->
        <div class="menu-divider"></div>
        
        <!-- Inicio -->
        <li>
            <a href="shop.php" onclick="cerrarSideMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
    <span class="menu-icon">🏠</span>
    <span>Inicio</span>
</a>
<!-- Directorio de Tiendas -->
<li>
    <a href="directorio_proveedores.php" onclick="cerrarSideMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        <span class="menu-icon">🏪</span>
        <span>Directorio de Tiendas</span>
    </a>
</li>
        </li>
        
        <!-- Categorías (abre menú derecho) -->
        <li>
            <div class="menu-item" onclick="cerrarSideMenu(); toggleCategoriasRightMenu();">
                <span class="menu-icon">📂</span>
                <span>Categorías</span>
            </div>
        </li>
        
        <!-- Separador -->
        <div class="menu-divider"></div>
        
       <!-- Título Productos -->
        <div class="menu-title">Productos</div>
        
        <!-- Todos -->
<li>
    <a href="/index.php?categoria=0<?php echo !empty($proveedor_slug) ? '&proveedor=' . urlencode($proveedor_slug) : ''; ?>" onclick="cerrarSideMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        <span class="menu-icon">🏷️</span>
        <span>Todos</span>
    </a>
</li>

<!-- Combos -->
<li>
    <a href="/index.php?filtro=combos<?php echo !empty($proveedor_slug) ? '&proveedor=' . urlencode($proveedor_slug) : ''; ?>" onclick="cerrarSideMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        <span class="menu-icon">🎁</span>
        <span>Combos</span>
    </a>
</li>

<!-- Nuevos -->
<li>
    <a href="/index.php?filtro=nuevos<?php echo !empty($proveedor_slug) ? '&proveedor=' . urlencode($proveedor_slug) : ''; ?>" onclick="cerrarSideMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        <span class="menu-icon">🆕</span>
        <span>Nuevos</span>
    </a>
</li>

<!-- Más vendidos -->
<li>
    <a href="/index.php?filtro=mas_vendidos<?php echo !empty($proveedor_slug) ? '&proveedor=' . urlencode($proveedor_slug) : ''; ?>" onclick="cerrarSideMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        <span class="menu-icon">🔥</span>
        <span>Más vendidos</span>
    </a>
</li>

<!-- En oferta -->
<li>
    <a href="/index.php?filtro=ofertas<?php echo !empty($proveedor_slug) ? '&proveedor=' . urlencode($proveedor_slug) : ''; ?>" onclick="cerrarSideMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
        <span class="menu-icon">💸</span>
        <span>En oferta</span>
    </a>
</li>
        
        <!-- Separador -->
        <div class="menu-divider"></div>
        
        <!-- Ayuda -->
        <li>
            <a href="/ayuda.php" onclick="cerrarSideMenu()">
                <span class="menu-icon">❓</span>
                <span>Ayuda</span>
            </a>
        </li>
        
        <!-- Sobre nosotros -->
        <li>
            <a href="/ayuda.php?seccion=nosotros" onclick="cerrarSideMenu()">
                <span class="menu-icon">📖</span>
                <span>Sobre nosotros</span>
            </a>
        </li>
        
        <!-- Qué debe saber -->
        <li>
            <a href="que-debes-saber.php" onclick="cerrarSideMenu();">
                <span class="menu-icon">⚠️</span>
                <span>Qué debe saber</span>
            </a>
        </li>
        
        <!-- Contacto -->
        <li>
            <a href="/ayuda.php?seccion=contacto" onclick="cerrarSideMenu()">
                <span class="menu-icon">📞</span>
                <span>Contacto</span>
            </a>
        </li>
    </ul>
    
    <div class="side-menu-footer">
        <?php if ($cliente_registrado): ?>
            <a href="#" onclick="cerrarSesion()">Cerrar sesión</a>
        <?php else: ?>
            <a href="#" onclick="cerrarSideMenu(); abrirRegistro();">Iniciar sesión / Registrarse</a>
        <?php endif; ?>
    </div>
</div>

<!-- Overlay para cerrar menú izquierdo -->
<div class="menu-overlay" id="menuOverlay" onclick="cerrarSideMenu(); return false;"></div>

<!-- FLECHA VOLVER ARRIBA -->
<button class="scroll-top-btn" id="scrollTopBtn" title="Volver arriba">⇧</button>

<!-- MODALES -->
<?php include 'index_modales/registro.php'; ?>
<?php include 'index_modales/verificar_password.php'; ?>
<?php include 'index_modales/carrito_pendiente.php'; ?>
<?php include 'index_modales/carrito.php'; ?>
<?php include 'index_modales/sincronizar.php'; ?>
<?php include 'index_modales/password_cliente.php'; ?>
<?php include 'index_modales/editar_cliente.php'; ?>
<?php include 'index_modales/password_compra.php'; ?>
<?php include 'index_modales/datos_entrega.php'; ?>
<?php include 'index_modales/producto_tienda.php'; ?>
<?php include 'index_modales/combo_tienda.php'; ?>
<?php include 'index_modales/bienvenida.php'; ?>
<?php include 'index_modales/comentarios.php'; ?>

<!-- FUNCIONES PARA BUSCADOR MÓVIL (ESPECÍFICAS DEL HEADER) -->
<script>
function cerrarModalBuscador() {
    document.getElementById('modalBuscadorMovil').style.display = 'none';
}

function abrirModalBuscador() {
    document.getElementById('modalBuscadorMovil').style.display = 'block';
}
</script>

<!-- MODAL PARA VER IMAGEN GRANDE -->
<div id="modalImagenGrande" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; justify-content: center; align-items: center; cursor: pointer;">
    <img id="imagenGrande" src="" style="max-width: 98%; max-height: 98%; object-fit: contain;">
    <span style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
</div>

<script>
// Cerrar modal de imagen grande
document.getElementById('modalImagenGrande').onclick = function(e) {
    if (e.target === this || e.target.tagName === 'SPAN') {
        this.style.display = 'none';
    }
};
</script>

<!-- BOTÓN FLOTANTE COMENTARIOS -->
<button class="btn-comentarios-flotante" onclick="abrirModalComentarios()" title="Dejar comentario">
    💬
</button>
    
    <!-- ============================================ -->
<!-- FLECHA VOLVER ARRIBA                         -->
<!-- ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const scrollTopBtn = document.getElementById('scrollTopBtn');
    
    if (scrollTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollTopBtn.classList.add('show');
            } else {
                scrollTopBtn.classList.remove('show');
            }
        });

        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
});
</script>
</body>
</html>
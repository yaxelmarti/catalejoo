<?php
// ============================================
// DIRECTORIO DE PROVEEDORES - VERSIÓN ESTABLE
// ============================================

if (!defined('SEGURO')) {
    define('SEGURO', true);
}

// ============================================
// CONFIGURACIÓN DE SESIÓN SEGURA
// ============================================
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// ============================================
// CONFIGURACIÓN DE ERRORES (SOLO EN DESARROLLO)
// ============================================
error_reporting(0);
ini_set('display_errors', 0);

// ============================================
// ARCHIVOS REQUERIDOS
// ============================================
require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';
require_once 'includes/carrito_funciones.php';
require_once 'includes/moneda_funciones.php';
require_once 'includes/proveedor_utils.php';

registrarVisita($conn);

// ============================================
// OBTENER CONFIGURACIÓN
// ============================================
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
// El directorio no es una tienda específica:
// el header resolverá moneda del cliente o carrito pendiente.
// ============================================
$proveedor_contexto = 0;
// $moneda_simbolo y $moneda_codigo se calculan en header_tienda.php

// ============================================
// SANITIZAR Y VALIDAR ENTRADAS
// ============================================
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$buscar = htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8');
$buscar = substr($buscar, 0, 100);

// Validar que solo contenga caracteres seguros
if (!empty($buscar) && !preg_match('/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\-\.]+$/', $buscar)) {
    $buscar = ''; // Si tiene caracteres sospechosos, limpiar
}

$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$pagina_actual = max(1, $pagina_actual);

// ============================================
// PAGINACIÓN Y CONSULTA SEGURA
// ============================================
$por_pagina = 12;
$offset = ($pagina_actual - 1) * $por_pagina;

// Contar total (con Prepared Statements)
if (!empty($buscar)) {
    $count_sql = "SELECT COUNT(*) as total FROM proveedores WHERE activo = 1 AND (nombre_tienda LIKE ? OR descripcion LIKE ?)";
    $count_stmt = $conn->prepare($count_sql);
    $like_buscar = "%$buscar%";
    $count_stmt->bind_param("ss", $like_buscar, $like_buscar);
    $count_stmt->execute();
    $total_registros = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $count_result = $conn->query("SELECT COUNT(*) as total FROM proveedores WHERE activo = 1");
    $total_registros = $count_result->fetch_assoc()['total'];
}

$total_paginas = max(1, ceil($total_registros / $por_pagina));
$pagina_actual = min($pagina_actual, $total_paginas);
$offset = ($pagina_actual - 1) * $por_pagina;

// Consulta principal con límite y offset
if (!empty($buscar)) {
    $sql = "SELECT p.*, u.username, 
            (SELECT COUNT(*) FROM productos WHERE proveedor_id = p.id AND activo = 1) as total_productos
            FROM proveedores p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.activo = 1 AND (p.nombre_tienda LIKE ? OR p.descripcion LIKE ?)
            ORDER BY p.nombre_tienda ASC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssii", $like_buscar, $like_buscar, $por_pagina, $offset);
} else {
    $sql = "SELECT p.*, u.username, 
            (SELECT COUNT(*) FROM productos WHERE proveedor_id = p.id AND activo = 1) as total_productos
            FROM proveedores p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.activo = 1
            ORDER BY p.nombre_tienda ASC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $por_pagina, $offset);
}

$stmt->execute();
$proveedores = $stmt->get_result();

// ============================================
// CLIENTE ACTUAL
// ============================================
$cliente = obtenerClienteActual($conn);
$cliente_id = $cliente['id'] ?? 0;
$cliente_registrado = ($cliente_id > 0 && !empty($cliente['nombre']));

// ============================================
// INCLUIR HEADER (define window.clienteRegistrado y window.monedaSimbolo)
// ============================================
include 'index_modales/carrito.php';
include 'header_tienda.php';
?>

<!-- ============================================ -->
<!-- CONTENIDO PRINCIPAL                         -->
<!-- ============================================ -->
<style>
:root {
    --primary: <?php echo $color_primario; ?>;
    --secondary: <?php echo $color_secundario; ?>;
    --radius: 16px;
    --shadow: 0 4px 15px rgba(0,0,0,0.08);
    --shadow-hover: 0 15px 40px rgba(0,0,0,0.15);
}

.directorio-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

@media (min-width: 768px) {
    .directorio-container {
        margin: 3rem auto;
        padding: 0 2rem;
    }
}

.directorio-header {
    text-align: center;
    margin-bottom: 2.5rem;
}

.directorio-titulo {
    font-size: clamp(1.5rem, 5vw, 2.5rem);
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.directorio-subtitulo {
    font-size: clamp(0.9rem, 2vw, 1.1rem);
    color: #6b7280;
}

.directorio-buscador {
    max-width: 500px;
    margin: 1.5rem auto 0;
    display: flex;
    gap: 0.5rem;
}

@media (max-width: 480px) {
    .directorio-buscador {
        flex-direction: column;
        padding: 0 0.5rem;
    }
}

.directorio-buscador input {
    flex: 1;
    padding: 0.7rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 30px;
    font-size: 0.9rem;
    outline: none;
    transition: border-color 0.3s;
    -webkit-appearance: none;
}

.directorio-buscador input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.directorio-buscador button {
    padding: 0.7rem 1.5rem;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border: none;
    border-radius: 30px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
}

@media (max-width: 480px) {
    .directorio-buscador button {
        width: 100%;
    }
}

.directorio-buscador button:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 15px rgba(102,126,234,0.4);
}

.btn-limpiar-busqueda {
    display: inline-block;
    padding: 0.7rem 1.2rem;
    background: #e5e7eb;
    color: #374151;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-limpiar-busqueda:hover {
    background: #d1d5db;
}

.directorio-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

@media (min-width: 640px) {
    .directorio-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (min-width: 1024px) {
    .directorio-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

.directorio-tarjeta {
    cursor: pointer;
    border-radius: var(--radius);
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    box-shadow: var(--shadow);
    position: relative;
    aspect-ratio: 16 / 10;
    min-height: 200px;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
}

@media (max-width: 480px) {
    .directorio-tarjeta {
        aspect-ratio: 16 / 10;
        min-height: 160px;
    }
}

.directorio-tarjeta:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: var(--shadow-hover);
}

.directorio-tarjeta-imagen {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    position: relative;
    transition: transform 0.6s ease;
}

.directorio-tarjeta:hover .directorio-tarjeta-imagen {
    transform: scale(1.05);
}

.directorio-tarjeta-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1.5rem 1rem;
    text-align: center;
    color: white;
    transition: all 0.3s ease;
    background: rgba(0,0,0,0.45);
    backdrop-filter: blur(2px);
}

@media (max-width: 480px) {
    .directorio-tarjeta-overlay {
        padding: 1rem 0.8rem;
    }
}

.directorio-tarjeta:hover .directorio-tarjeta-overlay {
    background: rgba(0,0,0,0.55);
    backdrop-filter: blur(4px);
}

.directorio-tarjeta-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(8px);
    color: white;
    padding: 0.15rem 0.6rem;
    border-radius: 20px;
    font-size: 0.6rem;
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.25);
}

@media (min-width: 768px) {
    .directorio-tarjeta-badge {
        top: 14px;
        right: 14px;
        padding: 0.25rem 0.8rem;
        font-size: 0.75rem;
    }
}

@media (max-width: 480px) {
    .directorio-tarjeta-badge {
        font-size: 0.5rem;
        padding: 0.1rem 0.4rem;
        top: 6px;
        right: 6px;
    }
}

.directorio-tarjeta-icono {
    font-size: clamp(1.5rem, 4vw, 2.5rem);
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
}

.directorio-tarjeta-titulo {
    font-size: clamp(1rem, 3vw, 1.6rem);
    font-weight: 700;
    margin: 0;
    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
    line-height: 1.2;
}

.directorio-tarjeta-descripcion {
    font-size: clamp(0.65rem, 1.5vw, 0.9rem);
    opacity: 0.9;
    margin: 0.3rem 0 0.5rem;
    max-width: 90%;
    text-shadow: 0 1px 4px rgba(0,0,0,0.3);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@media (max-width: 480px) {
    .directorio-tarjeta-descripcion {
        -webkit-line-clamp: 1;
        font-size: 0.6rem;
    }
}

.directorio-tarjeta-info {
    display: flex;
    gap: 0.6rem;
    flex-wrap: wrap;
    justify-content: center;
    font-size: clamp(0.5rem, 1.2vw, 0.75rem);
    opacity: 0.8;
    margin-bottom: 0.6rem;
}

@media (max-width: 480px) {
    .directorio-tarjeta-info {
        gap: 0.3rem;
        font-size: 0.5rem;
        margin-bottom: 0.3rem;
    }
}

.directorio-tarjeta-boton {
    display: inline-block;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(8px);
    color: white;
    padding: 0.25rem 1rem;
    border-radius: 30px;
    font-size: clamp(0.65rem, 1.5vw, 1rem);
    font-weight: 600;
    border: 1px solid rgba(255,255,255,0.25);
    transition: all 0.3s ease;
    text-shadow: 0 1px 4px rgba(0,0,0,0.2);
}

@media (max-width: 480px) {
    .directorio-tarjeta-boton {
        padding: 0.15rem 0.7rem;
        font-size: 0.6rem;
    }
}

.directorio-tarjeta:hover .directorio-tarjeta-boton {
    background: white;
    color: #1f2937;
    transform: scale(1.05);
}

.directorio-vacio {
    text-align: center;
    padding: 4rem 1.5rem;
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    border: 1px solid #e5e7eb;
}

.directorio-vacio h3 {
    font-size: 1.5rem;
    color: #1f2937;
    margin: 1rem 0 0.5rem;
}

.directorio-vacio p {
    color: #6b7280;
    margin-bottom: 1.5rem;
}

.directorio-footer {
    text-align: center;
    margin-top: 2.5rem;
}

.directorio-btn-ver-todos {
    display: inline-block;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 0.8rem 2rem;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102,126,234,0.35);
}

@media (min-width: 768px) {
    .directorio-btn-ver-todos {
        padding: 0.8rem 2.5rem;
        font-size: 1.1rem;
    }
}

@media (max-width: 480px) {
    .directorio-btn-ver-todos {
        padding: 0.6rem 1.2rem;
        font-size: 0.85rem;
        display: block;
        margin: 0 0.5rem;
    }
}

.directorio-btn-ver-todos:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102,126,234,0.45);
}

.directorio-paginacion {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 2rem;
    flex-wrap: wrap;
    padding: 0 0.5rem;
}

.directorio-paginacion a,
.directorio-paginacion span {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.3s;
    min-width: 40px;
    text-align: center;
}

@media (max-width: 480px) {
    .directorio-paginacion a,
    .directorio-paginacion span {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
        min-width: 35px;
    }
}

.directorio-paginacion .pagina-actual {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
}

.directorio-paginacion .pagina-link {
    background: #f3f4f6;
    color: #1f2937;
}

.directorio-paginacion .pagina-link:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.directorio-paginacion .pagina-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.directorio-paginacion .pagina-elipsis {
    background: transparent;
    color: #6b7280;
}

/* Mensaje de seguridad para ataques XSS */
.directorio-alerta {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 0.8rem 1.2rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    color: #92400e;
    font-size: 0.9rem;
}

.directorio-alerta a {
    color: #92400e;
    text-decoration: underline;
}
</style>

<div class="directorio-container">
    
    <!-- HEADER -->
    <div class="directorio-header">
        <h1 class="directorio-titulo">🏪 Directorio de Tiendas</h1>
        <p class="directorio-subtitulo">
            <?php if ($total_registros > 0): ?>
                <?php echo $total_registros; ?> tiendas disponibles
            <?php else: ?>
                Pronto tendremos tiendas disponibles
            <?php endif; ?>
        </p>
        
        <form class="directorio-buscador" method="GET" action="" autocomplete="off">
            <input type="text" name="buscar" placeholder="🔍 Buscar tiendas..." 
                   value="<?php echo htmlspecialchars($buscar, ENT_QUOTES, 'UTF-8'); ?>"
                   maxlength="100"
                   aria-label="Buscar tiendas">
            <button type="submit">Buscar</button>
            <?php if (!empty($buscar)): ?>
                <a href="directorio_proveedores.php" class="btn-limpiar-busqueda">✕</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- GRID DE PROVEEDORES -->
    <?php if ($proveedores && $proveedores->num_rows > 0): ?>
        <div class="directorio-grid">
            <?php while($proveedor = $proveedores->fetch_assoc()): 
                $total_productos = intval($proveedor['total_productos'] ?? 0);
                $imagen_fondo = '';
                if (!empty($proveedor['imagen_url'])) {
                    $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $proveedor['imagen_url'];
                    $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
                    $imagen_fondo = 'url(' . htmlspecialchars($proveedor['imagen_url']) . '?v=' . $version . ') center/cover no-repeat';
                } else {
                    $imagen_fondo = 'linear-gradient(135deg, ' . $color_primario . ', ' . $color_secundario . ')';
                }
            ?>
                <div class="directorio-tarjeta" 
                     onclick="window.location.href='index.php?proveedor=<?php echo urlencode($proveedor['slug']); ?>&registrar_visita=1'"
                     role="link" 
                     tabindex="0"
                     aria-label="Ir a la tienda <?php echo htmlspecialchars($proveedor['nombre_tienda']); ?>">
                    <div class="directorio-tarjeta-imagen" style="background: <?php echo $imagen_fondo; ?>;">
                        <div class="directorio-tarjeta-overlay">
                            <span class="directorio-tarjeta-badge">📦 <?php echo $total_productos; ?> productos</span>
                            
                            <div class="directorio-tarjeta-header">
                                <span class="directorio-tarjeta-icono">🏪</span>
                                <h3 class="directorio-tarjeta-titulo"><?php echo htmlspecialchars($proveedor['nombre_tienda']); ?></h3>
                            </div>
                            
                            <?php if (!empty($proveedor['descripcion'])): ?>
                                <p class="directorio-tarjeta-descripcion">
                                    <?php echo htmlspecialchars(substr($proveedor['descripcion'], 0, 80)); ?>
                                    <?php if (strlen($proveedor['descripcion']) > 80) echo '...'; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="directorio-tarjeta-info">
                                <?php if (!empty($proveedor['telefono'])): ?>
                                    <span>📱 <?php echo htmlspecialchars($proveedor['telefono']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($proveedor['direccion'])): ?>
                                    <span>📍 <?php echo htmlspecialchars($proveedor['direccion']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="directorio-tarjeta-boton">Ver productos →</div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        
        <!-- PAGINACIÓN -->
        <?php if ($total_paginas > 1): ?>
        <div class="directorio-paginacion">
            <?php if ($pagina_actual > 1): ?>
                <a href="?pagina=<?php echo $pagina_actual - 1; ?><?php echo !empty($buscar) ? '&buscar=' . urlencode($buscar) : ''; ?>" 
                   class="pagina-link" aria-label="Página anterior">❮</a>
            <?php else: ?>
                <span class="pagina-disabled">❮</span>
            <?php endif; ?>
            
            <?php
            $rango = 2;
            $inicio = max(1, $pagina_actual - $rango);
            $fin = min($total_paginas, $pagina_actual + $rango);
            
            if ($inicio > 1): ?>
                <a href="?pagina=1<?php echo !empty($buscar) ? '&buscar=' . urlencode($buscar) : ''; ?>" class="pagina-link">1</a>
                <?php if ($inicio > 2): ?>
                    <span class="pagina-elipsis">…</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                <?php if ($i == $pagina_actual): ?>
                    <span class="pagina-actual"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?pagina=<?php echo $i; ?><?php echo !empty($buscar) ? '&buscar=' . urlencode($buscar) : ''; ?>" class="pagina-link"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($fin < $total_paginas): ?>
                <?php if ($fin < $total_paginas - 1): ?>
                    <span class="pagina-elipsis">…</span>
                <?php endif; ?>
                <a href="?pagina=<?php echo $total_paginas; ?><?php echo !empty($buscar) ? '&buscar=' . urlencode($buscar) : ''; ?>" class="pagina-link"><?php echo $total_paginas; ?></a>
            <?php endif; ?>
            
            <?php if ($pagina_actual < $total_paginas): ?>
                <a href="?pagina=<?php echo $pagina_actual + 1; ?><?php echo !empty($buscar) ? '&buscar=' . urlencode($buscar) : ''; ?>" 
                   class="pagina-link" aria-label="Página siguiente">❯</a>
            <?php else: ?>
                <span class="pagina-disabled">❯</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="directorio-vacio">
            <div style="font-size: 4rem; margin-bottom: 0.5rem;">🏪</div>
            <h3>No hay tiendas disponibles</h3>
            <p><?php echo !empty($buscar) ? 'No se encontraron tiendas con "' . htmlspecialchars($buscar) . '"' : 'Pronto tendremos tiendas en nuestro directorio.'; ?></p>
            <a href="index.php" class="directorio-btn-ver-todos">🛍️ Ver todos los productos</a>
            <?php if (!empty($buscar)): ?>
                <a href="directorio_proveedores.php" style="display: inline-block; margin-top: 1rem; color: var(--primary); text-decoration: underline;">← Volver al directorio</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- FOOTER -->
    <div class="directorio-footer">
        <a href="index.php" class="directorio-btn-ver-todos" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
            🛍️ Ver todos los productos
        </a>
    </div>
</div>

<?php
$conn->close();
?>
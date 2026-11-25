<?php
// ============================================
// ÁRBOL DE ARCHIVOS - SOLO LECTURA
// Muestra toda la estructura desde la carpeta donde se coloque
// ⚠️ ELIMINAR DESPUÉS DE USAR
// ============================================

ini_set('display_errors', 0);

$base_dir = __DIR__;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
$ocultar_vendor = isset($_GET['ocultar_vendor']) ? $_GET['ocultar_vendor'] === '1' : true;

// Carpetas a ignorar por defecto (ruido)
$ignorar_defecto = ['.', '..', '.git', 'node_modules'];
if ($ocultar_vendor) {
    $ignorar_defecto[] = 'vendor';
    $ignorar_defecto[] = 'cache';
}

function formatSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

function contarElementos($dir, $ignorar) {
    $total_archivos = 0;
    $total_carpetas = 0;
    $items = @scandir($dir);
    if (!$items) return [0, 0];
    
    foreach ($items as $item) {
        if (in_array($item, $ignorar)) continue;
        $ruta = $dir . '/' . $item;
        if (is_dir($ruta)) {
            $total_carpetas++;
            list($a, $c) = contarElementos($ruta, $ignorar);
            $total_archivos += $a;
            $total_carpetas += $c;
        } else {
            $total_archivos++;
        }
    }
    return [$total_archivos, $total_carpetas];
}

$stats_globales = ['archivos' => 0, 'carpetas' => 0, 'php' => 0, 'tamano' => 0];

function renderArbol($dir, $base_dir, $nivel, $ignorar, $buscar, &$stats) {
    $items = @scandir($dir);
    if (!$items) return;
    
    // Separar carpetas y archivos
    $carpetas = [];
    $archivos = [];
    
    foreach ($items as $item) {
        if (in_array($item, $ignorar)) continue;
        $ruta = $dir . '/' . $item;
        if (is_dir($ruta)) {
            $carpetas[] = $item;
        } else {
            $archivos[] = $item;
        }
    }
    
    sort($carpetas);
    sort($archivos);
    
    // Renderizar carpetas primero
    foreach ($carpetas as $carpeta) {
        $ruta_carpeta = $dir . '/' . $carpeta;
        $relativa = str_replace($base_dir . '/', '', $ruta_carpeta);
        
        // Si hay búsqueda y no coincide, verificar si tiene hijos que coincidan
        if ($buscar !== '') {
            list($archivos_hijo, $carpetas_hijo) = contarElementos($ruta_carpeta, $ignorar);
            $tiene_match = false;
            
            // Buscar recursivamente
            $iterador = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($ruta_carpeta, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterador as $archivo) {
                if (stripos($archivo->getFilename(), $buscar) !== false) {
                    $tiene_match = true;
                    break;
                }
            }
            
            if (!$tiene_match && stripos($carpeta, $buscar) === false) {
                continue; // No mostrar
            }
        }
        
        list($total_arch, $total_carp) = contarElementos($ruta_carpeta, $ignorar);
        $stats['carpetas']++;
        
        $indent = str_repeat('│  ', $nivel);
        $clase_extra = $buscar !== '' ? ' highlight' : '';
        
        echo '<div class="linea carpeta-linea' . $clase_extra . '">';
        echo '<span class="indent">' . $indent . '</span>';
        echo '<span class="icono">📁</span>';
        echo '<span class="nombre carpeta">' . htmlspecialchars($carpeta) . '/</span>';
        echo '<span class="meta">' . $total_arch . ' archivos · ' . $total_carp . ' carpetas</span>';
        echo '</div>';
        
        renderArbol($ruta_carpeta, $base_dir, $nivel + 1, $ignorar, $buscar, $stats);
    }
    
    // Renderizar archivos
    foreach ($archivos as $archivo) {
        $ruta_archivo = $dir . '/' . $archivo;
        $relativa = str_replace($base_dir . '/', '', $ruta_archivo);
        
        // Filtrar por búsqueda
        if ($buscar !== '' && stripos($archivo, $buscar) === false) {
            continue;
        }
        
        $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        $tamano = filesize($ruta_archivo);
        $fecha = filemtime($ruta_archivo);
        
        $stats['archivos']++;
        $stats['tamano'] += $tamano;
        if ($ext === 'php') $stats['php']++;
        
        $emoji = in_array($ext, ['php', 'html']) ? '🐘' : 
                 (in_array($ext, ['js']) ? '📜' : 
                 (in_array($ext, ['css']) ? '🎨' : 
                 (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']) ? '🖼️' : 
                 (in_array($ext, ['pdf']) ? '📕' : 
                 (in_array($ext, ['zip', 'rar', 'tar', 'gz']) ? '📦' : 
                 (in_array($ext, ['sql']) ? '🗄️' : 
                 (in_array($ext, ['txt', 'md']) ? '📝' : 
                 (in_array($ext, ['json', 'yml', 'yaml', 'xml']) ? '⚙️' : '📄'))))))));
        
        $indent = str_repeat('│  ', $nivel);
        $clase_extra = $buscar !== '' ? ' highlight' : '';
        
        echo '<div class="linea archivo-linea' . $clase_extra . '">';
        echo '<span class="indent">' . $indent . '</span>';
        echo '<span class="icono">' . $emoji . '</span>';
        echo '<span class="nombre">' . htmlspecialchars($archivo) . '</span>';
        echo '<span class="meta tamano">' . formatSize($tamano) . '</span>';
        echo '<span class="meta fecha">' . date('d/m/Y H:i', $fecha) . '</span>';
        echo '</div>';
    }
}

// Ejecutar el render (con stats vacíos primero)
ob_start();
renderArbol($base_dir, $base_dir, 0, $ignorar_defecto, $buscar, $stats_globales);
$arbol_html = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Árbol de Archivos - CataLejoo</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, monospace; background: #1e1e2e; color: #cdd6f4; padding: 1rem; font-size: 13px; }
.container { max-width: 1400px; margin: 0 auto; }
h1 { font-size: 1.3rem; margin-bottom: 0.5rem; color: #89b4fa; font-family: -apple-system, sans-serif; }
.aviso { background: #3d2020; border-left: 4px solid #f38ba8; padding: 0.7rem 1rem; border-radius: 6px; margin-bottom: 1rem; font-size: 0.85rem; color: #f38ba8; font-family: -apple-system, sans-serif; }
.buscador { display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; font-family: -apple-system, sans-serif; }
.buscador input[type="text"] { flex: 1; min-width: 200px; padding: 0.6rem 1rem; border: 2px solid #45475a; background: #181825; color: #cdd6f4; border-radius: 8px; font-size: 0.9rem; }
.buscador input[type="text"]:focus { outline: none; border-color: #89b4fa; }
.buscador button { padding: 0.6rem 1.2rem; background: #89b4fa; color: #1e1e2e; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
.buscador button:hover { background: #74c7ec; }
.buscador label { display: flex; align-items: center; gap: 0.4rem; color: #a6adc8; font-size: 0.85rem; cursor: pointer; }
.buscador a { padding: 0.6rem 1rem; background: #45475a; color: #cdd6f4; text-decoration: none; border-radius: 8px; font-size: 0.85rem; }
.buscador a:hover { background: #585b70; }
.stats { background: #181825; border-radius: 8px; padding: 0.8rem 1rem; margin-bottom: 1rem; display: flex; gap: 1.5rem; flex-wrap: wrap; font-size: 0.85rem; font-family: -apple-system, sans-serif; }
.stats span { color: #a6adc8; }
.stats strong { color: #89b4fa; }
.arbol { background: #181825; border-radius: 12px; padding: 1rem; overflow-x: auto; line-height: 1.7; }
.linea { white-space: nowrap; padding: 1px 4px; border-radius: 4px; }
.linea:hover { background: #313244; }
.carpeta-linea { color: #f9e2af; font-weight: 600; }
.archivo-linea { color: #cdd6f4; }
.indent { color: #45475a; user-select: none; }
.icono { margin-right: 6px; }
.nombre.carpeta { color: #f9e2af; }
.nombre { color: #cdd6f4; }
.meta { color: #6c7086; font-size: 0.75rem; margin-left: 1rem; }
.tamano { min-width: 70px; display: inline-block; text-align: right; }
.fecha { min-width: 110px; display: inline-block; text-align: right; }
.highlight { background: #f9e2af33; }
.vacio { text-align: center; padding: 2rem; color: #6c7086; }
.acciones { margin-top: 1rem; text-align: center; font-family: -apple-system, sans-serif; }
.acciones a { display: inline-block; padding: 0.6rem 1.2rem; background: #89b4fa; color: #1e1e2e; text-decoration: none; border-radius: 8px; font-weight: 600; }
@media (max-width: 768px) {
    body { font-size: 11px; padding: 0.5rem; }
    .meta.fecha { display: none; }
    .stats { font-size: 0.75rem; }
}
</style>
</head>
<body>
<div class="container">
    <h1>🌳 Árbol de Archivos — CataLejoo</h1>
    
    <div class="aviso">
        ⚠️ <strong>Eliminar después de usar.</strong> Este archivo expone la estructura del proyecto.
    </div>
    
    <form class="buscador" method="GET">
        <input type="text" name="buscar" placeholder="Buscar archivo (ej: pdf, email, pedido, generar)..." value="<?php echo htmlspecialchars($buscar); ?>">
        <label>
            <input type="checkbox" name="ocultar_vendor" value="1" <?php echo $ocultar_vendor ? 'checked' : ''; ?>>
            Ocultar vendor/cache
        </label>
        <button type="submit">🔍 Buscar</button>
        <?php if ($buscar): ?>
            <a href="?">Limpiar</a>
        <?php endif; ?>
    </form>
    
    <div class="stats">
        <span>📁 Carpetas: <strong><?php echo number_format($stats_globales['carpetas']); ?></strong></span>
        <span>📄 Archivos: <strong><?php echo number_format($stats_globales['archivos']); ?></strong></span>
        <span>🐘 PHP: <strong><?php echo number_format($stats_globales['php']); ?></strong></span>
        <span>💾 Tamaño total: <strong><?php echo formatSize($stats_globales['tamano']); ?></strong></span>
        <span>📍 Ruta base: <strong><?php echo htmlspecialchars(str_replace($_SERVER['DOCUMENT_ROOT'], '', $base_dir) ?: '/'); ?></strong></span>
    </div>
    
    <div class="arbol">
        <?php if ($stats_globales['archivos'] === 0 && $stats_globales['carpetas'] === 0): ?>
            <div class="vacio">📭 No se encontraron archivos <?php echo $buscar ? 'con "' . htmlspecialchars($buscar) . '"' : ''; ?></div>
        <?php else: ?>
            <?php echo $arbol_html; ?>
        <?php endif; ?>
    </div>
    
    <div class="acciones">
        <a href="?">🏠 Ver todo sin filtro</a>
    </div>
</div>
</body>
</html>
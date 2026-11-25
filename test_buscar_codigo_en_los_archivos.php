<?php
// ============================================
// BUSCADOR DE REFERENCIAS EN EL PROYECTO
// Colocar en la RAÍZ del proyecto
// Borrar después de usar
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Patrón a buscar (editable por URL: ?q=texto)
$patron = $_GET['q'] ?? '';
$ejecutar_busqueda = isset($_GET['q']) && trim($_GET['q']) !== '';

// Directorio raíz de búsqueda
$raiz = __DIR__;

// Extensiones a revisar
$extensiones = ['php', 'js', 'html', 'htm', 'css', 'txt', 'sql'];

// Directorios a excluir (para no buscar en vendor, node_modules, etc.)
$excluir_dirs = ['.git', 'node_modules', 'vendor', '.idea', '.vscode'];

$resultados = [];
$total_archivos = 0;
$total_coincidencias = 0;

function buscarEnDirectorio($dir, $patron, $extensiones, $excluir_dirs, &$resultados, &$total_archivos, &$total_coincidencias) {
    $items = @scandir($dir);
    if (!$items) return;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $ruta = $dir . DIRECTORY_SEPARATOR . $item;
        
        // Excluir directorios
        if (is_dir($ruta)) {
            if (in_array($item, $excluir_dirs)) continue;
            buscarEnDirectorio($ruta, $patron, $extensiones, $excluir_dirs, $resultados, $total_archivos, $total_coincidencias);
            continue;
        }
        
        // Solo procesar archivos con extensión permitida
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensiones)) continue;
        
        // Evitar buscar en este mismo archivo
        if ($item === basename(__FILE__)) continue;
        
        $total_archivos++;
        
        $contenido = @file_get_contents($ruta);
        if ($contenido === false) continue;
        
        $lineas = explode("\n", $contenido);
        foreach ($lineas as $num => $linea) {
            if (stripos($linea, $patron) !== false) {
                $resultados[] = [
                    'archivo' => str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $ruta),
                    'linea' => $num + 1,
                    'texto' => trim($linea)
                ];
                $total_coincidencias++;
            }
        }
    }
}

if ($ejecutar_busqueda) {
    buscarEnDirectorio($raiz, $patron, $extensiones, $excluir_dirs, $resultados, $total_archivos, $total_coincidencias);
}

// ============================================
// MOSTRAR RESULTADOS
// ============================================
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscador de referencias</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; margin: 0; padding: 1rem; color: #1e293b; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { font-size: 1.3rem; margin-bottom: 1rem; }
        .form-busqueda { background: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .form-busqueda label { font-weight: 600; font-size: 0.9rem; display: block; margin-bottom: 0.3rem; }
        .form-busqueda input[type="text"] { width: 100%; padding: 0.6rem 0.8rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; box-sizing: border-box; }
        .form-busqueda button { margin-top: 0.6rem; padding: 0.6rem 1.2rem; background: #667eea; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 0.9rem; }
        .form-busqueda button:hover { background: #5568d3; }
        .resumen { background: #dbeafe; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; border: 1px solid #bfdbfe; color: #1e40af; }
        .sin-resultados { background: #f0fff4; padding: 1rem; border-radius: 8px; border: 1px solid #48bb78; color: #22543d; font-weight: 600; }
        .resultado { background: white; padding: 0.8rem 1rem; border-radius: 8px; margin-bottom: 0.5rem; border-left: 4px solid #f56565; box-shadow: 0 1px 4px rgba(0,0,0,0.05); }
        .resultado .archivo { font-weight: 700; color: #991b1b; font-size: 0.85rem; font-family: monospace; }
        .resultado .linea-num { color: #64748b; font-size: 0.8rem; margin-left: 0.5rem; }
        .resultado .texto { font-family: monospace; font-size: 0.82rem; color: #334155; margin-top: 0.3rem; background: #f8fafc; padding: 0.4rem 0.6rem; border-radius: 4px; white-space: pre-wrap; word-break: break-all; }
        .aviso { background: #fef3c7; padding: 0.8rem 1rem; border-radius: 8px; border: 1px solid #fcd34d; color: #92400e; font-size: 0.85rem; margin-bottom: 1rem; }
        code { background: #f1f5f9; padding: 0.1rem 0.4rem; border-radius: 4px; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Buscador de referencias en el proyecto</h1>
    
    <div class="aviso">
        ⚠️ <strong>Importante:</strong> Borra este archivo (<code>De busqueda de codigo</code>) después de usarlo.
    </div>
    
    <div class="form-busqueda">
        <form method="GET">
            <label for="q">Patrón de búsqueda (regex):</label>
            <input type="text" name="q" id="q" value="<?php echo htmlspecialchars($patron); ?>">
            <button type="submit">Buscar</button>
        </form>
    </div>
    
    <?php if ($ejecutar_busqueda): ?>
<div class="resumen">
    <strong>Patrón:</strong> <code><?php echo htmlspecialchars($patron); ?></code><br>
    <strong>Archivos analizados:</strong> <?php echo $total_archivos; ?><br>
    <strong>Coincidencias encontradas:</strong> <?php echo $total_coincidencias; ?>
</div>
<?php endif; ?>
    
    <?php if (!$ejecutar_busqueda): ?>
    <!-- Página en blanco esperando búsqueda -->
<?php elseif (empty($resultados)): ?>
    <div class="sin-resultados">
        ✅ No se encontraron coincidencias para el patrón <code><?php echo htmlspecialchars($patron); ?></code>.
    </div>
<?php else: ?>
        <?php foreach ($resultados as $r): ?>
            <div class="resultado">
                <div class="archivo">
                    📄 <?php echo htmlspecialchars($r['archivo']); ?>
                    <span class="linea-num">línea <?php echo $r['linea']; ?></span>
                </div>
                <div class="texto"><?php echo htmlspecialchars($r['texto']); ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
<?php
// ============================================
// INCREMENTAR CONTADOR DE VISITAS DEL COMBO
// ============================================
$conn->query("UPDATE combos SET vistas = vistas + 1 WHERE id = " . $combo['id']);

// Asegurar helper de proveedores (por si quien incluye no lo tiene)
if (!function_exists('obtenerNombreTienda')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/proveedor_utils.php';
}

// Asegurar funciones de moneda (convertirMoneda, etc.)
if (!function_exists('convertirMoneda')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/moneda_funciones.php';
}

// Fallback (el header normalmente ya las define)
if (!isset($moneda_simbolo)) $moneda_simbolo = '$';
if (!isset($moneda_codigo)) $moneda_codigo = 'USD';
if (!isset($moneda_actual)) $moneda_actual = 'USD';
if (!isset($sitio_nombre)) $sitio_nombre = 'TiendaVirtual';

$horas_creacion = floor((time() - strtotime($combo['fecha_creacion'])) / 3600);
$es_nuevo = $horas_creacion < 48;

// ============================================
// CONVERSIÓN DE PRECIOS DEL COMBO
// ============================================
$precio_combo_usd = floatval($combo['precio']);
$precio_final_combo_usd = floatval($combo['precio_final']);
$prov_id_combo = intval($combo['proveedor_id'] ?? 0);

// Asegurar $cliente (por si quien incluye no lo tiene)
if (!isset($cliente)) {
    $cliente = obtenerClienteActual($conn);
}

// Calcular la moneda del combo según su proveedor
$moneda_combo = getMonedaParaProveedor($conn, $prov_id_combo, $cliente, null);
$simbolo_combo = getSimboloPorMoneda($moneda_combo);

if ($moneda_combo !== 'USD') {
    $precio_combo = convertirMoneda($precio_combo_usd, 'USD', $moneda_combo, $conn, $prov_id_combo);
    $precio_final_combo = convertirMoneda($precio_final_combo_usd, 'USD', $moneda_combo, $conn, $prov_id_combo);
} else {
    $precio_combo = $precio_combo_usd;
    $precio_final_combo = $precio_final_combo_usd;
}
?>

<div class="producto-card" data-combo-id="<?php echo $combo['id']; ?>" onclick="abrirModalComboTienda(<?php echo $combo['id']; ?>)">
    <div class="producto-etiquetas">
        <?php if ($combo['stock_combo'] <= 0): ?>
            <span class="etiqueta-agotado">AGOTADO</span>
        <?php else: ?>
            <?php if ($es_nuevo): ?>
                <span class="etiqueta-nuevo">NUEVO</span>
            <?php endif; ?>
            <?php if ($combo['porcentaje_rebaja'] > 0): ?>
                <span class="etiqueta-rebaja">-<?php echo $combo['porcentaje_rebaja']; ?>%</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div class="producto-imagen-wrapper">
        <?php 
        $ruta_img = $combo['imagen_url'] ?? '/uploads/combos/default.webp';
        $ruta_fisica = $_SERVER['DOCUMENT_ROOT'] . $ruta_img;
        $version = file_exists($ruta_fisica) ? filemtime($ruta_fisica) : time();
        ?>
        <img src="<?php echo htmlspecialchars($ruta_img) . '?v=' . $version; ?>" 
             alt="<?php echo htmlspecialchars($combo['nombre']); ?>" 
             class="producto-imagen"
             loading="lazy"
             onclick="verImagenGrande(this.src)">
    </div>
    
        <div class="producto-info">
        <div class="producto-marca">
            <?php 
            if (!empty($combo['nombre_tienda'])) {
                echo '🏪 ' . htmlspecialchars($combo['nombre_tienda']);
            } elseif (!empty($combo['proveedor_id'])) {
                echo '🏪 ' . htmlspecialchars(obtenerNombreTienda($conn, $combo['proveedor_id']));
            } else {
                echo htmlspecialchars($sitio_nombre);
            }
            ?>
        </div>
        
        <h3 class="producto-nombre"><?php echo htmlspecialchars($combo['nombre']); ?></h3>
        
        <?php if ($productos_distintos > 0 || $unidades_totales > 0): ?>
            <div class="combo-stats" style="font-size: 0.7rem; color: #718096; margin-bottom: 0.5rem;">
                📦 <?php echo $productos_distintos; ?> prod - <?php echo $unidades_totales; ?> und
            </div>
        <?php endif; ?>
        
                                <div class="producto-precio">
            <?php if ($combo['porcentaje_rebaja'] > 0 && $combo['precio_final'] > 0): ?>
                <span class="precio-original">
                    <?php echo $simbolo_combo; ?><?php echo number_format($precio_combo, 2); ?>
                </span>
                <span class="precio-final">
                    <?php echo $simbolo_combo; ?><?php echo number_format($precio_final_combo, 2); ?>
                </span>
            <?php else: ?>
                <span class="precio-normal">
                    <?php echo $simbolo_combo; ?><?php echo number_format($precio_combo, 2); ?>
                </span>
            <?php endif; ?>
            <small><?php echo $moneda_combo; ?></small>
        </div>
        
       <div class="producto-acciones" style="<?php echo $combo['stock_combo'] <= 0 ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
    <div class="cantidad-selector" data-combo-id="<?php echo $combo['id']; ?>">
        <button class="cantidad-btn" onclick="event.stopPropagation(); event.preventDefault(); cambiarCantidadCombo(this, -1)">−</button>
        <input type="text" class="cantidad-input" value="1" maxlength="3" readonly>
        <button class="cantidad-btn" onclick="event.stopPropagation(); event.preventDefault(); cambiarCantidadCombo(this, 1)">+</button>
    </div>
    
    <button class="btn-carrito" onclick="event.stopPropagation(); agregarComboAlCarrito(<?php echo $combo['id']; ?>, this)">
        <span class="carrito-icono">🛒</span> Añadir
    </button>
</div>
    </div>
</div>
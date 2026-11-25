<?php
// Asegurar helpers
if (!function_exists('obtenerNombreTienda')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/proveedor_utils.php';
}
if (!function_exists('convertirMoneda')) {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/moneda_funciones.php';
}

// Fallback de variables
if (!isset($moneda_simbolo)) $moneda_simbolo = '$';
if (!isset($moneda_codigo)) $moneda_codigo = 'USD';
if (!isset($moneda_actual)) $moneda_actual = 'USD';
if (!isset($sitio_nombre)) $sitio_nombre = 'TiendaVirtual';

// ============================================
// CONVERSIÓN DE PRECIOS
// ============================================
$precio_base_usd = floatval($producto['precio'] ?? 0);
$precio_original_usd = floatval($producto['precio_original'] ?? 0);
$prov_id_producto = intval($producto['proveedor_id'] ?? 0);

// Asegurar $cliente (por si quien incluye no lo tiene)
if (!isset($cliente)) {
    $cliente = obtenerClienteActual($conn);
}

// Si estamos en global (sin proveedor), calcular moneda por producto
if (isset($proveedor_contexto) && $proveedor_contexto == 0) {
    $moneda_producto = getMonedaParaProveedor($conn, $prov_id_producto, $cliente, null);
    $simbolo_producto = getSimboloPorMoneda($moneda_producto);
} else {
    $moneda_producto = $moneda_actual;
    $simbolo_producto = $moneda_simbolo;
}

if ($moneda_producto !== 'USD') {
    $precio_final = convertirMoneda($precio_base_usd, 'USD', $moneda_producto, $conn, $prov_id_producto);
    $precio_original = ($precio_original_usd > 0) 
        ? convertirMoneda($precio_original_usd, 'USD', $moneda_producto, $conn, $prov_id_producto)
        : 0;
} else {
    $precio_final = $precio_base_usd;
    $precio_original = $precio_original_usd;
}

// Detectar rebaja
$tiene_rebaja = ($producto['porcentaje_rebaja'] ?? 0) > 0;
?>

<style>
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

.btn-carrito .carrito-icono {
    background: #1a2a4f;
    display: inline-block;
    width: 24px;
    height: 24px;
    line-height: 24px;
    text-align: center;
    border-radius: 50%;
    font-size: 0.9rem;
    margin-left: -0.2rem;
    position: relative;
    left: -0.1rem;
}

@media (max-width: 768px) {
    .btn-carrito .carrito-icono {
        width: 28px;
        height: 28px;
        line-height: 28px;
        font-size: 1rem;
        margin-left: -0.25rem;
    }
    
    .producto-card .btn-carrito {
        padding: 0.2rem 0.7rem !important;
        font-size: 0.7rem !important;
    }
}
</style>

<div class="producto-card" data-producto-id="<?php echo $producto['id']; ?>" onclick="abrirModalProductoTienda(<?php echo $producto['id']; ?>)">
   <div class="producto-etiquetas">
    <?php if ($producto['cantidad'] <= 0): ?>
        <span class="etiqueta-agotado">AGOTADO</span>
    <?php else: ?>
        <?php if ($es_nuevo): ?>
            <span class="etiqueta-nuevo">NUEVO</span>
        <?php endif; ?>
        <?php if ($tiene_rebaja): ?>
            <span class="etiqueta-rebaja">-<?php echo $producto['porcentaje_rebaja']; ?>%</span>
        <?php endif; ?>
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
    <?php 
    if (!empty($producto['nombre_tienda'])) {
        echo '🏪 ' . htmlspecialchars($producto['nombre_tienda']);
    } elseif (!empty($producto['proveedor_id'])) {
        echo '🏪 ' . htmlspecialchars(obtenerNombreTienda($conn, $producto['proveedor_id']));
    } else {
        echo !empty($producto['marca']) ? htmlspecialchars($producto['marca']) : htmlspecialchars($sitio_nombre);
    }
    ?>
</div>
        
        <h3 class="producto-nombre"><?php echo htmlspecialchars($producto['nombre']); ?></h3>
        
        <?php if (!empty($producto['categoria_nombre'])): ?>
            <span class="producto-categoria">
                <?php echo $producto['categoria_icono'] ?? '🏷️'; ?> 
                <?php echo htmlspecialchars($producto['categoria_nombre']); ?>
            </span>
        <?php endif; ?>
        
              <div class="producto-precio">
    <?php if ($precio_original > 0 && $precio_original > $precio_final): ?>
        <span class="precio-original">
            <?php echo $simbolo_producto; ?><?php echo number_format($precio_original, 2); ?>
        </span>
        <span class="precio-final">
            <?php echo $simbolo_producto; ?><?php echo number_format($precio_final, 2); ?>
        </span>
    <?php else: ?>
        <span class="precio-normal">
            <?php echo $simbolo_producto; ?><?php echo number_format($precio_final, 2); ?>
        </span>
    <?php endif; ?>
    <small><?php echo $moneda_producto; ?></small>
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
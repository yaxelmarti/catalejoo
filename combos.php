<?php
// ============================================
// CONFIGURAR SESIÓN Y COOKIES SEGURAS
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
// REQUERIR ARCHIVOS NECESARIOS
// ============================================
require_once 'conexion.php';
require_once 'includes/cliente_identificador.php';
require_once 'includes/carrito_funciones.php';
require_once 'includes/moneda_funciones.php';
require_once 'includes/proveedor_utils.php';

// ============================================
// MARCAR ACCESO SEGURO (necesario para el header)
// ============================================
if (!defined('SEGURO')) {
    define('SEGURO', true);
}

// ============================================
// OBTENER CLIENTE ACTUAL
// ============================================
$cliente = obtenerClienteActual($conn);
$cliente_id = $cliente['id'] ?? 0;
$cliente_registrado = ($cliente_id > 0 && !empty($cliente['nombre']));

// ============================================
// DETECTAR PROVEEDOR (URL o usuario logueado)
// ============================================
$proveedor_slug = isset($_GET['proveedor']) ? trim($_GET['proveedor']) : '';
$proveedor_id = 0;
$proveedor_nombre = '';

if (!empty($proveedor_slug)) {
    $stmt_prov = $conn->prepare("SELECT id, nombre_tienda FROM proveedores WHERE slug = ? AND activo = 1");
    $stmt_prov->bind_param("s", $proveedor_slug);
    $stmt_prov->execute();
    $result_prov = $stmt_prov->get_result();
    if ($row_prov = $result_prov->fetch_assoc()) {
        $proveedor_id = $row_prov['id'];
        $proveedor_nombre = $row_prov['nombre_tienda'];
    }
    $stmt_prov->close();
}

// Si no hay slug en URL, pero el usuario es proveedor logueado, filtrar por su proveedor
if ($proveedor_id === 0 && isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] > 0) {
    // proveedor_utils.php ya está cargado al inicio
    $proveedor_id_usuario = obtenerProveedorId($conn, $_SESSION['usuario_id'], $_SESSION['rol'] ?? '');
    $es_proveedor = esRolProveedor($_SESSION['rol'] ?? '');
    
    if ($es_proveedor && $proveedor_id_usuario > 0) {
        $proveedor_id = $proveedor_id_usuario;
        // Obtener nombre de la tienda
        $stmt_n = $conn->prepare("SELECT nombre_tienda FROM proveedores WHERE id = ? AND activo = 1");
        $stmt_n->bind_param("i", $proveedor_id);
        $stmt_n->execute();
        $res_n = $stmt_n->get_result()->fetch_assoc();
        $proveedor_nombre = $res_n['nombre_tienda'] ?? '';
        $stmt_n->close();
    }
}

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
// La moneda la determina el header (usa $proveedor_contexto
// + carrito pendiente + moneda del cliente)
// ============================================
$proveedor_contexto = $proveedor_id; // 0 si no hay tienda específica

// ============================================
// OBTENER CATEGORÍAS PARA EL MENÚ
// ============================================
$categorias_menu = $conn->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");

// ============================================
// OBTENER TOTAL DE COMBOS ACTIVOS (con filtro de proveedor)
// ============================================
if ($proveedor_id > 0) {
    $stmt_total = $conn->prepare("SELECT COUNT(*) as total FROM combos WHERE activo = 1 AND proveedor_id = ?");
    $stmt_total->bind_param("i", $proveedor_id);
    $stmt_total->execute();
    $combos_total = $stmt_total->get_result()->fetch_assoc()['total'];
    $stmt_total->close();
} else {
    $total_result = $conn->query("SELECT COUNT(*) as total FROM combos WHERE activo = 1");
    $combos_total = $total_result->fetch_assoc()['total'];
}

$combos_por_carga = 12;

if ($proveedor_id > 0) {
    $sql = "SELECT c.*, 
                   (SELECT COUNT(*) FROM combo_productos WHERE combo_id = c.id) as productos_distintos,
                   (SELECT SUM(cantidad) FROM combo_productos WHERE combo_id = c.id) as unidades_totales
            FROM combos c 
            WHERE c.activo = 1 AND c.proveedor_id = ?
            ORDER BY c.fecha_creacion DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $proveedor_id, $combos_por_carga);
} else {
    $sql = "SELECT c.*, 
                   (SELECT COUNT(*) FROM combo_productos WHERE combo_id = c.id) as productos_distintos,
                   (SELECT SUM(cantidad) FROM combo_productos WHERE combo_id = c.id) as unidades_totales
            FROM combos c 
            WHERE c.activo = 1 
            ORDER BY c.fecha_creacion DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $combos_por_carga);
}
$stmt->execute();
$combos = $stmt->get_result();

// ============================================
// PASAR PROVEEDOR A JAVASCRIPT
// ============================================
echo '<script>';
echo 'window.proveedor_actual = "' . addslashes($proveedor_slug) . '";';
echo '</script>';

// ============================================
// INCLUIR HEADER DE LA TIENDA
// ============================================
include 'header_tienda.php';
?>

<main class="container">
    <section class="productos-section">
        <h2 class="section-title" style="margin-bottom: 1rem;">
    🎁 <?php echo !empty($proveedor_nombre) ? htmlspecialchars($proveedor_nombre) . ' - Combos' : 'Combos Especiales'; ?>
</h2>
<p style="text-align: center; color: #718096; margin-bottom: 2rem;">
    <?php echo !empty($proveedor_nombre) ? 'Combos de esta tienda' : 'Ahorra más comprando en combo'; ?>
</p>
     
        <?php if ($combos_total > 0): ?>
            <div class="productos-grid" id="combos-grid" 
                 data-combos-total="<?php echo $combos_total; ?>"
                 data-combos-offset="<?php echo $combos_por_carga; ?>"
                 data-cargando="false">
                <?php while($combo = $combos->fetch_assoc()): 
                    $productos_distintos = intval($combo['productos_distintos'] ?? 0);
                    $unidades_totales = intval($combo['unidades_totales'] ?? 0);
                    include 'combo_card.php';
                endwhile; ?>
            </div>
            
            <div id="loader-mas-combos" style="text-align: center; padding: 2rem; display: none;">
                <div style="display: inline-block; width: 40px; height: 40px; border: 3px solid #e2e8f0; border-top-color: #667eea; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                <p style="margin-top: 0.5rem; color: #718096;">Cargando más combos...</p>
            </div>
            
            <div id="fin-combos" style="text-align: center; padding: 2rem; color: #718096; display: none;">
                ✅ Has visto todos los combos
                <br><br>
                <a href="combos.php" style="display: inline-block; padding: 0.6rem 1.2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 30px; text-decoration: none; font-size: 0.85rem;">
                    🔄 Recargar combos
                </a>
            </div>
            
            <div id="cargarMasCombosBtn" style="text-align: center; padding: 1rem; display: none;">
                <button onclick="cargarMasCombosManual()" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 30px; font-size: 0.85rem; cursor: pointer;">
                    ➕ Cargar más combos
                </button>
            </div>
            
            <style>
                @keyframes spin { to { transform: rotate(360deg); } }
            </style>
            
        <?php else: ?>
            <div style="text-align: center; padding: 4rem; background: white; border-radius: 16px;">
                <h3>🎁 No hay combos disponibles</h3>
                <p style="margin-top: 1rem;">Pronto tendremos ofertas especiales para ti.</p>
                <a href="index.php" style="display: inline-block; margin-top: 1rem; padding: 0.6rem 1.2rem; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white; border-radius: 30px; text-decoration: none;">🛍️ Ver productos</a>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- MODAL COMBO TIENDA -->
<div id="modalComboTienda" class="modal-overlay" style="display: none;">
    <div class="modal-contenido" style="max-width: 900px; width: 90%; padding: 1.5rem;">
        <span class="close-modal" onclick="cerrarModalComboTienda()">&times;</span>
        <div id="contenidoModalComboTienda">
            <div style="text-align: center; padding: 2rem;">
                <div class="spinner-modal"></div>
                <p>Cargando combo...</p>
            </div>
        </div>
    </div>
</div>

<style>
/* ========== ESTILOS MODAL COMBOS ========== */
#modalComboTienda.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    backdrop-filter: blur(4px);
}
#modalComboTienda .modal-contenido {
    position: relative;
    background: white;
    border-radius: 24px;
    box-shadow: 0 25px 45px rgba(0,0,0,0.3);
    max-width: 900px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 1.5rem;
    animation: fadeInCombo 0.3s ease;
}
@keyframes fadeInCombo {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
.spinner-modal {
    width: 40px;
    height: 40px;
    border: 3px solid #e2e8f0;
    border-top-color: var(--primary, #667eea);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
#modalComboTienda .close-modal {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    font-size: 2rem;
    font-weight: bold;
    color: #ef4444;
    background: rgba(255,255,255,0.95);
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    z-index: 10;
}
#modalComboTienda .close-modal:hover {
    background: #ef4444;
    color: white;
}
.producto-etiquetas {
    position: absolute;
    top: 10px;
    left: 10px;
    display: flex;
    gap: 6px;
    z-index: 2;
}
.etiqueta-nuevo, .etiqueta-rebaja, .etiqueta-agotado {
    padding: 4px 10px;
    border-radius: 30px;
    font-size: 0.7rem;
    font-weight: bold;
    color: white;
}
.etiqueta-nuevo { background: #3b82f6; }
.etiqueta-rebaja { background: #ef4444; }
.etiqueta-agotado { background: #4b5563; }

/* Botones de cantidad - mismo tamaño que en productos */
#modalComboTienda .cantidad-btn {
    width: 40px;
    height: 40px;
    font-size: 1.5rem;
    background: #f3f4f6;
    border: 1px solid #d1d5db;
    border-radius: 40px;
    cursor: pointer;
}
#modalComboTienda .cantidad-input {
    width: 50px;
    text-align: center;
    font-size: 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 0.3rem;
    background: white;
}
#modalComboTienda .btn-carrito {
    background: #10b981;
    color: white;
    padding: 0.5rem 1.2rem;
    border-radius: 40px;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    border: none;
    cursor: pointer;
}
#modalComboTienda .btn-carrito:hover {
    background: #059669;
}

/* Responsive móvil */
@media (max-width: 768px) {
    #modalComboTienda .modal-contenido {
        padding: 1rem;
        width: 95%;
    }
    #contenidoModalComboTienda > div {
        grid-template-columns: 1fr !important;
        gap: 0.5rem;
    }
    #modalComboTienda .close-modal {
        width: 36px;
        height: 36px;
        font-size: 1.6rem;
        top: 0.5rem;
        right: 0.5rem;
    }
    #modalComboTienda .cantidad-btn {
        width: 32px;
        height: 32px;
        font-size: 1.2rem;
    }
    #modalComboTienda .cantidad-input {
        width: 36px;
        font-size: 0.9rem;
    }
    #modalComboTienda .btn-carrito {
        padding: 0.4rem 0.9rem;
        font-size: 0.75rem;
    }
    .combo-descripcion {
        font-size: 0.75rem !important;
    }
}
.combo-descripcion {
    font-size: 0.85rem;
    line-height: 1.3;
    margin-bottom: 0.5rem;
}
    
/* Estilos específicos para el modal de imagen grande */
#modalImagenGrande.modal-overlay {
    background: rgba(0,0,0,0.85) !important;
    backdrop-filter: blur(8px);
}
#modalImagenGrande .modal-contenido {
    background: transparent !important;
    box-shadow: none !important;
    padding: 0 !important;
}
#modalImagenGrande .close-modal {
    background: rgba(0,0,0,0.6) !important;
    color: white !important;
    top: 1rem;
    right: 1rem;
}
#modalImagenGrande .close-modal:hover {
    background: #ef4444 !important;
}
</style>

<script>
// ============================================
// REDIRIGIR FILTROS
// ============================================
function filtrarCategoria(categoriaId) {
    window.location.href = 'index.php?categoria=' + categoriaId;
}
function filtrarNuevos() {
    window.location.href = 'index.php?filtro=nuevos';
}
function filtrarMasVendidos() {
    window.location.href = 'index.php?filtro=mas_vendidos';
}
function filtrarOfertas() {
    window.location.href = 'index.php?filtro=ofertas';
}
function limpiarBusquedaCompleta() {
    window.location.href = 'index.php';
}
function buscarProductosAJAX(termino) {
    if (termino && termino.trim() !== '') {
        window.location.href = 'index.php?buscar=' + encodeURIComponent(termino);
    }
}

// ============================================
// SCROLL INFINITO PARA COMBOS
// ============================================
window.combosCargados = <?php echo $combos_por_carga; ?>;
window.combosLimiteScroll = 36;
window.combosScrollActivo = true;

function cargarMasCombos() {
    const grid = document.getElementById('combos-grid');
    if (!grid) return;
    if (!window.combosScrollActivo) return;
    if (window.combosCargados >= window.combosLimiteScroll) {
        document.getElementById('cargarMasCombosBtn').style.display = 'block';
        return;
    }
    const offset = parseInt(grid.getAttribute('data-combos-offset') || 0);
    const total = parseInt(grid.getAttribute('data-combos-total') || 0);
    if (offset >= total) {
        document.getElementById('fin-combos').style.display = 'block';
        return;
    }
    if (grid.getAttribute('data-cargando') === 'true') return;
    grid.setAttribute('data-cargando', 'true');
    document.getElementById('loader-mas-combos').style.display = 'block';
    const urlCarga = 'includes/cargar_mas_combos.php?offset=' + offset + '&limite=12' + 
                 (window.proveedor_actual ? '&proveedor=' + encodeURIComponent(window.proveedor_actual) : '');
fetch(urlCarga)
        .then(response => response.text())
        .then(html => {
            if (html.trim()) {
                grid.insertAdjacentHTML('beforeend', html);
                const nuevosCombos = document.querySelectorAll('.producto-card').length;
                grid.setAttribute('data-combos-offset', nuevosCombos);
                window.combosCargados = nuevosCombos;
                if (typeof ajustarTamanosTarjetas === 'function') ajustarTamanosTarjetas();
            }
            grid.setAttribute('data-cargando', 'false');
            document.getElementById('loader-mas-combos').style.display = 'none';
            if (window.combosCargados >= window.combosLimiteScroll) {
                document.getElementById('cargarMasCombosBtn').style.display = 'block';
                window.combosScrollActivo = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            grid.setAttribute('data-cargando', 'false');
            document.getElementById('loader-mas-combos').style.display = 'none';
        });
}

function cargarMasCombosManual() {
    window.combosLimiteScroll += 36;
    window.combosScrollActivo = true;
    document.getElementById('cargarMasCombosBtn').style.display = 'none';
    cargarMasCombos();
}

window.addEventListener('scroll', function() {
    const scrollPos = window.innerHeight + window.scrollY;
    const alturaTotal = document.body.scrollHeight;
    if (scrollPos >= alturaTotal - 400) {
        cargarMasCombos();
    }
});

// ============================================
// MODAL COMBO TIENDA (CON ONCLICK EN IMAGEN)
// ============================================
window.modalComboAbierto = false;

function abrirModalComboTienda(comboId) {
    const idNumerico = parseInt(comboId);
    if (isNaN(idNumerico) || idNumerico <= 0) {
        console.error('ID de combo inválido');
        if (typeof mostrarNotificacion === 'function') mostrarNotificacion('Error: combo no válido', 'error');
        return;
    }
    if (window.modalComboAbierto) return;
    
    if (!window.location.hash.includes('modal')) {
        window.history.pushState({ modal: true }, '', window.location.pathname + window.location.search + '#modal');
    }
    
    const modal = document.getElementById('modalComboTienda');
    const contenedor = document.getElementById('contenidoModalComboTienda');
    
    contenedor.innerHTML = `<div style="text-align:center;padding:2rem;"><div class="spinner-modal"></div><p>Cargando combo...</p></div>`;
    modal.style.display = 'flex';
    window.modalComboAbierto = true;
    
    fetch('includes/get_combo_modal.php?id=' + idNumerico)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // ============================================
            // 1. REGISTRAR EVENTO DE VISUALIZACIÓN
            // ============================================
            fetch('includes/carrito_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=registrar_evento&evento=ver_combo&producto_id=' + idNumerico
            }).catch(e => console.error('Error registrando evento:', e));
            
            // ============================================
            // 2. INCREMENTAR CONTADOR DE VISITAS DEL COMBO
            // ============================================
            fetch('includes/carrito_ajax.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'accion=incrementar_vistas&tipo=combo&id=' + idNumerico
            }).catch(e => console.error('Error al incrementar vistas del combo:', e));
                const nombreSeguro = escaparHTML(data.combo.nombre);
                const descripcionSegura = escaparHTML(data.combo.descripcion || '').replace(/\n/g, '<br>');
                const imagenUrl = escaparHTML(data.combo.imagen_url);
                
                let productosHtml = '';
                data.productos.forEach(p => {
                    const pNombre = escaparHTML(p.nombre);
                    const pImagen = escaparHTML(p.imagen);
                    productosHtml += `
                        <div style="display: flex; gap: 1rem; padding: 0.8rem; border-bottom: 1px solid #edf2f7; align-items: center;">
                            <img src="${pImagen}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                            <div style="flex: 1;">
                                <div style="font-weight: 600;">${pNombre}</div>
                                <div style="font-size: 0.75rem;">Cantidad: ${p.cantidad}</div>
                            </div>
                            <div style="font-weight: 600;">${data.moneda_simbolo}${parseFloat(p.precio).toFixed(2)}</div>
                        </div>
                    `;
                });
                
                const etiquetasHtml = `
                    ${data.combo.es_nuevo ? '<span class="etiqueta-nuevo">NUEVO</span>' : ''}
                    ${data.combo.porcentaje_rebaja > 0 ? `<span class="etiqueta-rebaja">-${data.combo.porcentaje_rebaja}%</span>` : ''}
                    ${data.combo.stock_combo <= 0 ? '<span class="etiqueta-agotado">AGOTADO</span>' : ''}
                `;
                
                let precioHtml = '';
                if (data.combo.porcentaje_rebaja > 0) {
                    precioHtml = `
                        <span style="text-decoration: line-through; color: #ef4444; margin-right: 0.5rem;">${data.moneda_simbolo}${parseFloat(data.combo.precio).toFixed(2)}</span>
                        <span style="font-size: 1.8rem; font-weight: 700; color: #10b981;">${data.moneda_simbolo}${parseFloat(data.combo.precio_final).toFixed(2)}</span>
                        <span style="font-size: 0.8rem; color: #6b7280; margin-left: 0.2rem;">${data.moneda_codigo}</span>
                    `;
                } else {
                    precioHtml = `
                        <span style="font-size: 1.8rem; font-weight: 700; color: var(--primary, #3b82f6);">${data.moneda_simbolo}${parseFloat(data.combo.precio).toFixed(2)}</span>
                        <span style="font-size: 0.8rem; color: #6b7280; margin-left: 0.2rem;">${data.moneda_codigo}</span>
                    `;
                }
                
                const cantidadDeshabilitada = data.combo.stock_combo <= 0 ? 'opacity:0.5; pointer-events:none;' : '';
                
                // HTML sin onclick en la imagen
                contenedor.innerHTML = `
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div style="text-align: center;">
                            <div class="producto-etiquetas">${etiquetasHtml}</div>
                            <img src="${imagenUrl}" alt="${nombreSeguro}" style="max-width:100%; max-height:300px; object-fit:contain; border-radius:8px; cursor:pointer;">
                        </div>
                        <div>
                            <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;">${nombreSeguro}</h3>
                            <div class="combo-descripcion" style="margin-bottom: 1rem;">${descripcionSegura}</div>
                            <div style="margin-bottom: 0.8rem; font-size: 0.85rem; color: #718096;">
                                🎁 ${data.combo.total_productos} productos - ${data.combo.total_unidades} unidades
                            </div>
                            <div style="margin-bottom: 1.5rem;">
                                ${precioHtml}
                            </div>
                            <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                                <div style="display: flex; align-items: center; gap: 1rem; ${cantidadDeshabilitada}">
                                    <div class="cantidad-selector">
                                        <button class="cantidad-btn" onclick="cambiarCantidadComboModal(-1, ${data.combo.id})">−</button>
                                        <input type="text" id="cantidadComboModal_${data.combo.id}" class="cantidad-input" value="1" readonly>
                                        <button class="cantidad-btn" onclick="cambiarCantidadComboModal(1, ${data.combo.id})">+</button>
                                    </div>
                                    <button class="btn-carrito" onclick="agregarComboModalAlCarrito(${data.combo.id})" ${data.combo.stock_combo <= 0 ? 'disabled style="opacity:0.5;cursor:not-allowed;"' : ''}>
                                        <span class="carrito-icono">🛒</span> Agregar
                                    </button>
                                </div>
                            </div>
                            <div class="combo-productos-lista" style="margin-top: 1rem; border-top: 1px solid #edf2f7; padding-top: 1rem;">
                                <h4 style="margin-bottom: 0.8rem;">📦 Productos incluidos:</h4>
                                ${productosHtml}
                            </div>
                        </div>
                    </div>
                `;
                
                // === ASIGNAR EL EVENTO CLICK A LA IMAGEN ===
                const imgModal = contenedor.querySelector('img');
                if (imgModal) {
                    imgModal.onclick = function() {
                        verImagenGrande(imagenUrl);
                    };
                } else {
                    console.error('No se encontró la imagen');
                }
                
            } else {
                contenedor.innerHTML = `<div style="text-align:center;padding:2rem;color:#ef4444;">❌ ${escaparHTML(data.error)}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contenedor.innerHTML = `<div style="text-align:center;padding:2rem;color:#ef4444;">❌ Error al cargar el combo</div>`;
        });
}

function cerrarModalComboTienda() {
    if (window.location.hash.includes('modal')) {
        const urlLimpia = window.location.pathname + window.location.search;
        window.history.replaceState({}, '', urlLimpia);
    }
    const modal = document.getElementById('modalComboTienda');
    if (modal) modal.style.display = 'none';
    window.modalComboAbierto = false;
}

function cambiarCantidadComboModal(delta, comboId) {
    const input = document.getElementById(`cantidadComboModal_${comboId}`);
    if (!input) return;
    let valor = parseInt(input.value) || 1;
    valor = Math.max(1, Math.min(99, valor + delta));
    input.value = valor;
}

function agregarComboModalAlCarrito(comboId) {
    const input = document.getElementById(`cantidadComboModal_${comboId}`);
    const cantidad = input ? parseInt(input.value) : 1;
    
    if (!window.clienteRegistrado) {
        cerrarModalComboTienda();
        const modalRegistro = document.getElementById('modalRegistro');
        if (modalRegistro) modalRegistro.style.display = 'block';
        window.productoPendiente = { combo: true, id: comboId, cantidad: cantidad };
        return;
    }
    
    fetch('includes/carrito_ajax.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'accion=agregar_combo&combo_id=' + comboId + '&cantidad=' + cantidad
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (typeof mostrarNotificacion === 'function') mostrarNotificacion('✅ Combo agregado al carrito', 'exito');
            if (typeof actualizarContadorCarrito === 'function') actualizarContadorCarrito();
            cerrarModalComboTienda();
        } else {
            if (typeof mostrarNotificacion === 'function') mostrarNotificacion('❌ ' + (data.mensaje || 'Error'), 'error');
        }
    })
    .catch(() => {
        if (typeof mostrarNotificacion === 'function') mostrarNotificacion('❌ Error de conexión', 'error');
    });
}

// Función para ver imagen grande
function verImagenGrande(src) {
    if (!src) return;
    const modal = document.getElementById('modalImagenGrande');
    const img = document.getElementById('imagenGrande');
    if (modal && img) {
        img.src = src;
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    } else {
        window.open(src, '_blank');
    }
}

function cerrarImagenGrande() {
    const modal = document.getElementById('modalImagenGrande');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function ajustarTamanosTarjetas() {
    var anchoPantalla = window.innerWidth;
    var esMovil = anchoPantalla <= 768;
    if (esMovil) {
        document.querySelectorAll('.producto-marca').forEach(el => el.style.setProperty('font-size', '10px', 'important'));
        document.querySelectorAll('.producto-nombre').forEach(el => el.style.setProperty('font-size', '13px', 'important'));
        document.querySelectorAll('.producto-categoria').forEach(el => el.style.setProperty('font-size', '9px', 'important'));
    } else {
        document.querySelectorAll('.producto-marca').forEach(el => el.style.setProperty('font-size', '0.7rem', 'important'));
        document.querySelectorAll('.producto-nombre').forEach(el => el.style.setProperty('font-size', '0.9rem', 'important'));
        document.querySelectorAll('.producto-categoria').forEach(el => el.style.setProperty('font-size', '0.65rem', 'important'));
    }
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function(event) {
    const modalCombo = document.getElementById('modalComboTienda');
    if (event.target === modalCombo) cerrarModalComboTienda();
    const modalImg = document.getElementById('modalImagenGrande');
    if (event.target === modalImg) cerrarImagenGrande();
});

// Cerrar con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (window.modalComboAbierto) cerrarModalComboTienda();
        const modalImg = document.getElementById('modalImagenGrande');
        if (modalImg && modalImg.style.display === 'flex') cerrarImagenGrande();
    }
});

// Manejar botón atrás del navegador
window.addEventListener('popstate', function() {
    if (window.modalComboAbierto) cerrarModalComboTienda();
});

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(ajustarTamanosTarjetas, 50);
    if (typeof actualizarContadorCarrito === 'function') actualizarContadorCarrito();
});
</script>

<!-- MODAL PARA VER IMAGEN GRANDE -->
<div id="modalImagenGrande" class="modal-overlay" style="display: none;">
    <div class="modal-contenido" style="max-width: 90vw; width: auto; padding: 1rem; background: transparent; box-shadow: none; text-align: center;">
        <span class="close-modal" onclick="cerrarImagenGrande()">&times;</span>
        <img id="imagenGrande" src="" alt="Imagen ampliada" style="max-width: 90vw; max-height: 90vh; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
    </div>
</div>

<?php include 'footer_tienda.php'; ?>
<?php $conn->close(); ?>
<?php
function obtenerDatosPagoPDF($conn, $metodo_pago) {
    $datos = [];
    $like = $metodo_pago . '_%';
    $stmt = $conn->prepare("SELECT clave, valor FROM configuracion WHERE clave LIKE ? AND grupo = 'pagos'");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $clave = str_replace($metodo_pago . '_', '', $row['clave']);
        $datos[$clave] = $row['valor'];
    }
    return $datos;
}

function generarPDFPedido($pedido_id, $conn, $estado = null) {
    try {
        require_once dirname(__DIR__) . '/includes/tcpdf/tcpdf.php';
        
        // ===== OBTENER CONFIGURACIÓN =====
        $config = [];
        $stmt_config = $conn->prepare("SELECT clave, valor FROM configuracion WHERE clave IN ('sitio_nombre', 'logo_url', 'color_primario', 'color_secundario')");
        $stmt_config->execute();
        $result_config = $stmt_config->get_result();
        while ($row = $result_config->fetch_assoc()) {
            $config[$row['clave']] = $row['valor'];
        }
        
        $nombre_tienda = $config['sitio_nombre'] ?? 'Catalejo';
        $logo_url = $config['logo_url'] ?? '';
        $color_primario = $config['color_primario'] ?? '#667eea';
        $color_secundario = $config['color_secundario'] ?? '#764ba2';
        
        // ===== OBTENER DATOS DEL PEDIDO =====
        $stmt = $conn->prepare("SELECT p.*, c.nombre, c.apellidos, c.email as cliente_email 
                                FROM pedidos p 
                                JOIN clientes c ON p.cliente_id = c.id 
                                WHERE p.id = ?");
        $stmt->bind_param("i", $pedido_id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        
        if (!$pedido) {
            return generarPDFRespaldo($pedido_id, $conn);
        }
        
        // ===== OBTENER DIRECCIÓN =====
        $stmt = $conn->prepare("SELECT * FROM direcciones_envio WHERE pedido_id = ?");
        $stmt->bind_param("i", $pedido_id);
        $stmt->execute();
        $direccion = $stmt->get_result()->fetch_assoc();
        
        // ===== OBTENER PRODUCTOS =====
        $stmt = $conn->prepare("SELECT pd.*, p.nombre FROM pedido_detalles pd JOIN productos p ON pd.producto_id = p.id WHERE pd.pedido_id = ?");
        $stmt->bind_param("i", $pedido_id);
        $stmt->execute();
        $productos = $stmt->get_result();
        
        // ===== OBTENER COMBOS =====
        $stmt_combos = $conn->prepare("SELECT pd.*, c.nombre FROM pedido_detalles pd JOIN combos c ON pd.combo_id = c.id WHERE pd.pedido_id = ?");
        $stmt_combos->bind_param("i", $pedido_id);
        $stmt_combos->execute();
        $combos = $stmt_combos->get_result();
        
        // ===== COMBINAR ITEMS =====
        $items = [];
        while ($item = $productos->fetch_assoc()) {
            $item['tipo'] = 'producto';
            $items[] = $item;
        }
        while ($combo = $combos->fetch_assoc()) {
            $combo['tipo'] = 'combo';
            $combo['nombre'] = '🎁 ' . $combo['nombre'] . ' (COMBO)';
            $items[] = $combo;
        }
        
        usort($items, function($a, $b) {
            if ($a['tipo'] === $b['tipo']) return 0;
            return ($a['tipo'] === 'producto') ? -1 : 1;
        });
        
        // ===== CALCULAR TOTALES =====
        $subtotal = array_sum(array_column($items, 'subtotal'));
        $costo_envio = $pedido['envio'] ?? 0;
        $total = $pedido['total'];
        $fecha_estimada = date('d/m/Y', strtotime($pedido['fecha_pedido'] . ' +3 days'));
        $nombre_metodo = ucfirst(str_replace('_', ' ', $pedido['tipo_pago'] ?? 'contra_entrega'));
        $datos_metodo = obtenerDatosPagoPDF($conn, $pedido['tipo_pago'] ?? 'contra_entrega');
        $estado_texto = ucfirst(str_replace('_', ' ', $pedido['estado_actual'] ?? 'pendiente'));
        
        // ===== CREAR PDF =====
        $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator($nombre_tienda);
        $pdf->SetAuthor($nombre_tienda);
        $pdf->SetTitle('Pedido #' . $pedido['codigo']);
        $pdf->SetMargins(8, 5, 8);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();
        
        // ===== ENCABEZADO =====
        $pdf->SetY(4);
        
        // Logo
        if (!empty($logo_url)) {
            $logo_path = $_SERVER['DOCUMENT_ROOT'] . $logo_url;
            if (file_exists($logo_path)) {
                $pdf->Image($logo_path, 20, 7, 10, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                $pdf->SetY(4);
                $pdf->SetX(35);
                $pdf->SetFont('helvetica', 'B', 14);
                $pdf->SetTextColor(102, 126, 234);
                $pdf->Cell(0, 8, $nombre_tienda, 0, 1, 'L');
            } else {
                $pdf->SetFont('helvetica', 'B', 16);
                $pdf->SetTextColor(102, 126, 234);
                $pdf->Cell(0, 8, $nombre_tienda, 0, 1, 'C');
            }
        } else {
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(102, 126, 234);
            $pdf->Cell(0, 8, $nombre_tienda, 0, 1, 'C');
        }
        
        $pdf->Ln(1);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, 'COMPROBANTE DE PEDIDO', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 6, $pedido['codigo'], 0, 1, 'C');
        $pdf->Ln(1);
        $pdf->SetDrawColor(102, 126, 234);
        $pdf->Line(8, 24, 202, 24);
        $pdf->SetY(26);
        
        // ===== HTML DEL PDF =====
        $html = '<style>
            body { font-family: helvetica; font-size: 9px; color: #2d3748; }
            .info-linea { background: #f7fafc; padding: 4px 6px; border-radius: 3px; margin: 4px 0 6px 0; text-align: center; }
            .info-linea table { width: 100%; border-collapse: collapse; }
            .info-linea td { border: none; padding: 2px 4px; text-align: center; font-size: 9px; background: transparent; }
            .info-linea .sep { color: #cbd5e0; padding: 0 2px; }
            .section-title { font-size: 10px; font-weight: bold; margin: 6px 0 2px 0; border-bottom: 2px solid ' . $color_primario . '; padding-bottom: 2px; color: #2d3748; }
            table { width: 100%; border-collapse: collapse; margin: 2px 0; }
            th { background: ' . $color_primario . '; color: white; padding: 3px 5px; font-size: 8px; font-weight: bold; text-align: left; }
            td { padding: 2px 5px; font-size: 8px; border-bottom: 1px solid #edf2f7; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .totales { background: #f7fafc; padding: 4px 6px; border-radius: 3px; margin-top: 4px; }
            .total-linea { padding: 1px 0; font-size: 9px; }
            .total-linea table { width: 100%; border-collapse: collapse; }
            .total-linea td { border: none; padding: 1px 0; background: transparent; }
            .total-final { padding: 3px 0 0; margin-top: 2px; border-top: 2px solid #e2e8f0; font-size: 12px; font-weight: bold; color: ' . $color_primario . '; }
            .pago-card { background: #f0fff4; padding: 4px 6px; border-radius: 3px; margin: 3px 0; border-left: 3px solid #48bb78; }
            .entrega-card { background: #f7fafc; padding: 4px 6px; border-radius: 3px; margin: 3px 0; border-left: 3px solid ' . $color_primario . '; }
            .info-line { padding: 1px 0; font-size: 8px; border-bottom: 1px dashed #edf2f7; }
            .info-line:last-child { border-bottom: none; }
            .info-line strong { color: #4a5568; }
            .fecha-estimada { background: ' . $color_primario . '; color: white; padding: 4px; text-align: center; border-radius: 3px; margin-top: 5px; font-size: 9px; font-weight: bold; }
            .footer-text { text-align: center; font-size: 7px; color: #a0aec0; margin-top: 6px; padding-top: 3px; border-top: 1px solid #e2e8f0; }
        </style>
        <div>
            <div style="background: #f7fafc; padding: 2px 6px; border-radius: 3px; margin: 0.1px 0 0.1px 0; text-align: center; font-size: 8px;">
                <strong>📅 Fecha:</strong> ' . date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) . ' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>💰 Total:</strong> $' . number_format($total, 2) . ' USD &nbsp;&nbsp;|&nbsp;&nbsp; <strong>📦 Estado:</strong> ' . $estado_texto . '
            </div>
            <div style="background: #f7fafc; padding: 2px 6px; border-radius: 3px; margin: 0.1px 0 1px 0; text-align: center; font-size: 8px;">
                <strong>👤 Cliente:</strong> ' . htmlspecialchars($pedido['nombre'] . ' ' . ($pedido['apellidos'] ?? '')) . ' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>📧 Email:</strong> ' . htmlspecialchars($pedido['cliente_email']) . ' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>📞 Teléfono:</strong> ' . htmlspecialchars($direccion['telefono'] ?? '-') . '
            </div>
            <div class="section-title">📋 DETALLE DE PRODUCTOS</div>
            <table>
                <thead>
                    <tr><th>Producto</th><th style="width:30px" class="text-center">Cant</th><th style="width:50px" class="text-right">Precio</th><th style="width:50px" class="text-right">Subtotal</th></tr>
                </thead>
                <tbody>';
                
                foreach ($items as $item) {
                    $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['nombre']) . '</td>
                        <td class="text-center">' . $item['cantidad'] . '</td>
                        <td class="text-right">$' . number_format($item['precio_unitario'], 2) . '</td>
                        <td class="text-right">$' . number_format($item['subtotal'], 2) . '</td>
                    </tr>';
                }
                
                $html .= '
                </tbody>
            </table>
            <div class="totales">
                <div class="total-linea">
                    <table>
                        <tr><td style="text-align:left;">📦 Subtotal</td><td style="text-align:right;">$' . number_format($subtotal, 2) . ' USD</td></tr>
                        <tr><td style="text-align:left;">🚚 Envío</td><td style="text-align:right;">$' . number_format($costo_envio, 2) . ' USD</td></tr>
                    </table>
                </div>
                <div class="total-final">
                    <table style="width:100%; border-collapse:collapse;">
                        <tr><td style="text-align:left; border:none; font-size:12px; font-weight:bold;">💰 TOTAL</td><td style="text-align:right; border:none; font-size:12px; font-weight:bold; color:' . $color_primario . ';">$' . number_format($total, 2) . ' USD</td></tr>
                    </table>
                </div>
            </div>
            <div class="section-title">💳 DATOS DEL PAGO</div>
            <div class="pago-card">
                <div class="info-line"><strong>Método:</strong> ' . $nombre_metodo . '</div>';
                
                foreach ($datos_metodo as $clave => $valor) {
                    if ($clave != 'activo' && $clave != 'modo' && !empty($valor)) {
                        $html .= '<div class="info-line"><strong>' . ucfirst(str_replace('_', ' ', $clave)) . ':</strong> ' . htmlspecialchars($valor) . '</div>';
                    }
                }
                
                $html .= '
            </div>
            <div class="section-title">📍 DATOS DE ENTREGA</div>
            <div class="entrega-card">
                <div class="info-line"><strong>Beneficiario:</strong> ' . htmlspecialchars($direccion['nombre_completo'] ?? '-') . '</div>
                <div class="info-line"><strong>Documento:</strong> ' . htmlspecialchars($direccion['documento'] ?? '-') . '</div>
                <div class="info-line"><strong>Teléfono:</strong> ' . htmlspecialchars($direccion['telefono'] ?? '-') . '</div>
                <div class="info-line"><strong>Dirección:</strong> ' . htmlspecialchars($direccion['direccion'] ?? '') . ', ' . htmlspecialchars($direccion['municipio'] ?? '') . ', ' . htmlspecialchars($direccion['provincia'] ?? '') . '</div>';
                
                if (!empty($direccion['instrucciones'])) {
                    $html .= '<div class="info-line"><strong>Instrucciones:</strong> ' . htmlspecialchars($direccion['instrucciones']) . '</div>';
                }
                
                $html .= '
            </div>
            <div class="fecha-estimada">
                📅 FECHA ESTIMADA DE ENTREGA: ' . $fecha_estimada . '
            </div>
            <div class="footer-text">
                ¡Gracias por confiar en nosotros!<br>
                ' . $nombre_tienda . ' - ' . date('Y') . '
            </div>
        </div>';
        
      // Limpiar el HTML de cualquier carácter extraño al inicio
$html = ltrim($html);
$html = preg_replace('/^\xEF\xBB\xBF/', '', $html);
$html = preg_replace('/^[^<]/', '', $html);

$pdf->writeHTML($html, true, false, true, false, '');
        
    } catch (Exception $e) {
        error_log("ERROR en generarPDFPedido: " . $e->getMessage());
        return generarPDFRespaldo($pedido_id, $conn);
    }
}

/**
 * PDF DE RESPALDO
 */
function generarPDFRespaldo($pedido_id, $conn) {
    try {
        require_once dirname(__DIR__) . '/includes/tcpdf/tcpdf.php';
        
        $stmt = $conn->prepare("SELECT codigo, total, fecha_creacion FROM pedidos WHERE id = ?");
        $stmt->bind_param("i", $pedido_id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        
        if (!$pedido) {
            $codigo = 'DESCONOCIDO';
            $total = '0.00';
        } else {
            $codigo = $pedido['codigo'];
            $total = number_format($pedido['total'], 2);
        }
        
        $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
        $pdf->SetCreator('Catalejo');
        $pdf->SetAuthor('Catalejo');
        $pdf->SetTitle('Pedido #' . $codigo);
        $pdf->SetMargins(8, 5, 8);
        $pdf->AddPage();
        
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->SetTextColor(102, 126, 234);
        $pdf->Cell(0, 8, 'CATALEJO', 0, 1, 'C');
        $pdf->SetTextColor(50, 50, 50);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 6, 'Pedido #' . $codigo, 0, 1, 'C');
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(0, 6, 'Fecha: ' . date('d/m/Y H:i'), 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'Total: $' . $total, 0, 1, 'C');
        $pdf->Ln(4);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->Cell(0, 6, 'Gracias por su compra en Catalejo', 0, 1, 'C');
        
        return $pdf->Output('pedido_' . $codigo . '.pdf', 'S');
        
    } catch (Exception $e) {
        error_log("ERROR en PDF de respaldo: " . $e->getMessage());
        return null;
    }
}
?>
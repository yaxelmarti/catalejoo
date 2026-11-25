<?php
if (!defined('SEGURO')) {
    http_response_code(403);
    exit('Acceso directo no permitido');
}
// Este archivo se incluye al inicio de index.php
// Las variables ya están definidas en index.php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sitio_nombre); ?> - <?php echo htmlspecialchars($sitio_slogan); ?></title>
    <link rel="stylesheet" href="estilo.css?v=<?php echo filemtime('estilo.css'); ?>">
    <!-- Archivo de funciones JavaScript -->
<script src="includes/funciones_tienda.js?v=<?php echo filemtime('includes/funciones_tienda.js'); ?>"></script>
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
        /* ===== HEADER FIJO DE 2 LÍNEAS ===== */
        .site-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
        }
        
        body {
            padding-top: 90px;
        }
        
        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        /* Línea 1: Logo centrado */
        .header-logo {
            text-align: center;
            padding: 0.3rem 0;
        }
        
        .header-logo h1 {
            font-size: 1.4rem;
            margin: 0;
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        /* Línea 2: Botones */
        .header-buttons {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.8rem;
            padding: 0.3rem 0;
        }
        
        .header-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            border-radius: 12px;
        }
        
        .header-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: scale(1.05);
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
            }
            
            .footer-social {
                justify-content: center;
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
    </style>
</head>
<body>

<!-- HEADER FIJO DE 2 LÍNEAS -->
<header class="site-header">
    <div class="header-container">
        <!-- Línea 1: Logo centrado -->
<div class="header-logo">
   <?php 
$logo_url = $config['logo_url'] ?? '';
if (!empty($logo_url)): 
    $ruta_fisica_logo = $_SERVER['DOCUMENT_ROOT'] . $logo_url;
    $version_logo = file_exists($ruta_fisica_logo) ? filemtime($ruta_fisica_logo) : time();
?>
    <img src="<?php echo htmlspecialchars($logo_url) . '?v=' . $version_logo; ?>" alt="<?php echo htmlspecialchars($sitio_nombre); ?>" style="height: 40px; vertical-align: middle;">
    <span style="font-size: 1.2rem; font-weight: 700; margin-left: 0.5rem;"><?php echo htmlspecialchars($sitio_nombre); ?></span>
<?php else: ?>
    <h1>✨ <?php echo htmlspecialchars($sitio_nombre); ?></h1>
<?php endif; ?>
</div>
        
        <!-- Línea 2: Botones -->
        <div class="header-buttons">
            <!-- Botón Categorías (menú lateral izquierdo) -->
            <button class="header-btn" onclick="toggleSideMenu()" title="Categorías">☰</button>
            
           <!-- Botón Home -->
<a href="/index.php" class="header-btn" title="Inicio" onclick="sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">🏠</a>
            
            <!-- Botón Ayuda -->
            <a href="/ayuda.php" class="header-btn" title="Ayuda">❓</a>
            
            <!-- Botón Carrito -->
            <button class="header-btn header-btn-carrito" onclick="window.abrirCarrito()" title="Mi carrito" id="btnCarritoHeader">
                🛒
                <span class="carrito-contador-header" id="carritoContadorHeader" style="display: none;">0</span>
            </button>
            
            <!-- Botón Mi Cuenta (menú desde abajo) -->
            <button class="header-btn" onclick="toggleClienteBottomMenu()" title="Mi cuenta">👤</button>
            
            <!-- Botón 9 puntos (menú categorías lateral derecho) -->
            <button class="header-btn" onclick="toggleCategoriasRightMenu()" title="Categorías">
                <div class="btn-grid">
                    <span></span><span></span><span></span>
                    <span></span><span></span><span></span>
                    <span></span><span></span><span></span>
                </div>
            </button>
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
        <li><a href="#" onclick="filtrarCategoria(0); cerrarCategoriasRightMenu(); return false;">🏷️ Todos los productos</a></li>
        <li><a href="#" onclick="cargarCombos(); cerrarCategoriasRightMenu(); return false;">🎁 Combos especiales</a></li>
       <?php 
if ($categorias_menu && $categorias_menu->num_rows > 0):
    $categorias_menu->data_seek(0);
    while($categoria = $categorias_menu->fetch_assoc()): 
?>
<li><a href="#" onclick="filtrarCategoria(<?php echo $categoria['id']; ?>); cerrarCategoriasRightMenu(); return false;">
    <?php echo $categoria['icono']; ?> <?php echo htmlspecialchars($categoria['nombre']); ?>
</a></li>
<?php 
    endwhile;
endif; 
?>
    </ul>
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
            <a href="index.php" onclick="cerrarSideMenu(); sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');">
    <span class="menu-icon">🏠</span>
    <span>Inicio</span>
</a>
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
            <a href="#" onclick="filtrarCategoria(0); cerrarSideMenu(); return false;">
                <span class="menu-icon">🏷️</span>
                <span>Todos</span>
            </a>
        </li>
        
        <li>
    <a href="#" onclick="cargarCombos(); cerrarSideMenu(); return false;">
        <span class="menu-icon">🎁</span>
        <span>Combos</span>
    </a>
</li>
        
        <!-- Nuevos (10 días) -->
        <li>
            <a href="#" onclick="filtrarNuevos(); cerrarSideMenu(); return false;">
                <span class="menu-icon">🆕</span>
                <span>Nuevos</span>
            </a>
        </li>
        
        <!-- Más vendidos -->
        <li>
            <a href="#" onclick="filtrarMasVendidos(); cerrarSideMenu(); return false;">
                <span class="menu-icon">🔥</span>
                <span>Más vendidos</span>
            </a>
        </li>
        
        <!-- En oferta -->
        <li>
            <a href="#" onclick="filtrarOfertas(); cerrarSideMenu(); return false;">
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

<script>
// Función de búsqueda AJAX
function buscarProductosAJAX(termino) {
    // Ocultar combos (mismo comportamiento que el carrusel)
const combosContainer = document.getElementById('combosContainer');
if (combosContainer) combosContainer.style.display = 'none';
        // Reiniciar scroll infinito para móvil
    if (window.innerWidth <= 768) {
        window.totalCargado = 0;
        window.limiteScroll = 30;
        window.scrollActivo = true;
        document.getElementById('cargarMasBtn').style.display = 'none';
    }
    
   window.scrollInfinitoHabilitado = true;
document.getElementById('fin-productos').style.display = 'none';
    
    if (!termino || termino.trim() === '') {
        return;
    }
    
    if (typeof window.detenerScrollInfinito === 'function') {
        window.detenerScrollInfinito();
    }
    window.busquedaActiva = termino;
    
    const grid = document.getElementById('productos-grid');
    if (!grid) return;
    
    grid.innerHTML = '<div style="text-align: center; padding: 4rem;">🔄 Buscando productos...</div>';
    
    fetch('includes/buscar_ajax.php?termino=' + encodeURIComponent(termino))
    .then(response => response.text())
    .then(html => {
        grid.innerHTML = html;
        
        // Actualizar el contador con los resultados
        const totalProductos = document.querySelectorAll('.producto-card').length;
        const contadorDiv = document.getElementById('contador-productos');
        if (contadorDiv) {
            contadorDiv.innerHTML = `<span>🔍 ${totalProductos}  "${termino}"</span>`;
        }
        
        // Mostrar la X para limpiar
        const btnLimpiar = document.getElementById('btnLimpiarBusqueda');
        if (btnLimpiar) {
            btnLimpiar.style.display = 'block';
        }
        
        document.getElementById('loader-mas-productos').style.display = 'none';
        document.getElementById('fin-productos').style.display = 'none';
        
        if (typeof ajustarTamanosTarjetas === 'function') {
            ajustarTamanosTarjetas();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        grid.innerHTML = '<div style="text-align: center; padding: 4rem;"><h3>❌ Error al buscar</h3></div>';
    });
}
function limpiarBusquedaCompleta() {
    window.busquedaActiva = '';
    
    // Limpiar el input de búsqueda
    const inputBuscar = document.getElementById('buscarInputTitulo');
    if (inputBuscar) {
        inputBuscar.value = '';
    }
    
    // Ocultar la X de limpiar
    const btnLimpiar = document.getElementById('btnLimpiarBusqueda');
    if (btnLimpiar) {
        btnLimpiar.style.display = 'none';
    }
    
    // Recargar todos los productos
    filtrarCategoria(0);
    
        // Restaurar combos (mostrarlos nuevamente)
    const combosContainer = document.getElementById('combosContainer');
    if (combosContainer) combosContainer.style.display = 'block';
    
    // Restaurar el contador original
    setTimeout(() => {
        const totalProductos = document.querySelectorAll('.producto-card').length;
        const contadorDiv = document.getElementById('contador-productos');
        if (contadorDiv) {
            contadorDiv.innerHTML = `<span>(${totalProductos} productos)</span>`;
        }
    }, 100);
}
    function verImagenGrande(src) {
    if (window.innerWidth > 768) return;
    const modal = document.getElementById('modalImagenGrande');
    const img = document.getElementById('imagenGrande');
    if (modal && img) {
        img.src = src;
        modal.style.display = 'flex';
    } else {
        // Fallback: mostrar imagen en una ventana nueva
        window.open(src);
    }
}
</script>
    
  <!-- MODAL PARA VER IMAGEN GRANDE -->
<div id="modalImagenGrande" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; justify-content: center; align-items: center; cursor: pointer;">
    <img id="imagenGrande" src="" style="max-width: 98%; max-height: 98%; object-fit: contain;">
    <span style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
</div>

<script>
    /*
// Función para abrir imagen
function verImagenGrande(src) {
    if (window.innerWidth > 768) return;
    document.getElementById('modalImagenGrande').style.display = 'flex';
    document.getElementById('imagenGrande').src = src;
}
*/
// Cerrar modal al hacer clic en el fondo o en la X
document.getElementById('modalImagenGrande').onclick = function(e) {
    if (e.target === this || e.target.tagName === 'SPAN') {
        this.style.display = 'none';
    }
};
</script>
<script>
// ============================================
// DELEGACIÓN DE EVENTOS PARA MODAL DE PRODUCTO
// ============================================
document.addEventListener('click', function(e) {
    const tarjeta = e.target.closest('.producto-card');
    if (!tarjeta) return;
    
    // No interferir con botones del carrito o cantidad
    if (e.target.closest('.btn-carrito')) return;
    if (e.target.closest('.cantidad-btn')) return;
    if (e.target.closest('.cantidad-input')) return;
    
    // Prevenir que el clic en la imagen en móvil intente abrir imagen grande
    if (e.target.closest('.producto-imagen')) {
        e.preventDefault();
    }
    
    let productoId = tarjeta.getAttribute('data-producto-id');
    
    if (productoId && !isNaN(Number(productoId))) {
        e.preventDefault();
        abrirModalProductoTienda(parseInt(productoId));
    }
});

// ============================================
// POPSTATE UNIFICADO (BOTÓN ATRÁS MÓVIL/PC)
// ============================================
window.pasoAtras = 0;
window.modalCerradoConAtras = false;
    
if (sessionStorage.getItem('recargar_desde_modal') === 'true') {
    sessionStorage.removeItem('recargar_desde_modal');
    if (window.history.length === 1) {
        window.history.pushState({}, '', window.location.href);
    }
}

if (window.history.length === 1) {
    window.history.pushState({ pagina: 'index' }, '', window.location.href);
}

window.addEventListener('popstate', function(e) {
    // CASO 1: Menú lateral izquierdo
    if (document.getElementById('sideMenu')?.classList.contains('show')) {
        cerrarSideMenu();
        e.stopPropagation();
        return;
    }
    // CASO 2: Menú categorías derecho
    if (document.getElementById('categoriasRightMenu')?.classList.contains('show')) {
        cerrarCategoriasRightMenu();
        e.stopPropagation();
        return;
    }
    // CASO 3: Menú cliente bottom
    if (document.getElementById('clienteBottomMenu')?.classList.contains('show')) {
        cerrarClienteBottomMenu();
        e.stopPropagation();
        return;
    }
    // CASO 4: Modal abierto
    if (window.modalProductoAbierto) {
        cerrarModalProductoTienda(false);
        window.pasoAtras = 1;
        window.modalCerradoConAtras = true;
        e.stopPropagation();
        return;
    }
    // CASO 4b: Modal de combo abierto → cerrar modal de combo
if (window.modalComboAbierto) {
    cerrarModalComboTienda(false);   // No eliminar el hash
    window.pasoAtras = 1;
    window.modalCerradoConAtras = true;
    e.stopPropagation();
    return;
}
   // CASO 4.5: Limpiar filtros recargando la página (sin carrito pendiente)
if (window.location.pathname === '/' || window.location.pathname === '/index.php') {
    const params = new URLSearchParams(window.location.search);
    const tieneFiltro = params.has('categoria') || params.has('filtro') || params.has('buscar');
    if (tieneFiltro) {
        e.stopPropagation();
        sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');
        window.location.href = window.location.pathname;  // Recarga sin parámetros
        return;
    }
}
   // CASO 4.6: Acabamos de cerrar un modal con atrás → recargar limpio (sin replace)
if (window.modalCerradoConAtras) {
    window.modalCerradoConAtras = false;
    sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');
    sessionStorage.setItem('recargar_desde_modal', 'true');  // Marcador
    window.location.href = window.location.pathname;  // <--- Cambiado replace por href
    return;
}
    // CASO 5: Segundo atrás general
   if (window.pasoAtras === 1) {
    sessionStorage.setItem('no_mostrar_carrito_pendiente', 'true');
    sessionStorage.setItem('recargar_desde_modal', 'true');  // Marcador
    window.location.href = 'index.php';
}
});
    
// ============================================
// BLOQUEAR SCROLL DEL FONDO CUANDO MODAL ESTÁ ABIERTO (MÓVIL)
// ============================================
function bloquearScrollFondo() {
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.width = '100%';
}

function desbloquearScrollFondo() {
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.width = '';
}

// Guardar referencia original de abrir/cerrar modal si existen
if (typeof window.abrirModalProductoTiendaOriginal === 'undefined') {
    window.abrirModalProductoTiendaOriginal = window.abrirModalProductoTienda;
    window.cerrarModalProductoTiendaOriginal = window.cerrarModalProductoTienda;
    
    window.abrirModalProductoTienda = function(productoId) {
        if (window.innerWidth <= 768) {
            bloquearScrollFondo();
        }
        return window.abrirModalProductoTiendaOriginal(productoId);
    };
    
    window.cerrarModalProductoTienda = function() {
        if (window.innerWidth <= 768) {
            desbloquearScrollFondo();
        }
        return window.cerrarModalProductoTiendaOriginal();
    };
}
    
    function actualizarMenuCliente(nombre, email) {
    // Actualizar header del menú cliente
    const bottomNombre = document.querySelector('#clienteBottomMenu .cliente-bottom-nombre');
    const bottomEmail = document.querySelector('#clienteBottomMenu .cliente-bottom-email');
    
    if (bottomNombre) bottomNombre.textContent = nombre;
    if (bottomEmail) bottomEmail.textContent = email;
    
    // Actualizar side menu
    const sideNombre = document.querySelector('#sideMenu .side-menu-nombre');
    const sideEmail = document.querySelector('#sideMenu .side-menu-email');
    
    if (sideNombre) sideNombre.textContent = nombre;
    if (sideEmail) sideEmail.textContent = email;
    
    // Cambiar los items del menú cliente
    const bottomItems = document.querySelector('#clienteBottomMenu .cliente-bottom-items');
    if (bottomItems) {
        bottomItems.innerHTML = `
            <li><div class="menu-item" onclick="cerrarClienteBottomMenu(); abrirPanelCliente();">👤 Mi Perfil</div></li>
            <li><div class="menu-item" onclick="cerrarClienteBottomMenu(); abrirCarrito();">🛒 Mi Carrito</div></li>
            <li><a href="/mis_pedidos.php" class="menu-item" onclick="cerrarClienteBottomMenu()">📋 Mis Compras</a></li>
            <li><a href="/ayuda.php" class="menu-item" onclick="cerrarClienteBottomMenu()">❓ Ayuda</a></li>
        `;
    }
    
    // Cambiar footer del side menu
    const sideFooter = document.querySelector('#sideMenu .side-menu-footer');
    if (sideFooter) {
        sideFooter.innerHTML = '<a href="#" onclick="cerrarSesion()">Cerrar sesión</a>';
    }
    
    // Mostrar bienvenida
    mostrarNotificacion('👋 ¡Bienvenido ' + nombre + '!', 'exito');
}
</script>

<button class="btn-comentarios-flotante" onclick="abrirModalComentarios()" title="Dejar comentario">
    💬
</button>
</body>
</html>
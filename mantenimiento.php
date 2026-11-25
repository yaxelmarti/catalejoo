<?php
// Cargar configuración para mostrar nombre y logo
require_once 'conexion.php';
session_start();

$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

$sitio_nombre = $config['sitio_nombre'] ?? 'Mi Tienda';
$logo_url = $config['logo_url'] ?? '';
$color_primario = $config['color_primario'] ?? '#667eea';
$color_secundario = $config['color_secundario'] ?? '#764ba2';
$fecha_mantenimiento = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>En Mantenimiento - <?php echo htmlspecialchars($sitio_nombre); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        /* Animación de fondo */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 80%, rgba(255,255,255,0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        body::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 1px, transparent 1px);
            background-size: 40px 40px;
            animation: moveDots 60s linear infinite;
            pointer-events: none;
        }
        
        @keyframes moveDots {
            from { transform: translate(0, 0); }
            to { transform: translate(40px, 40px); }
        }
        
        .mantenimiento-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 2rem 2rem 2.5rem;
            max-width: 550px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            position: relative;
            z-index: 10;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Icono animado */
        .icono-mantenimiento {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            animation: pulse 2s infinite;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 20px 35px -8px rgba(0,0,0,0.3);
            }
            100% {
                transform: scale(1);
                box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
            }
        }
        
        .icono-mantenimiento span {
            font-size: 3.5rem;
        }
        
        .logo {
            max-width: 150px;
            max-height: 60px;
            margin-bottom: 1rem;
        }
        
        h1 {
            font-size: 1.8rem;
            margin-bottom: 0.75rem;
            color: #1e293b;
        }
        
        .subtitulo {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .mensaje {
            color: #475569;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }
        
        .progress-container {
            background: #e2e8f0;
            border-radius: 100px;
            height: 8px;
            margin: 1.5rem 0;
            overflow: hidden;
        }
        
        .progress-bar {
            width: 65%;
            height: 100%;
            background: linear-gradient(135deg, <?php echo $color_primario; ?> 0%, <?php echo $color_secundario; ?> 100%);
            border-radius: 100px;
            animation: loading 2s ease-in-out infinite;
        }
        
        @keyframes loading {
            0% { width: 20%; }
            50% { width: 70%; }
            100% { width: 20%; }
        }
        
        .info-contacto {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        .info-contacto a {
            color: <?php echo $color_primario; ?>;
            text-decoration: none;
        }
        
        .redes {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .redes a {
            width: 36px;
            height: 36px;
            background: #f1f5f9;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        
        .redes a:hover {
            background: <?php echo $color_primario; ?>;
            transform: translateY(-3px);
        }
        
        .redes a:hover span {
            filter: brightness(0) invert(1);
        }
        
        @media (max-width: 480px) {
            .mantenimiento-card {
                padding: 1.5rem;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .icono-mantenimiento {
                width: 80px;
                height: 80px;
            }
            
            .icono-mantenimiento span {
                font-size: 2.5rem;
            }
        }
        
        /* Efecto de brillo */
        .brillo {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);
            opacity: 0;
            animation: brillar 8s ease-in-out infinite;
            pointer-events: none;
        }
        
        @keyframes brillar {
            0% { opacity: 0; transform: translate(0, 0); }
            20% { opacity: 0.5; }
            40% { opacity: 0; transform: translate(20%, 20%); }
            100% { opacity: 0; transform: translate(0, 0); }
        }
    </style>
</head>
<body>
    <div class="brillo"></div>
    <div class="mantenimiento-card">
        <?php if (!empty($logo_url)): ?>
            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($sitio_nombre); ?>" class="logo">
        <?php endif; ?>
        
        <div class="icono-mantenimiento">
            <span>🔧</span>
        </div>
        
        <h1>Estamos mejorando tu experiencia</h1>
        <div class="subtitulo">MANTENIMIENTO PROGRAMADO</div>
        
        <div class="mensaje">
            <?php echo htmlspecialchars($config['mensaje_mantenimiento'] ?? 'Estamos realizando mejoras técnicas para ofrecerte un mejor servicio. Vuelve pronto.'); ?>
        </div>
        
        <div class="progress-container">
            <div class="progress-bar"></div>
        </div>
        
        <div class="info-contacto">
            <p>📧 <a href="mailto:<?php echo htmlspecialchars($config['sitio_email'] ?? 'info@' . $_SERVER['HTTP_HOST']); ?>"><?php echo htmlspecialchars($config['sitio_email'] ?? 'info@' . $_SERVER['HTTP_HOST']); ?></a></p>
            <?php if (!empty($config['sitio_telefono'])): ?>
                <p>📞 <a href="tel:<?php echo htmlspecialchars($config['sitio_telefono']); ?>"><?php echo htmlspecialchars($config['sitio_telefono']); ?></a></p>
            <?php endif; ?>
            <p>🕒 Actualización: <?php echo $fecha_mantenimiento; ?></p>
        </div>
        
        <?php if (!empty($config['facebook_url']) || !empty($config['instagram_url']) || !empty($config['whatsapp_numero'])): ?>
            <div class="redes">
                <?php if (!empty($config['facebook_url'])): ?>
                    <a href="<?php echo htmlspecialchars($config['facebook_url']); ?>" target="_blank"><span>📘</span></a>
                <?php endif; ?>
                <?php if (!empty($config['instagram_url'])): ?>
                    <a href="<?php echo htmlspecialchars($config['instagram_url']); ?>" target="_blank"><span>📷</span></a>
                <?php endif; ?>
                <?php if (!empty($config['whatsapp_numero'])): ?>
                    <a href="https://wa.me/<?php echo htmlspecialchars($config['whatsapp_numero']); ?>" target="_blank"><span>💬</span></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
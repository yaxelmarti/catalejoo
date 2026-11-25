<?php
// ============================================
// BIENVENIDA - LANDING DE CATALEJOO
// Enfoque: compra desde USA/España, entrega en Cuba
// ============================================

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexion.php';

// Cargar configuración
$config = [];
$result = $conn->query("SELECT clave, valor FROM configuracion WHERE proveedor_id = 0");
while ($row = $result->fetch_assoc()) {
    $config[$row['clave']] = $row['valor'];
}

$sitio_nombre = $config['sitio_nombre'] ?? 'Catalejoo';
$sitio_slogan = $config['sitio_slogan'] ?? 'Compras desde Estados Unidos y Europa, entregas para quienes quieres en Cuba.';
$color_primario = $config['color_primario'] ?? '#667eea';
$color_secundario = $config['color_secundario'] ?? '#764ba2';
$sitio_email = $config['sitio_email'] ?? '';
$logo_url = $config['logo_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title><?php echo htmlspecialchars($sitio_nombre); ?></title>
<meta name="description" content="<?php echo htmlspecialchars($sitio_slogan); ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --lc-paper: #f8fafc;
    --lc-ink: #1e293b;
    --lc-green: <?php echo htmlspecialchars($color_primario); ?>;
    --lc-orange: <?php echo htmlspecialchars($color_secundario); ?>;
    --lc-sand: #e2e8f0;
    --lc-muted: #64748b;
    --font-display: 'Inter', sans-serif;
    --font-body: 'Inter', sans-serif;
    /* Para el modal de comentarios */
    --primary: <?php echo htmlspecialchars($color_primario); ?>;
    --secondary: <?php echo htmlspecialchars($color_secundario); ?>;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: var(--font-body);
    background: var(--lc-paper);
    color: var(--lc-ink);
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

.lc-site {
    background: var(--lc-paper);
    min-height: 100vh;
    color: var(--lc-ink);
    font-family: var(--font-body);
}

.lc-site a { color: inherit; text-decoration: none; }
.lc-site a:focus-visible { outline: 2px solid var(--lc-orange); outline-offset: 4px; }

.lc-header, .lc-hero, .lc-route-line, .lc-section, .lc-contact, .lc-footer {
    width: min(100% - 96px, 1280px);
    margin-inline: auto;
}

/* HEADER */
.lc-header {
    border-bottom: 1px solid rgba(30, 41, 59, 0.15);
    align-items: center;
    gap: 40px;
    min-height: 88px;
    display: flex;
}
.lc-wordmark, .lc-footer-wordmark {
    font-family: var(--font-display);
    letter-spacing: -0.055em;
    align-items: center;
    gap: 10px;
    font-size: 1.15rem !important;
    font-weight: 700;
    display: inline-flex;
}

.lc-logo-img {
    height: 40px;
    width: auto;
    max-width: 120px;
    object-fit: contain;
    display: block;
}

.lc-tagline {
    font-size: 0.6em;
    font-weight: 500;
    color: #64748b;
    letter-spacing: 0;
    text-transform: none;
}

@media (max-width: 640px) {
    .lc-logo-img {
        height: 32px;
        max-width: 90px;
    }
    /* Nombre de la tienda 20% más pequeño en móvil (1.55rem × 0.8 = 1.24rem) */
    .lc-wordmark {
        font-size: 1.24rem;
        gap: 8px;
    }
    .lc-tagline {
        font-size: 0.55em;
    }
}

.lc-mark {
    border: 1px solid var(--lc-green);
    letter-spacing: -0.08em;
    border-radius: 50% 50% 50% 5px;
    place-items: center;
    width: 34px; height: 34px;
    font-size: 0.78rem;
    font-weight: 600;
    display: grid;
    color: var(--lc-green);
}
.lc-mark span { color: var(--lc-orange); margin-left: -2px; }
.lc-nav {
    color: #475569;
    align-items: center;
    gap: 34px;
    margin-left: auto;
    font-size: 0.88rem;
    font-weight: 600;
    display: flex;
}
.lc-nav a, .lc-header-cta, .lc-footer a { transition: color 0.16s; }
.lc-nav a:hover, .lc-header-cta:hover, .lc-footer a:hover { color: var(--lc-orange); }
.lc-header-cta {
    align-items: center;
    gap: 9px;
    font-size: 0.9rem;
    font-weight: 700;
    display: inline-flex;
    cursor: pointer;
}
.lc-header-cta span { color: var(--lc-orange); font-size: 1.1rem; }

/* HERO */
.lc-hero {
    grid-template-columns: minmax(0, 1.04fr) minmax(360px, 0.96fr);
    align-items: center;
    gap: clamp(50px, 7vw, 110px);
    padding-block: 54px 68px;
    display: grid;
}
.lc-hero-copy { max-width: 620px; padding-block: 25px; }
.lc-eyebrow, .lc-section-kicker {
    color: #64748b;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    align-items: center;
    gap: 10px;
    margin: 0 0 22px;
    font-size: 0.72rem;
    font-weight: 800;
    display: flex;
}
.lc-eyebrow > span { background: var(--lc-orange); border-radius: 50%; width: 8px; height: 8px; }
.lc-hero h1, .lc-section h2, .lc-contact h2, .lc-payments h2 {
    font-family: var(--font-display);
    letter-spacing: -0.045em;
    margin: 0;
    font-weight: 700;
}
.lc-hero h1 { max-width: 660px; font-size: clamp(1.65rem, 2.7vw, 2.55rem); line-height: 1.1; }
.lc-hero-description { color: #475569; max-width: 500px; margin: 26px 0 25px; font-size: 1.04rem; line-height: 1.75; }
.lc-button {
    background: var(--lc-green);
    justify-content: center;
    align-items: center;
    min-height: 54px;
    padding: 0 23px;
    font-size: 0.91rem;
    font-weight: 700;
    transition: background-color 0.16s, transform 0.16s;
    display: inline-flex;
    color: #ffffff !important;
    border-radius: 8px;
    cursor: pointer;
}
.lc-button:hover { background: <?php echo htmlspecialchars($color_primario); ?>dd; transform: translateY(-2px); }
.lc-hero-note { color: #64748b; margin: 15px 0 0; font-size: 0.8rem; }
.lc-hero-note a { color: var(--lc-ink); text-underline-offset: 3px; text-decoration: underline #94a3b8; cursor: pointer; }
.lc-hero-figure {
    background: var(--lc-sand);
    min-height: 570px;
    margin: 0;
    position: relative;
    overflow: hidden;
    border-radius: 16px;
}
.lc-hero-image { object-fit: cover; width: 100%; height: 100%; min-height: 570px; display: block; }
.lc-hero-figure figcaption {
    background: var(--lc-green);
    color: #ffffff;
    letter-spacing: 0.01em;
    max-width: calc(100% - 36px);
    padding: 17px 21px;
    font-size: 0.82rem;
    font-weight: 600;
    position: absolute;
    bottom: 0; right: 0;
    border-top-left-radius: 12px;
}

/* ROUTE LINE */
.lc-route-line {
    color: #475569;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    border-top: 1px solid rgba(30, 41, 59, 0.15);
    align-items: center;
    gap: 22px;
    padding-block: 24px 30px;
    font-size: 0.79rem;
    font-weight: 700;
    display: flex;
}
.lc-route-rule { background: var(--lc-orange); width: 52px; height: 1px; }

/* SECCIONES */
.lc-section { border-top: 1px solid rgba(30, 41, 59, 0.15); padding-block: 94px; }
.lc-idea { grid-template-columns: minmax(160px, 0.34fr) minmax(0, 1fr); gap: 50px; display: grid; }
.lc-section-label {
    color: #64748b;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    align-items: baseline;
    gap: 14px;
    font-size: 0.74rem;
    font-weight: 700;
    display: flex;
}
.lc-section-label span:first-child { color: var(--lc-orange); }
.lc-idea-content { grid-template-columns: minmax(0, 1fr) minmax(230px, 0.56fr); align-items: end; gap: 52px; display: grid; }
.lc-idea h2, .lc-contact h2 { max-width: 610px; font-size: clamp(2.6rem, 4.3vw, 4.1rem); line-height: 1.03; }
.lc-idea-content p, .lc-step p, .lc-contact-action p { color: #475569; margin: 0; font-size: 0.98rem; line-height: 1.75; }
.lc-steps { padding-top: 25px; padding-bottom: 112px; }
.lc-steps-title { max-width: 650px; font-size: clamp(2.6rem, 4.2vw, 4rem); line-height: 1.04; margin-top: 30px !important; }
.lc-step-list { border-top: 1px solid rgba(30, 41, 59, 0.2); grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 57px; display: grid; }
.lc-step { min-height: 190px; padding: 22px 32px 0 0; }
.lc-step + .lc-step { border-left: 1px solid rgba(30, 41, 59, 0.2); padding-left: 32px; }
.lc-step-number { color: var(--lc-orange); letter-spacing: 0.08em; font-size: 0.74rem; font-weight: 800; }
.lc-step h3 { font-family: var(--font-display); letter-spacing: -0.035em; margin: 23px 0 10px; font-size: 1.65rem; font-weight: 600; }
.lc-step p { max-width: 295px; font-size: 0.9rem; }

/* PAGOS */
.lc-payments {
    background: var(--lc-green);
    color: #ffffff;
    grid-template-columns: minmax(0, 0.95fr) minmax(0, 1.05fr);
    align-items: center;
    gap: 70px;
    padding: 76px max(48px, 50vw - 592px);
    display: grid;
}
.lc-eyebrow-light { color: #e2e8f0; margin-bottom: 18px; }
.lc-payments h2 { max-width: 480px; font-size: clamp(2.7rem, 4.3vw, 4.1rem); line-height: 1.02; }
.lc-payment-names { font-family: var(--font-display); letter-spacing: -0.035em; margin: 0 0 19px; font-size: clamp(1.6rem, 2.5vw, 2.3rem); font-weight: 500; }
.lc-payment-names span { color: #cbd5e1; padding-inline: 7px; }
.lc-payments-detail > p:last-child { color: #e2e8f0; max-width: 590px; margin: 0; font-size: 0.9rem; line-height: 1.75; }

/* CONTACTO */
.lc-contact { grid-template-columns: minmax(0, 1fr) minmax(280px, 0.58fr); align-items: end; gap: 50px; padding-block: 100px; display: grid; }
.lc-section-kicker { color: #64748b; margin-bottom: 21px; }
.lc-contact-action p { max-width: 400px; margin-bottom: 20px; }
.lc-button-dark { background: var(--lc-orange); color: #ffffff !important; border-radius: 8px; }
.lc-button-dark:hover { background: <?php echo htmlspecialchars($color_secundario); ?>dd; }

/* FOOTER */
.lc-footer {
    color: #475569;
    border-top: 1px solid rgba(30, 41, 59, 0.15);
    grid-template-columns: auto minmax(250px, 1fr) auto;
    align-items: center;
    gap: 32px;
    min-height: 110px;
    font-size: 0.78rem;
    display: grid;
}
.lc-footer-wordmark { font-size: 1.25rem; color: var(--lc-ink) !important; }
.lc-footer p { margin: 0; }
.lc-footer > a:last-child { text-underline-offset: 3px; text-decoration: underline; cursor: pointer; }

/* RESPONSIVE */
@media (max-width: 900px) {
    .lc-header, .lc-hero, .lc-route-line, .lc-section, .lc-contact, .lc-footer {
        width: min(100% - 48px, 720px);
    }
    .lc-hero { grid-template-columns: minmax(0, 1fr) minmax(250px, 0.88fr); gap: 36px; padding-block: 52px; }
    .lc-hero h1 { font-size: clamp(3rem, 6.1vw, 4.1rem); }
    .lc-hero-figure, .lc-hero-image { min-height: 490px; }
    .lc-idea-content { grid-template-columns: 1fr; gap: 24px; }
    .lc-payments { gap: 40px; padding-inline: 24px; }
}

@media (max-width: 640px) {
    .lc-header, .lc-hero, .lc-route-line, .lc-section, .lc-contact, .lc-footer {
        width: min(100% - 40px, 520px);
    }
    .lc-header { gap: 14px; min-height: 72px; }
    .lc-wordmark { gap: 8px; font-size: 1.32rem; }
    .lc-mark { width: 30px; height: 30px; }
    .lc-nav { display: none; }
    .lc-header-cta { gap: 5px; margin-left: auto; font-size: 0.82rem; }
    .lc-hero { grid-template-columns: 1fr; gap: 32px; padding-block: 43px 42px; }
    .lc-hero-copy { padding: 0; }
    .lc-eyebrow { margin-bottom: 17px; font-size: 0.66rem; }
    .lc-hero h1 { max-width: 510px; font-size: clamp(1.35rem, 5.25vw, 1.8rem); line-height: 1.15; }
    .lc-hero-description { margin-block: 19px 21px; font-size: 0.96rem; line-height: 1.65; }
    .lc-button { min-height: 50px; padding-inline: 18px; font-size: 0.84rem; }
    .lc-hero-note { font-size: 0.74rem; }
    .lc-hero-figure, .lc-hero-image { min-height: 400px; }
    .lc-hero-figure figcaption { padding: 14px 16px; font-size: 0.75rem; }
    .lc-route-line { letter-spacing: 0.025em; gap: 12px; padding-block: 19px 23px; font-size: 0.59rem; }
    .lc-route-rule { flex: 0 0 23px; width: 23px; }
    .lc-section { padding-block: 62px; }
    .lc-idea { grid-template-columns: 1fr; gap: 29px; }
    .lc-section-label { font-size: 0.68rem; }
    .lc-idea h2, .lc-contact h2, .lc-steps-title, .lc-payments h2 { font-size: clamp(2.55rem, 11vw, 3.45rem); }
    .lc-idea-content p, .lc-contact-action p { font-size: 0.91rem; }
    .lc-steps { padding-top: 23px; padding-bottom: 70px; }
    .lc-steps-title { margin-top: 24px !important; }
    .lc-step-list { grid-template-columns: 1fr; margin-top: 33px; }
    .lc-step, .lc-step + .lc-step { border-left: 0; min-height: 0; padding: 20px 0 24px; }
    .lc-step + .lc-step { border-top: 1px solid rgba(30, 41, 59, 0.2); }
    .lc-step h3 { margin: 12px 0 5px; font-size: 1.45rem; }
    .lc-step p { max-width: 360px; font-size: 0.88rem; }
    .lc-payments { grid-template-columns: 1fr; gap: 28px; padding: 55px 20px 58px; }
    .lc-payment-names { font-size: 1.75rem; }
    .lc-payments-detail > p:last-child { font-size: 0.85rem; }
    .lc-contact { grid-template-columns: 1fr; gap: 24px; padding-block: 67px; }
    .lc-contact-action p { margin-bottom: 17px; }
    .lc-footer { grid-template-columns: 1fr auto; gap: 8px 20px; padding-block: 22px; }
    .lc-footer-wordmark { grid-column: 1 / -1; }
    .lc-footer p { max-width: 245px; font-size: 0.72rem; line-height: 1.5; }
    .lc-footer > a:last-child { font-size: 0.7rem; }
}

@media (prefers-reduced-motion: reduce) {
    .lc-site *, .lc-site *::before, .lc-site *::after {
        scroll-behavior: auto !important;
        transition-duration: 0.01ms !important;
    }
}

/* ============================================ */
/* MODAL DE COMENTARIOS                        */
/* ============================================ */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(5px);
    z-index: 2000;
    animation: fadeIn 0.3s ease;
}

.modal-overlay[style*="display: block"] {
    display: flex !important;
    align-items: center;
    justify-content: center;
}

.modal-contenido {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 2rem;
    border-radius: 16px;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 30px rgba(0, 0, 0, 0.2);
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
    line-height: 1;
}

.close-modal:hover {
    color: #2d3748;
}

/* Responsive para móvil */
@media (max-width: 640px) {
    .modal-contenido {
        width: 95%;
        padding: 1rem;
    }
    .close-modal {
        top: 0.5rem;
        right: 1rem;
        font-size: 1.8rem;
    }
    .modal-contenido input,
    .modal-contenido select,
    .modal-contenido textarea {
        font-size: 16px !important;
    }
}
</style>
</head>
<body>

<main class="lc-site">

    <!-- HEADER -->
    <header class="lc-header">
                                <a class="lc-wordmark" href="#inicio" aria-label="<?php echo htmlspecialchars($sitio_nombre); ?>, inicio">
            <?php if (!empty($logo_url)): ?>
                <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($sitio_nombre); ?>" class="lc-logo-img">
            <?php else: ?>
                <span class="lc-mark" aria-hidden="true">C<span>J</span></span>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($sitio_nombre); ?></span>
        </a>
        <nav class="lc-nav" aria-label="Navegación principal">
            <a href="#proceso">La idea</a>
            <a href="#pagos">Pagos</a>
        </nav>
        <a class="lc-header-cta" href="javascript:void(0)" onclick="abrirModalComentarios()">
            Escríbenos <span aria-hidden="true">↗</span>
        </a>
    </header>

    <!-- HERO -->
    <section class="lc-hero" id="inicio" aria-labelledby="hero-title">
        <div class="lc-hero-copy">
            <p class="lc-eyebrow">
                <span aria-hidden="true"></span>
                Tienda en Producción
            </p>
            <h1 id="hero-title">Usted compra desde Estados Unidos y Europa. Nosotros entregamos a quienes más quieres en Cuba. "catalejoo.com" 24 horas trabajando para Ti..</h1>
            <p class="lc-hero-description">
                <?php echo htmlspecialchars($sitio_nombre); ?> , es una tienda online para comprar productos y combos desde donde estés y que sean entregados a familiares y amistades en Cuba. Estamos esperando tu pedido inicial.
            </p>
            <a class="lc-button" href="https://catalejoo.com">Visitar tienda</a>
            <p class="lc-hero-note">Consultas: <a href="javascript:void(0)" onclick="abrirModalComentarios()">Escríbenos</a></p>
        </div>
        <figure class="lc-hero-figure">
            <img class="lc-hero-image" src="/uploads/bienvenida/hero.jpg" alt="Bodegón editorial con un paquete de papel, una nota y luz cálida de una ventana." fetchpriority="high" onerror="this.style.display='none'">
            <figcaption>Una compra hecha pensando en alguien.</figcaption>
        </figure>
    </section>

    <!-- ROUTE LINE -->
    <div class="lc-route-line">
        <span>Estados Unidos · Europa</span>
        <span class="lc-route-rule" aria-hidden="true"></span>
        <span>Familia y amistades en Cuba</span>
    </div>

    <!-- LA IDEA -->
    <section class="lc-idea lc-section" id="proceso" aria-labelledby="idea-title">
        <div class="lc-section-label">
            <span>01</span>
            <span>La idea</span>
        </div>
        <div class="lc-idea-content">
            <h2 id="idea-title">Estar lejos no impide estar presente.</h2>
            <p>
                Queremos hacer más sencillo elegir una compra en línea para las personas que más quieres. El catálogo de Catalejoo, las opciones de entrega y el total serán su mejor opción en todo el cibercomercio.
            </p>
        </div>
    </section>

    <!-- PASOS -->
    <section class="lc-steps lc-section" aria-labelledby="steps-title">
        <div class="lc-section-label">
            <span>02</span>
            <span>Una compra clara</span>
        </div>
        <h2 class="lc-steps-title" id="steps-title">Cada detalle, a la vista antes de confirmar.</h2>
        <div class="lc-step-list">
            <article class="lc-step">
                <span class="lc-step-number">01</span>
                <h3>Elegir</h3>
                <p>Los productos publicados mostrarán sus variantes y precios en su moneda.</p>
            </article>
            <article class="lc-step">
                <span class="lc-step-number">02</span>
                <h3>Revisar</h3>
                <p>Las opciones de pago, todo el proceso de empaquetado, transportación y entregas sera informado poe email antes del pago.</p>
            </article>
            <article class="lc-step">
                <span class="lc-step-number">03</span>
                <h3>Confirmar</h3>
                <p>Solo se ofrecerán opciones de pago verificadas. Usted pagara después de que sea entregado su pedido.</p>
            </article>
        </div>
    </section>

    <!-- PAGOS -->
    <section class="lc-payments" id="pagos" aria-labelledby="payments-title">
        <div class="lc-payments-heading">
            <p class="lc-eyebrow lc-eyebrow-light">Opciones de pago sujetas a verificación</p>
            <h2 id="payments-title">Pagar también debe ser claro.</h2>
        </div>
        <div class="lc-payments-detail">
            <p class="lc-payment-names">Zelle <span>·</span> Bizum <span>·</span> PayPal</p>
            <p>
                Estos métodos están entre sus opciones. Su disponibilidad dependerá, de las políticas vigentes de la plataforma y de las normas aplicables. Confirmaremos únicamente las opciones verificadas.
            </p>
        </div>
    </section>

    <!-- CONTACTO -->
    <section class="lc-contact" aria-labelledby="contact-title">
        <div>
            <p class="lc-section-kicker">Hablemos</p>
            <h2 id="contact-title">¿Para quién te gustaría comprar?</h2>
        </div>
        <div class="lc-contact-action">
            <p>Escríbenos y cuéntanos qué te gustaría encontrar en <?php echo htmlspecialchars($sitio_nombre); ?>.</p>
            <a class="lc-button lc-button-dark" href="javascript:void(0)" onclick="abrirModalComentarios()">Contactar a <?php echo htmlspecialchars($sitio_nombre); ?></a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="lc-footer">
        <a class="lc-footer-wordmark" href="#inicio"><?php echo htmlspecialchars(strtolower($sitio_nombre)); ?></a>
        <p>Compras desde Estados Unidos y Europa. Entregas para quienes quieres en Cuba.</p>
        <a href="javascript:void(0)" onclick="abrirModalComentarios()">Contacto</a>
    </footer>

</main>

<!-- Modal de comentarios (incluye su propio CSS y JS) -->
<?php include __DIR__ . '/index_modales/comentarios.php'; ?>

<?php $conn->close(); ?>
</body>
</html>
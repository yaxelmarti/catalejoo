
/* ==========================================================
   CATALEJOO - REDISEÑO VISUAL
   SOLO CSS - NO MODIFICA FUNCIONALIDADES
   ========================================================== */

:root {
    --catalejoo-green: #087c50;
    --catalejoo-green-dark: #123d30;
    --catalejoo-green-soft: #eaf7ef;
    --catalejoo-blue: #263fce;
    --catalejoo-gold: #ffcc38;
    --catalejoo-ink: #24352d;
    --catalejoo-muted: #718078;
    --catalejoo-line: #e4ebe6;
    --catalejoo-shadow: 0 8px 26px rgba(18,61,48,.08);
}

/* CONTENEDOR PRINCIPAL */

main.container {
    width: min(1240px, 96%) !important;
    max-width: 1240px !important;
    margin-left: auto !important;
    margin-right: auto !important;
    padding-left: 12px !important;
    padding-right: 12px !important;
}

main.container > .container,
main.container .container {
    max-width: 100%;
}

/* SECCIONES */

.amazon-seccion {
    margin: 30px 0 !important;
}

.amazon-titulo-seccion {
    color: var(--catalejoo-ink) !important;
    font-size: clamp(1.15rem, 2vw, 1.55rem) !important;
    font-weight: 800 !important;
    letter-spacing: -.35px;
    border-bottom: 0 !important;
    padding-bottom: 12px !important;
    margin-bottom: 17px !important;
    position: relative;
}

.amazon-titulo-seccion::after {
    content: "";
    display: block;
    width: 58px;
    height: 4px;
    margin-top: 10px;
    border-radius: 10px;
    background: linear-gradient(90deg, var(--catalejoo-green), #45b77c);
}

/* BANNER / CARRUSEL PRINCIPAL */

.anuncios-fade-container {
    max-width: 100% !important;
    height: clamp(210px, 34vw, 430px) !important;
    margin: 18px 0 25px !important;
    border-radius: 22px !important;
    box-shadow: 0 12px 32px rgba(20,48,35,.13) !important;
    background-color: #eaf2ed;
}

/* CATEGORÍAS */

.categorias-barra-blanca {
    background: rgba(255,255,255,.96) !important;
    border: 1px solid var(--catalejoo-line) !important;
    border-radius: 16px !important;
    padding: 9px 8px !important;
    margin: 12px 0 18px !important;
    box-shadow: 0 5px 18px rgba(18,61,48,.05) !important;
}

.categorias-barra-scroll {
    gap: 10px !important;
    padding: 8px 12px !important;
}

.categoria-barra-item {
    background: #f5f8f6 !important;
    border: 1px solid #e4ebe6 !important;
    border-radius: 30px !important;
    color: #294338 !important;
    padding: 9px 15px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    transition: background .2s ease, color .2s ease, border-color .2s ease !important;
}

.categoria-barra-item:hover {
    background: var(--catalejoo-green) !important;
    color: #fff !important;
    border-color: var(--catalejoo-green) !important;
    transform: translateY(-1px);
}

.categoria-barra-icono {
    font-size: 19px !important;
}

.categoria-barra-nombre {
    font-size: 13px !important;
}

/* BUSCADOR */

.buscador-shop-container {
    margin: 18px auto 22px !important;
}

#buscadorShopForm {
    max-width: 720px !important;
    background: #fff;
    border: 1px solid var(--catalejoo-line);
    border-radius: 40px;
    padding: 5px !important;
    box-shadow: var(--catalejoo-shadow);
}

#buscadorShopInput {
    border: 0 !important;
    border-radius: 35px !important;
    padding: 13px 18px !important;
    color: var(--catalejoo-ink);
    background: transparent;
    box-shadow: none !important;
}

#buscadorShopInput:focus {
    outline: none;
    border: 0 !important;
    box-shadow: 0 0 0 3px rgba(8,124,80,.10) !important;
}

#buscadorShopForm button {
    background: linear-gradient(135deg, var(--catalejoo-green), #159965) !important;
    border-radius: 35px !important;
    padding: 12px 24px !important;
    box-shadow: none !important;
}

.buscador-shop-container p {
    color: var(--catalejoo-muted) !important;
    font-size: 12px !important;
    margin-top: 8px !important;
}

/* TARJETAS DE ACCESO RÁPIDO */

.amazon-tarjetas-grid {
    gap: 17px !important;
}

.amazon-tarjeta {
    border-radius: 18px !important;
    box-shadow: 0 7px 22px rgba(18,61,48,.12) !important;
    border: 1px solid rgba(255,255,255,.55);
}

.amazon-tarjeta:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 13px 30px rgba(18,61,48,.18) !important;
}

.amazon-tarjeta-overlay {
    background: linear-gradient(180deg, rgba(10,28,20,.05), rgba(10,28,20,.22));
}

.amazon-tarjeta-titulo {
    font-weight: 900 !important;
    letter-spacing: -.4px;
}

.amazon-tarjeta-badge {
    background: var(--catalejoo-gold) !important;
    color: #283727 !important;
    border: 0 !important;
    font-weight: 800 !important;
    padding: 6px 10px !important;
}

.amazon-tarjeta-boton {
    background: rgba(255,255,255,.94) !important;
    color: var(--catalejoo-green-dark) !important;
    border: 0 !important;
    border-radius: 30px !important;
    padding: 8px 17px !important;
    font-size: 13px !important;
    font-weight: 800 !important;
    text-shadow: none !important;
}

/* PRODUCTOS Y CARRUSELES */

.carrusel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.ver-todos-link {
    color: var(--catalejoo-green) !important;
    font-weight: 800 !important;
    font-size: 13px !important;
}

.carrusel-wrapper-productos {
    margin: 0 !important;
}

.carrusel-productos {
    gap: 15px !important;
    padding: 8px 5px 18px !important;
    scroll-padding-left: 5px;
}

.producto-card-sin-carrito {
    width: 205px !important;
    flex: 0 0 205px !important;
    background: #fff !important;
    border: 1px solid var(--catalejoo-line) !important;
    border-radius: 15px !important;
    overflow: hidden !important;
    box-shadow: 0 4px 15px rgba(18,61,48,.06) !important;
    transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease !important;
}

.producto-card-sin-carrito:hover {
    transform: translateY(-4px) !important;
    border-color: rgba(8,124,80,.35) !important;
    box-shadow: 0 11px 25px rgba(18,61,48,.12) !important;
}

.producto-card-sin-carrito .producto-imagen-wrapper {
    min-height: 165px !important;
    height: 165px !important;
    padding: 13px !important;
    background: #f7faf8 !important;
    border-bottom: 1px solid #edf1ee;
}

.producto-card-sin-carrito .producto-imagen {
    max-height: 140px !important;
    width: 100% !important;
    object-fit: contain !important;
}

.producto-info-sin-carrito {
    padding: 13px !important;
    gap: 6px;
}

.producto-info-sin-carrito .producto-marca {
    color: var(--catalejoo-green) !important;
    font-size: 11px !important;
    font-weight: 700;
}

.producto-info-sin-carrito .producto-nombre {
    color: var(--catalejoo-ink) !important;
    font-size: 13px !important;
    line-height: 1.4 !important;
    font-weight: 700 !important;
}

.producto-precio {
    margin-top: 7px !important;
}

.producto-precio .precio-final,
.producto-precio .precio-normal {
    color: var(--catalejoo-green) !important;
    font-size: 18px !important;
    font-weight: 900 !important;
}

.producto-precio .precio-original {
    color: #929b95 !important;
    font-size: 12px !important;
}

.ver-producto {
    color: var(--catalejoo-green) !important;
    font-size: 12px !important;
    font-weight: 800 !important;
}

.etiqueta-rebaja {
    background: #e74747 !important;
    border-radius: 7px !important;
    font-weight: 800 !important;
}

.etiqueta-nuevo {
    border-radius: 7px !important;
    font-weight: 800 !important;
}

/* FLECHAS DE CARRUSELES */

.carrusel-btn-productos {
    background: #fff !important;
    color: var(--catalejoo-green) !important;
    border: 1px solid var(--catalejoo-line) !important;
    box-shadow: 0 4px 14px rgba(18,61,48,.12) !important;
}

.carrusel-btn-productos:hover {
    background: var(--catalejoo-green) !important;
    color: #fff !important;
}

/* BOTÓN VER TODOS */

.amazon-btn-ver-todos {
    background: linear-gradient(135deg, var(--catalejoo-blue), var(--catalejoo-green)) !important;
    border-radius: 30px !important;
    padding: 13px 30px !important;
    font-size: 14px !important;
    font-weight: 800 !important;
    box-shadow: 0 7px 18px rgba(38,63,206,.18) !important;
}

/* PIE DE PÁGINA */

.site-footer {
    background: linear-gradient(135deg, #123d30, #0b5c40) !important;
    color: #fff !important;
    margin-top: 42px !important;
    padding-top: 38px !important;
}

.site-footer .footer-col h4 {
    color: #fff !important;
    font-weight: 800 !important;
}

.site-footer .footer-col p,
.site-footer .footer-col a {
    color: #d8e9df !important;
}

.site-footer a:hover {
    color: var(--catalejoo-gold) !important;
}

/* TABLETS */

@media (max-width: 900px) {
    main.container {
        width: 97% !important;
        padding-left: 8px !important;
        padding-right: 8px !important;
    }

    .amazon-tarjetas-grid {
        gap: 12px !important;
    }

    .producto-card-sin-carrito {
        width: 185px !important;
        flex-basis: 185px !important;
    }
}

/* MÓVILES */

@media (max-width: 600px) {
    main.container {
        width: 100% !important;
        padding-left: 10px !important;
        padding-right: 10px !important;
    }

    .anuncios-fade-container {
        height: clamp(175px, 58vw, 270px) !important;
        margin: 12px 0 18px !important;
        border-radius: 15px !important;
    }

    .categorias-barra-blanca {
        border-radius: 12px !important;
        margin-bottom: 12px !important;
    }

    .categoria-barra-item {
        padding: 7px 11px !important;
    }

    .amazon-seccion {
        margin: 23px 0 !important;
    }

    .amazon-titulo-seccion {
        font-size: 1.12rem !important;
        margin-bottom: 13px !important;
    }

    .amazon-tarjetas-grid {
        gap: 9px !important;
        padding: 0 !important;
    }

    .amazon-tarjeta {
        border-radius: 13px !important;
    }

    .amazon-tarjeta-titulo {
        font-size: 1.2rem !important;
    }

    .amazon-tarjeta-icono {
        font-size: 1.8rem !important;
    }

    .tarjeta-miniatura,
    .tarjeta-miniatura-placeholder {
        width: 48px !important;
        height: 48px !important;
    }

    .amazon-tarjeta-boton {
        font-size: 11px !important;
        padding: 6px 12px !important;
    }

    .producto-card-sin-carrito {
        width: 155px !important;
        flex-basis: 155px !important;
    }

    .producto-card-sin-carrito .producto-imagen-wrapper {
        height: 125px !important;
        min-height: 125px !important;
        padding: 9px !important;
    }

    .producto-card-sin-carrito .producto-imagen {
        max-height: 105px !important;
    }

    .producto-info-sin-carrito {
        padding: 10px !important;
    }

    .producto-info-sin-carrito .producto-nombre {
        font-size: 11px !important;
    }

    .producto-precio .precio-final,
    .producto-precio .precio-normal {
        font-size: 16px !important;
    }

    .carrusel-productos {
        gap: 10px !important;
    }

    .amazon-btn-ver-todos {
        padding: 12px 23px !important;
        font-size: 13px !important;
    }

    .site-footer {
        padding-top: 28px !important;
    }
}

/* TELÉFONOS PEQUEÑOS */

@media (max-width: 380px) {
    .producto-card-sin-carrito {
        width: 142px !important;
        flex-basis: 142px !important;
    }

    .tarjeta-miniatura,
    .tarjeta-miniatura-placeholder {
        width: 42px !important;
        height: 42px !important;
    }

    .amazon-tarjeta-titulo {
        font-size: 1.05rem !important;
    }
}

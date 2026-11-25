<style>
    /* Para que el footer sea sticky */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow-x: hidden;
    width: 100%;
    max-width: 100%;
}

body {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

main.container {
    flex: 1;
}

.site-footer {
    margin-top: auto;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    padding: 1rem 0 0.8rem;
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

/* === SOLO PARA MÓVIL: Footer fijo abajo en dos líneas === */
.footer-movil {
    display: none;
}

@media (max-width: 768px) {
    .site-footer .container {
        display: none;
    }
    
    .footer-movil {
        display: block;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        color: white;
        padding: 0.3rem 0;
        text-align: center;
        z-index: 100;
        box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
    }
    
    .footer-movil-redes {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-bottom: 0.2rem;
    }
    
    .social-icon-movil {
        font-size: 1.2rem;
        text-decoration: none;
        color: white;
    }
    
    .footer-movil-contacto {
        display: flex;
        justify-content: center;
        gap: 1rem;
        font-size: 0.7rem;
        flex-wrap: wrap;
    }
    
    .footer-movil-tel, .footer-movil-email {
        color: white;
        text-decoration: none;
    }
    
    body {
        padding-bottom: 55px;
    }
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
</style>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>✨ <?php echo htmlspecialchars($sitio_nombre); ?></h4>
                <p><?php echo htmlspecialchars($sitio_slogan); ?></p>
                <p>© <?php echo date('Y'); ?></p>
            </div>
            
            <div class="footer-col">
                <h4>📞 Contacto</h4>
                <ul class="footer-contacto">
                    <?php if (!empty($config['sitio_telefono'])): ?>
                    <li><span>📱</span> <a href="tel:<?php echo htmlspecialchars($config['sitio_telefono']); ?>"><?php echo htmlspecialchars($config['sitio_telefono']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($config['sitio_email'])): ?>
                    <li><span>📧</span> <a href="mailto:<?php echo htmlspecialchars($config['sitio_email']); ?>"><?php echo htmlspecialchars($config['sitio_email']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($config['horario_atencion'])): ?>
                    <li><span>⏰</span> <span><?php echo htmlspecialchars($config['horario_atencion']); ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="footer-col">
    <h4>🌐 Redes</h4>
    <div class="footer-social">
        <?php if (!empty($config['facebook_url'])): ?>
        <a href="<?php echo htmlspecialchars($config['facebook_url']); ?>" target="_blank" class="social-icon" title="Facebook">📘</a>
        <?php endif; ?>
        <?php if (!empty($config['instagram_url'])): ?>
        <a href="<?php echo htmlspecialchars($config['instagram_url']); ?>" target="_blank" class="social-icon" title="Instagram">📷</a>
        <?php endif; ?>
        <?php if (!empty($config['twitter_url'])): ?>
        <a href="<?php echo htmlspecialchars($config['twitter_url']); ?>" target="_blank" class="social-icon" title="Twitter">🐦</a>
        <?php endif; ?>
        <?php if (!empty($config['whatsapp_numero'])): ?>
        <a href="https://wa.me/<?php echo htmlspecialchars($config['whatsapp_numero']); ?>" target="_blank" class="social-icon" title="WhatsApp">💬</a>
        <?php endif; ?>
        <?php if (!empty($config['tiktok_url'])): ?>
        <a href="<?php echo htmlspecialchars($config['tiktok_url']); ?>" target="_blank" class="social-icon" title="TikTok">🎵</a>
        <?php endif; ?>
        <?php if (!empty($config['youtube_url'])): ?>
        <a href="<?php echo htmlspecialchars($config['youtube_url']); ?>" target="_blank" class="social-icon" title="YouTube">▶️</a>
        <?php endif; ?>
        <?php if (!empty($config['telegram_url'])): ?>
        <a href="<?php echo htmlspecialchars($config['telegram_url']); ?>" target="_blank" class="social-icon" title="Telegram">✈️</a>
        <?php endif; ?>
    </div>
</div>
            
            <div class="footer-col">
    <h4>🔗 Enlaces</h4>
    <ul class="footer-enlaces">
        <li><a href="/directorio_proveedores.php">🏪 Directorio de tiendas</a></li>
        <li><a href="/ayuda.php">❓ Ayuda</a></li>
        <?php if ($cliente_registrado): ?>
        <li><a href="/mis_pedidos.php">📋 Mis Compras</a></li>
        <li><a href="#" onclick="abrirPanelCliente()">👤 Mi cuenta</a></li>
        <?php endif; ?>
    </ul>
</div>
        </div>
    </div>
    
    <!-- Footer para móvil (dos líneas fijo abajo) -->
    <div class="footer-movil">
    <div class="footer-movil-redes">
        <?php if (!empty($config['facebook_url'])): ?>
            <a href="<?php echo htmlspecialchars($config['facebook_url']); ?>" target="_blank" class="social-icon-movil">📘</a>
        <?php endif; ?>
        <?php if (!empty($config['instagram_url'])): ?>
            <a href="<?php echo htmlspecialchars($config['instagram_url']); ?>" target="_blank" class="social-icon-movil">📷</a>
        <?php endif; ?>
        <?php if (!empty($config['twitter_url'])): ?>
            <a href="<?php echo htmlspecialchars($config['twitter_url']); ?>" target="_blank" class="social-icon-movil">🐦</a>
        <?php endif; ?>
        <?php if (!empty($config['whatsapp_numero'])): ?>
            <a href="https://wa.me/<?php echo htmlspecialchars($config['whatsapp_numero']); ?>" target="_blank" class="social-icon-movil">💬</a>
        <?php endif; ?>
        <?php if (!empty($config['tiktok_url'])): ?>
            <a href="<?php echo htmlspecialchars($config['tiktok_url']); ?>" target="_blank" class="social-icon-movil">🎵</a>
        <?php endif; ?>
        <?php if (!empty($config['youtube_url'])): ?>
            <a href="<?php echo htmlspecialchars($config['youtube_url']); ?>" target="_blank" class="social-icon-movil">▶️</a>
        <?php endif; ?>
        <?php if (!empty($config['telegram_url'])): ?>
            <a href="<?php echo htmlspecialchars($config['telegram_url']); ?>" target="_blank" class="social-icon-movil">✈️</a>
        <?php endif; ?>
    </div>
    <div class="footer-movil-contacto">
   <?php if (!empty($config['sitio_telefono'])): ?>
                <a href="tel:<?php echo htmlspecialchars($config['sitio_telefono']); ?>" class="footer-movil-tel">📱 <?php echo htmlspecialchars($config['sitio_telefono']); ?></a>
            <?php endif; ?>
            <?php if (!empty($config['sitio_email'])): ?>
                <a href="mailto:<?php echo htmlspecialchars($config['sitio_email']); ?>" class="footer-movil-email">📧 <?php echo htmlspecialchars($config['sitio_email']); ?></a>
            <?php endif; ?>
        </div>
    </div>
</footer>
<?php
// ============================================
// FOOTER
// ============================================
?>
        </main>
    </div>
    
    <footer class="admin-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $sitio_nombre ?? 'Catalejo'; ?> - Todos los derechos reservados</p>
        </div>
    </footer>
    
    <!-- Scripts comunes -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <?php if (isset($admin_js) && !empty($admin_js)): ?>
        <script src="<?php echo $admin_js; ?>"></script>
    <?php endif; ?>
</body>
</html>
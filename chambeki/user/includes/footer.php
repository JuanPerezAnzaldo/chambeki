    </main>
    
        <!-- Footer para escritorio -->
        <footer class="main-footer">
            <div class="footer-content">
                <div class="footer-col">
                    <h4><?php echo SITENAME; ?></h4>
                    <p style="font-size: 0.9rem; line-height: 1.5;">Conectamos el talento y los oficios calificados con quienes necesitan soluciones confiables, rápidas y transparentes.</p>
                </div>
                <div class="footer-col">
                    <h4>Explorar</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>?accion=servicios">Oficios Populares</a></li>
                        <li><a href="<?php echo BASE_URL; ?>?accion=cobertura">Mapa de Cobertura</a></li>
                        <li><a href="<?php echo BASE_URL; ?>?accion=precios">Planes para Socios</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Soporte</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>?accion=ayuda">Centro de Ayuda</a></li>
                        <li><a href="<?php echo BASE_URL; ?>?accion=disputas">Gestión de Tickets</a></li>
                        <li><a href="<?php echo BASE_URL; ?>?accion=seguridad">Garantía de Servicio</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>?accion=terminos">Términos y Condiciones</a></li>
                        <li><a href="<?php echo BASE_URL; ?>?accion=privacidad">Aviso de Privacidad</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo SITENAME; ?> - Todos los derechos reservados.</p>
            </div>
        </footer>
    
        <!-- Barra de navegacion para movil -->
        <nav class="mobile-nav-bar">
            <a href="<?php echo BASE_URL; ?>" class="mobile-nav-item active">
                <svg viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
                <span>Inicio</span>
            </a>
            <a href="<?php echo BASE_URL; ?>?accion=servicios" class="mobile-nav-item">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <span>Buscar</span>
            </a>
            <a href="<?php echo BASE_URL; ?>?accion=citas" class="mobile-nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                <span>Citas</span>
            </a>
            <a href="<?php echo BASE_URL; ?>?accion=perfil" class="mobile-nav-item">
                <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                <span>Perfil</span>
            </a>
        </nav>


        <!-- JavaScript y service worker -->
        <script src="<?php echo ASSETS_JS; ?>theme.js"></script>
        <script src="<?php echo ASSETS_JS; ?>app.js"></script>
        <script>
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', () => {
                    navigator.serviceWorker.register('/chambeki/sw.js')
                        .then(reg => console.log('SW registrado en CHAMBEKI', reg))
                        .catch(err => console.error('Error en SW', err));
                });
            }
        </script>
    </body>
</html>
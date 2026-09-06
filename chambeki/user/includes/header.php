                                    <!DOCTYPE html>
                                    <html lang="es" data-theme="light">
                                    <head>
                                        <meta charset="UTF-8">
                                        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
                                        <title><?php echo SITENAME; ?> - Soluciones a tu alcance</title>

                                        <link rel="manifest" href="/manifest.json">
                                        <meta name="theme-color" content="#ff682e">

                                        <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>global.css?v=10">
                                        <?php if (!isset($accion) || $accion === 'inicio' || $accion === 'home'): ?>
                                            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>home.css?v=10">
                                        <?php endif; ?>
                                    </head>
                                    <body>
                                        <header class="encabezado-principal">
                                            <div class="contenedor-navegacion">
                                                <a href="<?php echo URL_BASE; ?>" class="logotipo-marca"><?php echo SITENAME; ?></a>

                                                <nav class="acciones-navegacion">
                                                    <!-- Botón de Opciones (Tres Puntos) -->
                                                    <div class="contenedor-menu-opciones">
                                                        <button type="button" id="btnMenuOpciones" class="boton-tres-puntos" aria-label="Más opciones" aria-expanded="false">
                                                            <svg viewBox="0 0 24 24">
                                                                <circle cx="12" cy="5" r="2.2"/>
                                                                <circle cx="12" cy="12" r="2.2"/>
                                                                <circle cx="12" cy="19" r="2.2"/>
                                                            </svg>
                                                        </button>

                                                        <div id="desplegableOpciones" class="menu-desplegable-opciones" style="display: none;">
                                                            <a href="<?php echo URL_BASE; ?>?accion=registro_socio" class="item-menu-opcion">
                                                                <svg class="icono-menu-opcion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                                                                    <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                                                                </svg>
                                                                <span>Ofrece tu servicio</span>
                                                            </a>

                                                            <div class="divisor-menu-opciones"></div>

                                                            <a href="<?php echo URL_BASE; ?>?accion=ayuda" class="item-menu-opcion">
                                                                <svg class="icono-menu-opcion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                    <circle cx="12" cy="12" r="10"></circle>
                                                                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                                                                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                                                </svg>
                                                                <span>Obtén ayuda</span>
                                                            </a>
                                                        </div>
                                                    </div>

                                                    <?php if (isset($_SESSION['id_usuario'])): ?>
                                                        <div class="tarjeta-usuario-sesion">
                                                            <span>Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario'); ?></span>
                                                            <a href="<?php echo URL_BASE; ?>?accion=cerrar_sesion" class="boton-nav-sesion">(Salir)</a>
                                                        </div>
                                                    <?php else: ?>
                                                        <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-nav-sesion">Inicia sesión</a>
                                                    <?php endif; ?>
                                                </nav>

                                                <script>
                                                    (function()
                                                    {
                                                        const boton = document.getElementById('btnMenuOpciones');
                                                        const menu = document.getElementById('desplegableOpciones');

                                                        if (boton && menu)
                                                        {
                                                            boton.addEventListener('click', function(e)
                                                            {
                                                                e.stopPropagation();
                                                                const visible = menu.style.display === 'flex';
                                                                menu.style.display = visible ? 'none' : 'flex';
                                                                boton.setAttribute('aria-expanded', !visible);
                                                            });

                                                            document.addEventListener('click', function(e)
                                                            {
                                                                if (!e.target.closest('.contenedor-menu-opciones'))
                                                                {
                                                                    menu.style.display = 'none';
                                                                    boton.setAttribute('aria-expanded', 'false');
                                                                }
                                                            });
                                                        }
                                                    })();
                                                </script>
                                            </div>
                                        </header>

                                        <main>
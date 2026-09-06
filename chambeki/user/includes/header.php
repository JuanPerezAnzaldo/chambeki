                            <!DOCTYPE html>
                            <html lang="es" data-theme="light">
                            <head>
                                <meta charset="UTF-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
                                <title><?php echo SITENAME; ?> - Soluciones a tu alcance</title>

                                <link rel="manifest" href="/manifest.json">
                                <meta name="theme-color" content="#ff682e">

                                <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>global.css?v=7">
                                <?php if (!isset($accion) || $accion === 'inicio' || $accion === 'home'): ?>
                                    <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>home.css?v=7">
                                <?php endif; ?>
                            </head>
                            <body>
                                <header class="encabezado-principal">
                                    <div class="contenedor-navegacion">
                                        <a href="<?php echo URL_BASE; ?>" class="logotipo-marca"><?php echo SITENAME; ?></a>

                                        <nav class="acciones-navegacion">
                                            <!-- Botón Tres Puntos Verticales -->
                                            <div class="contenedor-menu-opciones">
                                                <button type="button" id="btnMenuOpciones" class="boton-tres-puntos" aria-label="Más opciones" aria-expanded="false" onclick="alternarMenuOpciones(event)">
                                                    <svg viewBox="0 0 24 24">
                                                        <circle cx="12" cy="5" r="2"></circle>
                                                        <circle cx="12" cy="12" r="2"></circle>
                                                        <circle cx="12" cy="19" r="2"></circle>
                                                    </svg>
                                                </button>

                                                <div id="desplegableOpciones" class="menu-desplegable-opciones oculto">
                                                    <a href="<?php echo URL_BASE; ?>?accion=registro_socio" class="item-menu-opcion">
                                                        <span class="icono-menu-opcion">
                                                            <svg viewBox="0 0 24 24"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z"/></svg>
                                                        </span>
                                                        <span>Ofrece tu servicio</span>
                                                    </a>

                                                    <div class="divisor-menu-opciones"></div>

                                                    <a href="<?php echo URL_BASE; ?>?accion=ayuda" class="item-menu-opcion">
                                                        <span class="icono-menu-opcion">
                                                            <svg viewBox="0 0 24 24"><path d="M11 18h2v-2h-2v2zm1-16C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm0-14c-2.21 0-4 1.79-4 4h2c0-1.1.9-2 2-2s2 .9 2 2c0 2-3 1.75-3 5h2c0-2.25 3-2.5 3-5 0-2.21-1.79-4-4-4z"/></svg>
                                                        </span>
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
                                </header>

                                <main>
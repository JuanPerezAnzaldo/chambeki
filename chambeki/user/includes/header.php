                    <!DOCTYPE html>
                    <html lang="es" data-theme="light">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title><?php echo SITENAME; ?> - Soluciones a tu alcance</title>

                        <link rel="manifest" href="/manifest.json">
                        <meta name="theme-color" content="#ff682e">

                        <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>global.css?v=4">
                        <?php if (!isset($accion) || $accion === 'inicio' || $accion === 'home'): ?>
                            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>home.css?v=4">
                        <?php endif; ?>
                    </head>
                    <body>
                        <header class="encabezado-principal">
                            <div class="contenedor-navegacion">
                                <a href="<?php echo URL_BASE; ?>" class="logotipo-marca"><?php echo SITENAME; ?></a>

                                <nav class="acciones-navegacion">
                                    <a href="<?php echo URL_BASE; ?>?accion=registro_socio" class="enlace-navegacion">Ofrece tu servicio</a>

                                    <?php if (isset($_SESSION['id_usuario'])): ?>
                                        <div class="tarjeta-usuario-sesion">
                                            <span>Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario'); ?></span>
                                            <a href="<?php echo URL_BASE; ?>?accion=cerrar_sesion" class="boton-nav-sesion">(Salir)</a>
                                        </div>
                                    <?php else: ?>
                                        <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-nav-sesion">Inicia sesión</a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        </header>

                        <main>
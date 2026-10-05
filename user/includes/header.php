<!DOCTYPE html>
<html lang="es" data-theme="light">
    <head>
        <!-- Pa que agarre acentos y caracteres bien y para que se adapte el contenido a todas las pantallas -->
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title><?php echo SITENAME; ?> - Soluciones a tu alcance</title>

        <!-- Para lo de la app descargada 
        <link rel="manifest" href="/manifest.json">-->
        <meta name="theme-color" content="#ff682e">

        <!-- aqui pal css -->
        <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>global.css?v=10">
        <?php if (!isset($accion) || $accion === 'inicio' || $accion === 'home' || $accion === 'servicios'): ?>
            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>home.css?v=10">
        <?php endif; ?>

        <!-- pantallas de registro y recuperacion de contrasena -->
        <?php if (isset($accion) && ($accion === 'login' || $accion === 'registro' || $accion === 'recuperar')): ?>
            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>autenticacion.css?v=1">
        <?php endif; ?>

        <!-- pantalla de perfil (reusa botones/avisos de autenticacion.css) -->
        <?php if (isset($accion) && $accion === 'perfil'): ?>
            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>autenticacion.css?v=1">
            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>perfil.css?v=1">
        <?php endif; ?>

        <!-- pantalla de configuracion de perfil (usuario y freelancer) -->
        <?php if (isset($accion) && $accion === 'configuracion'): ?>
            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>autenticacion.css?v=1">
            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>perfil.css?v=1">
            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>configuracion.css?v=2">
        <?php endif; ?>
    </head>

    <body>
        <header class="encabezado-principal">
            <div class="contenedor-navegacion">
                <a href="<?php echo URL_BASE; ?>" class="logotipo-marca"><?php echo SITENAME; ?></a>

                <nav class="acciones-navegacion">
                    <!-- Botón de opciones  -->
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
                                <?php if (isset($_SESSION['id_usuario'])): ?>
                                    <a href="<?php echo URL_BASE; ?>?accion=configuracion" class="item-menu-opcion">
                                        <svg class="icono-menu-opcion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="3"></circle>
                                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                        </svg>
                                        <span>Configuración</span>
                                    </a>

                                    <div class="divisor-menu-opciones"></div>
                                <?php endif; ?>
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
                        <a href="<?php echo URL_BASE; ?>?accion=perfil" class="tarjeta-usuario-sesion" title="Ver mi perfil">
                            <?php if (!empty($_SESSION['foto_usuario'])): ?>
                                <img src="<?php echo URL_BASE . htmlspecialchars($_SESSION['foto_usuario']); ?>" alt="" class="avatar-nav-usuario">
                            <?php else: ?>
                                <span class="avatar-nav-usuario avatar-nav-iniciales"><?php echo htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['nombre_usuario'] ?? 'U', 0, 1))); ?></span>
                            <?php endif; ?>

                            <span class="datos-usuario-nav">
                                <span class="nombre-usuario-nav">Hola, <?php echo htmlspecialchars($_SESSION['nombre_usuario'] ?? 'Usuario'); ?></span>
                                <span class="rol-usuario-nav"><?php echo htmlspecialchars(nombreRol($_SESSION['rol_usuario'] ?? null)); ?></span>
                            </span>
                        </a>
                        <a href="<?php echo URL_BASE; ?>?accion=cerrar_sesion" class="boton-nav-sesion">Salir</a>
                    <?php else: ?>
                        <a href="<?php echo URL_BASE; ?>?accion=registro" class="boton-nav-sesion">Crea tu cuenta</a>
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
<!DOCTYPE html>
<html lang="es" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo SITENAME; ?> - Soluciones a tu alcance</title>
        
        <link rel="manifest" href="/chambeki/manifest.json">
        <meta name="theme-color" content="#ff682e">
        
        <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>global.css">
        <?php if (!isset($accion) || $accion === 'inicio'): ?>
            <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>home.css">
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
                            <a href="<?php echo URL_BASE; ?>?accion=cerrar_sesion" class="enlace-navegacion" style="font-size:0.8rem;">(Salir)</a>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo URL_BASE; ?>?accion=login" class="enlace-navegacion">Inicia sesión</a>
                    <?php endif; ?>
                    
                    <button id="botonAlternarTema" class="boton-cambio-tema" type="button">Modo Oscuro</button>
                </nav>
            </div>
        </header>
    
        <main>
<?php
/*
    Estructura visual compartida por las pantallas de
    inicio de sesion, registro y recuperacion de contrasena.

    Se usa asi dentro de una pagina:
        abrirPantallaAutenticacion('registro');
        ...contenido del formulario...
        cerrarPantallaAutenticacion();

    $pestanaActiva acepta 'login', 'registro' o null (sin pestanas,
    para las pantallas de codigo y confirmacion).
*/

function abrirPantallaAutenticacion($pestanaActiva = null)
{
    $textoHero = 'Tu red de confianza para servicios locales. Encuentra expertos verificados cerca de ti y resuelve lo que necesitas en casa.';
    ?>
    <section class="pantalla-autenticacion">

        <aside class="panel-hero-autenticacion">
            <h2 class="titulo-hero-autenticacion"><?php echo SITENAME; ?></h2>
            <p class="texto-hero-autenticacion"><?php echo $textoHero; ?></p>
        </aside>

        <div class="panel-formulario-autenticacion">
            <div class="tarjeta-autenticacion">

                <a href="<?php echo URL_BASE; ?>" class="marca-autenticacion"><?php echo SITENAME; ?></a>

                <?php if ($pestanaActiva !== null): ?>
                    <nav class="pestanas-autenticacion">
                        <a href="<?php echo URL_BASE; ?>?accion=login" class="pestana-autenticacion <?php echo $pestanaActiva === 'login' ? 'activa' : ''; ?>">Iniciar sesion</a>
                        <a href="<?php echo URL_BASE; ?>?accion=registro" class="pestana-autenticacion <?php echo $pestanaActiva === 'registro' ? 'activa' : ''; ?>">Crear cuenta</a>
                    </nav>
                <?php endif; ?>
    <?php
}

function cerrarPantallaAutenticacion()
{
    ?>
            </div>
        </div>
    </section>

    <script src="<?php echo JS_RUTA; ?>autenticacion.js?v=1"></script>
    <?php
}
?>

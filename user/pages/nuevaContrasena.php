<?php
/*
    PROCESO: Cambio / recuperacion de contrasena (paso 3 de 3)
    Requerimientos: RS-01, RS-05

    Pantalla dedicada para capturar la contrasena nueva. Solo se
    llega aqui despues de validar el codigo en
    verificarCodigoRecuperacion.php, y con una ventana de tiempo
    limitada (MINUTOS_VENTANA_CAMBIO) para completar el cambio.
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

//ventana para definir la contrasena despues de validar el codigo
define('MINUTOS_VENTANA_CAMBIO', 10);

if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

//solo se llega aqui con un codigo ya validado
if (!isset($_SESSION['recuperacion']) || $_SESSION['recuperacion']['paso'] !== 'nueva')
{
    redirigir(URL_BASE . '?accion=recuperar');
}

$errores = [];
$recuperacion = $_SESSION['recuperacion'];

//si se paso el tiempo de la ventana, hay que empezar de nuevo
if ((time() - $recuperacion['verificado_en']) > (MINUTOS_VENTANA_CAMBIO * 60))
{
    unset($_SESSION['recuperacion']);

    guardarMensaje('error', 'Se acabo el tiempo para cambiar la contrasena. Empieza de nuevo.');
    redirigir(URL_BASE . '?accion=recuperar');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'guardar_contrasena')
{
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $confirmacion = (string) ($_POST['contrasena_confirmar'] ?? '');

    //RS-05: validacion del lado del servidor
    $errorContrasena = validarContrasena($contrasena, $confirmacion);

    if ($errorContrasena !== null)
    {
        $errores['contrasena'] = $errorContrasena;
    }
    else
    {
        //RS-01: se guarda el hash, nunca la contrasena en claro
        actualizarContrasena($recuperacion['correo'], $contrasena);
        registrarEnBitacora($recuperacion['correo'], 'contrasena_actualizada');

        //notificacion de exito al usuario
        enviarCorreo('contrasena_cambiada', $recuperacion['correo'], $recuperacion['nombre']);

        unset($_SESSION['recuperacion']);
        $_SESSION['recuperacion_exito'] = true;

        //el proceso termina en la pantalla de confirmacion de recuperar.php
        redirigir(URL_BASE . '?accion=recuperar');
    }
}

abrirPantallaAutenticacion(null);
?>

        <h1 class="titulo-autenticacion">Define tu contrasena nueva</h1>
        <p class="texto-autenticacion">Tienes <?php echo MINUTOS_VENTANA_CAMBIO; ?> minutos para terminar el cambio.</p>

        <?php if (isset($errores['contrasena'])): ?>
            <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['contrasena']); ?></p>
        <?php endif; ?>

        <form action="<?php echo URL_BASE; ?>?accion=nueva-contrasena" method="POST" class="formulario-autenticacion" novalidate>
            <input type="hidden" name="operacion" value="guardar_contrasena">

            <div class="campo-formulario">
                <label for="campoContrasenaNueva">Contrasena nueva</label>
                <input type="password" id="campoContrasenaNueva" name="contrasena" class="icono-candado" placeholder="Minimo 8 caracteres" autofocus required>
            </div>

            <div class="campo-formulario">
                <label for="campoContrasenaNuevaConfirmar">Confirma la contrasena</label>
                <input type="password" id="campoContrasenaNuevaConfirmar" name="contrasena_confirmar" class="icono-candado" placeholder="Repitela" required>
            </div>

            <small class="ayuda-campo">Combina letras y numeros, minimo 8 caracteres.</small>

            <button type="submit" class="boton-autenticacion">Guardar contrasena</button>
        </form>

<?php cerrarPantallaAutenticacion(); ?>

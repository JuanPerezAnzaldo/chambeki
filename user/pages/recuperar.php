<?php
/*
    PROCESO: Cambio / recuperacion de contrasena (paso 1 de 3)
    Requerimientos: RUS-01, RSIS-05, RI-03

    Esta pantalla solo captura el correo y envia el codigo.
    Los otros dos pasos viven en sus propias paginas:
      user/pages/verificarCodigoRecuperacion.php (accion=verificar-codigo-recuperacion)
      user/pages/nuevaContrasena.php             (accion=nueva-contrasena)
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

//si ya se envio un codigo, esta pantalla no tiene nada que hacer
if (isset($_SESSION['recuperacion']) && $_SESSION['recuperacion']['paso'] === 'codigo')
{
    redirigir(URL_BASE . '?accion=verificar-codigo-recuperacion');
}

if (isset($_SESSION['recuperacion']) && $_SESSION['recuperacion']['paso'] === 'nueva')
{
    redirigir(URL_BASE . '?accion=nueva-contrasena');
}

$errores = [];
$cambioCompletado = false;
$correoCapturado = '';

if (isset($_SESSION['recuperacion_exito']))
{
    $cambioCompletado = true;
    unset($_SESSION['recuperacion_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'solicitar_codigo')
{
    $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));

    if (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL))
    {
        $errores['correo'] = 'Escribe un correo valido.';
    }
    else
    {
        $usuario = obtenerUsuarioPorCorreo($correoCapturado);

        if ($usuario === null)
        {
            //el diagrama regresa al paso de capturar el correo
            $errores['correo'] = 'No encontramos una cuenta con ese correo.';
        }
        else
        {
            //RSIS-05 + RI-03: codigo temporal por SMTP con PHPMailer
            $codigo = generarCodigoVerificacion($correoCapturado, 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $correoCapturado, $usuario['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if (!$resultadoCorreo['exito'])
            {
                $errores['correo'] = 'No pudimos enviar el codigo a ese correo. Intenta de nuevo en un momento.';
            }
            else
            {
                $_SESSION['recuperacion'] = [
                    'correo' => $correoCapturado,
                    'nombre' => $usuario['nombre'],
                    'paso'   => 'codigo'
                ];

                registrarEnBitacora($correoCapturado, 'codigo_recuperacion_enviado');

                guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $correoCapturado . '.');

                //aqui esta el reenvio a la pagina dedicada del codigo
                redirigir(URL_BASE . '?accion=verificar-codigo-recuperacion');
            }
        }
    }
}

abrirPantallaAutenticacion(null);
?>

        <?php if ($cambioCompletado): ?>

            <div class="bloque-confirmacion">
                <div class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h1 class="titulo-autenticacion">Contrasena actualizada</h1>
                <p class="texto-autenticacion">Te enviamos un correo confirmando el cambio. Ya puedes entrar con tu contrasena nueva.</p>
                <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-autenticacion">Iniciar sesion</a>
            </div>

        <?php else: ?>

            <h1 class="titulo-autenticacion">Recupera tu acceso</h1>
            <p class="texto-autenticacion">Escribe el correo de tu cuenta y te mandamos un codigo temporal para verificar que eres tu.</p>

            <?php if (isset($errores['correo'])): ?>
                <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['correo']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="solicitar_codigo">

                <div class="campo-formulario">
                    <label for="campoCorreoRecuperacion">Correo electronico</label>
                    <input type="email" id="campoCorreoRecuperacion" name="correo" class="icono-correo" value="<?php echo escaparSalida($correoCapturado); ?>" placeholder="tucorreo@ejemplo.com" required>
                </div>

                <button type="submit" class="boton-autenticacion">Enviar codigo</button>
            </form>

            <p class="pie-autenticacion">
                ¿Te acordaste? <a href="<?php echo URL_BASE; ?>?accion=login">Volver al inicio de sesion</a>
            </p>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>

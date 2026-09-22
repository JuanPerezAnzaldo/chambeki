<?php
/*
    PROCESO: Cambio / recuperacion de contrasena (paso 2 de 3)
    Requerimientos: RUS-01, RSIS-05, RI-03

    Pantalla dedicada para validar el codigo temporal que se envio
    en recuperar.php. Al validarse correctamente, reenvia a la
    pantalla de nueva contrasena (accion=nueva-contrasena).
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

//sin un correo verificado antes, no hay nada que validar aqui
if (!isset($_SESSION['recuperacion']) || $_SESSION['recuperacion']['paso'] !== 'codigo')
{
    redirigir(URL_BASE . '?accion=recuperar');
}

$errores = [];
$recuperacion = $_SESSION['recuperacion'];

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = isset($_POST['operacion']) ? $_POST['operacion'] : '';

    switch ($operacion)
    {
        //-----------------------------------------------------
        // Validar el codigo temporal
        //-----------------------------------------------------
        case 'validar_codigo':
        {
            $codigoCapturado = preg_replace('/[^0-9]/', '', limpiarEntrada($_POST['codigo'] ?? ''));

            $resultadoCodigo = validarCodigoVerificacion($recuperacion['correo'], 'recuperacion', $codigoCapturado);

            if (!$resultadoCodigo['valido'])
            {
                $errores['codigo'] = $resultadoCodigo['mensaje'];
                break;
            }

            $_SESSION['recuperacion']['paso'] = 'nueva';
            $_SESSION['recuperacion']['verificado_en'] = time();

            //aqui esta el reenvio a la pantalla de la contrasena nueva
            redirigir(URL_BASE . '?accion=nueva-contrasena');
        }

        //-----------------------------------------------------
        // Reenviar el codigo
        //-----------------------------------------------------
        case 'reenviar_codigo':
        {
            $codigo = generarCodigoVerificacion($recuperacion['correo'], 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $recuperacion['correo'], $recuperacion['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if ($resultadoCorreo['exito'])
            {
                guardarMensaje('exito', 'Listo, te enviamos un codigo nuevo.');
            }
            else
            {
                guardarMensaje('error', 'No se pudo reenviar el codigo. Intenta en un momento.');
            }

            redirigir(URL_BASE . '?accion=verificar-codigo-recuperacion');
        }

        //-----------------------------------------------------
        // Usar otro correo
        //-----------------------------------------------------
        case 'cancelar_recuperacion':
        {
            unset($_SESSION['recuperacion']);
            redirigir(URL_BASE . '?accion=recuperar');
        }
    }
}

$mensajeFlash = obtenerMensaje();

abrirPantallaAutenticacion(null);
?>

        <h1 class="titulo-autenticacion">Verifica que eres tu</h1>
        <p class="texto-autenticacion">
            Escribe el codigo de 6 digitos que enviamos a
            <strong><?php echo escaparSalida($recuperacion['correo']); ?></strong>.
            Vence en <?php echo MINUTOS_VIGENCIA_CODIGO; ?> minutos.
        </p>

        <?php if ($mensajeFlash !== null): ?>
            <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
        <?php endif; ?>

        <form action="<?php echo URL_BASE; ?>?accion=verificar-codigo-recuperacion" method="POST" class="formulario-autenticacion" novalidate>
            <input type="hidden" name="operacion" value="validar_codigo">

            <div class="campo-formulario">
                <label for="campoCodigoRecuperacion">Codigo temporal</label>
                <input type="text" id="campoCodigoRecuperacion" name="codigo" class="campo-codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus required>
                <?php if (isset($errores['codigo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['codigo']); ?></span><?php endif; ?>
            </div>

            <button type="submit" class="boton-autenticacion">Continuar</button>
        </form>

        <div class="acciones-secundarias">
            <form action="<?php echo URL_BASE; ?>?accion=verificar-codigo-recuperacion" method="POST">
                <input type="hidden" name="operacion" value="reenviar_codigo">
                <button type="submit" class="boton-enlace">Reenviar codigo</button>
            </form>

            <form action="<?php echo URL_BASE; ?>?accion=verificar-codigo-recuperacion" method="POST">
                <input type="hidden" name="operacion" value="cancelar_recuperacion">
                <button type="submit" class="boton-enlace">Usar otro correo</button>
            </form>
        </div>

<?php cerrarPantallaAutenticacion(); ?>

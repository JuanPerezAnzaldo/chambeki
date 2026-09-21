<?php
/*
    PROCESO: Registro de usuario (paso 2 de 2)
    Requerimientos: RF-04, RSIS-05, RS-01, RI-03

    Pantalla dedicada para validar el codigo de 6 digitos que se
    envio en registro.php. Solo se puede entrar aqui si ya existe
    un registro pendiente en sesion (accion=registro lo crea).
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

//sin datos capturados no hay nada que verificar
if (!isset($_SESSION['registro_pendiente']))
{
    redirigir(URL_BASE . '?accion=registro');
}

$errores = [];
$pendiente = $_SESSION['registro_pendiente'];

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = isset($_POST['operacion']) ? $_POST['operacion'] : '';

    switch ($operacion)
    {
        //-----------------------------------------------------
        // Validar el codigo y dar de alta al usuario
        //-----------------------------------------------------
        case 'validar_codigo':
        {
            $codigoCapturado = preg_replace('/[^0-9]/', '', limpiarEntrada($_POST['codigo'] ?? ''));

            $resultadoCodigo = validarCodigoVerificacion($pendiente['correo'], 'registro', $codigoCapturado);

            if (!$resultadoCodigo['valido'])
            {
                $errores['codigo'] = $resultadoCodigo['mensaje'];
                break;
            }

            //segunda revision de duplicidad por si alguien registro el correo mientras tanto
            if (correoYaRegistrado($pendiente['correo']))
            {
                eliminarFotoPerfil($pendiente['foto_perfil']);
                unset($_SESSION['registro_pendiente']);

                guardarMensaje('error', 'Ese correo acaba de ser registrado. Intenta iniciar sesion.');
                redirigir(URL_BASE . '?accion=registro');
            }

            //RF-04: se guarda el registro con la fecha actual y la contrasena cifrada
            crearUsuario($pendiente);
            registrarEnBitacora($pendiente['correo'], 'registro_completado');

            //correo de bienvenida
            enviarCorreo('registro', $pendiente['correo'], $pendiente['nombre']);

            unset($_SESSION['registro_pendiente']);
            $_SESSION['registro_exito'] = $pendiente['nombre'];

            //el proceso termina en la pantalla de confirmacion de registro.php
            redirigir(URL_BASE . '?accion=registro');
        }

        //-----------------------------------------------------
        // Reenviar el codigo
        //-----------------------------------------------------
        case 'reenviar_codigo':
        {
            $codigo = generarCodigoVerificacion($pendiente['correo'], 'registro');

            $resultadoCorreo = enviarCorreo('codigo', $pendiente['correo'], $pendiente['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'registro',
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

            redirigir(URL_BASE . '?accion=verificar-codigo');
        }

        //-----------------------------------------------------
        // Cancelar y volver a capturar los datos
        //-----------------------------------------------------
        case 'cancelar_registro':
        {
            eliminarFotoPerfil($pendiente['foto_perfil']);
            unset($_SESSION['registro_pendiente']);

            redirigir(URL_BASE . '?accion=registro');
        }
    }
}

$mensajeFlash = obtenerMensaje();

abrirPantallaAutenticacion(null);
?>

        <h1 class="titulo-autenticacion">Verifica tu correo</h1>
        <p class="texto-autenticacion">
            Escribe el codigo de 6 digitos que enviamos a
            <strong><?php echo escaparSalida($pendiente['correo']); ?></strong>.
            Vence en <?php echo MINUTOS_VIGENCIA_CODIGO; ?> minutos.
        </p>

        <?php if ($mensajeFlash !== null): ?>
            <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
        <?php endif; ?>

        <form action="<?php echo URL_BASE; ?>?accion=verificar-codigo" method="POST" class="formulario-autenticacion" novalidate>
            <input type="hidden" name="operacion" value="validar_codigo">

            <div class="campo-formulario">
                <label for="campoCodigo">Codigo de verificacion</label>
                <input type="text" id="campoCodigo" name="codigo" class="campo-codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus required>
                <?php if (isset($errores['codigo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['codigo']); ?></span><?php endif; ?>
            </div>

            <button type="submit" class="boton-autenticacion">Crear mi cuenta</button>
        </form>

        <div class="acciones-secundarias">
            <form action="<?php echo URL_BASE; ?>?accion=verificar-codigo" method="POST">
                <input type="hidden" name="operacion" value="reenviar_codigo">
                <button type="submit" class="boton-enlace">Reenviar codigo</button>
            </form>

            <form action="<?php echo URL_BASE; ?>?accion=verificar-codigo" method="POST">
                <input type="hidden" name="operacion" value="cancelar_registro">
                <button type="submit" class="boton-enlace">Cambiar mis datos</button>
            </form>
        </div>

<?php cerrarPantallaAutenticacion(); ?>

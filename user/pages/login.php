<?php
/*
    PROCESO: Inicio de sesion
    Requerimientos: RF-01, RF-04, RNF-02, RSIS-01, RS-01, RSIS-05, RI-03

    El proceso tiene dos pasos dentro de la misma pantalla, igual que
    el registro (registro.php):
      paso 'credenciales' -> valida correo/contrasena contra la BD y
                              envia el codigo de verificacion
      paso 'codigo'       -> valida el codigo y recien ahi abre la
                              sesion de PHP (RSIS-01)
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

$errores = [];
$correoCapturado = '';

//si ya se valido correo/contrasena, se conserva el correo y se
//muestra directo el paso del codigo (igual que registro_pendiente)
if (isset($_SESSION['login_pendiente']))
{
    $correoCapturado = $_SESSION['login_pendiente']['correo'];
}

$paso = isset($_SESSION['login_pendiente']) ? 'codigo' : 'credenciales';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = isset($_POST['operacion']) ? $_POST['operacion'] : '';

    switch ($operacion)
    {
        //-----------------------------------------------------
        // PASO 1: validar correo/contrasena y enviar el codigo
        //-----------------------------------------------------
        case 'validar_credenciales':
        {
            $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));
            $contrasena      = (string) ($_POST['contrasena'] ?? '');
            $recordarme      = isset($_POST['recordarme']);

            $paso = 'credenciales';

            if (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL) || $contrasena === '')
            {
                $errores['general'] = 'Escribe tu correo y tu contrasena.';
                break;
            }

            $usuario = obtenerUsuarioPorCorreo($correoCapturado);

            //mismo mensaje para correo inexistente y contrasena incorrecta
            if ($usuario === null || !password_verify($contrasena, $usuario['password_hash']))
            {
                $errores['general'] = 'El correo o la contrasena no son correctos.';
                registrarEnBitacora($correoCapturado, 'intento_fallido');
                break;
            }

            if ((int) $usuario['estatus'] !== ESTATUS_ACTIVO)
            {
                $errores['general'] = 'Esta cuenta esta suspendida. Escribenos a soporte.';
                break;
            }

            //credenciales correctas: se guarda en sesion mientras se
            //confirma el codigo, todavia NO se abre la sesion (RSIS-01)
            $_SESSION['login_pendiente'] = [
                'id_usuario' => $usuario['id_usuario'],
                'correo'     => $usuario['correo'],
                'nombre'     => $usuario['nombre'],
                'recordarme' => $recordarme
            ];

            //se libera el candado de sesion para no congelar el navegador
            //mientras se espera al SMTP (igual que en registro.php)
            session_write_close();

            $codigo = generarCodigoVerificacion($usuario['correo'], 'login');

            $resultadoCorreo = enviarCorreo('codigo', $usuario['correo'], $usuario['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'login',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            session_start();

            if ($resultadoCorreo['exito'])
            {
                guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $usuario['correo'] . '.');
            }
            else
            {
                guardarMensaje('error', 'El codigo de verificacion fue generado. Si tarda en llegar a tu bandeja de entrada, revisa tu carpeta de Spam o usa "Reenviar codigo".');
            }

            redirigir(URL_BASE . '?accion=login');
        }

        //-----------------------------------------------------
        // PASO 2: validar el codigo y recien ahi abrir la sesion
        //-----------------------------------------------------
        case 'validar_codigo':
        {
            if (!isset($_SESSION['login_pendiente']))
            {
                redirigir(URL_BASE . '?accion=login');
            }

            $pendiente = $_SESSION['login_pendiente'];
            $correoCapturado = $pendiente['correo'];
            $codigoCapturado = preg_replace('/[^0-9]/', '', limpiarEntrada($_POST['codigo'] ?? ''));

            $resultadoCodigo = validarCodigoVerificacion($pendiente['correo'], 'login', $codigoCapturado);

            if (!$resultadoCodigo['valido'])
            {
                $errores['codigo'] = $resultadoCodigo['mensaje'];
                $paso = 'codigo';
                break;
            }

            //se vuelve a consultar por si la cuenta cambio de estatus
            //mientras el usuario esperaba/escribia el codigo
            $usuario = obtenerUsuarioPorCorreo($pendiente['correo']);

            if ($usuario === null || (int) $usuario['estatus'] !== ESTATUS_ACTIVO)
            {
                unset($_SESSION['login_pendiente']);
                guardarMensaje('error', 'Ya no se puede iniciar sesion con esta cuenta.');
                redirigir(URL_BASE . '?accion=login');
            }

            //RSIS-01: la sesion de PHP mantiene la autenticacion
            iniciarSesionUsuario($usuario);
            registrarEnBitacora($usuario['correo'], 'inicio_sesion');

            //recordarme: mantiene la cookie de sesion 30 dias
            if (!empty($pendiente['recordarme']))
            {
                setcookie(session_name(), session_id(), [
                    'expires'  => time() + (30 * 24 * 60 * 60),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure'   => isset($_SERVER['HTTPS'])
                ]);
            }

            unset($_SESSION['login_pendiente']);

            redirigir(URL_BASE);
        }

        //-----------------------------------------------------
        // Reenviar el codigo
        //-----------------------------------------------------
        case 'reenviar_codigo':
        {
            if (!isset($_SESSION['login_pendiente']))
            {
                redirigir(URL_BASE . '?accion=login');
            }

            $pendiente = $_SESSION['login_pendiente'];

            session_write_close();
            $codigo = generarCodigoVerificacion($pendiente['correo'], 'login');

            $resultadoCorreo = enviarCorreo('codigo', $pendiente['correo'], $pendiente['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'login',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            session_start();

            if ($resultadoCorreo['exito'])
            {
                guardarMensaje('exito', 'Listo, te enviamos un codigo nuevo.');
            }
            else
            {
                guardarMensaje('error', 'No se pudo reenviar el codigo. Intenta en un momento.');
            }

            redirigir(URL_BASE . '?accion=login');
        }

        //-----------------------------------------------------
        // Cancelar y volver a capturar correo/contrasena
        //-----------------------------------------------------
        case 'cancelar_login':
        {
            unset($_SESSION['login_pendiente']);
            redirigir(URL_BASE . '?accion=login');
        }
    }
}

$mensajeFlash = obtenerMensaje();

//pestanas solo en el formulario de credenciales; en el paso del
//codigo estorban (igual criterio que registro.php)
$pestanaActiva = ($paso === 'credenciales') ? 'login' : null;

abrirPantallaAutenticacion($pestanaActiva);
?>

        <?php if ($paso === 'codigo'): ?>

            <h1 class="titulo-autenticacion">Verifica que eres tu</h1>
            <p class="texto-autenticacion">
                Escribe el codigo de 6 digitos que enviamos a
                <strong><?php echo escaparSalida($correoCapturado); ?></strong>.
                Vence en <?php echo MINUTOS_VIGENCIA_CODIGO; ?> minutos.
            </p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=login" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="validar_codigo">

                <div class="campo-formulario">
                    <label for="campoCodigoSesion">Codigo de verificacion</label>
                    <input type="text" id="campoCodigoSesion" name="codigo" class="campo-codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" required autofocus>
                    <?php if (isset($errores['codigo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['codigo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Iniciar sesion</button>
            </form>

            <div class="acciones-secundarias">
                <form action="<?php echo URL_BASE; ?>?accion=login" method="POST">
                    <input type="hidden" name="operacion" value="reenviar_codigo">
                    <button type="submit" class="boton-enlace">Reenviar codigo</button>
                </form>

                <form action="<?php echo URL_BASE; ?>?accion=login" method="POST">
                    <input type="hidden" name="operacion" value="cancelar_login">
                    <button type="submit" class="boton-enlace">Usar otra cuenta</button>
                </form>
            </div>

        <?php else: ?>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <?php if (isset($errores['general'])): ?>
                <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['general']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=login" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="validar_credenciales">

                <div class="campo-formulario">
                    <label for="campoCorreoSesion">Correo electronico</label>
                    <input type="email" id="campoCorreoSesion" name="correo" class="icono-correo" value="<?php echo escaparSalida($correoCapturado); ?>" placeholder="usuario@chambeki.com" required>
                </div>

                <div class="campo-formulario">
                    <label for="campoContrasenaSesion">Contrasena</label>
                    <input type="password" id="campoContrasenaSesion" name="contrasena" class="icono-candado" placeholder="Tu contrasena" required>
                </div>

                <div class="fila-opciones-sesion">
                    <label class="opcion-recordarme">
                        <input type="checkbox" name="recordarme" value="1">
                        Recordarme
                    </label>

                    <a href="<?php echo URL_BASE; ?>?accion=recuperar" class="enlace-acento">¿Olvidaste tu contrasena?</a>
                </div>

                <button type="submit" class="boton-autenticacion">Iniciar sesion</button>
            </form>

            <p class="pie-autenticacion">
                ¿No tienes cuenta? <a href="<?php echo URL_BASE; ?>?accion=registro">Registrate</a>
            </p>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>
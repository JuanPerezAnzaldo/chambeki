<?php
/*
    PROCESO: Inicio de sesion
    Requerimientos: RF-01, RF-04, RNF-02, RSIS-01, RS-01

    Version base para que las pestanas de la maqueta funcionen.
    Falta el redireccionamiento por rol a los paneles de Freelancer
    y Administrador, que todavia no existen.
*/

require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

$errores = [];
$correoCapturado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'iniciar_sesion')
{
    $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');

    if (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL) || $contrasena === '')
    {
        $errores['general'] = 'Escribe tu correo y tu contrasena.';
    }
    else
    {
        $usuario = obtenerUsuarioPorCorreo($correoCapturado);

        //mismo mensaje para correo inexistente y contrasena incorrecta
        if ($usuario === null || !password_verify($contrasena, $usuario['password_hash']))
        {
            $errores['general'] = 'El correo o la contrasena no son correctos.';
            registrarEnBitacora($correoCapturado, 'intento_fallido');
        }
        elseif ((int) $usuario['estatus'] !== ESTATUS_ACTIVO)
        {
            $errores['general'] = 'Esta cuenta esta suspendida. Escribenos a soporte.';
        }
        else
        {
            //RSIS-01: la sesion de PHP mantiene la autenticacion
            iniciarSesionUsuario($usuario);
            registrarEnBitacora($correoCapturado, 'inicio_sesion');

            //recordarme: mantiene la cookie de sesion 30 dias
            if (isset($_POST['recordarme']))
            {
                setcookie(session_name(), session_id(), [
                    'expires'  => time() + (30 * 24 * 60 * 60),
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure'   => isset($_SERVER['HTTPS'])
                ]);
            }

            redirigir(URL_BASE);
        }
    }
}

$mensajeFlash = obtenerMensaje();

abrirPantallaAutenticacion('login');
?>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <?php if (isset($errores['general'])): ?>
                <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['general']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=login" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="iniciar_sesion">

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

<?php cerrarPantallaAutenticacion(); ?>

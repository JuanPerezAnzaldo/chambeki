<?php
/*
    Cambio de contrasena del administrador (/admin/cambiar-contrasena).
    Cualquier administrador autenticado puede abrirla. Si su contrasena es
    temporal (alta nueva o restablecida por un Super Administrador), el
    middleware solo le deja entrar aqui hasta que la cambie.

    Requerimientos: RS-01, RS-05
*/

$errores = [];
$mensajeFlash = obtenerMensaje();
$esTemporal = (int) $admin['debe_cambiar_contrasena'] === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $actual    = (string) ($_POST['actual'] ?? '');
    $nueva     = (string) ($_POST['nueva'] ?? '');
    $confirmar = (string) ($_POST['confirmar'] ?? '');

    if (!csrfAdminValido($_POST['csrf'] ?? ''))
    {
        $errores['general'] = 'El formulario expiro. Intenta de nuevo.';
    }
    else
    {
        $conexion = conexionBD();

        $sentencia = $conexion->prepare('SELECT password_hash FROM usuarios WHERE id_usuario = :id LIMIT 1');
        $sentencia->execute([':id' => $admin['id_usuario']]);
        $fila = $sentencia->fetch();

        if ($fila === false || !password_verify($actual, $fila['password_hash']))
        {
            registrarEnBitacora($admin['correo'], 'admin_cambio_pass_fallido');
            $errores['actual'] = 'La contrasena actual no es correcta.';
        }
        elseif (($errorNueva = validarContrasena($nueva, $confirmar)) !== null)
        {
            $errores['nueva'] = $errorNueva;
        }
        elseif ($nueva === $actual)
        {
            $errores['nueva'] = 'La contrasena nueva debe ser distinta a la actual.';
        }
        else
        {
            $actualizar = $conexion->prepare(
                'UPDATE usuarios
                 SET password_hash = :hash, debe_cambiar_contrasena = 0, fecha_actualizacion = NOW()
                 WHERE id_usuario = :id'
            );
            $actualizar->execute([
                ':hash' => password_hash($nueva, PASSWORD_DEFAULT),   //RS-01
                ':id'   => $admin['id_usuario']
            ]);

            registrarEnBitacora($admin['correo'], 'admin_cambio_contrasena');

            //identificador de sesion nuevo despues de un cambio de credenciales
            session_regenerate_id(true);

            guardarMensaje('exito', 'Tu contrasena se actualizo.');
            redirigir(URL_ADMIN . 'dashboard');
        }
    }
}
?>

<div class="admin-formulario-angosto">
    <h1 class="admin-titulo">Cambiar contrasena</h1>
    <p class="admin-subtitulo">
        <?php echo $esTemporal
            ? 'Tu contrasena es temporal. Define una nueva para poder usar el panel.'
            : 'Escribe tu contrasena actual y la nueva.'; ?>
    </p>

    <?php if ($mensajeFlash !== null): ?>
        <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
    <?php endif; ?>

    <?php if (isset($errores['general'])): ?>
        <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['general']); ?></p>
    <?php endif; ?>

    <form action="<?php echo URL_ADMIN; ?>cambiar-contrasena" method="POST" class="formulario-autenticacion" novalidate>
        <input type="hidden" name="csrf" value="<?php echo escaparSalida(csrfAdminToken()); ?>">

        <div class="campo-formulario">
            <label for="passActual"><?php echo $esTemporal ? 'Contrasena temporal' : 'Contrasena actual'; ?></label>
            <input type="password" id="passActual" name="actual" class="icono-candado" autocomplete="current-password" required>
            <?php if (isset($errores['actual'])): ?><span class="error-campo"><?php echo escaparSalida($errores['actual']); ?></span><?php endif; ?>
        </div>

        <div class="campo-formulario">
            <label for="passNueva">Contrasena nueva</label>
            <input type="password" id="passNueva" name="nueva" class="icono-candado" autocomplete="new-password" required>
            <small class="ayuda-campo">Minimo 8 caracteres, combinando letras y numeros.</small>
            <?php if (isset($errores['nueva'])): ?><span class="error-campo"><?php echo escaparSalida($errores['nueva']); ?></span><?php endif; ?>
        </div>

        <div class="campo-formulario">
            <label for="passConfirmar">Confirma la contrasena nueva</label>
            <input type="password" id="passConfirmar" name="confirmar" class="icono-candado" autocomplete="new-password" required>
        </div>

        <button type="submit" class="boton-autenticacion">Guardar contrasena</button>
    </form>
</div>
<script src="<?php echo JS_RUTA; ?>autenticacion.js?v=1"></script>

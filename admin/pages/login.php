<?php
/*
    PROCESO: Inicio de sesion de administradores (https://chambeki.com/admin/login)
    Requerimientos: RF-01, RS-01, RS-02, RS-03, RS-05, RS-06, RSIS-01, RLN-07

    Diferencias con el login del sitio publico:
      - usa la sesion aislada "session_admin" (ver admin/index.php)
      - solo entra quien tenga usuarios.rol = 1, cuenta activa y un rol
        administrativo asignado (id_rol_admin)
      - un solo mensaje de error para cualquier causa, para no revelar si
        la cuenta existe, esta suspendida o simplemente no es de admin
      - bloqueo temporal despues de 5 fallos (por correo o por IP)
      - cada intento queda en bitacora_accesos
*/

$errores = [];
$correoCapturado = '';
$mensajeFlash = obtenerMensaje();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'iniciar_sesion_admin')
{
    $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));
    $contrasena      = (string) ($_POST['contrasena'] ?? '');

    if (!csrfAdminValido($_POST['csrf'] ?? ''))
    {
        $errores['general'] = 'El formulario expiro. Intenta de nuevo.';
    }
    elseif (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL) || $contrasena === '')
    {
        $errores['general'] = 'Escribe tu correo y tu contrasena.';
    }
    elseif (loginAdminBloqueado($correoCapturado))
    {
        registrarEnBitacora($correoCapturado, 'admin_login_bloqueado');
        $errores['general'] = 'Demasiados intentos fallidos. Intenta de nuevo en ' . MINUTOS_BLOQUEO_LOGIN_ADMIN . ' minutos.';
    }
    else
    {
        $usuario = obtenerAdminPorCorreo($correoCapturado);

        //si el correo no existe se hace igual un password_verify (contra un hash
        //falso) para que tarde lo mismo y no se pueda adivinar que cuentas existen
        $hashParaVerificar = $usuario !== null
            ? $usuario['password_hash']
            : password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);

        $contrasenaCorrecta = password_verify($contrasena, $hashParaVerificar);

        if ($usuario === null || !$contrasenaCorrecta)
        {
            registrarEnBitacora($correoCapturado, 'admin_login_fallido');
            $errores['general'] = 'Credenciales incorrectas o sin permiso de acceso.';
        }
        elseif ((int) $usuario['rol'] !== ROL_ADMIN
            || (int) $usuario['estatus'] !== ESTATUS_ACTIVO
            || empty($usuario['id_rol_admin']))
        {
            //contrasena correcta pero la cuenta no puede entrar al panel:
            //se registra el motivo real, pero al usuario se le da el mismo mensaje
            $motivo = (int) $usuario['rol'] !== ROL_ADMIN ? 'sin_rol' : ((int) $usuario['estatus'] !== ESTATUS_ACTIVO ? 'suspendida' : 'sin_perfil');
            registrarEnBitacora($correoCapturado, 'admin_login_fallido:' . $motivo);
            $errores['general'] = 'Credenciales incorrectas o sin permiso de acceso.';
        }
        else
        {
            iniciarSesionAdmin($usuario);
            registrarUltimoAccesoAdmin($usuario['id_usuario']);
            registrarEnBitacora($usuario['correo'], 'admin_login');

            redirigir(URL_ADMIN . 'dashboard');
        }
    }
}
?>

<section class="admin-login-tarjeta">
    <div class="admin-login-marca"><?php echo SITENAME; ?> <span>Admin</span></div>
    <h1 class="titulo-autenticacion">Acceso para administradores</h1>
    <p class="texto-autenticacion">Area restringida. Tu actividad queda registrada.</p>

    <?php if ($mensajeFlash !== null): ?>
        <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
    <?php endif; ?>

    <?php if (isset($errores['general'])): ?>
        <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['general']); ?></p>
    <?php endif; ?>

    <form action="<?php echo URL_ADMIN; ?>login" method="POST" class="formulario-autenticacion" novalidate>
        <input type="hidden" name="operacion" value="iniciar_sesion_admin">
        <input type="hidden" name="csrf" value="<?php echo escaparSalida(csrfAdminToken()); ?>">

        <div class="campo-formulario">
            <label for="adminCorreo">Correo electronico</label>
            <input type="email" id="adminCorreo" name="correo" class="icono-correo" value="<?php echo escaparSalida($correoCapturado); ?>" placeholder="admin@chambeki.com" autocomplete="username" required>
        </div>

        <div class="campo-formulario">
            <label for="adminContrasena">Contrasena</label>
            <input type="password" id="adminContrasena" name="contrasena" class="icono-candado" placeholder="Tu contrasena" autocomplete="current-password" required>
        </div>

        <button type="submit" class="boton-autenticacion">Entrar al panel</button>
    </form>

    <p class="pie-autenticacion"><a href="<?php echo URL_BASE; ?>">Volver al sitio</a></p>
</section>

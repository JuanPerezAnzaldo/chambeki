<?php
/*
    EDITAR DATOS BASICOS DE UNA CUENTA (https://chambeki.com/admin/cuenta-editar?id=N)
    Permiso: usuarios.editar.

    Pensado para cuando el usuario lo solicita (por ejemplo, corregir su nombre
    o telefono). Solo nombre y telefono: el correo y la contrasena NO se cambian
    desde aqui porque equivaldria a tomar control de la cuenta; eso se hace con
    la recuperacion de contrasena del propio usuario. Se exige un motivo y queda
    el cambio (valor anterior -> nuevo) en el historial de la cuenta.
*/

require_once DOCROOT . 'admin/includes/funcionesModeracion.php';

$idCuenta = (int) ($_GET['id'] ?? 0);
$cuenta = obtenerCuenta($idCuenta);

if ($cuenta === null)
{
    guardarMensaje('error', 'No se encontro esa cuenta.');
    redirigir(URL_ADMIN . 'cuentas');
}

$errores = [];
$valores = [
    'nombre'   => $cuenta['nombre'],
    'telefono' => $cuenta['telefono'] ?? '',
    'motivo'   => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    if (!csrfAdminValido($_POST['csrf'] ?? ''))
    {
        $errores['general'] = 'El formulario expiro. Intenta de nuevo.';
    }
    else
    {
        $valores = [
            'nombre'   => limpiarEntrada($_POST['nombre'] ?? ''),
            'telefono' => limpiarEntrada($_POST['telefono'] ?? ''),
            'motivo'   => mb_substr(limpiarEntrada($_POST['motivo'] ?? ''), 0, 255)
        ];

        $errores = validarDatosBasicosCuenta($valores);

        $errorMotivo = validarMotivoModeracion($valores['motivo']);

        if ($errorMotivo !== null)
        {
            $errores['motivo'] = $errorMotivo;
        }

        $telefonoNuevo   = $valores['telefono'] !== '' ? preg_replace('/[^0-9]/', '', $valores['telefono']) : '';
        $telefonoAnterior = (string) ($cuenta['telefono'] ?? '');
        $huboCambios = $valores['nombre'] !== $cuenta['nombre'] || $telefonoNuevo !== $telefonoAnterior;

        if (empty($errores) && !$huboCambios)
        {
            $errores['general'] = 'No cambiaste ningun dato.';
        }

        if (empty($errores))
        {
            try
            {
                //se arma el detalle con lo que cambio (valor anterior -> nuevo)
                $cambios = [];

                if ($valores['nombre'] !== $cuenta['nombre'])
                {
                    $cambios[] = 'nombre: "' . $cuenta['nombre'] . '" -> "' . $valores['nombre'] . '"';
                }

                if ($telefonoNuevo !== $telefonoAnterior)
                {
                    $cambios[] = 'telefono: ' . ($telefonoAnterior !== '' ? $telefonoAnterior : '(vacio)') . ' -> ' . ($telefonoNuevo !== '' ? $telefonoNuevo : '(vacio)');
                }

                actualizarDatosBasicosCuenta($idCuenta, $valores['nombre'], $valores['telefono']);
                registrarAccionModeracion($admin, $idCuenta, 'editar_datos', implode('; ', $cambios), $valores['motivo']);

                guardarMensaje('exito', 'Los datos de la cuenta se actualizaron.');
                redirigir(URL_ADMIN . 'cuenta?id=' . $idCuenta);
            }
            catch (PDOException $error)
            {
                error_log('Edicion de cuenta: ' . $error->getMessage());
                $errores['general'] = 'No se pudo guardar. Intenta de nuevo.';
            }
        }
    }
}

$token = csrfAdminToken();
?>

<p class="migaja-admin"><a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo $idCuenta; ?>">&larr; Perfil de <?php echo escaparSalida($cuenta['nombre']); ?></a></p>

<h1 class="admin-titulo">Editar datos basicos</h1>
<p class="admin-subtitulo">
    Cuenta #<?php echo $idCuenta; ?> &middot; <?php echo escaparSalida($cuenta['correo']); ?>.
    Usa esta pantalla solo cuando el usuario lo solicite. El correo y la contrasena no se pueden cambiar desde aqui.
</p>

<?php if (isset($errores['general'])): ?>
    <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['general']); ?></p>
<?php endif; ?>

<form action="<?php echo URL_ADMIN; ?>cuenta-editar?id=<?php echo $idCuenta; ?>" method="POST" class="formulario-autenticacion admin-formulario" novalidate>
    <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">

    <div class="campo-formulario">
        <label for="cuentaNombre">Nombre completo</label>
        <input type="text" id="cuentaNombre" name="nombre" class="icono-usuario" maxlength="100" value="<?php echo escaparSalida($valores['nombre']); ?>" required>
        <?php if (isset($errores['nombre'])): ?><span class="error-campo"><?php echo escaparSalida($errores['nombre']); ?></span><?php endif; ?>
    </div>

    <div class="campo-formulario">
        <label for="cuentaTelefono">Telefono</label>
        <input type="tel" id="cuentaTelefono" name="telefono" class="icono-telefono" maxlength="20" value="<?php echo escaparSalida($valores['telefono']); ?>" placeholder="664 123 4567">
        <?php if (isset($errores['telefono'])): ?><span class="error-campo"><?php echo escaparSalida($errores['telefono']); ?></span><?php endif; ?>
    </div>

    <div class="campo-formulario">
        <label for="cuentaMotivo">Motivo del cambio</label>
        <input type="text" id="cuentaMotivo" name="motivo" maxlength="255" value="<?php echo escaparSalida($valores['motivo']); ?>" placeholder="Ej. El usuario pidio corregir su nombre por correo" required>
        <small class="ayuda-campo">Queda en el historial de la cuenta.</small>
        <?php if (isset($errores['motivo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['motivo']); ?></span><?php endif; ?>
    </div>

    <button type="submit" class="boton-autenticacion">Guardar cambios</button>
</form>

<?php
/*
    Alta y edicion de empleados.
      /admin/equipo-nuevo            -> modo 'nuevo'
      /admin/equipo-editar?id=N      -> modo 'editar'
    Permiso: admins.gestionar (Super Administrador).

    Nota: el modo viene de la tabla rutasAdmin() ($configRuta['modo']).
*/

require_once DOCROOT . 'admin/includes/funcionesPersonal.php';

exigirSuperAdmin($admin);

$modo = $configRuta['modo'];
$esNuevo = $modo === 'nuevo';

$roles = listarRolesAdmin();
$catalogoPermisos = todosLosPermisosAdmin();
$reservados = permisosReservadosAdmin();

$errores = [];
$empleado = null;
$editandoseASiMismo = false;

if (!$esNuevo)
{
    $empleado = obtenerEmpleado((int) ($_GET['id'] ?? 0));

    if ($empleado === null)
    {
        guardarMensaje('error', 'No se encontro al empleado.');
        redirigir(URL_ADMIN . 'equipo');
    }

    $editandoseASiMismo = (int) $empleado['id_usuario'] === (int) $admin['id_usuario'];
}

//valores iniciales del formulario
if ($esNuevo)
{
    $valores = ['nombre' => '', 'correo' => '', 'telefono' => '', 'id_rol_admin' => ROL_ADMIN_SOPORTE];
    $permisosMarcados = matrizPermisosAdmin()[ROL_ADMIN_SOPORTE];
}
else
{
    $valores = [
        'nombre'       => $empleado['nombre'],
        'correo'       => $empleado['correo'],
        'telefono'     => $empleado['telefono'] ?? '',
        'id_rol_admin' => (int) $empleado['id_rol_admin']
    ];
    $permisosMarcados = permisosEfectivosAdmin($empleado['id_usuario'], $empleado['id_rol_admin']);
}

//-------- guardar --------
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    if (!csrfAdminValido($_POST['csrf'] ?? ''))
    {
        $errores['general'] = 'El formulario expiro. Intenta de nuevo.';
    }
    else
    {
        $datos = [
            'nombre'       => limpiarEntrada($_POST['nombre'] ?? ''),
            'correo'       => strtolower(limpiarEntrada($_POST['correo'] ?? '')),
            'telefono'     => limpiarEntrada($_POST['telefono'] ?? ''),
            //si se edita a si mismo el rol no se toma del formulario
            'id_rol_admin' => $editandoseASiMismo ? (int) $empleado['id_rol_admin'] : (int) ($_POST['id_rol_admin'] ?? 0)
        ];

        $permisosEnviados = $_POST['permisos'] ?? [];
        $valores = $datos;
        $permisosMarcados = is_array($permisosEnviados) ? $permisosEnviados : [];

        $errores = validarDatosEmpleado($datos, $esNuevo ? 0 : (int) $empleado['id_usuario']);

        if (!$esNuevo)
        {
            $problema = validarProteccionEmpleado($empleado, $admin['id_usuario'], ESTATUS_ACTIVO, $datos['id_rol_admin']);

            if ($problema !== null)
            {
                $errores['id_rol_admin'] = $problema;
            }
        }

        if (empty($errores))
        {
            try
            {
                if ($esNuevo)
                {
                    $resultado = crearEmpleado($datos, $permisosMarcados, $admin['id_usuario']);

                    registrarEnBitacora($admin['correo'], 'admin_alta_emp:' . $resultado['id']);

                    $_SESSION['credencial_temporal'] = [
                        'nombre' => $datos['nombre'], 'correo' => $datos['correo'], 'contrasena' => $resultado['contrasena']
                    ];
                    guardarMensaje('exito', 'Empleado dado de alta. Entrega la contrasena temporal que aparece abajo.');
                }
                else
                {
                    actualizarEmpleado($empleado['id_usuario'], $datos, $permisosMarcados, $admin['id_usuario'], !$editandoseASiMismo);

                    registrarEnBitacora($admin['correo'], 'admin_editar_emp:' . $empleado['id_usuario']);
                    guardarMensaje('exito', 'Los datos de ' . $datos['nombre'] . ' se actualizaron.');
                }

                redirigir(URL_ADMIN . 'equipo');
            }
            catch (PDOException $error)
            {
                //el detalle va al log del servidor; al usuario solo un aviso general
                error_log('Gestion de personal: ' . $error->getMessage());
                $errores['general'] = 'No se pudo guardar. Intenta de nuevo.';
            }
        }
    }
}

//permisos base de cada rol para que el JS recalcule las casillas al cambiar el rol
$permisosPorRol = matrizPermisosAdmin();
$token = csrfAdminToken();
$scriptsExtra = [URL_ADMIN . 'assets/js/equipo.js?v=1'];
?>

<p class="migaja-admin"><a href="<?php echo URL_ADMIN; ?>equipo">&larr; Gestion de personal</a></p>

<h1 class="admin-titulo"><?php echo $esNuevo ? 'Nuevo empleado' : 'Editar empleado'; ?></h1>
<p class="admin-subtitulo">
    <?php if ($esNuevo): ?>
        El sistema genera una contrasena temporal, la guarda cifrada y te la muestra una sola vez. El empleado la cambiara en su primer acceso.
    <?php elseif ($editandoseASiMismo): ?>
        Estas editando tu propia cuenta: puedes cambiar tus datos, pero no tu rol ni tus permisos.
    <?php else: ?>
        Ultimo acceso:
        <?php echo $empleado['ultimo_acceso_admin'] !== null
            ? escaparSalida(date('d/m/Y H:i', strtotime($empleado['ultimo_acceso_admin'])) . ' desde la IP ' . $empleado['ip_ultimo_acceso_admin'])
            : 'nunca ha entrado'; ?>.
    <?php endif; ?>
</p>

<?php if (isset($errores['general'])): ?>
    <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['general']); ?></p>
<?php endif; ?>

<form method="POST" class="formulario-autenticacion admin-formulario" novalidate
      action="<?php echo URL_ADMIN . ($esNuevo ? 'equipo-nuevo' : 'equipo-editar?id=' . (int) $empleado['id_usuario']); ?>"
      data-permisos-por-rol="<?php echo escaparSalida(json_encode($permisosPorRol)); ?>">
    <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">

    <div class="fila-campos">
        <div class="campo-formulario">
            <label for="empNombre">Nombre completo</label>
            <input type="text" id="empNombre" name="nombre" class="icono-usuario" maxlength="100" value="<?php echo escaparSalida($valores['nombre']); ?>" required>
            <?php if (isset($errores['nombre'])): ?><span class="error-campo"><?php echo escaparSalida($errores['nombre']); ?></span><?php endif; ?>
        </div>

        <div class="campo-formulario">
            <label for="empTelefono">Telefono (opcional)</label>
            <input type="tel" id="empTelefono" name="telefono" class="icono-telefono" maxlength="20" value="<?php echo escaparSalida($valores['telefono']); ?>" placeholder="664 123 4567">
            <?php if (isset($errores['telefono'])): ?><span class="error-campo"><?php echo escaparSalida($errores['telefono']); ?></span><?php endif; ?>
        </div>
    </div>

    <div class="campo-formulario">
        <label for="empCorreo">Correo de acceso</label>
        <input type="email" id="empCorreo" name="correo" class="icono-correo" maxlength="150" value="<?php echo escaparSalida($valores['correo']); ?>" required>
        <small class="ayuda-campo">Debe ser un correo que no tenga cuenta en CHAMBEKI.</small>
        <?php if (isset($errores['correo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['correo']); ?></span><?php endif; ?>
    </div>

    <div class="campo-formulario">
        <label for="empRol">Rol</label>
        <select id="empRol" name="id_rol_admin" <?php echo $editandoseASiMismo ? 'disabled' : ''; ?>>
            <?php foreach ($roles as $rol): ?>
                <option value="<?php echo (int) $rol['id_rol_admin']; ?>" <?php echo (int) $valores['id_rol_admin'] === (int) $rol['id_rol_admin'] ? 'selected' : ''; ?>>
                    <?php echo escaparSalida($rol['nombre']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php foreach ($roles as $rol): ?>
            <small class="ayuda-campo"><strong><?php echo escaparSalida($rol['nombre']); ?>:</strong> <?php echo escaparSalida($rol['descripcion']); ?></small>
        <?php endforeach; ?>
        <?php if (isset($errores['id_rol_admin'])): ?><span class="error-campo"><?php echo escaparSalida($errores['id_rol_admin']); ?></span><?php endif; ?>
    </div>

    <fieldset class="grupo-formas-pago admin-permisos" <?php echo $editandoseASiMismo ? 'disabled' : ''; ?>>
        <legend>Permisos especificos</legend>
        <p class="ayuda-campo">Se marcan los que da el rol. Marca uno extra o quita uno para ajustar a este empleado; al cambiar el rol se reinician.</p>

        <?php foreach ($catalogoPermisos as $permiso): ?>
            <?php
                $esReservado = in_array($permiso, $reservados, true);
                //los reservados siempre siguen al rol (no dependen de lo enviado en el formulario)
                $marcado = $esReservado
                    ? in_array($permiso, matrizPermisosAdmin()[(int) $valores['id_rol_admin']] ?? [], true)
                    : in_array($permiso, $permisosMarcados, true);
            ?>
            <label class="opcion-casilla">
                <input type="checkbox" name="permisos[]" value="<?php echo escaparSalida($permiso); ?>"
                    <?php echo $marcado ? 'checked' : ''; ?>
                    <?php echo $esReservado ? 'disabled data-reservado="1"' : ''; ?>>
                <?php echo escaparSalida(etiquetaPermiso($permiso)); ?>
                <?php if ($esReservado): ?><small>(reservado al Super Administrador)</small><?php endif; ?>
            </label>
        <?php endforeach; ?>
    </fieldset>

    <button type="submit" class="boton-autenticacion"><?php echo $esNuevo ? 'Dar de alta' : 'Guardar cambios'; ?></button>
</form>

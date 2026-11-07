<?php
/*
    GESTION DE PERSONAL INTERNO - listado (https://chambeki.com/admin/equipo)
    Permiso: admins.gestionar (reservado al Super Administrador).

    Acciones por POST con token CSRF: suspender, reactivar y restablecer
    contrasena. El alta y la edicion estan en equipo-formulario.php.
*/

require_once DOCROOT . 'admin/includes/funcionesPersonal.php';

exigirSuperAdmin($admin);

//-------- acciones sobre un empleado --------
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion  = $_POST['operacion'] ?? '';
    $idEmpleado = (int) ($_POST['id_usuario'] ?? 0);

    if (!csrfAdminValido($_POST['csrf'] ?? ''))
    {
        guardarMensaje('error', 'El formulario expiro. Intenta de nuevo.');
        redirigir(URL_ADMIN . 'equipo');
    }

    $empleado = obtenerEmpleado($idEmpleado);

    if ($empleado === null)
    {
        guardarMensaje('error', 'No se encontro al empleado.');
        redirigir(URL_ADMIN . 'equipo');
    }

    switch ($operacion)
    {
        case 'suspender':
        {
            $problema = validarProteccionEmpleado($empleado, $admin['id_usuario'], ESTATUS_SUSPENDIDO, $empleado['id_rol_admin']);

            if ($problema !== null)
            {
                guardarMensaje('error', $problema);
                break;
            }

            cambiarEstatusEmpleado($idEmpleado, ESTATUS_SUSPENDIDO);
            registrarEnBitacora($admin['correo'], 'admin_suspender_emp:' . $idEmpleado);

            //el empleado pierde el acceso en su siguiente clic (adminAutenticado relee el estatus)
            guardarMensaje('exito', $empleado['nombre'] . ' fue suspendido. Ya no puede entrar al panel.');
            break;
        }

        case 'reactivar':
        {
            cambiarEstatusEmpleado($idEmpleado, ESTATUS_ACTIVO);
            registrarEnBitacora($admin['correo'], 'admin_reactivar_emp:' . $idEmpleado);

            guardarMensaje('exito', $empleado['nombre'] . ' fue reactivado.');
            break;
        }

        case 'restablecer':
        {
            if ((int) $empleado['id_usuario'] === (int) $admin['id_usuario'])
            {
                guardarMensaje('error', 'Tu propia contrasena se cambia desde "Mi contrasena".');
                break;
            }

            $nueva = restablecerContrasenaEmpleado($idEmpleado);
            registrarEnBitacora($admin['correo'], 'admin_reset_pass_emp:' . $idEmpleado);

            //se muestra una sola vez en la siguiente carga y se borra
            $_SESSION['credencial_temporal'] = ['nombre' => $empleado['nombre'], 'correo' => $empleado['correo'], 'contrasena' => $nueva];
            guardarMensaje('exito', 'Se genero una contrasena temporal para ' . $empleado['nombre'] . '.');
            break;
        }
    }

    redirigir(URL_ADMIN . 'equipo');
}

//-------- listado --------
$mensajeFlash = obtenerMensaje();

$credencial = $_SESSION['credencial_temporal'] ?? null;
unset($_SESSION['credencial_temporal']);   //solo se ve una vez

$empleados = listarEmpleados();
$token = csrfAdminToken();
?>

<div class="admin-encabezado-pagina">
    <div>
        <h1 class="admin-titulo">Gestion de personal</h1>
        <p class="admin-subtitulo">Los empleados no se registran solos: solo un Super Administrador puede darlos de alta, asignarles rol y permisos o suspenderlos.</p>
    </div>
    <a href="<?php echo URL_ADMIN; ?>equipo-nuevo" class="boton-autenticacion admin-boton-accion">+ Nuevo empleado</a>
</div>

<?php if ($mensajeFlash !== null): ?>
    <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
<?php endif; ?>

<?php if ($credencial !== null): ?>
    <div class="admin-credencial">
        <strong>Credenciales temporales de <?php echo escaparSalida($credencial['nombre']); ?></strong>
        <p>Se muestran <u>una sola vez</u>. Entregalas de forma segura; en su primer acceso tendra que cambiarlas.</p>
        <dl>
            <dt>Correo</dt><dd><?php echo escaparSalida($credencial['correo']); ?></dd>
            <dt>Contrasena</dt><dd><code><?php echo escaparSalida($credencial['contrasena']); ?></code></dd>
        </dl>
    </div>
<?php endif; ?>

<div class="admin-tabla-contenedor">
    <table class="admin-tabla">
        <thead>
            <tr>
                <th>Empleado</th>
                <th>Rol</th>
                <th>Permisos</th>
                <th>Estatus</th>
                <th>Ultimo acceso</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($empleados)): ?>
                <tr><td colspan="6">Todavia no hay empleados registrados.</td></tr>
            <?php endif; ?>

            <?php foreach ($empleados as $empleado): ?>
                <?php
                    $esElMismo   = (int) $empleado['id_usuario'] === (int) $admin['id_usuario'];
                    $estaActivo  = (int) $empleado['estatus'] === ESTATUS_ACTIVO;
                ?>
                <tr class="<?php echo $estaActivo ? '' : 'admin-fila-suspendida'; ?>">
                    <td>
                        <strong><?php echo escaparSalida($empleado['nombre']); ?></strong><?php echo $esElMismo ? ' <span class="admin-tu">(tu)</span>' : ''; ?><br>
                        <small><?php echo escaparSalida($empleado['correo']); ?></small>
                    </td>
                    <td>
                        <span class="admin-insignia-rol admin-rol-<?php echo (int) $empleado['id_rol_admin']; ?>">
                            <?php echo escaparSalida(nombreRolAdmin($empleado['id_rol_admin'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ((int) $empleado['ajustes_permisos'] > 0): ?>
                            <span class="admin-insignia-ajuste"><?php echo (int) $empleado['ajustes_permisos']; ?> ajuste(s)</span>
                        <?php else: ?>
                            <small>Los del rol</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="admin-estatus <?php echo $estaActivo ? 'admin-estatus-activo' : 'admin-estatus-suspendido'; ?>">
                            <?php echo escaparSalida(nombreEstatus($empleado['estatus'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($empleado['ultimo_acceso_admin'] !== null): ?>
                            <?php echo escaparSalida(date('d/m/Y H:i', strtotime($empleado['ultimo_acceso_admin']))); ?><br>
                            <small>IP <?php echo escaparSalida($empleado['ip_ultimo_acceso_admin']); ?></small>
                        <?php else: ?>
                            <small>Nunca ha entrado</small>
                        <?php endif; ?>
                    </td>
                    <td class="admin-acciones">
                        <a href="<?php echo URL_ADMIN; ?>equipo-editar?id=<?php echo (int) $empleado['id_usuario']; ?>" class="admin-enlace-accion">Editar</a>

                        <?php if (!$esElMismo): ?>
                            <form action="<?php echo URL_ADMIN; ?>equipo" method="POST" class="admin-form-linea"
                                  onsubmit="return confirm('<?php echo $estaActivo ? 'Suspender' : 'Reactivar'; ?> a este empleado?');">
                                <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">
                                <input type="hidden" name="id_usuario" value="<?php echo (int) $empleado['id_usuario']; ?>">
                                <input type="hidden" name="operacion" value="<?php echo $estaActivo ? 'suspender' : 'reactivar'; ?>">
                                <button type="submit" class="admin-enlace-accion <?php echo $estaActivo ? 'admin-peligro' : ''; ?>"><?php echo $estaActivo ? 'Suspender' : 'Reactivar'; ?></button>
                            </form>

                            <form action="<?php echo URL_ADMIN; ?>equipo" method="POST" class="admin-form-linea"
                                  onsubmit="return confirm('Se generara una contrasena temporal nueva y la actual dejara de servir. Continuar?');">
                                <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">
                                <input type="hidden" name="id_usuario" value="<?php echo (int) $empleado['id_usuario']; ?>">
                                <input type="hidden" name="operacion" value="restablecer">
                                <button type="submit" class="admin-enlace-accion">Restablecer contrasena</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

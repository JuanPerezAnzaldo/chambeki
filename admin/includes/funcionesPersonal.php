<?php
/*
    Gestion de personal interno (solo Super Administrador).
    Se incluye con require_once desde admin/pages/equipo*.php.

    Reglas del modulo:
      - Los empleados NO se registran solos: solo un Super Administrador los
        da de alta (el registro publico siempre crea rol Cliente).
      - Nunca se borran cuentas: se suspenden (queda el historial de bitacora).
      - La contrasena inicial la genera el sistema, se guarda cifrada con
        password_hash (RS-01), se muestra UNA sola vez y obliga a cambiarla
        en el primer acceso.
      - Un Super Administrador no puede suspenderse ni cambiarse el rol a si
        mismo (evita que la empresa se quede sin quien administre).
*/

//=============================================================
// Consultas
//=============================================================

function listarEmpleados()
{
    $conexion = conexionBD();

    $sentencia = $conexion->query(
        'SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.estatus, u.id_rol_admin,
                u.ultimo_acceso_admin, u.ip_ultimo_acceso_admin, u.fecha_registro,
                (SELECT COUNT(*) FROM admin_permisos p WHERE p.id_usuario = u.id_usuario) AS ajustes_permisos
         FROM usuarios u
         WHERE u.rol = ' . (int) ROL_ADMIN . '
         ORDER BY u.estatus ASC, u.id_rol_admin ASC, u.nombre ASC'
    );

    return $sentencia->fetchAll();
}

function obtenerEmpleado($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_usuario, nombre, correo, telefono, estatus, id_rol_admin, ultimo_acceso_admin, ip_ultimo_acceso_admin
         FROM usuarios
         WHERE id_usuario = :id AND rol = :rol
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario, ':rol' => ROL_ADMIN]);

    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

function listarRolesAdmin()
{
    $conexion = conexionBD();

    return $conexion->query('SELECT id_rol_admin, nombre, descripcion FROM cat_roles_admin ORDER BY id_rol_admin')->fetchAll();
}

function correoEnUso($correo, $idExcluir = 0)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE correo = :correo AND id_usuario <> :id LIMIT 1');
    $sentencia->execute([':correo' => $correo, ':id' => $idExcluir]);

    return $sentencia->fetch() !== false;
}

//=============================================================
// Validaciones (servidor, RS-05)
//=============================================================

function validarDatosEmpleado($datos, $idExcluir = 0)
{
    $errores = [];

    if (mb_strlen($datos['nombre']) < 3 || mb_strlen($datos['nombre']) > 100)
    {
        $errores['nombre'] = 'Escribe el nombre completo (entre 3 y 100 caracteres).';
    }

    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['correo']) > 150)
    {
        $errores['correo'] = 'El correo no tiene un formato valido.';
    }
    elseif (correoEnUso($datos['correo'], $idExcluir))
    {
        //aunque sea un cliente o freelancer: el personal usa cuenta propia, nunca se "promueve" una ya existente
        $errores['correo'] = 'Ese correo ya tiene una cuenta. Usa un correo distinto para el empleado.';
    }

    if ($datos['telefono'] !== '' && strlen(preg_replace('/[^0-9]/', '', $datos['telefono'])) !== 10)
    {
        $errores['telefono'] = 'El telefono debe tener 10 digitos (o dejarlo vacio).';
    }

    $rolesValidos = array_column(listarRolesAdmin(), 'id_rol_admin');

    if (!in_array((string) $datos['id_rol_admin'], array_map('strval', $rolesValidos), true))
    {
        $errores['id_rol_admin'] = 'Selecciona un rol valido.';
    }

    return $errores;
}

/*
    Reglas de proteccion al cambiar rol o estatus de un empleado.
    Devuelve un texto de error o null si se puede.
*/
function validarProteccionEmpleado($empleado, $idActor, $nuevoEstatus, $nuevoRol)
{
    if ((int) $empleado['id_usuario'] === (int) $idActor)
    {
        if ((int) $nuevoEstatus !== ESTATUS_ACTIVO)
        {
            return 'No puedes suspender tu propia cuenta.';
        }

        if ((int) $nuevoRol !== (int) $empleado['id_rol_admin'])
        {
            return 'No puedes cambiar tu propio rol. Pidelo a otro Super Administrador.';
        }
    }

    return null;
}

//=============================================================
// Contrasena temporal
//=============================================================

//12 caracteres sin letras/numeros ambiguos (0/O, 1/l/I); siempre trae letras y numeros
function generarContrasenaTemporal()
{
    $letras  = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
    $numeros = '23456789';
    $todo    = $letras . $numeros;

    $caracteres = [
        $letras[random_int(0, strlen($letras) - 1)],
        $numeros[random_int(0, strlen($numeros) - 1)]
    ];

    while (count($caracteres) < 12)
    {
        $caracteres[] = $todo[random_int(0, strlen($todo) - 1)];
    }

    //se mezcla con random_int para que las posiciones fijas no sean predecibles
    for ($i = count($caracteres) - 1; $i > 0; $i--)
    {
        $j = random_int(0, $i);
        [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
    }

    return implode('', $caracteres);
}

//=============================================================
// Altas, cambios y permisos (cada operacion es una transaccion)
//=============================================================

/*
    Calcula SOLO las diferencias entre lo marcado en el formulario y lo que ya
    da el rol: marcado y no esta en el rol -> otorgado (1); desmarcado y si esta
    en el rol -> revocado (2). Los permisos reservados se ignoran y solo se
    aceptan nombres que existan en el catalogo.
*/
function calcularAjustesPermisos($permisosMarcados, $idRolAdmin)
{
    $catalogo   = todosLosPermisosAdmin();
    $reservados = permisosReservadosAdmin();
    $delRol     = matrizPermisosAdmin()[(int) $idRolAdmin] ?? [];
    $marcados   = array_intersect($catalogo, (array) $permisosMarcados);

    $ajustes = [];

    foreach ($catalogo as $permiso)
    {
        if (in_array($permiso, $reservados, true))
        {
            continue;
        }

        $loTieneElRol = in_array($permiso, $delRol, true);
        $loMarcaron   = in_array($permiso, $marcados, true);

        if ($loMarcaron && !$loTieneElRol)
        {
            $ajustes[$permiso] = 1;
        }
        elseif (!$loMarcaron && $loTieneElRol)
        {
            $ajustes[$permiso] = 2;
        }
    }

    return $ajustes;
}

function guardarAjustesPermisos($conexion, $idEmpleado, $ajustes, $idActor)
{
    $borrar = $conexion->prepare('DELETE FROM admin_permisos WHERE id_usuario = :id');
    $borrar->execute([':id' => $idEmpleado]);

    $insertar = $conexion->prepare(
        'INSERT INTO admin_permisos (id_usuario, permiso, tipo, id_admin_asigna, fecha_asignacion)
         VALUES (:id, :permiso, :tipo, :actor, NOW())'
    );

    foreach ($ajustes as $permiso => $tipo)
    {
        $insertar->execute([':id' => $idEmpleado, ':permiso' => $permiso, ':tipo' => $tipo, ':actor' => $idActor]);
    }
}

/*
    Da de alta a un empleado. Devuelve ['id' => ..., 'contrasena' => ...]
    (la contrasena en claro solo existe aqui para mostrarla una vez).
*/
function crearEmpleado($datos, $permisosMarcados, $idActor)
{
    $conexion = conexionBD();
    $contrasenaTemporal = generarContrasenaTemporal();

    $conexion->beginTransaction();

    try
    {
        $sentencia = $conexion->prepare(
            'INSERT INTO usuarios
                (nombre, correo, password_hash, rol, telefono, estatus, id_rol_admin, debe_cambiar_contrasena, creado_por, fecha_registro)
             VALUES
                (:nombre, :correo, :hash, :rol, :telefono, :estatus, :id_rol_admin, 1, :creado_por, NOW())'
        );

        $sentencia->execute([
            ':nombre'       => $datos['nombre'],
            ':correo'       => $datos['correo'],
            ':hash'         => password_hash($contrasenaTemporal, PASSWORD_DEFAULT),   //RS-01
            ':rol'          => ROL_ADMIN,
            ':telefono'     => $datos['telefono'] !== '' ? preg_replace('/[^0-9]/', '', $datos['telefono']) : null,
            ':estatus'      => ESTATUS_ACTIVO,
            ':id_rol_admin' => (int) $datos['id_rol_admin'],
            ':creado_por'   => $idActor
        ]);

        $idNuevo = (int) $conexion->lastInsertId();

        guardarAjustesPermisos($conexion, $idNuevo, calcularAjustesPermisos($permisosMarcados, $datos['id_rol_admin']), $idActor);

        $conexion->commit();
    }
    catch (Throwable $error)
    {
        $conexion->rollBack();
        throw $error;
    }

    return ['id' => $idNuevo, 'contrasena' => $contrasenaTemporal];
}

//$cambiarRolYPermisos = false cuando el Super se edita a si mismo (solo nombre/correo/telefono)
function actualizarEmpleado($idEmpleado, $datos, $permisosMarcados, $idActor, $cambiarRolYPermisos)
{
    $conexion = conexionBD();
    $conexion->beginTransaction();

    try
    {
        if ($cambiarRolYPermisos)
        {
            $sentencia = $conexion->prepare(
                'UPDATE usuarios
                 SET nombre = :nombre, correo = :correo, telefono = :telefono,
                     id_rol_admin = :id_rol_admin, fecha_actualizacion = NOW()
                 WHERE id_usuario = :id AND rol = :rol'
            );
            $sentencia->bindValue(':id_rol_admin', (int) $datos['id_rol_admin'], PDO::PARAM_INT);
        }
        else
        {
            $sentencia = $conexion->prepare(
                'UPDATE usuarios
                 SET nombre = :nombre, correo = :correo, telefono = :telefono, fecha_actualizacion = NOW()
                 WHERE id_usuario = :id AND rol = :rol'
            );
        }

        $sentencia->bindValue(':nombre',   $datos['nombre']);
        $sentencia->bindValue(':correo',   $datos['correo']);
        $sentencia->bindValue(':telefono', $datos['telefono'] !== '' ? preg_replace('/[^0-9]/', '', $datos['telefono']) : null);
        $sentencia->bindValue(':id',       (int) $idEmpleado, PDO::PARAM_INT);
        $sentencia->bindValue(':rol',      ROL_ADMIN, PDO::PARAM_INT);
        $sentencia->execute();

        if ($cambiarRolYPermisos)
        {
            guardarAjustesPermisos($conexion, $idEmpleado, calcularAjustesPermisos($permisosMarcados, $datos['id_rol_admin']), $idActor);
        }

        $conexion->commit();
    }
    catch (Throwable $error)
    {
        $conexion->rollBack();
        throw $error;
    }
}

function cambiarEstatusEmpleado($idEmpleado, $estatus)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'UPDATE usuarios SET estatus = :estatus, fecha_actualizacion = NOW() WHERE id_usuario = :id AND rol = :rol'
    );
    $sentencia->execute([':estatus' => $estatus, ':id' => $idEmpleado, ':rol' => ROL_ADMIN]);
}

//genera otra contrasena temporal (empleado que la olvido); devuelve la nueva en claro para mostrarla una vez
function restablecerContrasenaEmpleado($idEmpleado)
{
    $conexion = conexionBD();
    $contrasenaTemporal = generarContrasenaTemporal();

    $sentencia = $conexion->prepare(
        'UPDATE usuarios
         SET password_hash = :hash, debe_cambiar_contrasena = 1, fecha_actualizacion = NOW()
         WHERE id_usuario = :id AND rol = :rol'
    );
    $sentencia->execute([
        ':hash' => password_hash($contrasenaTemporal, PASSWORD_DEFAULT),
        ':id'   => $idEmpleado,
        ':rol'  => ROL_ADMIN
    ]);

    return $contrasenaTemporal;
}

//ajustes actuales de un empleado: ['permiso' => 1|2]
function obtenerAjustesPermisos($idEmpleado)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT permiso, tipo FROM admin_permisos WHERE id_usuario = :id');
    $sentencia->execute([':id' => $idEmpleado]);

    $ajustes = [];

    foreach ($sentencia->fetchAll() as $fila)
    {
        $ajustes[$fila['permiso']] = (int) $fila['tipo'];
    }

    return $ajustes;
}

//seguridad adicional: aun con el middleware, cada accion de escritura confirma que quien actua es Super
function exigirSuperAdmin($admin)
{
    if (!adminEsSuper($admin))
    {
        http_response_code(403);
        exit('Acceso denegado.');
    }
}
?>

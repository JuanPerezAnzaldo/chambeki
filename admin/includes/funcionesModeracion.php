<?php
/*
    Metricas del dashboard y moderacion basica de cuentas de clientes y
    freelancers. Se incluye con require_once desde admin/pages.

    Reglas:
      - Aqui SOLO se tocan cuentas con rol Cliente o Freelancer. Las cuentas
        de empleados (rol Admin) se administran unicamente desde
        "Gestion de personal" (Super Administrador).
      - Toda accion que cambie algo exige un motivo y queda en
        moderacion_acciones (historial visible en el perfil) y en bitacora.
      - Todas las consultas son preparadas. Como la conexion usa prepares
        nativos, ningun parametro con nombre se repite dentro de una consulta.
*/

define('CUENTAS_POR_PAGINA', 15);

//rol IN (2, 3): clientes y freelancers (fragmento fijo, sin datos del usuario)
function sqlRolesModerables()
{
    return 'rol IN (' . (int) ROL_FREELANCER . ', ' . (int) ROL_CLIENTE . ')';
}

//=============================================================
// Metricas del dashboard
//=============================================================

function metricasDashboard()
{
    $conexion = conexionBD();

    $fila = $conexion->query(
        'SELECT
            COUNT(*)                                             AS total,
            COALESCE(SUM(rol = ' . (int) ROL_CLIENTE . '), 0)    AS clientes,
            COALESCE(SUM(rol = ' . (int) ROL_FREELANCER . '), 0) AS freelancers,
            COALESCE(SUM(rol = ' . (int) ROL_FREELANCER . ' AND estatus = ' . (int) ESTATUS_ACTIVO . '), 0) AS freelancers_activos,
            COALESCE(SUM(estatus = ' . (int) ESTATUS_SUSPENDIDO . '), 0) AS suspendidas,
            COALESCE(SUM(DATE(fecha_registro) = CURDATE()), 0)   AS nuevos_hoy,
            COALESCE(SUM(fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0)  AS nuevos_7,
            COALESCE(SUM(fecha_registro >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                         AND fecha_registro < DATE_SUB(NOW(), INTERVAL 7 DAY)), 0) AS nuevos_7_previos,
            COALESCE(SUM(fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS nuevos_30
         FROM usuarios
         WHERE ' . sqlRolesModerables()
    )->fetch();

    $metricas = array_map('intval', $fila);

    $metricas['freelancers_sin_verificar'] = (int) $conexion->query(
        'SELECT COUNT(*) FROM usuarios u
         WHERE u.rol = ' . (int) ROL_FREELANCER . ' AND u.estatus = ' . (int) ESTATUS_ACTIVO . '
           AND NOT EXISTS (SELECT 1 FROM perfil_freelancer pf WHERE pf.id_usuario = u.id_usuario AND pf.perfil_verificado = 1)'
    )->fetchColumn();

    $metricas['docs_pendientes'] = (int) $conexion->query(
        'SELECT COUNT(*) FROM docs_verificacion WHERE estado_revision = 1'
    )->fetchColumn();

    $metricas['suscripciones_activas'] = (int) $conexion->query(
        'SELECT COUNT(*) FROM suscripciones WHERE estado = 1 AND fecha_vencimiento > NOW()'
    )->fetchColumn();

    $citas = $conexion->query(
        "SELECT COUNT(*) AS total, COALESCE(SUM(estado_cita = 3), 0) AS completadas
         FROM citas
         WHERE fecha_hora_inicio >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
    )->fetch();

    $metricas['citas_mes']            = (int) $citas['total'];
    $metricas['citas_mes_completadas'] = (int) $citas['completadas'];

    return $metricas;
}

//altas por dia de los ultimos 30 dias (dias sin altas = 0): ['2026-10-01' => 3, ...]
function registrosPorDia($dias = 30)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT DATE(fecha_registro) AS dia, COUNT(*) AS total
         FROM usuarios
         WHERE ' . sqlRolesModerables() . '
           AND fecha_registro >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
         GROUP BY DATE(fecha_registro)'
    );
    $sentencia->bindValue(':dias', (int) $dias - 1, PDO::PARAM_INT);
    $sentencia->execute();

    $porDia = [];

    foreach ($sentencia->fetchAll() as $fila)
    {
        $porDia[$fila['dia']] = (int) $fila['total'];
    }

    $resultado = [];

    for ($i = $dias - 1; $i >= 0; $i--)
    {
        $dia = date('Y-m-d', strtotime('-' . $i . ' days'));
        $resultado[$dia] = $porDia[$dia] ?? 0;
    }

    return $resultado;
}

function ultimosRegistros($cantidad = 8)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_usuario, nombre, correo, rol, estatus, fecha_registro
         FROM usuarios
         WHERE ' . sqlRolesModerables() . '
         ORDER BY fecha_registro DESC, id_usuario DESC
         LIMIT :cantidad'
    );
    $sentencia->bindValue(':cantidad', (int) $cantidad, PDO::PARAM_INT);
    $sentencia->execute();

    return $sentencia->fetchAll();
}

//=============================================================
// Buscador centralizado de cuentas
//=============================================================

//EXISTS reutilizado: el freelancer tiene al menos un perfil verificado (id_usuario no es UNIQUE en perfil_freelancer)
function sqlEsVerificado()
{
    return 'EXISTS (SELECT 1 FROM perfil_freelancer pf WHERE pf.id_usuario = u.id_usuario AND pf.perfil_verificado = 1)';
}

/*
    $filtros: q (texto), rol ('2'|'3'), estatus ('1'|'2'), verificado ('si'|'no')
    Devuelve ['filas' => [...], 'total' => n, 'paginas' => n, 'pagina' => n]
*/
function buscarCuentas($filtros, $pagina)
{
    $conexion = conexionBD();

    $where  = ['u.' . sqlRolesModerables()];
    $params = [];

    $texto = trim((string) ($filtros['q'] ?? ''));

    if ($texto !== '')
    {
        //se escapan % y _ para que se busquen como texto normal
        $like = '%' . addcslashes($texto, '%_\\') . '%';

        $condicion = 'u.nombre LIKE :q_nombre OR u.correo LIKE :q_correo OR u.telefono LIKE :q_telefono';
        $params[':q_nombre']   = $like;
        $params[':q_correo']   = $like;
        $params[':q_telefono'] = $like;

        if (ctype_digit($texto))
        {
            $condicion .= ' OR u.id_usuario = :q_id';
            $params[':q_id'] = (int) $texto;
        }

        $where[] = '(' . $condicion . ')';
    }

    if (in_array($filtros['rol'] ?? '', ['2', '3'], true))
    {
        $where[] = 'u.rol = :rol';
        $params[':rol'] = (int) $filtros['rol'];
    }

    if (in_array($filtros['estatus'] ?? '', ['1', '2'], true))
    {
        $where[] = 'u.estatus = :estatus';
        $params[':estatus'] = (int) $filtros['estatus'];
    }

    if (($filtros['verificado'] ?? '') === 'si')
    {
        $where[] = sqlEsVerificado();
    }
    elseif (($filtros['verificado'] ?? '') === 'no')
    {
        $where[] = 'u.rol = ' . (int) ROL_FREELANCER . ' AND NOT ' . sqlEsVerificado();
    }

    $condiciones = implode(' AND ', $where);

    //total para la paginacion
    $sentenciaTotal = $conexion->prepare('SELECT COUNT(*) FROM usuarios u WHERE ' . $condiciones);
    $sentenciaTotal->execute($params);
    $total = (int) $sentenciaTotal->fetchColumn();

    $paginas = max(1, (int) ceil($total / CUENTAS_POR_PAGINA));
    $pagina  = min(max(1, (int) $pagina), $paginas);

    $sentencia = $conexion->prepare(
        'SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.rol, u.estatus, u.fecha_registro,
                ' . sqlEsVerificado() . ' AS verificado
         FROM usuarios u
         WHERE ' . $condiciones . '
         ORDER BY u.fecha_registro DESC, u.id_usuario DESC
         LIMIT :limite OFFSET :desplazamiento'
    );

    foreach ($params as $nombre => $valor)
    {
        $sentencia->bindValue($nombre, $valor, is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }

    $sentencia->bindValue(':limite', CUENTAS_POR_PAGINA, PDO::PARAM_INT);
    $sentencia->bindValue(':desplazamiento', ($pagina - 1) * CUENTAS_POR_PAGINA, PDO::PARAM_INT);
    $sentencia->execute();

    return ['filas' => $sentencia->fetchAll(), 'total' => $total, 'paginas' => $paginas, 'pagina' => $pagina];
}

//=============================================================
// Inspeccion de una cuenta
//=============================================================

//solo cuentas de cliente o freelancer; un id de empleado devuelve null
function obtenerCuenta($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_usuario, nombre, correo, telefono, rol, tipo_cuenta, estatus, foto_perfil_url, fecha_registro, fecha_actualizacion
         FROM usuarios
         WHERE id_usuario = :id AND ' . sqlRolesModerables() . '
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

function perfilProfesionalDe($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_perfil, identificador_fiscal, es_empresa, perfil_verificado, descripcion, acepto_efectivo, acepto_tarjeta
         FROM perfil_freelancer
         WHERE id_usuario = :id
         ORDER BY id_perfil ASC
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

function coberturaDe($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT c.direccion_texto, c.ciudad_limite, c.radio_cobertura_km, m.nombre AS modalidad
         FROM cobertura_freelancer c
         LEFT JOIN cat_modalidad m ON m.id_modalidad = c.id_modalidad
         WHERE c.id_usuario = :id
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

function documentosDe($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT d.id_documento, d.tipo_documento, d.url_archivo, d.estado_revision, d.notas_aprobacion,
                d.fecha_revision, r.nombre AS revisor
         FROM docs_verificacion d
         LEFT JOIN usuarios r ON r.id_usuario = d.id_admin_revisor
         WHERE d.id_usuario = :id
         ORDER BY d.id_documento DESC'
    );
    $sentencia->execute([':id' => $idUsuario]);

    return $sentencia->fetchAll();
}

function suscripcionDe($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT s.estado, s.fecha_vencimiento, p.nombre_plan
         FROM suscripciones s
         LEFT JOIN cat_planes p ON p.id_plan = s.id_plan
         WHERE s.id_usuario = :id
         ORDER BY s.fecha_vencimiento DESC
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila;
}

//indicadores de actividad de la cuenta (segun su rol)
function estadisticasCuenta($cuenta)
{
    $conexion = conexionBD();
    $id = (int) $cuenta['id_usuario'];
    $datos = [];

    if ((int) $cuenta['rol'] === ROL_FREELANCER)
    {
        $s = $conexion->prepare('SELECT COUNT(*) FROM servicios WHERE id_usuario = :id');
        $s->execute([':id' => $id]);
        $datos['servicios'] = (int) $s->fetchColumn();

        $s = $conexion->prepare(
            'SELECT COUNT(*) AS total, COALESCE(SUM(c.estado_cita = 3), 0) AS completadas
             FROM citas c JOIN servicios sv ON sv.id_servicio = c.id_servicio
             WHERE sv.id_usuario = :id'
        );
        $s->execute([':id' => $id]);
        $citas = $s->fetch();
        $datos['citas']             = (int) $citas['total'];
        $datos['citas_completadas'] = (int) $citas['completadas'];

        $s = $conexion->prepare(
            'SELECT AVG(e.calificacion) AS promedio, COUNT(*) AS total
             FROM evaluaciones e
             JOIN citas c ON c.id_cita = e.id_cita
             JOIN servicios sv ON sv.id_servicio = c.id_servicio
             WHERE sv.id_usuario = :id'
        );
        $s->execute([':id' => $id]);
        $evaluaciones = $s->fetch();
        $datos['calificacion']      = $evaluaciones['promedio'] !== null ? round((float) $evaluaciones['promedio'], 1) : null;
        $datos['evaluaciones']      = (int) $evaluaciones['total'];
    }
    else
    {
        $s = $conexion->prepare(
            'SELECT COUNT(*) AS total, COALESCE(SUM(estado_cita = 3), 0) AS completadas FROM citas WHERE id_cliente = :id'
        );
        $s->execute([':id' => $id]);
        $citas = $s->fetch();
        $datos['citas']             = (int) $citas['total'];
        $datos['citas_completadas'] = (int) $citas['completadas'];
    }

    return $datos;
}

function historialModeracion($idUsuario, $limite = 10)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT m.accion, m.detalle, m.motivo, m.fecha, a.nombre AS admin_nombre
         FROM moderacion_acciones m
         LEFT JOIN usuarios a ON a.id_usuario = m.id_admin
         WHERE m.id_usuario_objetivo = :id
         ORDER BY m.id_accion DESC
         LIMIT :limite'
    );
    $sentencia->bindValue(':id', (int) $idUsuario, PDO::PARAM_INT);
    $sentencia->bindValue(':limite', (int) $limite, PDO::PARAM_INT);
    $sentencia->execute();

    return $sentencia->fetchAll();
}

//=============================================================
// Etiquetas
//=============================================================

function nombreTipoDocumento($tipo)
{
    $tipos = [1 => 'INE', 2 => 'Cedula profesional', 3 => 'Constancia SAT'];

    return $tipos[(int) $tipo] ?? 'Documento';
}

function nombreEstadoRevision($estado)
{
    $estados = [1 => 'Pendiente', 2 => 'Aprobado', 3 => 'Rechazado'];

    return $estados[(int) $estado] ?? 'Pendiente';
}

function etiquetaAccionModeracion($accion)
{
    $etiquetas = [
        'suspender'          => 'Cuenta suspendida',
        'activar'            => 'Cuenta activada',
        'editar_datos'       => 'Datos basicos editados',
        'quitar_foto'        => 'Foto de perfil retirada',
        'verificar'          => 'Freelancer verificado',
        'quitar_verificacion'=> 'Verificacion retirada',
        'doc_aprobado'       => 'Documento aprobado',
        'doc_rechazado'      => 'Documento rechazado'
    ];

    return $etiquetas[$accion] ?? $accion;
}

//muestra solo el inicio de un dato sensible (RFC/CURP): ABCD*********
function enmascararDato($texto)
{
    $texto = (string) $texto;

    if ($texto === '')
    {
        return '';
    }

    return mb_substr($texto, 0, 4) . str_repeat('*', max(0, mb_strlen($texto) - 4));
}

//=============================================================
// Validaciones
//=============================================================

//toda accion que cambia algo debe justificarse (queda en el historial)
function validarMotivoModeracion($motivo)
{
    $largo = mb_strlen($motivo);

    if ($largo < 5)
    {
        return 'Escribe el motivo (minimo 5 caracteres): quedara en el historial de la cuenta.';
    }

    if ($largo > 255)
    {
        return 'El motivo no puede pasar de 255 caracteres.';
    }

    return null;
}

function validarDatosBasicosCuenta($datos)
{
    $errores = [];

    if (mb_strlen($datos['nombre']) < 3 || mb_strlen($datos['nombre']) > 100)
    {
        $errores['nombre'] = 'El nombre debe tener entre 3 y 100 caracteres.';
    }

    if ($datos['telefono'] !== '' && strlen(preg_replace('/[^0-9]/', '', $datos['telefono'])) !== 10)
    {
        $errores['telefono'] = 'El telefono debe tener 10 digitos (o dejarlo vacio).';
    }

    return $errores;
}

//=============================================================
// Acciones de moderacion
//=============================================================

function registrarAccionModeracion($admin, $idObjetivo, $accion, $detalle, $motivo)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'INSERT INTO moderacion_acciones (id_admin, id_usuario_objetivo, accion, detalle, motivo, fecha)
         VALUES (:admin, :objetivo, :accion, :detalle, :motivo, NOW())'
    );
    $sentencia->execute([
        ':admin'    => $admin['id_usuario'],
        ':objetivo' => $idObjetivo,
        ':accion'   => $accion,
        ':detalle'  => $detalle !== null ? mb_substr($detalle, 0, 255) : null,
        ':motivo'   => $motivo !== null ? mb_substr($motivo, 0, 255) : null
    ]);

    //tambien en la bitacora general (evento corto: cabe en varchar(50))
    registrarEnBitacora($admin['correo'], 'adm_mod_' . $accion . ':' . $idObjetivo);
}

function cambiarEstatusCuenta($idUsuario, $estatus)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'UPDATE usuarios SET estatus = :estatus, fecha_actualizacion = NOW()
         WHERE id_usuario = :id AND ' . sqlRolesModerables()
    );
    $sentencia->execute([':estatus' => $estatus, ':id' => $idUsuario]);
}

function actualizarDatosBasicosCuenta($idUsuario, $nombre, $telefono)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'UPDATE usuarios SET nombre = :nombre, telefono = :telefono, fecha_actualizacion = NOW()
         WHERE id_usuario = :id AND ' . sqlRolesModerables()
    );
    $sentencia->execute([
        ':nombre'   => $nombre,
        ':telefono' => $telefono !== '' ? preg_replace('/[^0-9]/', '', $telefono) : null,
        ':id'       => $idUsuario
    ]);
}

//quita la foto de la cuenta y borra el archivo solo si esta en la carpeta de fotos del sitio
function quitarFotoCuenta($idUsuario, $rutaActual)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'UPDATE usuarios SET foto_perfil_url = NULL, fecha_actualizacion = NOW()
         WHERE id_usuario = :id AND ' . sqlRolesModerables()
    );
    $sentencia->execute([':id' => $idUsuario]);

    $carpetaFotos = 'user/assets/img/perfiles/';

    if (!empty($rutaActual)
        && strpos($rutaActual, $carpetaFotos) === 0
        && strpos($rutaActual, '..') === false
        && file_exists(DOCROOT . $rutaActual))
    {
        unlink(DOCROOT . $rutaActual);
    }
}

function establecerVerificacion($idUsuario, $valor)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('UPDATE perfil_freelancer SET perfil_verificado = :valor WHERE id_usuario = :id');
    $sentencia->execute([':valor' => $valor ? 1 : 0, ':id' => $idUsuario]);
}

//$estado: 2 = aprobado, 3 = rechazado. El documento debe pertenecer a esa cuenta.
function revisarDocumento($idDocumento, $idUsuario, $estado, $nota, $idRevisor)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'UPDATE docs_verificacion
         SET estado_revision = :estado, notas_aprobacion = :nota, id_admin_revisor = :revisor, fecha_revision = NOW()
         WHERE id_documento = :documento AND id_usuario = :usuario'
    );
    $sentencia->execute([
        ':estado'    => $estado,
        ':nota'      => $nota !== '' ? mb_substr($nota, 0, 255) : null,
        ':revisor'   => $idRevisor,
        ':documento' => $idDocumento,
        ':usuario'   => $idUsuario
    ]);

    return $sentencia->rowCount() > 0;
}
?>

<?php
/*
    REPORTAR A UN USUARIO (desde el perfil publico de un profesional)

    Guarda el reporte en reportes_incidencias con estado 'pendiente'.
    Llega solo al buzon del panel de administracion (/admin/reportes),
    donde el personal lo asigna y lo dictamina.
*/

const REPORTE_DESCRIPCION_MIN   = 20;
const REPORTE_DESCRIPCION_MAX   = 1000;
const REPORTES_MAX_POR_DIA      = 5;

//clave => texto que se guarda en motivo_corto (el admin lo ve tal cual)
function motivosReporte()
{
    return [
        'incompleto' => 'Servicio incompleto o no realizado',
        'cobro'      => 'Cobro adicional no acordado',
        'trato'      => 'Mal trato o falta de respeto',
        'falso'      => 'Perfil con información falsa',
        'fraude'     => 'Posible fraude o estafa',
        'otro'       => 'Otro motivo'
    ];
}

function validarReporteUsuario($claveMotivo, $descripcion)
{
    if (!isset(motivosReporte()[$claveMotivo]))
    {
        return 'Elige el motivo del reporte.';
    }

    $largo = mb_strlen($descripcion);

    if ($largo < REPORTE_DESCRIPCION_MIN)
    {
        return 'Cuéntanos qué pasó con un poco más de detalle (mínimo ' . REPORTE_DESCRIPCION_MIN . ' caracteres).';
    }

    if ($largo > REPORTE_DESCRIPCION_MAX)
    {
        return 'La descripción no puede pasar de ' . REPORTE_DESCRIPCION_MAX . ' caracteres.';
    }

    return null;
}

/*
    Devuelve ['exito' => bool, 'mensaje' => string].
    Reglas: no puedes reportarte a ti mismo, solo se reportan clientes o freelancers,
    un solo reporte abierto por pareja y maximo REPORTES_MAX_POR_DIA al dia.
*/
function crearReporteUsuario($idDenunciante, $idReportado, $claveMotivo, $descripcion)
{
    $idDenunciante = (int) $idDenunciante;
    $idReportado   = (int) $idReportado;

    if ($idDenunciante === $idReportado)
    {
        return ['exito' => false, 'mensaje' => 'No puedes reportar tu propia cuenta.'];
    }

    $error = validarReporteUsuario($claveMotivo, $descripcion);
    if ($error !== null)
    {
        return ['exito' => false, 'mensaje' => $error];
    }

    $conexion = conexionBD();

    //solo cuentas de clientes o freelancers (nunca empleados)
    $existe = $conexion->prepare('SELECT 1 FROM usuarios WHERE id_usuario = :id AND rol IN (2, 3) LIMIT 1');
    $existe->execute([':id' => $idReportado]);
    if ($existe->fetchColumn() === false)
    {
        return ['exito' => false, 'mensaje' => 'No encontramos a la persona que quieres reportar.'];
    }

    $abierto = $conexion->prepare(
        "SELECT id_reporte FROM reportes_incidencias
         WHERE id_denunciante = :den AND id_reportado = :rep AND estado IN ('pendiente', 'revision')
         LIMIT 1"
    );
    $abierto->execute([':den' => $idDenunciante, ':rep' => $idReportado]);
    $folio = $abierto->fetchColumn();
    if ($folio !== false)
    {
        return ['exito' => false, 'mensaje' => 'Ya tienes un reporte abierto contra esta cuenta (folio #' . (int) $folio . '). Nuestro equipo lo está revisando.'];
    }

    $hoy = $conexion->prepare(
        'SELECT COUNT(*) FROM reportes_incidencias
         WHERE id_denunciante = :den AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 1 DAY)'
    );
    $hoy->execute([':den' => $idDenunciante]);
    if ((int) $hoy->fetchColumn() >= REPORTES_MAX_POR_DIA)
    {
        return ['exito' => false, 'mensaje' => 'Alcanzaste el máximo de reportes por día. Intenta de nuevo mañana.'];
    }

    $insertar = $conexion->prepare(
        "INSERT INTO reportes_incidencias (id_denunciante, id_reportado, motivo_corto, descripcion, estado, fecha_creacion)
         VALUES (:den, :rep, :motivo, :descripcion, 'pendiente', NOW())"
    );
    $insertar->execute([
        ':den'         => $idDenunciante,
        ':rep'         => $idReportado,
        ':motivo'      => motivosReporte()[$claveMotivo],
        ':descripcion' => $descripcion
    ]);

    return ['exito' => true, 'mensaje' => 'Recibimos tu reporte (folio #' . (int) $conexion->lastInsertId() . '). Nuestro equipo lo revisará y tomará las medidas necesarias.'];
}

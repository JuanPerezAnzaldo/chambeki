<?php
/*
    FUNCIONES DEL MODULO DE REPORTES E INCIDENCIAS (/admin/reportes)
    Tabla: reportes_incidencias
*/

function estadosReporte()
{
    return [
        'pendiente' => 'Pendiente',
        'revision'  => 'En revisión',
        'resuelto'  => 'Resuelto',
        'desechado' => 'Desechado'
    ];
}

function etiquetaEstadoReporte($estado)
{
    return estadosReporte()[$estado] ?? ucfirst((string) $estado);
}

//clase CSS de la insignia segun el estado (reutiliza las del panel)
function claseEstadoReporte($estado)
{
    switch ($estado)
    {
        case 'resuelto':  return 'insignia-verificado';
        case 'desechado': return 'admin-estatus-suspendido';
        default:          return 'insignia-pendiente';
    }
}

//empleados activos a los que se puede asignar un reporte (Super y Supervisores/Soporte)
function listarSupervisoresActivos()
{
    $conexion = conexionBD();

    return $conexion->query(
        'SELECT id_usuario, nombre, id_rol_admin
         FROM usuarios
         WHERE rol = 1 AND id_rol_admin IS NOT NULL AND estatus = 1
         ORDER BY nombre ASC'
    )->fetchAll();
}

function supervisorValido($idUsuario)
{
    foreach (listarSupervisoresActivos() as $s)
    {
        if ((int) $s['id_usuario'] === (int) $idUsuario)
        {
            return true;
        }
    }

    return false;
}

//conteo por estado para las pestañas del listado
function conteoReportesPorEstado()
{
    $conexion = conexionBD();
    $conteo = array_fill_keys(array_keys(estadosReporte()), 0);

    foreach ($conexion->query('SELECT estado, COUNT(*) AS total FROM reportes_incidencias GROUP BY estado')->fetchAll() as $fila)
    {
        if (isset($conteo[$fila['estado']])) { $conteo[$fila['estado']] = (int) $fila['total']; }
    }

    return $conteo;
}

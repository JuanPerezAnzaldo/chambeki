<?php
/*
    LISTADO DE REPORTES E INCIDENCIAS (/admin/reportes)
    Permiso: reportes.ver
    Filtros: estado, asignado (todos / sin asignar / mis reportes / un supervisor) y texto.
*/
require_once DOCROOT . 'admin/includes/funcionesReportes.php';

$conexion = conexionBD();

$estado   = (string) ($_GET['estado'] ?? '');
$asignado = (string) ($_GET['asignado'] ?? '');
$q        = mb_substr(limpiarEntrada($_GET['q'] ?? ''), 0, 100);

if (!isset(estadosReporte()[$estado])) { $estado = ''; }

$sql = "SELECT r.*, den.nombre AS denunciante, rep.nombre AS reportado, rep.rol AS rol_reportado, adm.nombre AS asignado
        FROM reportes_incidencias r
        JOIN usuarios den ON r.id_denunciante = den.id_usuario
        JOIN usuarios rep ON r.id_reportado = rep.id_usuario
        LEFT JOIN usuarios adm ON r.id_admin_asignado = adm.id_usuario
        WHERE 1=1";
$params = [];

if ($estado !== '')
{
    $sql .= ' AND r.estado = :estado';
    $params[':estado'] = $estado;
}

if ($asignado === 'ninguno')
{
    $sql .= ' AND r.id_admin_asignado IS NULL';
}
elseif ($asignado === 'mios')
{
    $sql .= ' AND r.id_admin_asignado = :mio';
    $params[':mio'] = $admin['id_usuario'];
}
elseif (ctype_digit($asignado) && (int) $asignado > 0)
{
    $sql .= ' AND r.id_admin_asignado = :asig';
    $params[':asig'] = (int) $asignado;
}

if ($q !== '')
{
    $sql .= ' AND (r.id_reporte = :q_id OR rep.nombre LIKE :q_texto OR den.nombre LIKE :q_texto2 OR r.motivo_corto LIKE :q_texto3)';
    $params[':q_id']     = (int) ltrim($q, '#');
    $params[':q_texto']  = "%$q%";
    $params[':q_texto2'] = "%$q%";
    $params[':q_texto3'] = "%$q%";
}

//los casos abiertos primero, luego los mas recientes
$sql .= " ORDER BY FIELD(r.estado, 'pendiente', 'revision', 'resuelto', 'desechado'), r.fecha_creacion DESC";

$sentencia = $conexion->prepare($sql);
$sentencia->execute($params);
$reportes = $sentencia->fetchAll();

$conteo = conteoReportesPorEstado();
$supervisores = listarSupervisoresActivos();
$mensajeFlash = obtenerMensaje();

function enlaceEstadoReportes($estadoNuevo, $asignado, $q)
{
    $p = [];
    if ($estadoNuevo !== '') { $p['estado'] = $estadoNuevo; }
    if ($asignado !== '')    { $p['asignado'] = $asignado; }
    if ($q !== '')           { $p['q'] = $q; }

    return URL_ADMIN . 'reportes' . ($p ? '?' . http_build_query($p) : '');
}
?>

<h1 class="admin-titulo">Reportes e Incidencias</h1>
<p class="admin-subtitulo">Buzón de moderación. Revisa conflictos entre usuarios, asígnalos a un supervisor y dictamina soluciones.</p>

<?php if ($mensajeFlash): ?>
    <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
<?php endif; ?>

<nav class="reportes-pestanas" aria-label="Filtrar por estado">
    <a href="<?php echo escaparSalida(enlaceEstadoReportes('', $asignado, $q)); ?>" class="<?php echo $estado === '' ? 'activo' : ''; ?>">Todos <span><?php echo array_sum($conteo); ?></span></a>
    <?php foreach (estadosReporte() as $clave => $etiqueta): ?>
        <a href="<?php echo escaparSalida(enlaceEstadoReportes($clave, $asignado, $q)); ?>" class="<?php echo $estado === $clave ? 'activo' : ''; ?>"><?php echo escaparSalida($etiqueta); ?> <span><?php echo $conteo[$clave]; ?></span></a>
    <?php endforeach; ?>
</nav>

<form method="GET" action="<?php echo URL_ADMIN; ?>reportes" class="admin-filtros">
    <div class="campo-formulario admin-filtro-texto">
        <label for="repQ">Buscar</label>
        <input type="search" id="repQ" name="q" value="<?php echo escaparSalida($q); ?>" placeholder="Folio, usuario o motivo" maxlength="100">
    </div>
    <div class="campo-formulario">
        <label for="repEstado">Estado</label>
        <select id="repEstado" name="estado">
            <option value="">Todos</option>
            <?php foreach (estadosReporte() as $clave => $etiqueta): ?>
                <option value="<?php echo $clave; ?>" <?php echo $estado === $clave ? 'selected' : ''; ?>><?php echo escaparSalida($etiqueta); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="campo-formulario">
        <label for="repAsignado">Asignado a</label>
        <select id="repAsignado" name="asignado">
            <option value="">Cualquiera</option>
            <option value="mios" <?php echo $asignado === 'mios' ? 'selected' : ''; ?>>Mis reportes</option>
            <option value="ninguno" <?php echo $asignado === 'ninguno' ? 'selected' : ''; ?>>Sin asignar</option>
            <?php foreach ($supervisores as $s): ?>
                <option value="<?php echo (int) $s['id_usuario']; ?>" <?php echo $asignado === (string) $s['id_usuario'] ? 'selected' : ''; ?>><?php echo escaparSalida($s['nombre']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="admin-filtros-botones">
        <button type="submit" class="boton-autenticacion admin-boton-accion">Filtrar</button>
        <?php if ($estado !== '' || $asignado !== '' || $q !== ''): ?><a href="<?php echo URL_ADMIN; ?>reportes" class="admin-enlace-accion">Limpiar</a><?php endif; ?>
    </div>
</form>

<div class="admin-tabla-contenedor">
    <table class="admin-tabla">
        <thead>
            <tr><th>Folio</th><th>Denunciante</th><th>Reportado</th><th>Motivo</th><th>Estado</th><th>Asignado</th><th>Acción</th></tr>
        </thead>
        <tbody>
            <?php if (empty($reportes)): ?><tr><td colspan="7">No se encontraron reportes.</td></tr><?php endif; ?>
            <?php foreach ($reportes as $r): ?>
                <tr>
                    <td><strong>#<?php echo (int) $r['id_reporte']; ?></strong><br><small><?php echo date('d/m/Y', strtotime($r['fecha_creacion'])); ?></small></td>
                    <td><?php echo escaparSalida($r['denunciante']); ?></td>
                    <td><strong><?php echo escaparSalida($r['reportado']); ?></strong><br><small><?php echo nombreRol($r['rol_reportado']); ?></small></td>
                    <td><?php echo escaparSalida($r['motivo_corto']); ?></td>
                    <td><span class="insignia-verificacion <?php echo claseEstadoReporte($r['estado']); ?>"><?php echo escaparSalida(etiquetaEstadoReporte($r['estado'])); ?></span></td>
                    <td><small><?php echo escaparSalida($r['asignado'] ?? 'Sin asignar'); ?></small></td>
                    <td><a href="<?php echo URL_ADMIN; ?>reporte-detalle?id=<?php echo (int) $r['id_reporte']; ?>" class="admin-enlace-accion">Dictaminar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php
/*
    LISTADO DE REPORTES E INCIDENCIAS (/admin/reportes)
    Permiso: reportes.ver
*/
$conexion = conexionBD();

// Filtros
$estado = $_GET['estado'] ?? '';
$q = limpiarEntrada($_GET['q'] ?? '');

$sql = "SELECT r.*, den.nombre as denunciante, rep.nombre as reportado, rep.rol as rol_reportado, adm.nombre as asignado 
        FROM reportes_incidencias r
        JOIN usuarios den ON r.id_denunciante = den.id_usuario
        JOIN usuarios rep ON r.id_reportado = rep.id_usuario
        LEFT JOIN usuarios adm ON r.id_admin_asignado = adm.id_usuario
        WHERE 1=1";
$params = [];

if ($estado !== '') {
    $sql .= " AND r.estado = :estado";
    $params[':estado'] = $estado;
}
if ($q !== '') {
    $sql .= " AND (r.id_reporte = :q_id OR rep.nombre LIKE :q_texto OR den.nombre LIKE :q_texto)";
    $params[':q_id'] = (int)$q;
    $params[':q_texto'] = "%$q%";
}
$sql .= " ORDER BY r.fecha_creacion DESC";

$sentencia = $conexion->prepare($sql);
$sentencia->execute($params);
$reportes = $sentencia->fetchAll();
$mensajeFlash = obtenerMensaje();
?>

<h1 class="admin-titulo">Reportes e Incidencias</h1>
<p class="admin-subtitulo">Buzón de moderación. Revisa conflictos entre usuarios y dictamina soluciones.</p>

<?php if ($mensajeFlash): ?>
    <p class="aviso-autenticacion aviso-<?php echo $mensajeFlash['tipo']; ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
<?php endif; ?>

<form method="GET" class="admin-filtros">
    <div class="campo-formulario admin-filtro-texto">
        <label>Buscar</label>
        <input type="search" name="q" value="<?php echo escaparSalida($q); ?>" placeholder="ID de reporte, nombre de usuario">
    </div>
    <div class="campo-formulario">
        <label>Estado</label>
        <select name="estado">
            <option value="">Todos</option>
            <option value="pendiente" <?php echo $estado==='pendiente'?'selected':'';?>>Pendiente</option>
            <option value="revision" <?php echo $estado==='revision'?'selected':'';?>>En Revisión</option>
            <option value="resuelto" <?php echo $estado==='resuelto'?'selected':'';?>>Resuelto</option>
            <option value="desechado" <?php echo $estado==='desechado'?'selected':'';?>>Desechado</option>
        </select>
    </div>
    <div class="admin-filtros-botones">
        <button type="submit" class="boton-autenticacion admin-boton-accion">Filtrar</button>
    </div>
</form>

<div class="admin-tabla-contenedor">
    <table class="admin-tabla">
        <thead>
            <tr><th>Folio</th><th>Denunciante</th><th>Reportado</th><th>Motivo</th><th>Estado</th><th>Asignado</th><th>Acción</th></tr>
        </thead>
        <tbody>
            <?php if(empty($reportes)): ?><tr><td colspan="7">No se encontraron reportes.</td></tr><?php endif; ?>
            <?php foreach($reportes as $r): ?>
                <tr>
                    <td><strong>#<?php echo $r['id_reporte']; ?></strong><br><small><?php echo date('d/m/Y', strtotime($r['fecha_creacion'])); ?></small></td>
                    <td><?php echo escaparSalida($r['denunciante']); ?></td>
                    <td><strong><?php echo escaparSalida($r['reportado']); ?></strong><br><small><?php echo nombreRol($r['rol_reportado']); ?></small></td>
                    <td><?php echo escaparSalida($r['motivo_corto']); ?></td>
                    <td>
                        <span class="insignia-verificacion <?php echo $r['estado']=='resuelto'?'insignia-verificado':($r['estado']=='desechado'?'admin-estatus-suspendido':'insignia-pendiente'); ?>">
                            <?php echo ucfirst($r['estado']); ?>
                        </span>
                    </td>
                    <td><small><?php echo escaparSalida($r['asignado'] ?? 'Sin asignar'); ?></small></td>
                    <td><a href="<?php echo URL_ADMIN; ?>reporte-detalle?id=<?php echo $r['id_reporte']; ?>" class="admin-enlace-accion">Dictaminar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
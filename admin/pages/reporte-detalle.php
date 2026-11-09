<?php
/*
    DETALLE DE REPORTE (/admin/reporte-detalle?id=N)
    Permiso: reportes.ver (Para ver), reportes.resolver (Para guardar dictamen)
*/
$idReporte = (int) ($_GET['id'] ?? 0);
$conexion = conexionBD();

$sql = "SELECT r.*, den.nombre as denunciante, den.correo as correo_den, rep.nombre as reportado, rep.correo as correo_rep 
        FROM reportes_incidencias r
        JOIN usuarios den ON r.id_denunciante = den.id_usuario
        JOIN usuarios rep ON r.id_reportado = rep.id_usuario
        WHERE r.id_reporte = :id";
$sentencia = $conexion->prepare($sql);
$sentencia->execute([':id' => $idReporte]);
$reporte = $sentencia->fetch();

if (!$reporte) {
    guardarMensaje('error', 'Reporte no encontrado.');
    redirigir(URL_ADMIN . 'reportes');
}

$puedeResolver = adminTienePermiso($admin, 'reportes.resolver');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $puedeResolver) {
    if (csrfAdminValido($_POST['csrf'])) {
        $estado = $_POST['estado'];
        $dictamen = limpiarEntrada($_POST['dictamen']);

        $upd = $conexion->prepare("UPDATE reportes_incidencias SET estado = :est, dictamen = :dic, id_admin_asignado = :admin, fecha_actualizacion = NOW() WHERE id_reporte = :id");
        $upd->execute([':est' => $estado, ':dic' => $dictamen, ':admin' => $admin['id_usuario'], ':id' => $idReporte]);

        registrarEnBitacora($admin['correo'], 'reporte_actualizado:'.$idReporte);
        guardarMensaje('exito', 'Dictamen y estado guardados correctamente.');
        redirigir(URL_ADMIN . 'reporte-detalle?id=' . $idReporte);
    }
}
$token = csrfAdminToken();
$mensajeFlash = obtenerMensaje();
?>

<p class="migaja-admin"><a href="<?php echo URL_ADMIN; ?>reportes">&larr; Volver a Reportes</a></p>

<div class="admin-encabezado-pagina" style="margin-bottom: 20px;">
    <div>
        <h1 class="admin-titulo">Reporte #<?php echo $idReporte; ?></h1>
        <p class="admin-subtitulo">Motivo: <strong><?php echo escaparSalida($reporte['motivo_corto']); ?></strong></p>
    </div>
</div>

<?php if ($mensajeFlash): ?><p class="aviso-autenticacion aviso-<?php echo $mensajeFlash['tipo']; ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p><?php endif; ?>

<div class="admin-dos-columnas">
    <!-- Detalles del caso -->
    <section class="admin-bloque">
        <h2>Detalles del conflicto</h2>
        <dl class="admin-datos" style="margin-bottom: 20px;">
            <dt>Denunciante</dt>
            <dd>
                <a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo $reporte['id_denunciante']; ?>"><?php echo escaparSalida($reporte['denunciante']); ?></a> 
                <a href="<?php echo URL_ADMIN; ?>enviar-correo?id=<?php echo $reporte['id_denunciante']; ?>" class="admin-enlace-accion" style="margin-left:10px;">✉️ Contactar</a>
            </dd>
            <dt>Reportado</dt>
            <dd>
                <a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo $reporte['id_reportado']; ?>"><?php echo escaparSalida($reporte['reportado']); ?></a>
                <a href="<?php echo URL_ADMIN; ?>enviar-correo?id=<?php echo $reporte['id_reportado']; ?>" class="admin-enlace-accion" style="margin-left:10px; color:#C0392B;">✉️ Notificar</a>
            </dd>
            <dt>Fecha</dt><dd><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_creacion'])); ?></dd>
            <?php if($reporte['id_cita']): ?><dt>Cita Relacionada</dt><dd>Folio #<?php echo $reporte['id_cita']; ?></dd><?php endif; ?>
        </dl>
        <h3>Descripción proporcionada:</h3>
        <div style="background-color: var(--color-fondo-principal); padding: 15px; border-radius: 8px; font-size: 0.95rem; border: 1px solid var(--color-borde);">
            <?php echo nl2br(escaparSalida($reporte['descripcion'])); ?>
        </div>
    </section>

    <!-- Resolucion -->
    <section class="admin-bloque">
        <h2>Dictamen y Resolución</h2>
        <?php if ($puedeResolver): ?>
            <form method="POST" class="formulario-autenticacion">
                <input type="hidden" name="csrf" value="<?php echo $token; ?>">

                <div class="campo-formulario">
                    <label>Estado del Reporte</label>
                    <select name="estado">
                        <option value="pendiente" <?php echo $reporte['estado']=='pendiente'?'selected':'';?>>Pendiente de atención</option>
                        <option value="revision" <?php echo $reporte['estado']=='revision'?'selected':'';?>>En Revisión (Investigando)</option>
                        <option value="resuelto" <?php echo $reporte['estado']=='resuelto'?'selected':'';?>>Resuelto (Se tomó acción)</option>
                        <option value="desechado" <?php echo $reporte['estado']=='desechado'?'selected':'';?>>Desechado (Sin pruebas / Falso)</option>
                    </select>
                </div>

                <div class="campo-formulario">
                    <label>Dictamen de Administración</label>
                    <textarea name="dictamen" rows="6" placeholder="Escribe las acciones tomadas, ej: Se suspendió al freelancer, se reembolsó, etc."><?php echo escaparSalida($reporte['dictamen']); ?></textarea>
                    <small class="ayuda-campo">Este texto solo es visible para el equipo de administración.</small>
                </div>

                <button type="submit" class="boton-autenticacion admin-boton-accion">Guardar Dictamen</button>
            </form>
        <?php else: ?>
            <p><strong>Estado Actual:</strong> <?php echo ucfirst($reporte['estado']); ?></p>
            <p><strong>Dictamen:</strong><br> <?php echo nl2br(escaparSalida($reporte['dictamen'] ?? 'No se ha redactado dictamen.')); ?></p>
            <p class="ayuda-campo">Tu rol actual no te permite resolver reportes.</p>
        <?php endif; ?>
    </section>
</div>
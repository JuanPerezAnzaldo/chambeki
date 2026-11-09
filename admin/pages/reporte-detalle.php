<?php
/*
    DETALLE DE REPORTE (/admin/reporte-detalle?id=N)
    Permiso: reportes.ver (ver) · reportes.resolver (asignar y dictaminar)
*/
require_once DOCROOT . 'admin/includes/funcionesReportes.php';

$idReporte = (int) ($_GET['id'] ?? 0);
$conexion = conexionBD();

$sql = "SELECT r.*, den.nombre AS denunciante, den.correo AS correo_den, rep.nombre AS reportado, rep.correo AS correo_rep,
               adm.nombre AS asignado
        FROM reportes_incidencias r
        JOIN usuarios den ON r.id_denunciante = den.id_usuario
        JOIN usuarios rep ON r.id_reportado = rep.id_usuario
        LEFT JOIN usuarios adm ON r.id_admin_asignado = adm.id_usuario
        WHERE r.id_reporte = :id";
$sentencia = $conexion->prepare($sql);
$sentencia->execute([':id' => $idReporte]);
$reporte = $sentencia->fetch();

if (!$reporte)
{
    guardarMensaje('error', 'Reporte no encontrado.');
    redirigir(URL_ADMIN . 'reportes');
}

$puedeResolver = adminTienePermiso($admin, 'reportes.resolver');
$urlDetalle = URL_ADMIN . 'reporte-detalle?id=' . $idReporte;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    if (!$puedeResolver)
    {
        guardarMensaje('error', 'Tu rol no permite modificar reportes.');
        redirigir($urlDetalle);
    }

    if (!csrfAdminValido($_POST['csrf'] ?? ''))
    {
        guardarMensaje('error', 'El formulario expiró. Intenta de nuevo.');
        redirigir($urlDetalle);
    }

    $accion = $_POST['accion'] ?? '';

    //--- asignar a un supervisor
    if ($accion === 'asignar')
    {
        $destino = $_POST['id_admin_asignado'] ?? '';

        if ($destino === '')
        {
            $nuevo = null;
        }
        elseif (ctype_digit((string) $destino) && supervisorValido((int) $destino))
        {
            $nuevo = (int) $destino;
        }
        else
        {
            guardarMensaje('error', 'El supervisor seleccionado no es válido.');
            redirigir($urlDetalle);
        }

        //un reporte pendiente que recibe responsable pasa a revision
        $nuevoEstado = ($nuevo !== null && $reporte['estado'] === 'pendiente') ? 'revision' : $reporte['estado'];

        $upd = $conexion->prepare('UPDATE reportes_incidencias SET id_admin_asignado = :asig, estado = :est, fecha_actualizacion = NOW() WHERE id_reporte = :id');
        $upd->execute([':asig' => $nuevo, ':est' => $nuevoEstado, ':id' => $idReporte]);

        registrarEnBitacora($admin['correo'], 'reporte_asignado:' . $idReporte . ':' . ($nuevo ?? 'ninguno'));
        guardarMensaje('exito', $nuevo === null ? 'Se quitó la asignación del reporte.' : 'Reporte asignado correctamente.');
        redirigir($urlDetalle);
    }

    //--- estado + dictamen
    if ($accion === 'dictaminar')
    {
        $estado = (string) ($_POST['estado'] ?? '');
        $dictamen = limpiarEntrada($_POST['dictamen'] ?? '');

        if (!isset(estadosReporte()[$estado]))
        {
            guardarMensaje('error', 'Estado no válido.');
            redirigir($urlDetalle);
        }

        if (in_array($estado, ['resuelto', 'desechado'], true) && mb_strlen($dictamen) < 10)
        {
            guardarMensaje('error', 'Para cerrar el reporte redacta un dictamen (mínimo 10 caracteres).');
            redirigir($urlDetalle);
        }

        if (mb_strlen($dictamen) > 5000)
        {
            guardarMensaje('error', 'El dictamen no puede pasar de 5000 caracteres.');
            redirigir($urlDetalle);
        }

        //si nadie lo tenia asignado, queda a cargo de quien lo dictamina
        $responsable = $reporte['id_admin_asignado'] !== null ? (int) $reporte['id_admin_asignado'] : (int) $admin['id_usuario'];

        $upd = $conexion->prepare('UPDATE reportes_incidencias SET estado = :est, dictamen = :dic, id_admin_asignado = :asig, fecha_actualizacion = NOW() WHERE id_reporte = :id');
        $upd->execute([':est' => $estado, ':dic' => ($dictamen === '' ? null : $dictamen), ':asig' => $responsable, ':id' => $idReporte]);

        registrarEnBitacora($admin['correo'], 'reporte_actualizado:' . $idReporte . ':' . $estado);
        guardarMensaje('exito', 'Dictamen y estado guardados correctamente.');
        redirigir($urlDetalle);
    }
}

$supervisores = listarSupervisoresActivos();

//otros reportes contra la misma cuenta (contexto para el dictamen)
$otros = $conexion->prepare('SELECT COUNT(*) FROM reportes_incidencias WHERE id_reportado = :rep AND id_reporte <> :id');
$otros->execute([':rep' => $reporte['id_reportado'], ':id' => $idReporte]);
$otrosReportes = (int) $otros->fetchColumn();

$token = csrfAdminToken();
$mensajeFlash = obtenerMensaje();
?>

<p class="migaja-admin"><a href="<?php echo URL_ADMIN; ?>reportes">&larr; Volver a Reportes</a></p>

<div class="admin-encabezado-pagina" style="margin-bottom: 20px;">
    <div>
        <h1 class="admin-titulo">Reporte #<?php echo $idReporte; ?>
            <span class="insignia-verificacion <?php echo claseEstadoReporte($reporte['estado']); ?>" style="font-size:0.8rem; vertical-align: middle;"><?php echo escaparSalida(etiquetaEstadoReporte($reporte['estado'])); ?></span>
        </h1>
        <p class="admin-subtitulo">Motivo: <strong><?php echo escaparSalida($reporte['motivo_corto']); ?></strong></p>
    </div>
</div>

<?php if ($mensajeFlash): ?><p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p><?php endif; ?>

<div class="admin-dos-columnas">
    <!-- Detalles del caso -->
    <section class="admin-bloque">
        <h2>Detalles del conflicto</h2>
        <dl class="admin-datos" style="margin-bottom: 20px;">
            <dt>Denunciante</dt>
            <dd>
                <a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo (int) $reporte['id_denunciante']; ?>"><?php echo escaparSalida($reporte['denunciante']); ?></a>
                <a href="<?php echo URL_ADMIN; ?>enviar-correo?id=<?php echo (int) $reporte['id_denunciante']; ?>&amp;plantilla=reporte" class="admin-enlace-accion" style="margin-left:10px;">Contactar</a>
            </dd>
            <dt>Reportado</dt>
            <dd>
                <a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo (int) $reporte['id_reportado']; ?>"><?php echo escaparSalida($reporte['reportado']); ?></a>
                <a href="<?php echo URL_ADMIN; ?>enviar-correo?id=<?php echo (int) $reporte['id_reportado']; ?>&amp;plantilla=reporte" class="admin-enlace-accion" style="margin-left:10px; color:#C0392B;">Notificar</a>
                <?php if ($otrosReportes > 0): ?><br><small><?php echo $otrosReportes; ?> reporte(s) más contra esta cuenta</small><?php endif; ?>
            </dd>
            <dt>Fecha</dt><dd><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_creacion'])); ?></dd>
            <?php if ($reporte['fecha_actualizacion']): ?><dt>Actualizado</dt><dd><?php echo date('d/m/Y H:i', strtotime($reporte['fecha_actualizacion'])); ?></dd><?php endif; ?>
            <?php if ($reporte['id_cita']): ?><dt>Cita relacionada</dt><dd>Folio #<?php echo (int) $reporte['id_cita']; ?></dd><?php endif; ?>
            <dt>Responsable</dt><dd><?php echo escaparSalida($reporte['asignado'] ?? 'Sin asignar'); ?></dd>
        </dl>
        <h3>Descripción proporcionada:</h3>
        <div style="background-color: var(--color-fondo-principal); padding: 15px; border-radius: 8px; font-size: 0.95rem; border: 1px solid var(--color-borde);">
            <?php echo nl2br(escaparSalida($reporte['descripcion'])); ?>
        </div>
    </section>

    <!-- Asignacion y resolucion -->
    <section class="admin-bloque">
        <?php if ($puedeResolver): ?>
            <h2>Asignación</h2>
            <form method="POST" action="<?php echo $urlDetalle; ?>" class="formulario-autenticacion" style="margin-bottom: 26px;">
                <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">
                <input type="hidden" name="accion" value="asignar">

                <div class="campo-formulario">
                    <label for="asignarA">Supervisor responsable</label>
                    <select id="asignarA" name="id_admin_asignado">
                        <option value="">Sin asignar</option>
                        <?php foreach ($supervisores as $s): ?>
                            <option value="<?php echo (int) $s['id_usuario']; ?>" <?php echo (int) $reporte['id_admin_asignado'] === (int) $s['id_usuario'] ? 'selected' : ''; ?>>
                                <?php echo escaparSalida($s['nombre']); ?> (<?php echo escaparSalida(nombreRolAdmin($s['id_rol_admin'])); ?>)<?php echo (int) $s['id_usuario'] === (int) $admin['id_usuario'] ? ' — yo' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="ayuda-campo">Al asignar un reporte pendiente pasa a "En revisión".</small>
                </div>

                <button type="submit" class="boton-autenticacion admin-boton-accion">Guardar asignación</button>
            </form>

            <h2>Dictamen y resolución</h2>
            <form method="POST" action="<?php echo $urlDetalle; ?>" class="formulario-autenticacion">
                <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">
                <input type="hidden" name="accion" value="dictaminar">

                <div class="campo-formulario">
                    <label for="estadoReporte">Estado del reporte</label>
                    <select id="estadoReporte" name="estado">
                        <option value="pendiente" <?php echo $reporte['estado'] === 'pendiente' ? 'selected' : ''; ?>>Pendiente de atención</option>
                        <option value="revision" <?php echo $reporte['estado'] === 'revision' ? 'selected' : ''; ?>>En revisión (investigando)</option>
                        <option value="resuelto" <?php echo $reporte['estado'] === 'resuelto' ? 'selected' : ''; ?>>Resuelto (se tomó acción)</option>
                        <option value="desechado" <?php echo $reporte['estado'] === 'desechado' ? 'selected' : ''; ?>>Desechado (sin pruebas / falso)</option>
                    </select>
                </div>

                <div class="campo-formulario">
                    <label for="dictamenReporte">Dictamen de administración</label>
                    <textarea id="dictamenReporte" name="dictamen" rows="6" maxlength="5000" placeholder="Escribe las acciones tomadas, ej: Se suspendió al freelancer, se reembolsó, etc."><?php echo escaparSalida($reporte['dictamen']); ?></textarea>
                    <small class="ayuda-campo">Obligatorio al marcar como resuelto o desechado. Solo es visible para el equipo de administración.</small>
                </div>

                <button type="submit" class="boton-autenticacion admin-boton-accion">Guardar dictamen</button>
            </form>
        <?php else: ?>
            <h2>Resolución</h2>
            <p><strong>Estado actual:</strong> <?php echo escaparSalida(etiquetaEstadoReporte($reporte['estado'])); ?></p>
            <p><strong>Responsable:</strong> <?php echo escaparSalida($reporte['asignado'] ?? 'Sin asignar'); ?></p>
            <p><strong>Dictamen:</strong><br> <?php echo nl2br(escaparSalida($reporte['dictamen'] ?? 'No se ha redactado dictamen.')); ?></p>
            <p class="ayuda-campo">Tu rol actual no te permite resolver reportes.</p>
        <?php endif; ?>
    </section>
</div>

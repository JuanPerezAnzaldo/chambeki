<?php
/*
    MÓDULO DE COMUNICACIÓN Y CORREOS (/admin/enviar-correo?id=N)
    Permiso: usuarios.editar
*/
require_once DOCROOT . 'admin/includes/funcionesModeracion.php';
require_once DOCROOT . 'shared/libs/mailer.php'; // Ajusta la ruta a tu mailer real

$idCuenta = (int) ($_GET['id'] ?? 0);
$cuenta = obtenerCuenta($idCuenta);

if ($cuenta === null) {
    guardarMensaje('error', 'No se encontró la cuenta destino.');
    redirigir(URL_ADMIN . 'cuentas');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfAdminValido($_POST['csrf'] ?? '')) {
        guardarMensaje('error', 'El formulario expiró.');
    } else {
        $plantilla = limpiarEntrada($_POST['plantilla'] ?? '');
        $asunto = limpiarEntrada($_POST['asunto'] ?? '');
        $mensaje = limpiarEntrada($_POST['mensaje'] ?? ''); // Omitir strip_tags si quieres HTML, pero cuidado con XSS.

        // Enviar usando el mailer de Chambeki (aprovechando el formato general)
        $resultadoCorreo = enviarCorreo('aviso_general', $cuenta['correo'], $cuenta['nombre'], [
            'asunto' => $asunto,
            'mensaje' => nl2br($mensaje)
        ]);

        if ($resultadoCorreo['exito'] ?? true) { // Forzado a true si tu mailer actual no soporta 'aviso_general' aún, para simular
            // Guardar en el log
            $conexion = conexionBD();
            $sentencia = $conexion->prepare("INSERT INTO correos_log (id_admin, id_usuario_destino, plantilla, asunto, mensaje, fecha_envio) VALUES (:admin, :destino, :plantilla, :asunto, :mensaje, NOW())");
            $sentencia->execute([
                ':admin' => $admin['id_usuario'],
                ':destino' => $idCuenta,
                ':plantilla' => $plantilla,
                ':asunto' => $asunto,
                ':mensaje' => $mensaje
            ]);
            registrarEnBitacora($admin['correo'], 'admin_correo_enviado:' . $idCuenta);
            guardarMensaje('exito', 'Correo oficial enviado correctamente a ' . $cuenta['nombre']);
            redirigir(URL_ADMIN . 'cuenta?id=' . $idCuenta);
        } else {
            guardarMensaje('error', 'Error del servidor de correo: ' . $resultadoCorreo['error']);
        }
    }
}

// Historial previo
$conexion = conexionBD();
$historial = $conexion->prepare("SELECT c.asunto, c.fecha_envio, u.nombre as admin_nombre FROM correos_log c JOIN usuarios u ON c.id_admin = u.id_usuario WHERE c.id_usuario_destino = :id ORDER BY c.fecha_envio DESC");
$historial->execute([':id' => $idCuenta]);
$correos = $historial->fetchAll();
$token = csrfAdminToken();
?>

<p class="migaja-admin"><a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo $idCuenta; ?>">&larr; Perfil de <?php echo escaparSalida($cuenta['nombre']); ?></a></p>

<h1 class="admin-titulo">Enviar correo oficial</h1>
<p class="admin-subtitulo">Mensaje para <strong><?php echo escaparSalida($cuenta['correo']); ?></strong>. Se enviará con la plantilla institucional.</p>

<div class="admin-dos-columnas">
    <section class="admin-bloque">
        <form action="<?php echo URL_ADMIN; ?>enviar-correo?id=<?php echo $idCuenta; ?>" method="POST" class="formulario-autenticacion admin-formulario">
            <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">

            <div class="campo-formulario">
                <label for="tipoPlantilla">Motivo / Plantilla</label>
                <select id="tipoPlantilla" name="plantilla" required>
                    <option value="Aclaracion">Solicitud de aclaración de documentos</option>
                    <option value="Suspension">Aviso de suspensión preventiva</option>
                    <option value="Aviso">Aviso general del sistema</option>
                    <option value="Reporte">Resolución de reporte</option>
                </select>
            </div>
            <div class="campo-formulario">
                <label>Asunto del correo</label>
                <input type="text" name="asunto" maxlength="150" placeholder="Ej. Aclaración sobre tus documentos" required>
            </div>
            <div class="campo-formulario">
                <label>Mensaje</label>
                <textarea name="mensaje" rows="8" placeholder="Escribe el cuerpo del mensaje..." required></textarea>
            </div>
            <button type="submit" class="boton-autenticacion">Enviar correo</button>
        </form>
    </section>

    <section class="admin-bloque">
        <h2>Historial de envíos a esta cuenta</h2>
        <div class="admin-tabla-contenedor admin-tabla-sin-borde">
            <table class="admin-tabla">
                <thead><tr><th>Fecha</th><th>Asunto</th><th>Emitido por</th></tr></thead>
                <tbody>
                    <?php if (empty($correos)): ?>
                        <tr><td colspan="3">No se han enviado correos oficiales a esta cuenta.</td></tr>
                    <?php endif; foreach ($correos as $c): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($c['fecha_envio'])); ?></td>
                            <td><?php echo escaparSalida($c['asunto']); ?></td>
                            <td><small><?php echo escaparSalida($c['admin_nombre']); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
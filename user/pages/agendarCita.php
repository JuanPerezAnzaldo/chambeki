<?php
/*
    PROCESO: Solicitar una cita
    Ruta: ?accion=agendar_cita&servicio=ID[&fecha=AAAA-MM-DD]

    El cliente ve los dias y horas libres del freelancer, elige uno y
    envia la solicitud. El freelancer la responde en ?accion=citas.
    El servidor vuelve a validar el hueco al guardar (funcionesCitas.php).
*/

require_once DOCROOT . 'user/includes/funcionesCitas.php';

if (!hayUsuarioEnSesion())
{
    guardarMensaje('error', 'Inicia sesión para agendar una cita.');
    redirigir(URL_BASE . '?accion=login');
}

$idUsuario = (int) $_SESSION['id_usuario'];
$usuario = obtenerUsuarioPorId($idUsuario);

if ($usuario === null)
{
    redirigir(URL_BASE . '?accion=cerrar_sesion');
}

if ((int) $usuario['estatus'] !== ESTATUS_ACTIVO || !in_array((int) $usuario['rol'], [ROL_CLIENTE, ROL_FREELANCER], true))
{
    guardarMensaje('error', 'Esta cuenta no puede agendar citas.');
    redirigir(URL_BASE);
}

$idServicio = (int) ($_GET['servicio'] ?? $_POST['servicio'] ?? 0);
$servicio = obtenerServicioAgendable($idServicio);

if ($servicio === null)
{
    guardarMensaje('error', 'Ese servicio no está disponible para agendar.');
    redirigir(URL_BASE . '?accion=servicios');
}

if ((int) $servicio['id_freelancer'] === $idUsuario)
{
    guardarMensaje('error', 'No puedes agendar un servicio tuyo.');
    redirigir(URL_BASE . '?accion=citas');
}

$errorCita = null;
$fechaElegida = '';
$horaElegida = '';
$nota = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'solicitar_cita')
{
    $fechaElegida = limpiarEntrada($_POST['fecha'] ?? '');
    $horaElegida = limpiarEntrada($_POST['hora'] ?? '');
    $nota = limpiarEntrada($_POST['nota'] ?? '');

    if (!validarCsrf($_POST['token_csrf'] ?? ''))
    {
        $errorCita = 'La sesión del formulario expiró. Vuelve a enviar tu solicitud.';
    }
    else
    {
        $resultado = crearCita($idUsuario, $idServicio, $fechaElegida, $horaElegida, $nota);

        if ($resultado['exito'])
        {
            guardarMensaje('exito', $resultado['mensaje']);
            redirigir(URL_BASE . '?accion=citas');
        }

        $errorCita = $resultado['mensaje'];
    }
}
else
{
    $fechaElegida = limpiarEntrada($_GET['fecha'] ?? '');
}

$horario = obtenerHorarioSemanal($servicio['id_freelancer']);
$dias = calcularDisponibilidad($servicio, $horario);

//dia mostrado: el pedido si tiene huecos; si no, el primero que tenga
if (!isset($dias[$fechaElegida]) || empty($dias[$fechaElegida]['huecos']))
{
    $fechaElegida = '';

    foreach ($dias as $clave => $dia)
    {
        if (!empty($dia['huecos']))
        {
            $fechaElegida = $clave;
            break;
        }
    }
}

$huecosDia = $fechaElegida !== '' ? $dias[$fechaElegida]['huecos'] : [];
$nombreProfesional = nombreProfesionalCita($servicio);
$duracion = duracionCita($servicio);
$urlBaseAgenda = URL_BASE . '?accion=agendar_cita&servicio=' . (int) $idServicio;
$urlPerfil = !empty($servicio['slug'])
    ? URL_BASE . '?accion=profesional&slug=' . rawurlencode($servicio['slug'])
    : URL_BASE . '?accion=profesional&id=' . (int) $servicio['id_freelancer'];

$textoPrecio = (int) $servicio['id_tipo_precio'] === 3
    ? 'Previa cotización'
    : $servicio['tipo_precio'] . ' $' . number_format((float) $servicio['monto'], fmod((float) $servicio['monto'], 1) == 0 ? 0 : 2);

$mesesCortos = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$diasCortos = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
?>

<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>citas.css?v=1">

<section class="seccion-citas">
    <div class="contenedor-citas">

        <a href="<?php echo escaparSalida($urlPerfil); ?>" class="cita-volver">&larr; Volver al perfil</a>

        <header class="cita-encabezado">
            <h1 class="cita-titulo-pagina">Agendar: <?php echo escaparSalida($servicio['titulo']); ?></h1>
            <p class="cita-subtitulo">
                Con <strong><?php echo escaparSalida($nombreProfesional); ?></strong>.
                Duración aproximada: <?php echo (int) $duracion; ?> min. <?php echo escaparSalida($textoPrecio); ?>.
            </p>
        </header>

        <?php if ($errorCita !== null): ?>
            <p class="cita-aviso cita-aviso-error" role="alert"><?php echo escaparSalida($errorCita); ?></p>
        <?php endif; ?>

        <?php if (empty($horario)): ?>

            <div class="cita-panel cita-vacio">
                <h2>Este profesional aún no publica su horario</h2>
                <p>Cuando configure sus días de atención podrás elegir una hora aquí.</p>
            </div>

        <?php else: ?>

            <form action="<?php echo escaparSalida($urlBaseAgenda); ?>" method="POST" class="cita-panel">
                <input type="hidden" name="operacion" value="solicitar_cita">
                <input type="hidden" name="token_csrf" value="<?php echo escaparSalida(tokenCsrf()); ?>">
                <input type="hidden" name="servicio" value="<?php echo (int) $idServicio; ?>">
                <input type="hidden" name="fecha" value="<?php echo escaparSalida($fechaElegida); ?>">

                <h2 class="cita-paso">Elige el día</h2>
                <div class="cita-dias" role="list">
                    <?php foreach ($dias as $clave => $dia): ?>
                        <?php
                            $fecha = $dia['fecha'];
                            $hayHuecos = !empty($dia['huecos']);
                            $clases = 'cita-dia' . ($clave === $fechaElegida ? ' activo' : '') . ($hayHuecos ? '' : ' sin-huecos');
                            $contenido = '<span>' . $diasCortos[(int) $fecha->format('N')] . '</span><strong>' . $fecha->format('j') . '</strong><span>' . $mesesCortos[(int) $fecha->format('n')] . '</span>';
                        ?>
                        <?php if ($hayHuecos): ?>
                            <a href="<?php echo escaparSalida($urlBaseAgenda . '&fecha=' . $clave); ?>" class="<?php echo $clases; ?>" role="listitem"<?php echo $clave === $fechaElegida ? ' aria-current="date"' : ''; ?>><?php echo $contenido; ?></a>
                        <?php else: ?>
                            <span class="<?php echo $clases; ?>" role="listitem" aria-disabled="true"><?php echo $contenido; ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($fechaElegida === ''): ?>

                    <p class="cita-vacio-texto">No hay horarios libres en los próximos <?php echo CITA_DIAS_MAXIMOS; ?> días. Vuelve más tarde.</p>

                <?php else: ?>

                    <h2 class="cita-paso">Elige la hora</h2>
                    <div class="cita-horas">
                        <?php foreach ($huecosDia as $hueco): ?>
                            <label class="cita-hora">
                                <input type="radio" name="hora" value="<?php echo escaparSalida($hueco); ?>" <?php echo $hueco === $horaElegida ? 'checked' : ''; ?> required>
                                <span><?php echo escaparSalida($hueco); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <h2 class="cita-paso">Mensaje para el profesional</h2>
                    <div class="cita-campo">
                        <label for="notaCita">Cuéntale qué necesitas (opcional)</label>
                        <textarea id="notaCita" name="nota" rows="3" maxlength="<?php echo CITA_NOTA_MAX; ?>" placeholder="Dirección, detalles del problema o lo que sea útil saber."><?php echo escaparSalida($nota); ?></textarea>
                    </div>

                    <button type="submit" class="boton-cita boton-cita-principal boton-cita-ancho">Solicitar cita</button>
                    <p class="cita-ayuda">El profesional tiene que aceptar tu solicitud. No se hace ningún cobro al agendar.</p>

                <?php endif; ?>
            </form>

            <section class="cita-panel" aria-labelledby="tituloHorarioSemanal">
                <h2 class="cita-paso" id="tituloHorarioSemanal">Horario de atención</h2>
                <ul class="cita-semana">
                    <?php for ($d = 1; $d <= 7; $d++): ?>
                        <li>
                            <span><?php echo nombreDiaSemana($d); ?></span>
                            <span><?php echo isset($horario[$d]) ? horaDesdeMinutos($horario[$d]['a']) . ' a ' . horaDesdeMinutos($horario[$d]['c']) : 'Cerrado'; ?></span>
                        </li>
                    <?php endfor; ?>
                </ul>
            </section>

        <?php endif; ?>

    </div>
</section>
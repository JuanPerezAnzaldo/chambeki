<?php
/*
    PANEL: Citas
    Ruta: ?accion=citas  (el enlace "Citas" de la barra movil ya apunta aqui)

    - Freelancer: ve las solicitudes que le llegan y las acepta o rechaza.
    - Todos: ven las citas que ellos mismos solicitaron y pueden cancelarlas.
    Todo se consulta con el id_usuario de la sesion (RSIS-01).
*/

require_once DOCROOT . 'user/includes/funcionesCitas.php';

if (!hayUsuarioEnSesion())
{
    guardarMensaje('error', 'Inicia sesión para ver tus citas.');
    redirigir(URL_BASE . '?accion=login');
}

$idUsuario = (int) $_SESSION['id_usuario'];
$usuario = obtenerUsuarioPorId($idUsuario);

if ($usuario === null)
{
    redirigir(URL_BASE . '?accion=cerrar_sesion');
}

$esFreelancer = (int) $usuario['rol'] === ROL_FREELANCER;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = $_POST['operacion'] ?? '';
    $idCita = (int) ($_POST['id_cita'] ?? 0);
    $motivo = limpiarEntrada($_POST['motivo'] ?? '');
    $resultado = null;

    if (!validarCsrf($_POST['token_csrf'] ?? ''))
    {
        $resultado = ['exito' => false, 'mensaje' => 'La sesión del formulario expiró. Intenta de nuevo.'];
    }
    elseif ($operacion === 'aceptar_cita' && $esFreelancer)
    {
        $resultado = responderCita($idUsuario, $idCita, true, '');
    }
    elseif ($operacion === 'rechazar_cita' && $esFreelancer)
    {
        $resultado = responderCita($idUsuario, $idCita, false, $motivo);
    }
    elseif ($operacion === 'cancelar_cita')
    {
        $resultado = cancelarCita($idUsuario, $idCita, $motivo);
    }

    if ($resultado !== null)
    {
        guardarMensaje($resultado['exito'] ? 'exito' : 'error', $resultado['mensaje']);
    }

    redirigir(URL_BASE . '?accion=citas');
}

$ahora = ahoraCitas();
$tokenCsrf = tokenCsrf();
$mensajeFlash = obtenerMensaje();

//citas recibidas (solo freelancers): por responder, confirmadas y el resto
$porResponder = [];
$confirmadas = [];
$historialRecibidas = [];

if ($esFreelancer)
{
    foreach (array_reverse(listarCitasFreelancer($idUsuario)) as $cita)
    {
        if (citaVigente($cita, $ahora) && (int) $cita['estado_cita'] === CITA_PENDIENTE)
        {
            $porResponder[] = $cita;
        }
        elseif (citaVigente($cita, $ahora))
        {
            $confirmadas[] = $cita;
        }
        else
        {
            array_unshift($historialRecibidas, $cita);
        }
    }
}

//citas que el usuario pidio como cliente
$proximasMias = [];
$historialMias = [];

foreach (array_reverse(listarCitasCliente($idUsuario)) as $cita)
{
    if (citaVigente($cita, $ahora))
    {
        $proximasMias[] = $cita;
    }
    else
    {
        array_unshift($historialMias, $cita);
    }
}
?>

<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>citas.css?v=1">

<section class="seccion-citas">
    <div class="contenedor-citas">

        <h1 class="cita-titulo-pagina">Mis citas</h1>

        <?php if ($mensajeFlash !== null): ?>
            <p class="cita-aviso cita-aviso-<?php echo $mensajeFlash['tipo'] === 'exito' ? 'exito' : 'error'; ?>" role="status"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
        <?php endif; ?>

        <?php if ($esFreelancer): ?>

            <section class="cita-bloque" aria-labelledby="tituloPorResponder">
                <h2 id="tituloPorResponder" class="cita-subtitulo-bloque">Solicitudes por responder (<?php echo count($porResponder); ?>)</h2>
                <?php if (empty($porResponder)): ?>
                    <p class="cita-vacio-texto">No tienes solicitudes pendientes.</p>
                <?php endif; ?>
                <?php foreach ($porResponder as $cita): ?>
                    <?php pintarTarjetaCita($cita, 'freelancer', $tokenCsrf); ?>
                <?php endforeach; ?>
            </section>

            <?php if (!empty($confirmadas)): ?>
                <section class="cita-bloque" aria-labelledby="tituloConfirmadas">
                    <h2 id="tituloConfirmadas" class="cita-subtitulo-bloque">Próximas citas confirmadas</h2>
                    <?php foreach ($confirmadas as $cita): ?>
                        <?php pintarTarjetaCita($cita, 'freelancer', $tokenCsrf); ?>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

            <?php if (!empty($historialRecibidas)): ?>
                <section class="cita-bloque" aria-labelledby="tituloHistorialRecibidas">
                    <h2 id="tituloHistorialRecibidas" class="cita-subtitulo-bloque">Historial de solicitudes recibidas</h2>
                    <?php foreach ($historialRecibidas as $cita): ?>
                        <?php pintarTarjetaCita($cita, 'freelancer', $tokenCsrf); ?>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>

        <?php endif; ?>

        <section class="cita-bloque" aria-labelledby="tituloMisCitas">
            <h2 id="tituloMisCitas" class="cita-subtitulo-bloque"><?php echo $esFreelancer ? 'Citas que solicité a otros profesionales' : 'Mis solicitudes'; ?></h2>

            <?php if (empty($proximasMias) && empty($historialMias)): ?>
                <div class="cita-panel cita-vacio">
                    <h3>Aún no has agendado ninguna cita</h3>
                    <p>Busca un profesional, entra a su perfil y elige el servicio que necesitas.</p>
                    <a href="<?php echo URL_BASE; ?>?accion=servicios" class="boton-cita boton-cita-principal">Buscar profesionales</a>
                </div>
            <?php endif; ?>

            <?php foreach ($proximasMias as $cita): ?>
                <?php pintarTarjetaCita($cita, 'cliente', $tokenCsrf); ?>
            <?php endforeach; ?>

            <?php if (!empty($historialMias)): ?>
                <h3 class="cita-subtitulo-historial">Historial</h3>
                <?php foreach ($historialMias as $cita): ?>
                    <?php pintarTarjetaCita($cita, 'cliente', $tokenCsrf); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

    </div>
</section>
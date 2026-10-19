<?php
/*
    PROCESO: Perfil del usuario con Métodos de Pago
    Requerimientos: RSIS-01, RS-01, RS-02, RS-05
*/

require_once DOCROOT . 'user/includes/funcionesPerfil.php';
require_once DOCROOT . 'user/includes/funcionesTarjetas.php';

if (!hayUsuarioEnSesion())
{
    guardarMensaje('error', 'Inicia sesion para ver tu perfil.');
    redirigir(URL_BASE . '?accion=login');
}

$usuario = obtenerUsuarioPorId($_SESSION['id_usuario']);

if ($usuario === null)
{
    session_unset();
    session_destroy();
    session_start();

    guardarMensaje('error', 'No encontramos tu cuenta. Inicia sesion de nuevo.');
    redirigir(URL_BASE . '?accion=login');
}

$idUsuario = (int) $usuario['id_usuario'];
$urlPerfil = URL_BASE . '?accion=perfil';
$urlTarjetas = $urlPerfil . '#metodos-pago';
$pagosDisponibles = pagosConTarjetaDisponibles();

//-------------------------------------------------------------
// Acciones POST sobre tarjetas
//-------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = $_POST['operacion'] ?? '';

    if (!validarCsrf($_POST['token_csrf'] ?? ''))
    {
        guardarMensaje('error', 'La sesion del formulario expiro. Intenta de nuevo.');
        redirigir(in_array($operacion, ['suscribirse', 'cancelar_suscripcion'], true) ? $urlPerfil : $urlTarjetas);
    }

    //Suscripcion: no depende de los pagos con tarjeta
    if ($operacion === 'suscribirse')
    {
        try
        {
            if (usuarioEstaSuscrito($idUsuario))
            {
                guardarMensaje('exito', 'Ya eres suscriptor.');
            }
            else
            {
                activarSuscripcion($idUsuario);
                guardarMensaje('exito', '¡Listo! Ahora eres suscriptor.');
            }
        }
        catch (\Throwable $excepcion)
        {
            error_log('Error al suscribir al usuario: ' . $excepcion->getMessage());
            guardarMensaje('error', 'No pudimos activar tu suscripcion. Intenta de nuevo en unos minutos.');
        }

        redirigir($urlPerfil);
    }

    if ($operacion === 'cancelar_suscripcion')
    {
        try
        {
            cancelarSuscripcion($idUsuario);
            guardarMensaje('exito', 'Cancelaste tu suscripcion.');
        }
        catch (\Throwable $excepcion)
        {
            error_log('Error al cancelar la suscripcion: ' . $excepcion->getMessage());
            guardarMensaje('error', 'No pudimos cancelar tu suscripcion. Intenta de nuevo en unos minutos.');
        }

        redirigir($urlPerfil);
    }

    if (!$pagosDisponibles)
    {
        guardarMensaje('error', 'Los pagos con tarjeta no estan disponibles por el momento.');
        redirigir($urlTarjetas);
    }

    $idTarjeta = (int) ($_POST['id_tarjeta'] ?? 0);

    try
    {
        switch ($operacion)
        {
            case 'guardar_tarjeta':
            {
                $resultado = guardarTarjetaDesdeSetupIntent($usuario, limpiarEntrada($_POST['setup_intent'] ?? ''));
                break;
            }

            case 'editar_tarjeta':
            {
                $resultado = actualizarVencimientoTarjeta($usuario, $idTarjeta, (int) ($_POST['exp_mes'] ?? 0), (int) ($_POST['exp_anio'] ?? 0));
                break;
            }

            case 'predeterminar_tarjeta':
            {
                $resultado = establecerTarjetaPredeterminada($usuario, $idTarjeta);
                break;
            }

            case 'eliminar_tarjeta':
            {
                $resultado = eliminarTarjetaUsuario($usuario, $idTarjeta);
                break;
            }

            default:
            {
                $resultado = ['exito' => false, 'mensaje' => 'No reconocimos esa accion.'];
            }
        }

        guardarMensaje($resultado['exito'] ? 'exito' : 'error', $resultado['mensaje']);
    }
    catch (\Throwable $excepcion)
    {
        error_log('Error en tarjetas del perfil: ' . $excepcion->getMessage());
        guardarMensaje('error', 'No pudimos completar la accion con tu tarjeta. Intenta de nuevo en unos minutos.');
    }

    redirigir($urlTarjetas);
}

//-------------------------------------------------------------
// Datos para la vista
//-------------------------------------------------------------
$mensajeFlash = obtenerMensaje();
$token = tokenCsrf();

$esFreelancer = (int) $usuario['rol'] === ROL_FREELANCER;
$perfilFreelancer = $esFreelancer ? obtenerPerfilFreelancer($idUsuario) : null;

$tarjetas = obtenerTarjetas($idUsuario);
$limiteTarjetas = count($tarjetas) >= MAXIMO_TARJETAS_USUARIO;
$anioActual = (int) date('Y');
$cuentaActiva = (int) $usuario['estatus'] === ESTATUS_ACTIVO;
$estaSuscrito = usuarioEstaSuscrito($idUsuario);
$inicialNombre = mb_strtoupper(mb_substr($usuario['nombre'], 0, 1));
?>

<section class="seccion-perfil">
    <div class="contenedor-perfil">

        <?php if ($mensajeFlash !== null): ?>
            <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>" role="status"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
        <?php endif; ?>

        <!-- ============ Identidad ============ -->
        <div class="tarjeta-perfil tarjeta-identidad">
            <div class="banner-perfil" aria-hidden="true"></div>

            <div class="cabecera-perfil">
                <?php if (!empty($usuario['foto_perfil_url'])): ?>
                    <img src="<?php echo URL_BASE . escaparSalida($usuario['foto_perfil_url']); ?>" alt="Foto de perfil de <?php echo escaparSalida($usuario['nombre']); ?>" class="foto-perfil-grande">
                <?php else: ?>
                    <div class="foto-perfil-grande foto-perfil-iniciales" aria-hidden="true">
                        <?php echo escaparSalida($inicialNombre); ?>
                    </div>
                <?php endif; ?>

                <div class="identidad-perfil">
                    <h1 class="nombre-perfil"><?php echo escaparSalida($usuario['nombre']); ?></h1>

                    <div class="etiquetas-perfil">
                        <span class="etiqueta-rol-perfil"><?php echo escaparSalida(nombreRol($usuario['rol'])); ?></span>
                        <span class="etiqueta-estado <?php echo $cuentaActiva ? 'estado-activo' : 'estado-alerta'; ?>">
                            <?php echo $cuentaActiva ? 'Cuenta activa' : 'Cuenta suspendida'; ?>
                        </span>
                        <span class="etiqueta-estado <?php echo $estaSuscrito ? 'estado-activo' : 'estado-alerta'; ?>">
                            <?php echo $estaSuscrito ? 'Suscrito' : 'No suscrito'; ?>
                        </span>
                        <?php if ($esFreelancer): ?>
                            <span class="etiqueta-estado <?php echo !empty($perfilFreelancer['perfil_verificado']) ? 'estado-activo' : 'estado-pendiente'; ?>">
                                <?php echo !empty($perfilFreelancer['perfil_verificado']) ? 'Perfil verificado' : 'Verificación pendiente'; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <a href="<?php echo URL_BASE; ?>?accion=configuracion" class="boton-autenticacion boton-perfil-enlace boton-editar-perfil">Editar mi perfil</a>
            </div>
        </div>

        <div class="rejilla-perfil">

            <!-- ============ Datos de la cuenta ============ -->
            <section class="tarjeta-perfil" aria-labelledby="tituloDatos">
                <h2 class="titulo-seccion-perfil" id="tituloDatos">Datos de la cuenta</h2>

                <dl class="lista-datos-perfil">
                    <div class="dato-perfil">
                        <dt>
                            <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg></span>
                            Correo electrónico
                        </dt>
                        <dd><?php echo escaparSalida($usuario['correo']); ?></dd>
                    </div>

                    <div class="dato-perfil">
                        <dt>
                            <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 4 5a2 2 0 0 1 2-2z"/></svg></span>
                            Teléfono
                        </dt>
                        <dd><?php echo ($usuario['telefono'] !== null && $usuario['telefono'] !== '') ? escaparSalida($usuario['telefono']) : 'No registrado'; ?></dd>
                    </div>

                    <div class="dato-perfil">
                        <dt>
                            <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg></span>
                            Tipo de cuenta
                        </dt>
                        <dd><?php echo escaparSalida(nombreTipoCuenta($usuario['tipo_cuenta'])); ?></dd>
                    </div>

                    <div class="dato-perfil">
                        <dt>
                            <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3 4 6v6c0 4.5 3.2 8 8 9 4.8-1 8-4.5 8-9V6l-8-3z"/><path d="m9 12 2 2 4-4"/></svg></span>
                            Estatus de la cuenta
                        </dt>
                        <dd><?php echo escaparSalida(nombreEstatus($usuario['estatus'])); ?></dd>
                    </div>

                    <div class="dato-perfil">
                        <dt>
                            <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/></svg></span>
                            Miembro desde
                        </dt>
                        <dd><?php echo escaparSalida(date('d/m/Y', strtotime($usuario['fecha_registro']))); ?></dd>
                    </div>
                </dl>
            </section>

            <!-- ============ Accesos rápidos ============ -->
            <section class="tarjeta-perfil" aria-labelledby="tituloAccesos">
                <h2 class="titulo-seccion-perfil" id="tituloAccesos">Accesos rápidos</h2>

                <nav class="lista-accesos" aria-label="Accesos rápidos del perfil">
                    <a href="<?php echo URL_BASE; ?>?accion=configuracion#foto" class="acceso-perfil">
                        <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 8a2 2 0 0 1 2-2h2l1.5-2h5L16 6h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><circle cx="12" cy="13" r="3.5"/></svg></span>
                        <span class="texto-acceso">Cambiar foto de perfil</span>
                        <svg class="flecha-acceso" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </a>

                    <a href="<?php echo URL_BASE; ?>?accion=recuperar" class="acceso-perfil">
                        <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg></span>
                        <span class="texto-acceso">Cambiar mi contraseña</span>
                        <svg class="flecha-acceso" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </a>

                    <a href="#metodos-pago" class="acceso-perfil">
                        <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg></span>
                        <span class="texto-acceso">Mis métodos de pago</span>
                        <svg class="flecha-acceso" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </a>

                    <?php if ($estaSuscrito): ?>
                    <button type="button" class="acceso-perfil acceso-boton" data-abrir-dialogo="dlgCancelarSuscripcion">
                        <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg></span>
                        <span class="texto-acceso">Cancelar Suscripción</span>
                        <svg class="flecha-acceso" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                    <?php else: ?>
                    <button type="button" class="acceso-perfil acceso-boton" data-abrir-dialogo="dlgSuscripcion">
                        <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 3 2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.5 6.6 19.5l1.2-6L3.3 9.3l6.1-.7z"/></svg></span>
                        <span class="texto-acceso">Beneficios de Suscriptor</span>
                        <svg class="flecha-acceso" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                    </button>
                    <?php endif; ?>

                    <a href="<?php echo URL_BASE; ?>?accion=cerrar_sesion" class="acceso-perfil acceso-salir">
                        <span class="icono-dato" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></span>
                        <span class="texto-acceso">Cerrar sesión</span>
                    </a>
                </nav>
            </section>

        </div>

        <!-- ============ Métodos de pago ============ -->
        <section class="tarjeta-perfil" id="metodos-pago" aria-labelledby="tituloPagos"
                 data-clave-stripe="<?php echo $pagosDisponibles ? escaparSalida(clavePublicaStripe()) : ''; ?>"
                 data-url-intent="<?php echo escaparSalida(URL_BASE . '?accion=tarjeta-intent'); ?>"
                 data-token-csrf="<?php echo escaparSalida($token); ?>">

            <div class="encabezado-pagos">
                <div>
                    <h2 class="titulo-seccion-perfil" id="tituloPagos">Métodos de pago</h2>
                    <p class="descripcion-pagos">Guarda tus tarjetas de crédito o débito para pagar tus servicios más rápido.</p>
                </div>

                <?php if ($pagosDisponibles && !$limiteTarjetas): ?>
                    <button type="button" class="boton-pago boton-pago-principal" data-abrir-dialogo="dlgAgregarTarjeta">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Agregar tarjeta
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!$pagosDisponibles): ?>
                <p class="aviso-autenticacion aviso-error">Los pagos con tarjeta no están disponibles por el momento. Intenta más tarde.</p>
            <?php endif; ?>

            <?php if (empty($tarjetas)): ?>
                <div class="estado-vacio-pagos">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>
                    <p><strong>Aún no tienes tarjetas guardadas</strong></p>
                    <p>Agrega una para que pagar tus citas sea cuestión de un toque.</p>
                </div>
            <?php else: ?>
                <div class="rejilla-tarjetas">
                    <?php foreach ($tarjetas as $tarjeta):
                        $vencida = tarjetaVencida($tarjeta['exp_mes'], $tarjeta['exp_anio']);
                        $esPredeterminado = (int) $tarjeta['es_predeterminado'] === 1;
                        $nombreMarca = nombreMarcaTarjeta($tarjeta['marca']);
                        $clasesTarjeta = 'tarjeta-pago marca-' . claseMarcaTarjeta($tarjeta['marca'])
                            . ($esPredeterminado ? ' es-predeterminada' : '')
                            . ($vencida ? ' esta-vencida' : '');
                    ?>
                        <article class="<?php echo $clasesTarjeta; ?>">
                            <div class="tarjeta-pago-visual">
                                <div class="tp-fila">
                                    <span class="tp-chip" aria-hidden="true"></span>
                                    <span class="tp-marca" aria-hidden="true"><?php echo escaparSalida(claseMarcaTarjeta($tarjeta['marca']) === 'generica' ? 'TARJETA' : $nombreMarca); ?></span>
                                </div>

                                <p class="tp-numero" aria-hidden="true">•••• •••• •••• <?php echo escaparSalida($tarjeta['ultimos_4_digitos']); ?></p>

                                <div class="tp-fila">
                                    <div class="tp-vence" aria-hidden="true">
                                        <small>Vence</small>
                                        <strong><?php echo escaparSalida(formatoVencimiento($tarjeta['exp_mes'], $tarjeta['exp_anio'])); ?></strong>
                                    </div>

                                    <?php if ($vencida): ?>
                                        <span class="tp-insignia tp-insignia-vencida">Vencida</span>
                                    <?php elseif ($esPredeterminado): ?>
                                        <span class="tp-insignia">Predeterminada</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="tarjeta-pago-pie">
                                <p class="tp-descripcion">
                                    <?php echo escaparSalida($nombreMarca); ?> terminada en <?php echo escaparSalida($tarjeta['ultimos_4_digitos']); ?>
                                    <span class="sr-solo">, vence <?php echo escaparSalida(formatoVencimiento($tarjeta['exp_mes'], $tarjeta['exp_anio'])); ?><?php echo $esPredeterminado ? ', predeterminada' : ''; ?><?php echo $vencida ? ', vencida' : ''; ?></span>
                                </p>

                                <div class="tp-acciones">
                                    <?php if (!$esPredeterminado && !$vencida): ?>
                                        <form action="<?php echo escaparSalida($urlPerfil); ?>" method="POST">
                                            <input type="hidden" name="operacion" value="predeterminar_tarjeta">
                                            <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
                                            <input type="hidden" name="id_tarjeta" value="<?php echo (int) $tarjeta['id_tarjeta']; ?>">
                                            <button type="submit" class="boton-pago boton-pago-texto">Predeterminar</button>
                                        </form>
                                    <?php endif; ?>

                                    <button type="button" class="boton-pago boton-pago-texto"
                                            data-abrir-edicion
                                            data-id="<?php echo (int) $tarjeta['id_tarjeta']; ?>"
                                            data-marca="<?php echo escaparSalida($nombreMarca); ?>"
                                            data-clase-marca="<?php echo escaparSalida(claseMarcaTarjeta($tarjeta['marca'])); ?>"
                                            data-ultimos4="<?php echo escaparSalida($tarjeta['ultimos_4_digitos']); ?>"
                                            data-mes="<?php echo (int) $tarjeta['exp_mes']; ?>"
                                            data-anio="<?php echo (int) $tarjeta['exp_anio']; ?>"
                                            <?php echo $pagosDisponibles ? '' : 'disabled'; ?>>Editar</button>

                                    <button type="button" class="boton-pago boton-pago-texto boton-pago-peligro"
                                            data-abrir-eliminacion
                                            data-id="<?php echo (int) $tarjeta['id_tarjeta']; ?>"
                                            data-marca="<?php echo escaparSalida($nombreMarca); ?>"
                                            data-ultimos4="<?php echo escaparSalida($tarjeta['ultimos_4_digitos']); ?>"
                                            <?php echo $pagosDisponibles ? '' : 'disabled'; ?>>Eliminar</button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($limiteTarjetas): ?>
                    <p class="nota-limite-pagos">Llegaste al máximo de <?php echo MAXIMO_TARJETAS_USUARIO; ?> tarjetas. Elimina una para agregar otra.</p>
                <?php endif; ?>
            <?php endif; ?>

            <p class="nota-seguridad-pagos">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <span>Tus datos de pago los procesa <strong>Stripe</strong> y se guardan cifrados. CHAMBEKI nunca almacena el número completo de tu tarjeta ni el código de seguridad.</span>
            </p>
        </section>

    </div>
</section>

<!-- ============ Dialog: Beneficios de Suscriptor ============ -->
<dialog class="dialogo-tarjeta" id="dlgSuscripcion" aria-labelledby="tituloSuscripcion">
    <form action="<?php echo escaparSalida($urlPerfil); ?>" method="POST" novalidate>
        <input type="hidden" name="operacion" value="suscribirse">
        <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

        <header class="dialogo-cabecera">
            <h3 id="tituloSuscripcion">Beneficios de Suscriptor</h3>
            <button type="button" class="dialogo-cerrar" data-cerrar-dialogo aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="dialogo-cuerpo">
            <p class="texto-dialogo">Beneficios de suscriptor</p>
        </div>

        <footer class="dialogo-pie">
            <button type="button" class="boton-pago boton-pago-secundario" data-cerrar-dialogo>No gracias</button>
            <button type="submit" class="boton-pago boton-pago-principal">Suscribirse</button>
        </footer>
    </form>
</dialog>

<!-- ============ Dialog: Cancelar suscripción ============ -->
<dialog class="dialogo-tarjeta dialogo-tarjeta-chico" id="dlgCancelarSuscripcion" aria-labelledby="tituloCancelarSuscripcion">
    <form action="<?php echo escaparSalida($urlPerfil); ?>" method="POST" novalidate>
        <input type="hidden" name="operacion" value="cancelar_suscripcion">
        <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

        <header class="dialogo-cabecera">
            <h3 id="tituloCancelarSuscripcion">Cancelar suscripción</h3>
            <button type="button" class="dialogo-cerrar" data-cerrar-dialogo aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="dialogo-cuerpo">
            <p class="texto-dialogo">¿Quieres cancelar tu suscripción? Tu perfil volverá a mostrarse como <strong>No suscrito</strong>.</p>
        </div>

        <footer class="dialogo-pie">
            <button type="button" class="boton-pago boton-pago-secundario" data-cerrar-dialogo>Mantener</button>
            <button type="submit" class="boton-pago boton-pago-peligro-solido">Sí, cancelar</button>
        </footer>
    </form>
</dialog>

<?php if ($pagosDisponibles): ?>
<!-- ============ Dialogs (Agregar / Editar / Eliminar Tarjeta) ============ -->

<dialog class="dialogo-tarjeta" id="dlgAgregarTarjeta" aria-labelledby="tituloAgregarTarjeta">
    <form id="formAgregarTarjeta" action="<?php echo escaparSalida($urlPerfil); ?>" method="POST" novalidate>
        <input type="hidden" name="operacion" value="guardar_tarjeta">
        <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
        <input type="hidden" name="setup_intent" id="campoSetupIntent" value="">

        <header class="dialogo-cabecera">
            <h3 id="tituloAgregarTarjeta">Agregar tarjeta</h3>
            <button type="button" class="dialogo-cerrar" data-cerrar-dialogo aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="dialogo-cuerpo">
            <div class="tarjeta-pago tarjeta-vista-previa marca-generica" id="vistaPreviaTarjeta" aria-hidden="true">
                <div class="tarjeta-pago-visual">
                    <div class="tp-fila">
                        <span class="tp-chip"></span>
                        <span class="tp-marca" id="vistaPreviaMarca">TARJETA</span>
                    </div>
                    <p class="tp-numero">•••• •••• •••• ••••</p>
                    <div class="tp-fila">
                        <div class="tp-vence"><small>Titular</small><strong id="vistaPreviaTitular">NOMBRE APELLIDO</strong></div>
                    </div>
                </div>
            </div>

            <div class="formulario-autenticacion">
                <div class="campo-formulario">
                    <label for="titularTarjeta">Nombre en la tarjeta</label>
                    <input type="text" id="titularTarjeta" maxlength="60" autocomplete="cc-name" placeholder="Como aparece en tu tarjeta">
                </div>

                <div class="campo-formulario">
                    <label for="numeroTarjeta">Número de tarjeta</label>
                    <div class="campo-stripe" id="numeroTarjeta"></div>
                </div>

                <div class="fila-campos">
                    <div class="campo-formulario">
                        <label for="vencimientoTarjeta">Vencimiento</label>
                        <div class="campo-stripe" id="vencimientoTarjeta"></div>
                    </div>

                    <div class="campo-formulario">
                        <label for="cvcTarjeta">CVC</label>
                        <div class="campo-stripe" id="cvcTarjeta"></div>
                    </div>
                </div>

                <span class="error-campo" id="errorTarjeta" role="alert" hidden></span>
                <small class="ayuda-campo">Aceptamos tarjetas de crédito y débito. Tu banco puede pedirte confirmar la tarjeta.</small>
            </div>
        </div>

        <footer class="dialogo-pie">
            <button type="button" class="boton-pago boton-pago-secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton-pago boton-pago-principal" id="botonGuardarTarjeta">Guardar tarjeta</button>
        </footer>
    </form>
</dialog>

<dialog class="dialogo-tarjeta" id="dlgEditarTarjeta" aria-labelledby="tituloEditarTarjeta">
    <form action="<?php echo escaparSalida($urlPerfil); ?>" method="POST" novalidate>
        <input type="hidden" name="operacion" value="editar_tarjeta">
        <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
        <input type="hidden" name="id_tarjeta" id="editarIdTarjeta" value="">

        <header class="dialogo-cabecera">
            <h3 id="tituloEditarTarjeta">Editar tarjeta</h3>
            <button type="button" class="dialogo-cerrar" data-cerrar-dialogo aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="dialogo-cuerpo">
            <div class="tarjeta-pago tarjeta-vista-previa marca-generica" id="editarVistaPrevia" aria-hidden="true">
                <div class="tarjeta-pago-visual">
                    <div class="tp-fila">
                        <span class="tp-chip"></span>
                        <span class="tp-marca" id="editarMarca">TARJETA</span>
                    </div>
                    <p class="tp-numero" id="editarNumero">•••• •••• •••• ••••</p>
                    <div class="tp-fila">
                        <div class="tp-vence"><small>Vence</small><strong id="editarVence">MM/AA</strong></div>
                    </div>
                </div>
            </div>

            <div class="formulario-autenticacion">
                <div class="fila-campos">
                    <div class="campo-formulario">
                        <label for="editarMes">Mes</label>
                        <select id="editarMes" name="exp_mes" required>
                            <?php for ($mes = 1; $mes <= 12; $mes++): ?>
                                <option value="<?php echo $mes; ?>"><?php echo str_pad((string) $mes, 2, '0', STR_PAD_LEFT); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="campo-formulario">
                        <label for="editarAnio">Año</label>
                        <select id="editarAnio" name="exp_anio" required>
                            <?php for ($anio = $anioActual; $anio <= $anioActual + ANIOS_VIGENCIA_TARJETA; $anio++): ?>
                                <option value="<?php echo $anio; ?>"><?php echo $anio; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <small class="ayuda-campo">Por seguridad el número y el CVC no se pueden cambiar. Si tu tarjeta es otra, agrégala como una tarjeta nueva.</small>
            </div>
        </div>

        <footer class="dialogo-pie">
            <button type="button" class="boton-pago boton-pago-secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton-pago boton-pago-principal">Guardar cambios</button>
        </footer>
    </form>
</dialog>

<dialog class="dialogo-tarjeta dialogo-tarjeta-chico" id="dlgEliminarTarjeta" aria-labelledby="tituloEliminarTarjeta">
    <form action="<?php echo escaparSalida($urlPerfil); ?>" method="POST" novalidate>
        <input type="hidden" name="operacion" value="eliminar_tarjeta">
        <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
        <input type="hidden" name="id_tarjeta" id="eliminarIdTarjeta" value="">

        <header class="dialogo-cabecera">
            <h3 id="tituloEliminarTarjeta">Eliminar tarjeta</h3>
            <button type="button" class="dialogo-cerrar" data-cerrar-dialogo aria-label="Cerrar">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        <div class="dialogo-cuerpo">
            <p class="texto-dialogo">¿Quieres eliminar tu tarjeta <strong id="eliminarDescripcion"></strong>? Dejará de estar disponible para tus pagos.</p>
        </div>

        <footer class="dialogo-pie">
            <button type="button" class="boton-pago boton-pago-secundario" data-cerrar-dialogo>Cancelar</button>
            <button type="submit" class="boton-pago boton-pago-peligro-solido">Sí, eliminar</button>
        </footer>
    </form>
</dialog>

<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script src="<?php echo JS_RUTA; ?>tarjetas.js?v=1"></script>
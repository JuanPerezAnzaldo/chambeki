<?php
/*
    PANEL: Mi Negocio
    Centro de mando del freelancer: estado de su cuenta, porcentaje de
    completitud del perfil, vista previa de su pagina publica y acceso a
    configuracion, horarios y catalogo de servicios.

    Solo lo ve un freelancer con sesion iniciada. Todo se consulta con el
    id_usuario de la sesion de PHP, nunca con un id de la URL (RSIS-01).
*/

require_once DOCROOT . 'user/includes/funcionesPanel.php';

if (!hayUsuarioEnSesion())
{
    guardarMensaje('error', 'Inicia sesión para entrar a Mi Negocio.');
    redirigir(URL_BASE . '?accion=login');
}

$idUsuario = (int) $_SESSION['id_usuario'];
$usuario = obtenerUsuarioPorId($idUsuario);

//si el usuario ya no existe en la BD se cierra la sesion
if ($usuario === null)
{
    redirigir(URL_BASE . '?accion=cerrar_sesion');
}

//se revisa el rol en la BD (no solo en la sesion, que puede estar desactualizada)
if ((int) $usuario['rol'] !== ROL_FREELANCER)
{
    guardarMensaje('error', 'Mi Negocio es solo para freelancers.');
    redirigir(URL_BASE . '?accion=perfil');
}

$cuentaActiva = (int) $usuario['estatus'] === ESTATUS_ACTIVO;

$perfilFreelancer = obtenerPerfilFreelancer($idUsuario);
$identidad = obtenerIdentidadComercial($idUsuario);
$cobertura = obtenerCoberturaFreelancer($idUsuario);
$redes = obtenerRedesFreelancer($idUsuario);
$resumenHorarios = obtenerResumenHorarios($idUsuario);
$totalServicios = contarServiciosPublicados($idUsuario);
$estaSuscrito = usuarioEstaSuscrito($idUsuario);

$completitud = calcularCompletitudPerfil([
    'usuario'   => $usuario,
    'identidad' => $identidad,
    'banner'    => obtenerBannerFreelancer($idUsuario),
    'redes'     => $redes,
    'cobertura' => $cobertura,
    'horarios'  => $resumenHorarios,
    'servicios' => $totalServicios
]);

$porcentaje = $completitud['porcentaje'];
$pendientes = $completitud['total'] - $completitud['completos'];
$urlConfig = URL_BASE . '?accion=configuracion';
$urlVistaPrevia = urlPaginaPublica($idUsuario, $identidad['slug']);
$nombreMostrado = $identidad['nombre_negocio'] !== '' ? $identidad['nombre_negocio'] : $usuario['nombre'];
$perfilVerificado = !empty($perfilFreelancer['perfil_verificado']);

$mensajeFlash = obtenerMensaje();
?>

<section class="seccion-negocio-panel">
    <div class="contenedor-negocio-panel">

        <?php if ($mensajeFlash !== null): ?>
            <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>" role="status"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
        <?php endif; ?>

        <!-- ============ Encabezado: identidad, estado y vista previa ============ -->
        <header class="tarjeta-panel cabecera-panel">
            <?php if (!empty($usuario['foto_perfil_url'])): ?>
                <img src="<?php echo URL_BASE . escaparSalida(ltrim($usuario['foto_perfil_url'], '/')); ?>" alt="Foto de <?php echo escaparSalida($nombreMostrado); ?>" class="foto-panel">
            <?php else: ?>
                <span class="foto-panel foto-panel-iniciales" aria-hidden="true"><?php echo escaparSalida(mb_strtoupper(mb_substr($nombreMostrado, 0, 1))); ?></span>
            <?php endif; ?>

            <div class="identidad-panel">
                <p class="etiqueta-panel">Mi Negocio</p>
                <h1 class="titulo-panel"><?php echo escaparSalida($nombreMostrado); ?></h1>

                <div class="estados-panel" aria-label="Estado de tu cuenta">
                    <span class="insignia-panel <?php echo $cuentaActiva ? 'insignia-ok' : 'insignia-alerta'; ?>">
                        <?php echo $cuentaActiva ? 'Cuenta activa' : 'Cuenta suspendida'; ?>
                    </span>
                    <span class="insignia-panel <?php echo $perfilVerificado ? 'insignia-ok' : 'insignia-aviso'; ?>">
                        <?php echo $perfilVerificado ? 'Perfil verificado' : 'Verificación pendiente'; ?>
                    </span>
                    <span class="insignia-panel <?php echo $estaSuscrito ? 'insignia-ok' : 'insignia-neutra'; ?>">
                        <?php echo $estaSuscrito ? 'Suscrito' : 'No suscrito'; ?>
                    </span>
                </div>
            </div>

            <div class="acciones-cabecera-panel">
                <?php if ($cuentaActiva): ?>
                    <a href="<?php echo escaparSalida($urlVistaPrevia); ?>" class="boton-panel boton-panel-principal" target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                        Vista previa
                    </a>
                    <small>Así ven tu página los clientes</small>
                <?php else: ?>
                    <span class="boton-panel boton-panel-desactivado" aria-disabled="true">Vista previa</span>
                    <small>Tu cuenta está suspendida: tu página no es visible</small>
                <?php endif; ?>
            </div>
        </header>

        <!-- ============ Completitud del perfil ============ -->
        <section class="tarjeta-panel" id="completitud" aria-labelledby="tituloCompletitud">
            <div class="cabecera-completitud">
                <div class="anillo-completitud" style="--porcentaje: <?php echo (int) $porcentaje; ?>;" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $porcentaje; ?>" aria-label="Completitud del perfil">
                    <span><?php echo (int) $porcentaje; ?>%</span>
                </div>

                <div>
                    <h2 id="tituloCompletitud" class="subtitulo-panel">Completitud de tu perfil</h2>
                    <?php if ($pendientes === 0): ?>
                        <p class="texto-panel">¡Tu perfil está completo! Los clientes tienen toda la información para elegirte.</p>
                    <?php else: ?>
                        <p class="texto-panel">
                            <?php echo $pendientes === 1 ? 'Te falta 1 paso' : 'Te faltan ' . (int) $pendientes . ' pasos'; ?>
                            para tener un perfil completo. Un perfil completo aparece más confiable ante los clientes.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <ul class="lista-completitud">
                <?php foreach ($completitud['items'] as $punto): ?>
                    <li class="punto-completitud<?php echo $punto['completo'] ? ' completo' : ''; ?>">
                        <span class="marca-completitud" aria-hidden="true">
                            <?php if ($punto['completo']): ?>
                                <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                            <?php endif; ?>
                        </span>

                        <span class="texto-punto">
                            <strong><?php echo escaparSalida($punto['titulo']); ?></strong>
                            <small><?php echo $punto['completo'] ? 'Listo' : escaparSalida($punto['detalle']); ?></small>
                        </span>

                        <?php if (!$punto['completo']): ?>
                            <a href="<?php echo escaparSalida($punto['url']); ?>" class="enlace-completar">Completar</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- ============ Submodulos ============ -->
        <section aria-labelledby="tituloModulos">
            <h2 id="tituloModulos" class="subtitulo-panel subtitulo-modulos">Administra tu negocio</h2>

            <div class="rejilla-modulos-panel">

                <a href="<?php echo $urlConfig; ?>" class="modulo-panel">
                    <span class="icono-modulo" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span class="cuerpo-modulo">
                        <strong>Configuración</strong>
                        <small>Identidad comercial, banner, redes sociales, formas de cobro y zona de cobertura con mapa.</small>
                    </span>
                    <span class="accion-modulo">Abrir</span>
                </a>

                <a href="<?php echo $urlConfig; ?>#horarios" class="modulo-panel">
                    <span class="icono-modulo" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    </span>
                    <span class="cuerpo-modulo">
                        <strong>Horarios</strong>
                        <small>
                            <?php if ($resumenHorarios['filas'] > 0): ?>
                                <?php echo escaparSalida(resumenDiasAtencion($resumenHorarios['laborables'])); ?>. Cambia tus horas de apertura y cierre.
                            <?php else: ?>
                                Aún no los configuras: elige tus horas de apertura y cierre.
                            <?php endif; ?>
                        </small>
                    </span>
                    <span class="accion-modulo">Gestionar</span>
                </a>

                <?php if (CATALOGO_DISPONIBLE): ?>
                    <a href="<?php echo URL_BASE; ?>?accion=catalogo" class="modulo-panel">
                        <span class="icono-modulo" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/></svg>
                        </span>
                        <span class="cuerpo-modulo">
                            <strong>Catálogo de servicios</strong>
                            <small><?php echo $totalServicios === 1 ? '1 servicio publicado.' : (int) $totalServicios . ' servicios publicados.'; ?> Crea, edita y ordena tu oferta.</small>
                        </span>
                        <span class="accion-modulo">Administrar</span>
                    </a>
                <?php else: ?>
                    <div class="modulo-panel modulo-desactivado" aria-disabled="true">
                        <span class="icono-modulo" aria-hidden="true">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M9 10v10"/></svg>
                        </span>
                        <span class="cuerpo-modulo">
                            <strong>Catálogo de servicios</strong>
                            <small>Aquí podrás crear, editar y ordenar los servicios que ofreces.</small>
                        </span>
                        <span class="accion-modulo accion-proximamente">Próximamente</span>
                    </div>
                <?php endif; ?>

            </div>
        </section>

    </div>
</section>

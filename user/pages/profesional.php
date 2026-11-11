<?php
/*
    Vista publica del perfil de un negocio.
    Ruta: ?accion=profesional&slug=...   (o &id=... si todavia no tiene slug)
    Pestanas: Servicios, Ubicacion, Horario, Opiniones y Redes.
*/

require_once DOCROOT . 'user/includes/funcionesNegocios.php';

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? preg_replace('/[^a-z0-9-]/', '', strtolower($_GET['slug'])) : '';
$idSolicitado = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$negocio = null;
$errorCarga = false;

if ($slug !== '' || $idSolicitado > 0)
{
    try
    {
        $negocio = obtenerNegocioPublico($slug, $idSolicitado);

        if ($negocio !== null)
        {
            $idNegocio = (int) $negocio['id_usuario'];

            $servicios  = obtenerServiciosNegocio($idNegocio);
            $categorias = obtenerCategoriasNegocio($idNegocio);
            $horario    = obtenerHorarioNegocio($idNegocio);
            $redes      = obtenerRedesNegocio($idNegocio);
            $resumen    = obtenerResumenOpiniones($idNegocio);
            $opiniones  = obtenerOpiniones($idNegocio, 20);
        }
    }
    catch (\PDOException $excepcion)
    {
        error_log('profesional.php: ' . $excepcion->getMessage());
        $negocio = null;
        $errorCarga = true;
    }
}

$urlListado = URL_BASE . '?accion=servicios';
?>

<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>autenticacion.css?v=1">
<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>negocios.css?v=3">

<section class="seccion-negocio">
    <div class="contenedor-negocio">

    <?php if ($negocio === null): ?>
        <?php http_response_code($errorCarga ? 500 : 404); ?>

        <div class="sin-resultados-negocios">
            <h3><?php echo $errorCarga ? 'No pudimos cargar este perfil' : 'No encontramos este perfil'; ?></h3>
            <p><?php echo $errorCarga ? 'Intenta de nuevo en un momento.' : 'Puede que el enlace esté mal escrito o que el profesional ya no esté disponible.'; ?></p>
            <a href="<?php echo escaparSalida($urlListado); ?>" class="boton-autenticacion boton-negocio-enlace">Ver profesionales</a>
        </div>

    <?php else: ?>
        <?php
            $nombre = nombreNegocio($negocio);
            $foto = urlImagen($negocio['foto_perfil_url']);
            $estado = estadoNegocio($horario);
            $claseEstado = $estado['abierto'] === null ? 'estado-sin-horario' : ($estado['abierto'] ? 'estado-abierto' : 'estado-cerrado');

            //el numero de contacto del wizard va en whatsapp_contacto; si falta, el telefono de la cuenta
            $numeroContacto = numeroParaEnlace($negocio['whatsapp_contacto']) ?? numeroParaEnlace($negocio['telefono']);
            $mensajeWhatsapp = 'Hola, vi tu perfil en ' . SITENAME . ' y me interesa tu servicio.';

            $mapa = datosMapaNegocio($negocio);
            $nombresDias = nombresDias();
            $diaHoy = (int) (new DateTime('now', new DateTimeZone(ZONA_HORARIA_NEGOCIOS)))->format('N');
            $totalOpiniones = (int) $resumen['total'];
        ?>

        <a href="<?php echo escaparSalida($urlListado); ?>" class="enlace-volver">&larr; Volver a los resultados</a>

        <article class="perfil-negocio"
            <?php if ($horario !== null): ?>data-horario="<?php echo escaparSalida(horarioEnJson($horario)); ?>" data-zona="<?php echo escaparSalida(ZONA_HORARIA_NEGOCIOS); ?>"<?php endif; ?>>

            <!-- Banner de portada (lo sube el freelancer en Configuracion) -->
            <?php $banner = urlImagen($negocio['banner_url'] ?? ''); ?>
            <?php if ($banner !== null): ?>
                <div class="banner-negocio">
                    <img src="<?php echo escaparSalida($banner); ?>" alt="" onerror="this.parentNode.hidden=true;">
                </div>
            <?php endif; ?>

            <!-- Cabecera -->
            <header class="cabecera-negocio">
                <div class="avatar-negocio avatar-negocio-grande">
                    <?php if ($foto !== null): ?>
                        <img src="<?php echo escaparSalida($foto); ?>" alt="Foto de <?php echo escaparSalida($nombre); ?>" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                        <span class="avatar-iniciales" hidden><?php echo escaparSalida(inicialesNegocio($nombre)); ?></span>
                    <?php else: ?>
                        <span class="avatar-iniciales"><?php echo escaparSalida(inicialesNegocio($nombre)); ?></span>
                    <?php endif; ?>
                </div>

                <div class="identidad-negocio">
                    <div class="fila-nombre-negocio">
                        <h1 class="titulo-negocio"><?php echo escaparSalida($nombre); ?></h1>
                        <?php if ((int) $negocio['perfil_verificado'] === 1): ?><span class="insignia-negocio insignia-verificado">Verificado</span><?php endif; ?>
                    </div>

                    <div class="meta-negocio">
                        <?php if ($totalOpiniones > 0): ?>
                            <a href="#opiniones" class="bloque-valoracion" data-ir-pestana="opiniones">
                                <?php echo htmlEstrellas($resumen['promedio']); ?>
                                <strong><?php echo number_format((float) $resumen['promedio'], 1); ?></strong>
                                <span class="total-opiniones">(<?php echo $totalOpiniones; ?> <?php echo $totalOpiniones === 1 ? 'opinión' : 'opiniones'; ?>)</span>
                            </a>
                        <?php else: ?>
                            <span class="sin-opiniones">Aún sin opiniones</span>
                        <?php endif; ?>

                        <span class="etiqueta-estado-negocio <?php echo $claseEstado; ?>" data-estado-texto><?php echo escaparSalida($estado['texto']); ?></span>
                        <span class="detalle-estado" data-estado-detalle><?php echo escaparSalida($estado['detalle']); ?></span>
                    </div>

                    <p class="subtitulo-negocio">
                        <?php echo (int) $negocio['es_empresa'] === 1 ? 'Empresa' : 'Profesional independiente'; ?>
                        <?php if (etiquetaModalidad($negocio['id_modalidad']) !== ''): ?> · <?php echo escaparSalida(etiquetaModalidad($negocio['id_modalidad'])); ?><?php endif; ?>
                        <?php if (!empty($negocio['ciudad_limite'])): ?> · <?php echo escaparSalida($negocio['ciudad_limite']); ?><?php endif; ?>
                    </p>

                    <?php if (!empty($categorias)): ?>
                        <div class="categorias-negocio">
                            <?php foreach ($categorias as $categoria): ?>
                                <span class="chip-categoria"><?php echo escaparSalida($categoria); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Contacto directo -->
                <div class="acciones-contacto">
                    <?php if ($numeroContacto !== null): ?>
                        <a href="tel:+<?php echo escaparSalida($numeroContacto); ?>" class="boton-contacto contacto-llamar">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 4 5a2 2 0 0 1 2-2z"/></svg>
                            Llamar
                        </a>
                        <a href="https://wa.me/<?php echo escaparSalida($numeroContacto); ?>?text=<?php echo rawurlencode($mensajeWhatsapp); ?>" class="boton-contacto contacto-whatsapp" target="_blank" rel="noopener noreferrer">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 21l2.1-5.4A8.4 8.4 0 1 1 21 11.5z"/></svg>
                            WhatsApp
                        </a>
                    <?php endif; ?>

                    <?php if (!empty($negocio['correo'])): ?>
                        <a href="mailto:<?php echo escaparSalida($negocio['correo']); ?>?subject=<?php echo rawurlencode('Consulta desde ' . SITENAME); ?>" class="boton-contacto contacto-correo">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
                            Correo
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <?php if (trim((string) $negocio['descripcion']) !== ''): ?>
                <section class="tarjeta-negocio-detalle bloque-acerca">
                    <h2>Acerca del negocio</h2>
                    <p><?php echo nl2br(escaparSalida($negocio['descripcion'])); ?></p>
                </section>
            <?php endif; ?>

            <!-- Pestanas -->
            <div class="contenedor-pestanas" id="contenedorPestanas">
                <div class="pestanas-perfil" role="tablist" aria-label="Información del negocio">
                    <button type="button" role="tab" id="tab-servicios" aria-controls="panel-servicios" aria-selected="true" data-pestana="servicios">Servicios <span class="contador-pestana"><?php echo count($servicios); ?></span></button>
                    <button type="button" role="tab" id="tab-ubicacion" aria-controls="panel-ubicacion" aria-selected="false" data-pestana="ubicacion">Ubicación</button>
                    <button type="button" role="tab" id="tab-horario" aria-controls="panel-horario" aria-selected="false" data-pestana="horario">Horario</button>
                    <button type="button" role="tab" id="tab-opiniones" aria-controls="panel-opiniones" aria-selected="false" data-pestana="opiniones">Opiniones <span class="contador-pestana"><?php echo $totalOpiniones; ?></span></button>
                    <button type="button" role="tab" id="tab-redes" aria-controls="panel-redes" aria-selected="false" data-pestana="redes">Redes</button>
                </div>

                <!-- Servicios -->
                <section class="panel-perfil" id="panel-servicios" role="tabpanel" aria-labelledby="tab-servicios">
                    <h2 class="titulo-panel">Catálogo de servicios</h2>

                    <?php if (empty($servicios)): ?>
                        <p class="estado-vacio-negocio">Este negocio todavía no publica servicios en su catálogo.</p>
                    <?php else: ?>
                        <ul class="lista-servicios-negocio">
                            <?php foreach ($servicios as $servicio): ?>
                                <li class="servicio-item">
                                    <div class="servicio-datos">
                                        <span class="chip-categoria"><?php echo escaparSalida($servicio['nombre_categoria']); ?></span>
                                        <h3><?php echo escaparSalida($servicio['titulo']); ?></h3>
                                        <?php if (trim((string) $servicio['descripcion']) !== ''): ?>
                                            <p><?php echo escaparSalida($servicio['descripcion']); ?></p>
                                        <?php endif; ?>
                                        <?php if ((int) $servicio['duracion_minutos'] > 0): ?>
                                            <small class="servicio-duracion">Duración aproximada: <?php echo escaparSalida(duracionLegible($servicio['duracion_minutos'])); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($servicio['imagenes'])): ?>
                                            <ul class="servicio-galeria">
                                                <?php foreach ($servicio['imagenes'] as $rutaImagen): ?>
                                                    <?php $urlImagenServicio = urlImagen($rutaImagen); ?>
                                                    <?php if ($urlImagenServicio !== null): ?>
                                                        <li><a href="<?php echo escaparSalida($urlImagenServicio); ?>" target="_blank" rel="noopener"><img src="<?php echo escaparSalida($urlImagenServicio); ?>" alt="Imagen de <?php echo escaparSalida($servicio['titulo']); ?>" loading="lazy"></a></li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </div>
                                    <span class="servicio-precio"><?php echo escaparSalida(textoPrecio($servicio['monto'], $servicio['id_tipo_precio'])); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <!-- Ubicacion -->
                <section class="panel-perfil" id="panel-ubicacion" role="tabpanel" aria-labelledby="tab-ubicacion">
                    <h2 class="titulo-panel">Ubicación</h2>

                    <?php if ($mapa['tipo'] === 'online'): ?>
                        <p class="estado-vacio-negocio">Este negocio atiende en línea, sin ubicación física.</p>

                    <?php elseif ($mapa['tipo'] === 'sin_coordenadas'): ?>
                        <p class="texto-ubicacion">
                            <?php if (!empty($negocio['direccion_texto'])): ?><strong><?php echo escaparSalida($negocio['direccion_texto']); ?></strong><br><?php endif; ?>
                            <?php echo !empty($negocio['ciudad_limite']) ? escaparSalida($negocio['ciudad_limite']) : 'Ubicación no registrada.'; ?>
                        </p>
                        <?php if ($mapa['enlace'] !== null): ?>
                            <a href="<?php echo escaparSalida($mapa['enlace']); ?>" class="enlace-mapa" target="_blank" rel="noopener noreferrer">Buscar en el mapa</a>
                        <?php endif; ?>

                    <?php else: ?>
                        <?php if ($mapa['tipo'] === 'zona'): ?>
                            <p class="texto-ubicacion">Atiende a domicilio en <strong><?php echo !empty($negocio['ciudad_limite']) ? escaparSalida($negocio['ciudad_limite']) : 'su zona'; ?></strong>, hasta <strong><?php echo (int) $mapa['radio']; ?> km</strong> a la redonda. El mapa muestra una zona aproximada.</p>
                        <?php elseif (!empty($negocio['direccion_texto'])): ?>
                            <p class="texto-ubicacion"><strong><?php echo escaparSalida($negocio['direccion_texto']); ?></strong><?php echo !empty($negocio['ciudad_limite']) ? ', ' . escaparSalida($negocio['ciudad_limite']) : ''; ?></p>
                        <?php endif; ?>

                        <div class="mapa-negocio">
                            <iframe src="<?php echo escaparSalida($mapa['src']); ?>" title="Mapa de ubicación de <?php echo escaparSalida($nombre); ?>" loading="lazy" referrerpolicy="no-referrer"></iframe>
                        </div>

                        <?php if (!empty($mapa['enlace'])): ?>
                            <a href="<?php echo escaparSalida($mapa['enlace']); ?>" class="enlace-mapa" target="_blank" rel="noopener noreferrer">Cómo llegar</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>

                <!-- Horario -->
                <section class="panel-perfil" id="panel-horario" role="tabpanel" aria-labelledby="tab-horario">
                    <h2 class="titulo-panel">Horario semanal</h2>

                    <?php if ($horario === null): ?>
                        <p class="estado-vacio-negocio">Este negocio todavía no registra su horario.</p>
                    <?php else: ?>
                        <ul class="tabla-horario">
                            <?php foreach ($nombresDias as $numeroDia => $nombreDia): ?>
                                <?php $textoDia = textoHorarioDia($horario[$numeroDia]); ?>
                                <li class="fila-horario <?php echo $numeroDia === $diaHoy ? 'hoy' : ''; ?>" data-dia="<?php echo $numeroDia; ?>">
                                    <span class="dia-horario"><?php echo escaparSalida($nombreDia); ?><?php echo $numeroDia === $diaHoy ? ' <em>(hoy)</em>' : ''; ?></span>
                                    <span class="horas-horario <?php echo $textoDia === 'Cerrado' ? 'dia-cerrado' : ''; ?>"><?php echo escaparSalida($textoDia); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="nota-horario">Horario de <?php echo escaparSalida(ZONA_HORARIA_NEGOCIOS === 'America/Tijuana' ? 'Tijuana' : ZONA_HORARIA_NEGOCIOS); ?>.</p>
                    <?php endif; ?>
                </section>

                <!-- Opiniones -->
                <section class="panel-perfil" id="panel-opiniones" role="tabpanel" aria-labelledby="tab-opiniones">
                    <h2 class="titulo-panel">Opiniones de clientes</h2>

                    <?php if ($totalOpiniones === 0): ?>
                        <p class="estado-vacio-negocio">Todavía no hay opiniones. Las opiniones llegan cuando un cliente califica un servicio terminado.</p>
                    <?php else: ?>
                        <div class="resumen-opiniones">
                            <div class="resumen-promedio">
                                <span class="numero-promedio"><?php echo number_format((float) $resumen['promedio'], 1); ?></span>
                                <?php echo htmlEstrellas($resumen['promedio']); ?>
                                <small><?php echo $totalOpiniones; ?> <?php echo $totalOpiniones === 1 ? 'opinión' : 'opiniones'; ?></small>
                            </div>

                            <div class="distribucion-opiniones">
                                <?php foreach ($resumen['distribucion'] as $estrellas => $cantidad): ?>
                                    <div class="fila-distribucion">
                                        <span><?php echo $estrellas; ?> ★</span>
                                        <span class="barra-distribucion"><span style="width: <?php echo round(($cantidad / $totalOpiniones) * 100); ?>%"></span></span>
                                        <span class="cantidad-distribucion"><?php echo $cantidad; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <ul class="lista-opiniones">
                            <?php foreach ($opiniones as $opinion): ?>
                                <li class="opinion-item">
                                    <div class="opinion-cabecera">
                                        <strong><?php echo escaparSalida(nombreCorto($opinion['cliente'])); ?></strong>
                                        <?php echo htmlEstrellas($opinion['calificacion']); ?>
                                        <time class="opinion-fecha"><?php echo escaparSalida(date('d/m/Y', strtotime($opinion['fecha']))); ?></time>
                                    </div>
                                    <small class="opinion-servicio"><?php echo escaparSalida($opinion['servicio']); ?></small>
                                    <?php if (trim((string) $opinion['comentario']) !== ''): ?>
                                        <p><?php echo nl2br(escaparSalida($opinion['comentario'])); ?></p>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>

                <!-- Redes -->
                <section class="panel-perfil" id="panel-redes" role="tabpanel" aria-labelledby="tab-redes">
                    <h2 class="titulo-panel">Redes sociales</h2>
                    <?php
                        $redesSeguras = [];

                        foreach ($redes as $red)
                        {
                            $urlSegura = urlRedSegura($red['url']);

                            if ($urlSegura !== null)
                            {
                                $redesSeguras[] = ['etiqueta' => etiquetaRedSocial($red['red_social']), 'url' => $urlSegura];
                            }
                        }
                    ?>

                    <?php if (empty($redesSeguras)): ?>
                        <p class="estado-vacio-negocio">Este negocio todavía no agrega sus redes sociales.</p>
                    <?php else: ?>
                        <ul class="lista-redes">
                            <?php foreach ($redesSeguras as $red): ?>
                                <li><a href="<?php echo escaparSalida($red['url']); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo escaparSalida($red['etiqueta']); ?> &rarr;</a></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </section>
            </div>
        </article>

        <script src="<?php echo JS_RUTA; ?>negocios.js?v=1"></script>
    <?php endif; ?>

    </div>
</section>
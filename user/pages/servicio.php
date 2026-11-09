<?php
/*
    Listado de resultados: una tarjeta por negocio.
    Ruta: ?accion=servicios (index.php incluye este archivo).
    Recibe del buscador: oficio, ubicacion, latitud, longitud, tipo_ubicacion.
*/

require_once DOCROOT . 'user/includes/funcionesNegocios.php';

$oficio_buscado = leerTextoGet($_GET, 'oficio');
$filtroUbicacion = leerFiltroUbicacion($_GET);
$negocios = [];
$errorBusqueda = false;

try
{
    $negocios = buscarNegocios($oficio_buscado, $filtroUbicacion);
}
catch (\PDOException $excepcion)
{
    error_log('buscarNegocios: ' . $excepcion->getMessage());
    $errorBusqueda = true;
}

$hayCoordenadas = $filtroUbicacion['lat'] !== null;
?>

<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>negocios.css?v=1">

<!-- Buscador en la parte superior del listado -->
<div class="franja-buscador" style="background: var(--color-fondo-hero); padding: 2rem 1rem;">
    <div class="contenedor-buscador" style="margin-bottom: 0;">
        <form action="/" method="GET" class="caja-busqueda" id="formularioBusqueda" autocomplete="off">
            <input type="hidden" name="accion" value="servicios">
            <input type="hidden" name="latitud" id="campoLatitudListado" value="<?php echo $hayCoordenadas ? escaparSalida((string) $filtroUbicacion['lat']) : ''; ?>">
            <input type="hidden" name="longitud" id="campoLongitudListado" value="<?php echo $hayCoordenadas ? escaparSalida((string) $filtroUbicacion['lon']) : ''; ?>">
            <input type="hidden" name="tipo_ubicacion" id="campoTipoListado" value="<?php echo escaparSalida($filtroUbicacion['tipo']); ?>">

            <div class="campo-busqueda campo-oficio">
                <input type="text" name="oficio" id="inputOficio" class="input-busqueda" value="<?php echo escaparSalida($oficio_buscado); ?>" placeholder="Oficio, especialidad o técnico..." role="combobox" aria-expanded="false" aria-controls="menuOficios" aria-autocomplete="list">
                <button type="button" class="boton-limpiar-input <?php echo $oficio_buscado === '' ? 'oculto' : ''; ?>" id="btnLimpiarOficio" aria-label="Limpiar campo">&times;</button>

                <!-- Sugerencias de oficios (las llena busqueda.js) -->
                <div id="menuOficios" class="menu-desplegable-oficios oculto" role="listbox"></div>
            </div>

            <div class="divisor-vertical-campos"></div>

            <div class="campo-busqueda campo-zona">
                <input type="text" name="ubicacion" id="campoUbicacionListado" class="input-busqueda" value="<?php echo escaparSalida($filtroUbicacion['texto']); ?>" placeholder="Ubicación">
            </div>

            <button type="submit" class="boton-buscar" aria-label="Buscar profesional">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </button>
        </form>
    </div>
</div>

<div class="envoltorio-contenido">
    <div class="cabecera-seccion">
        <h2><?php echo $oficio_buscado !== '' ? 'Resultados para: "' . escaparSalida($oficio_buscado) . '"' : 'Profesionales disponibles'; ?></h2>
        <?php if (!$errorBusqueda): ?>
            <span class="conteo-negocios"><?php echo count($negocios); ?> <?php echo count($negocios) === 1 ? 'profesional' : 'profesionales'; ?></span>
        <?php endif; ?>
    </div>

    <?php if ($errorBusqueda): ?>

        <div class="sin-resultados-negocios">
            <h3>No pudimos cargar los resultados</h3>
            <p>Intenta de nuevo en un momento.</p>
        </div>

    <?php elseif (count($negocios) === 0): ?>

        <div class="sin-resultados-negocios">
            <h3>No encontramos profesionales</h3>
            <p>Intenta utilizar términos más generales o revisar la ortografía.</p>
        </div>

    <?php else: ?>

        <div class="rejilla-negocios">
            <?php foreach ($negocios as $negocio): ?>
                <?php
                    $nombre = nombreNegocio($negocio);
                    $foto = urlImagen($negocio['foto_perfil_url']);
                    $estado = estadoNegocio($negocio['horario']);
                    $esEnLinea = (int) $negocio['id_modalidad'] === MODALIDAD_EN_LINEA;
                    $textoKm = $esEnLinea ? '' : textoDistancia($negocio['distancia_km']);
                    $totalOpiniones = (int) $negocio['total_opiniones'];
                    $categoriasVisibles = array_slice($negocio['categorias'], 0, 3);
                    $claseEstado = $estado['abierto'] === null ? 'estado-sin-horario' : ($estado['abierto'] ? 'estado-abierto' : 'estado-cerrado');
                ?>
                <a href="<?php echo escaparSalida(enlaceNegocio($negocio)); ?>" class="tarjeta-negocio"
                   <?php if ($negocio['horario'] !== null): ?>data-horario="<?php echo escaparSalida(horarioEnJson($negocio['horario'])); ?>" data-zona="<?php echo escaparSalida(ZONA_HORARIA_NEGOCIOS); ?>"<?php endif; ?>>

                    <div class="avatar-negocio">
                        <?php if ($foto !== null): ?>
                            <img src="<?php echo escaparSalida($foto); ?>" alt="" loading="lazy" onerror="this.hidden=true;this.nextElementSibling.hidden=false;">
                            <span class="avatar-iniciales" hidden><?php echo escaparSalida(inicialesNegocio($nombre)); ?></span>
                        <?php else: ?>
                            <span class="avatar-iniciales"><?php echo escaparSalida(inicialesNegocio($nombre)); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="cuerpo-negocio">
                        <div class="fila-nombre-negocio">
                            <h3 class="nombre-negocio"><?php echo escaparSalida($nombre); ?></h3>
                            <?php if ((int) $negocio['es_premium'] === 1): ?><span class="insignia-negocio insignia-destacado">Destacado</span><?php endif; ?>
                            <?php if ((int) $negocio['perfil_verificado'] === 1): ?><span class="insignia-negocio insignia-verificado">Verificado</span><?php endif; ?>
                        </div>

                        <div class="meta-negocio">
                            <?php if ($totalOpiniones > 0): ?>
                                <span class="bloque-valoracion">
                                    <?php echo htmlEstrellas($negocio['promedio']); ?>
                                    <strong><?php echo number_format((float) $negocio['promedio'], 1); ?></strong>
                                    <span class="total-opiniones">(<?php echo $totalOpiniones; ?>)</span>
                                </span>
                            <?php else: ?>
                                <span class="sin-opiniones">Sin opiniones</span>
                            <?php endif; ?>

                            <?php if ($esEnLinea): ?>
                                <span class="distancia-negocio">En línea</span>
                            <?php elseif ($textoKm !== ''): ?>
                                <span class="distancia-negocio"><?php echo escaparSalida($textoKm); ?></span>
                            <?php elseif (!empty($negocio['ciudad_limite'])): ?>
                                <span class="distancia-negocio"><?php echo escaparSalida($negocio['ciudad_limite']); ?></span>
                            <?php endif; ?>

                            <span class="etiqueta-estado-negocio <?php echo $claseEstado; ?>" data-estado-texto><?php echo escaparSalida($estado['texto']); ?></span>
                        </div>
                    </div>

                    <div class="pie-tarjeta-negocio">
                        <?php if (!empty($categoriasVisibles)): ?>
                            <div class="categorias-negocio">
                                <?php foreach ($categoriasVisibles as $categoria): ?>
                                    <span class="chip-categoria"><?php echo escaparSalida($categoria); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <p class="resumen-negocio">
                            <?php if ($negocio['precio_desde'] !== null): ?>
                                Desde <strong>$<?php echo number_format((float) $negocio['precio_desde'], fmod((float) $negocio['precio_desde'], 1) == 0 ? 0 : 2); ?></strong> ·
                            <?php endif; ?>
                            <?php $totalServicios = (int) $negocio['total_servicios']; ?>
                            <?php echo $totalServicios; ?> <?php echo $totalServicios === 1 ? 'servicio' : 'servicios'; ?>
                            <?php if (etiquetaModalidad($negocio['id_modalidad']) !== ''): ?> · <?php echo escaparSalida(etiquetaModalidad($negocio['id_modalidad'])); ?><?php endif; ?>
                        </p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</div>

<script src="<?php echo JS_RUTA; ?>negocios.js?v=1"></script>
<?php
/*
    LISTADO DE SERVICIOS / TRABAJADORES (?accion=servicios)
    Buscador arriba + filtros laterales (desktop) o panel inferior (movil).
    Todos los campos pertenecen al MISMO formulario (#formularioBusqueda),
    incluso los del panel de filtros (atributo form="..."), asi un solo
    submit conserva buscador + filtros y siempre se vuelve a pagina 1.
*/

require_once DOCROOT . 'user/includes/funcionesPerfil.php'; //obtenerModalidades, normalizarTexto, PLAN_PREMIUM

$errorTecnico = null;
$modalidades  = [];
$categorias   = [];
$resultado    = ['items' => [], 'total' => 0];

try { $modalidades = obtenerModalidades(); }
catch (\Throwable $e) { $errorTecnico = 'modalidades: ' . $e->getMessage(); error_log('[listado] ' . $errorTecnico); }

$filtros = leerFiltrosListado($_GET, $modalidades);

try { $categorias = obtenerCategoriasListado(); }
catch (\Throwable $e) { $errorTecnico = 'categorias: ' . $e->getMessage(); error_log('[listado] ' . $errorTecnico); }

try { $resultado = buscarServicios($filtros); }
catch (\Throwable $e) { $errorTecnico = 'busqueda: ' . $e->getMessage(); error_log('[listado] ' . $errorTecnico); }

$servicios     = $resultado['items'];
$total         = $resultado['total'];
$totalPaginas  = max(1, (int) ceil($total / SERVICIOS_POR_PAGINA));

//---- helpers de URL -------------------------------------------------
function urlListado(array $cambios = [], array $quitar = []): string
{
    $actual = $_GET;
    $actual['accion'] = 'servicios';

    foreach ($quitar as $clave)
    {
        unset($actual[$clave]);
    }
    foreach ($cambios as $clave => $valor)
    {
        $actual[$clave] = $valor;
    }

    return '/?' . http_build_query($actual);
}

//---- chips de filtros activos (cada uno con su "x") ------------------
$chips = [];

if ($filtros['id_categoria'] > 0)
{
    foreach ($categorias as $cat)
    {
        if ((int) $cat['id_categoria'] === $filtros['id_categoria'])
        {
            $chips[] = ['texto' => $cat['nombre_categoria'], 'quitar' => ['categoria', 'pagina']];
        }
    }
}
if ($filtros['modalidad'] > 0)
{
    foreach ($modalidades as $m)
    {
        if ((int) $m['id_modalidad'] === $filtros['modalidad'])
        {
            $chips[] = ['texto' => $m['nombre'], 'quitar' => ['modalidad', 'pagina']];
        }
    }
}
if ($filtros['precio_min'] !== null || $filtros['precio_max'] !== null)
{
    $min = $filtros['precio_min'] !== null ? '$' . number_format($filtros['precio_min'], 0) : '';
    $max = $filtros['precio_max'] !== null ? '$' . number_format($filtros['precio_max'], 0) : '';
    $texto = ($min !== '' && $max !== '') ? "$min – $max" : ($min !== '' ? "Desde $min" : "Hasta $max");
    $chips[] = ['texto' => $texto, 'quitar' => ['precio_min', 'precio_max', 'pagina']];
}
if ($filtros['max_km'] > 0)
{
    $chips[] = ['texto' => 'Hasta ' . $filtros['max_km'] . ' km', 'quitar' => ['max_km', 'pagina']];
}

$cantidadFiltros = count($chips);
?>

<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>listado.css?v=1"><!-- o muevelo al header.php junto a home.css -->

<!-- ===== Buscador superior (mismos ids que busqueda.js) ===== -->
<div class="franja-buscador" style="background: var(--color-fondo-hero); padding: 2rem 1rem;">
    <div class="contenedor-buscador" style="margin-bottom: 0;">
        <form action="/" method="GET" class="caja-busqueda" id="formularioBusqueda" autocomplete="off">
            <input type="hidden" name="accion" value="servicios">
            <input type="hidden" name="latitud" id="campoLatitud" value="<?php echo $filtros['hay_geo'] ? escaparSalida($filtros['latitud']) : ''; ?>">
            <input type="hidden" name="longitud" id="campoLongitud" value="<?php echo $filtros['hay_geo'] ? escaparSalida($filtros['longitud']) : ''; ?>">
            <input type="hidden" name="tipo_ubicacion" id="campoTipoUbicacion" value="<?php echo escaparSalida($filtros['tipo_ubicacion']); ?>">

            <div class="campo-busqueda campo-oficio">
                <input type="text" name="oficio" id="inputOficio" class="input-busqueda" value="<?php echo escaparSalida($filtros['oficio']); ?>" placeholder="Oficio, especialidad o técnico..." role="combobox" aria-expanded="false" aria-controls="menuOficios" aria-autocomplete="list">
                <button type="button" class="boton-limpiar-input <?php echo $filtros['oficio'] === '' ? 'oculto' : ''; ?>" id="btnLimpiarOficio" aria-label="Limpiar campo">&times;</button>
                <div id="menuOficios" class="menu-desplegable-oficios oculto" role="listbox"></div>
            </div>

            <div class="divisor-vertical-campos"></div>

            <div class="campo-busqueda campo-zona contenedor-desplegable-ubicacion">
                <input type="text" name="ubicacion" id="inputUbicacion" class="input-busqueda" value="<?php echo escaparSalida($filtros['ubicacion']); ?>" placeholder="Ubicación" inputmode="none" readonly>
                <button type="button" class="boton-limpiar-input <?php echo $filtros['ubicacion'] === '' ? 'oculto' : ''; ?>" id="btnLimpiarUbicacion" aria-label="Limpiar ubicación">&times;</button>

                <div id="menuUbicaciones" class="menu-desplegable-ubicaciones oculto">
                    <div class="opciones-fijas">
                        <button type="button" class="item-opcion-ubicacion" data-tipo="online">
                            <span class="icono-opcion"><svg viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg></span>
                            <span class="texto-opcion">En línea / Remoto</span>
                        </button>
                        <button type="button" class="item-opcion-ubicacion" data-tipo="gps">
                            <span class="icono-opcion"><svg viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg></span>
                            <span class="texto-opcion textoGpsEtiqueta">Cerca de mí</span>
                        </button>
                    </div>
                    <div class="divisor-opciones"></div>
                    <div id="listaUbicacionesSugeridas" class="lista-opciones-dinamicas"></div>
                </div>
            </div>

            <button type="submit" class="boton-buscar" aria-label="Buscar profesional">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </button>
        </form>
    </div>
</div>

<!-- Modal de ubicación para móvil (lo maneja ubicacion.js, igual que en el home) -->
<div id="modalUbicacionMovil" class="modal-ubicacion-movil oculto">
    <div class="cabecera-modal-ubicacion">
        <h3 class="titulo-modal">Ubicación</h3>
        <button type="button" id="btnCerrarModalMovil" class="boton-cerrar-modal" aria-label="Cerrar modal">&times;</button>
    </div>
    <div class="cuerpo-modal-ubicacion">
        <div class="caja-input-modal">
            <input type="text" id="inputUbicacionMovil" class="input-modal-texto" placeholder="Ciudad, colonia o dirección...">
            <button type="button" class="boton-limpiar-input oculto" id="btnLimpiarUbicacionMovil" aria-label="Limpiar campo">&times;</button>
        </div>
        <div class="opciones-fijas">
            <button type="button" class="item-opcion-ubicacion" data-tipo="online">
                <span class="icono-opcion"><svg viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg></span>
                <span class="texto-opcion">En línea / Remoto</span>
            </button>
            <button type="button" class="item-opcion-ubicacion" data-tipo="gps">
                <span class="icono-opcion"><svg viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg></span>
                <span class="texto-opcion textoGpsEtiqueta">Cerca de mí</span>
            </button>
        </div>
        <div class="divisor-opciones"></div>
        <div id="listaUbicacionesMovil" class="lista-opciones-dinamicas"></div>
    </div>
</div>

<div class="envoltorio-contenido">

    <?php if ($errorTecnico !== null): ?>
        <!-- TEMPORAL mientras se prueba: borrar este aviso cuando todo funcione -->
        <p class="aviso-autenticacion aviso-error" style="margin-bottom:1rem;">ERROR REAL: <?php echo escaparSalida($errorTecnico); ?></p>
    <?php endif; ?>

    <div class="barra-resultados">
        <div>
            <h2 class="titulo-resultados">
                <?php echo $filtros['oficio'] !== '' ? 'Resultados para: "' . escaparSalida($filtros['oficio']) . '"' : 'Todos los servicios disponibles'; ?>
            </h2>
            <p class="conteo-resultados" aria-live="polite">
                <?php echo $total; ?> <?php echo $total === 1 ? 'servicio encontrado' : 'servicios encontrados'; ?>
            </p>
        </div>

        <div class="acciones-resultados">
            <button type="button" class="boton-abrir-filtros" id="btnAbrirFiltros" aria-controls="panelFiltros" aria-expanded="false">
                Filtros<?php echo $cantidadFiltros > 0 ? ' (' . $cantidadFiltros . ')' : ''; ?>
            </button>

            <label class="campo-orden">
                <span>Ordenar por</span>
                <select name="orden" form="formularioBusqueda" class="autoenviar">
                    <option value="relevancia" <?php echo $filtros['orden'] === 'relevancia' ? 'selected' : ''; ?>>Relevancia</option>
                    <?php if ($filtros['hay_geo']): ?>
                        <option value="cercania" <?php echo $filtros['orden'] === 'cercania' ? 'selected' : ''; ?>>Más cercanos</option>
                    <?php endif; ?>
                    <option value="precio_asc" <?php echo $filtros['orden'] === 'precio_asc' ? 'selected' : ''; ?>>Precio: menor a mayor</option>
                    <option value="precio_desc" <?php echo $filtros['orden'] === 'precio_desc' ? 'selected' : ''; ?>>Precio: mayor a menor</option>
                    <option value="reciente" <?php echo $filtros['orden'] === 'reciente' ? 'selected' : ''; ?>>Más recientes</option>
                </select>
            </label>
        </div>
    </div>

    <?php if ($cantidadFiltros > 0): ?>
        <div class="chips-filtros" aria-label="Filtros activos">
            <?php foreach ($chips as $chip): ?>
                <a href="<?php echo escaparSalida(urlListado([], $chip['quitar'])); ?>" class="chip-filtro" aria-label="Quitar filtro <?php echo escaparSalida($chip['texto']); ?>">
                    <?php echo escaparSalida($chip['texto']); ?> <span aria-hidden="true">&times;</span>
                </a>
            <?php endforeach; ?>
            <a href="<?php echo escaparSalida(urlListado([], ['categoria', 'modalidad', 'precio_min', 'precio_max', 'max_km', 'pagina'])); ?>" class="enlace-limpiar-todo">Limpiar todo</a>
        </div>
    <?php endif; ?>

    <div class="disposicion-listado">

        <!-- ===== Panel de filtros ===== -->
        <aside class="panel-filtros" id="panelFiltros" aria-label="Filtros">
            <div class="cabecera-panel-filtros">
                <h3>Filtros</h3>
                <button type="button" class="boton-cerrar-filtros" id="btnCerrarFiltros" aria-label="Cerrar filtros">&times;</button>
            </div>

            <fieldset class="grupo-filtro">
                <legend>Categoría</legend>
                <select name="categoria" form="formularioBusqueda" class="autoenviar">
                    <option value="0">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo (int) $cat['id_categoria']; ?>" <?php echo $filtros['id_categoria'] === (int) $cat['id_categoria'] ? 'selected' : ''; ?>>
                            <?php echo escaparSalida($cat['nombre_categoria']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </fieldset>

            <fieldset class="grupo-filtro">
                <legend>Modalidad</legend>
                <label class="opcion-filtro"><input type="radio" name="modalidad" form="formularioBusqueda" value="0" class="autoenviar" <?php echo $filtros['modalidad'] === 0 ? 'checked' : ''; ?>> Todas</label>
                <?php foreach ($modalidades as $m): ?>
                    <label class="opcion-filtro"><input type="radio" name="modalidad" form="formularioBusqueda" value="<?php echo (int) $m['id_modalidad']; ?>" class="autoenviar" <?php echo $filtros['modalidad'] === (int) $m['id_modalidad'] ? 'checked' : ''; ?>> <?php echo escaparSalida($m['nombre']); ?></label>
                <?php endforeach; ?>
            </fieldset>

            <fieldset class="grupo-filtro">
                <legend>Precio (MXN)</legend>
                <div class="fila-precio">
                    <input type="number" name="precio_min" form="formularioBusqueda" class="autoenviar" min="0" step="50" inputmode="numeric" placeholder="Mín." value="<?php echo $filtros['precio_min'] !== null ? escaparSalida($filtros['precio_min']) : ''; ?>">
                    <span>–</span>
                    <input type="number" name="precio_max" form="formularioBusqueda" class="autoenviar" min="0" step="50" inputmode="numeric" placeholder="Máx." value="<?php echo $filtros['precio_max'] !== null ? escaparSalida($filtros['precio_max']) : ''; ?>">
                </div>
                <small class="ayuda-filtro">Al filtrar por precio no se muestran los servicios “A cotizar”.</small>
            </fieldset>

            <?php if ($filtros['hay_geo']): ?>
                <fieldset class="grupo-filtro">
                    <legend>Distancia</legend>
                    <label class="opcion-filtro"><input type="radio" name="max_km" form="formularioBusqueda" value="0" class="autoenviar" <?php echo $filtros['max_km'] === 0 ? 'checked' : ''; ?>> Cualquiera</label>
                    <?php foreach ([5, 10, 25, 50] as $km): ?>
                        <label class="opcion-filtro"><input type="radio" name="max_km" form="formularioBusqueda" value="<?php echo $km; ?>" class="autoenviar" <?php echo $filtros['max_km'] === $km ? 'checked' : ''; ?>> Hasta <?php echo $km; ?> km</label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>

            <div class="pie-panel-filtros">
                <a href="<?php echo escaparSalida(urlListado([], ['categoria', 'modalidad', 'precio_min', 'precio_max', 'max_km', 'pagina'])); ?>" class="enlace-limpiar-todo">Limpiar filtros</a>
                <!-- Solo visible en movil: ahi los filtros se aplican todos juntos -->
                <button type="submit" form="formularioBusqueda" class="boton-aplicar-filtros">Ver <?php echo $total; ?> resultados</button>
            </div>
        </aside>

        <!-- ===== Resultados ===== -->
        <section class="columna-resultados" aria-label="Resultados">

            <?php if (count($servicios) > 0): ?>
                <div class="rejilla-servicios">
                    <?php foreach ($servicios as $servicio): ?>
                        <article class="tarjeta-servicio">

                            <div class="fila-tarjeta-superior">
                                <span class="etiqueta-categoria"><?php echo escaparSalida($servicio['nombre_categoria']); ?></span>
                                <span class="precio-servicio">
                                    <?php echo $servicio['monto'] > 0 ? '$' . number_format($servicio['monto'], 2) : 'A cotizar'; ?>
                                </span>
                            </div>

                            <h3 class="titulo-servicio"><?php echo escaparSalida($servicio['titulo']); ?></h3>
                            <p class="descripcion-servicio"><?php echo escaparSalida($servicio['descripcion']); ?></p>

                            <div class="datos-servicio">
                                <span class="dato-freelancer">
                                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    <?php echo escaparSalida($servicio['freelancer']); ?>
                                </span>

                                <?php if ((int) $servicio['es_premium'] === 1): ?>
                                    <span class="insignia-destacado">Destacado</span>
                                <?php endif; ?>

                                <?php if ($servicio['distancia_km'] !== null): ?>
                                    <span class="dato-distancia">a <?php echo number_format((float) $servicio['distancia_km'], 1); ?> km</span>
                                <?php elseif (!empty($servicio['ciudad_limite'])): ?>
                                    <span class="dato-distancia"><?php echo escaparSalida($servicio['ciudad_limite']); ?></span>
                                <?php endif; ?>
                            </div>

                            <button class="boton-nav-sesion boton-agendar-deshabilitado" disabled title="Disponible en futuras actualizaciones">Agendar Cita</button>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPaginas > 1): ?>
                    <nav class="paginacion" aria-label="Paginación">
                        <?php if ($filtros['pagina'] > 1): ?>
                            <a href="<?php echo escaparSalida(urlListado(['pagina' => $filtros['pagina'] - 1])); ?>">&larr; Anterior</a>
                        <?php endif; ?>

                        <span>Página <?php echo $filtros['pagina']; ?> de <?php echo $totalPaginas; ?></span>

                        <?php if ($filtros['pagina'] < $totalPaginas): ?>
                            <a href="<?php echo escaparSalida(urlListado(['pagina' => $filtros['pagina'] + 1])); ?>">Siguiente &rarr;</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="sin-resultados">
                    <h3>No encontramos profesionales</h3>
                    <p>
                        <?php echo $cantidadFiltros > 0
                            ? 'Prueba quitando algún filtro o amplía tu búsqueda.'
                            : 'Intenta usar términos más generales o revisa la ortografía.'; ?>
                    </p>
                    <?php if ($cantidadFiltros > 0): ?>
                        <a href="<?php echo escaparSalida(urlListado([], ['categoria', 'modalidad', 'precio_min', 'precio_max', 'max_km', 'pagina'])); ?>" class="boton-plan boton-plan-secundario">Quitar todos los filtros</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </section>
    </div>
</div>

<script>
(function ()
{
    const formulario  = document.getElementById('formularioBusqueda');
    const panel       = document.getElementById('panelFiltros');
    const btnAbrir    = document.getElementById('btnAbrirFiltros');
    const btnCerrar   = document.getElementById('btnCerrarFiltros');
    const esMovil     = () => window.matchMedia('(max-width: 800px)').matches;

    //desktop: cada cambio de filtro/orden aplica de inmediato.
    //movil: se aplican todos juntos con el boton "Ver N resultados".
    document.querySelectorAll('.autoenviar').forEach(function (campo)
    {
        campo.addEventListener('change', function ()
        {
            if (!esMovil()) { formulario.requestSubmit(); }
        });
    });

    //panel de filtros en movil (hoja inferior)
    function abrirPanel()  { panel.classList.add('abierto');    btnAbrir.setAttribute('aria-expanded', 'true');  document.body.classList.add('sin-scroll'); }
    function cerrarPanel() { panel.classList.remove('abierto'); btnAbrir.setAttribute('aria-expanded', 'false'); document.body.classList.remove('sin-scroll'); }

    btnAbrir.addEventListener('click', abrirPanel);
    btnCerrar.addEventListener('click', cerrarPanel);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cerrarPanel(); } });
})();
</script>
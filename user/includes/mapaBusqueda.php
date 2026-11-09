<?php
/*
    Ventana con el mapa interactivo del buscador.
    Se incluye al final de home.php y servicio.php:
        <?php include DOCROOT . 'user/includes/mapaBusqueda.php'; ?>
    Se abre desde la opcion "Elegir en el mapa" del selector de ubicacion
    (ubicacion.js llama a window.abrirMapaBusqueda) o con cualquier
    boton que tenga el atributo data-abrir-mapa.
*/

//las constantes del radio (RADIO_BUSQUEDA_*) viven en funcionesNegocios.php;
//en el home esa libreria no se cargaba y el include fallaba
require_once DOCROOT . 'user/includes/funcionesNegocios.php';
?>
<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>mapaBusqueda.css?v=1">

<dialog id="dialogoMapaBusqueda" class="dialogo-mapa" aria-labelledby="tituloMapaBusqueda"
    data-radio-min="<?php echo RADIO_BUSQUEDA_MIN_KM; ?>"
    data-radio-max="<?php echo RADIO_BUSQUEDA_MAX_KM; ?>"
    data-radio-defecto="<?php echo RADIO_BUSQUEDA_PREDETERMINADO_KM; ?>">

    <div class="dialogo-mapa-cabecera">
        <h3 id="tituloMapaBusqueda">¿Dónde necesitas el servicio?</h3>
        <button type="button" class="dialogo-mapa-cerrar" id="mapaCerrar" aria-label="Cerrar">&times;</button>
    </div>

    <div class="dialogo-mapa-cuerpo">
        <div class="fila-busqueda-mapa">
            <input type="search" id="mapaBuscarDireccion" placeholder="Busca una dirección, colonia o ciudad" autocomplete="off" aria-label="Buscar dirección">
            <button type="button" class="boton-mapa boton-mapa-secundario" id="mapaBotonBuscar">Buscar</button>
            <button type="button" class="boton-mapa boton-mapa-secundario" id="mapaBotonGps">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>
                Mi ubicación
            </button>
        </div>

        <ul id="mapaResultadosDireccion" class="lista-resultados-mapa" hidden></ul>

        <div id="mapaBusqueda" class="mapa-interactivo" role="application" aria-label="Mapa para elegir tu ubicación"></div>

        <p id="mapaDireccionElegida" class="direccion-elegida-mapa">Toca el mapa, arrastra el marcador o usa tu ubicación.</p>

        <div class="control-radio-mapa">
            <label for="mapaRadio">Radio de búsqueda: <output id="mapaRadioValor" for="mapaRadio"></output> km</label>
            <input type="range" id="mapaRadio" min="<?php echo RADIO_BUSQUEDA_MIN_KM; ?>" max="<?php echo RADIO_BUSQUEDA_MAX_KM; ?>" step="1" value="<?php echo RADIO_BUSQUEDA_PREDETERMINADO_KM; ?>">
        </div>

        <p id="mapaMensaje" class="mensaje-mapa" role="status" aria-live="polite"></p>
    </div>

    <div class="dialogo-mapa-pie">
        <button type="button" class="boton-mapa boton-mapa-secundario" id="mapaCancelar">Cancelar</button>
        <button type="button" class="boton-mapa boton-mapa-principal" id="mapaAplicar" disabled>Usar esta zona</button>
    </div>
</dialog>

<script src="<?php echo JS_RUTA; ?>mapaBusqueda.js?v=1"></script>
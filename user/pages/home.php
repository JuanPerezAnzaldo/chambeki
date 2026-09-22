<section class="seccion-hero">
    <div class="contenedor-hero">
        <h1 class="titulo-hero">Encuentra el servicio que necesitas</h1>
        <p class="subtitulo-hero">Plomeros, electricistas, carpinteros, técnicos y más a domicilio</p>

        <div class="contenedor-buscador">
            <form action="/" method="GET" class="caja-busqueda"
                id="formularioBusqueda" autocomplete="off">
                <input type="hidden" name="accion" value="servicios">

                <input type="hidden" name="latitud" id="campoLatitud" value="">
                <input type="hidden" name="longitud" id="campoLongitud" value="">
                <input type="hidden" name="tipo_ubicacion" id="campoTipoUbicacion" value="texto">

                <!-- Campo de oficio-->
                <div class="campo-busqueda campo-oficio">
                    <input type="text" name="oficio" id="inputOficio" class="input-busqueda" placeholder="Oficio, especialidad o técnico...">
                    <button type="button" class="boton-limpiar-input oculto" id="btnLimpiarOficio" aria-label="Limpiar campo">&times;</button>
                </div>

                <div class="divisor-vertical-campos"></div>

                <!-- Campo de Ubicación -->
                <div class="campo-busqueda campo-zona contenedor-desplegable-ubicacion">
                    <input type="text" name="ubicacion" id="inputUbicacion" class="input-busqueda" placeholder="Ubicación" inputmode="none" readonly>
                    <button type="button" class="boton-limpiar-input oculto" id="btnLimpiarUbicacion" aria-label="Limpiar ubicación">&times;</button>

                    <div id="menuUbicaciones" class="menu-desplegable-ubicaciones oculto">
                        <div class="opciones-fijas">
                            <button type="button" class="item-opcion-ubicacion" data-tipo="online">
                                <span class="icono-opcion">
                                    <svg viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                                </span>
                                <span class="texto-opcion">En línea / Remoto</span>
                            </button>

                            <button type="button" class="item-opcion-ubicacion" data-tipo="gps">
                                <span class="icono-opcion">
                                    <svg viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>
                                </span>
                                <span class="texto-opcion textoGpsEtiqueta">Cerca de mí</span>
                            </button>
                        </div>

                        <div class="divisor-opciones"></div>

                        <div id="listaUbicacionesSugeridas" class="lista-opciones-dinamicas">
                            <!-- JS llena esto -->
                        </div>
                    </div>
                </div>

                <button type="submit" class="boton-buscar" aria-label="Buscar profesional">
                    <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                </button>
            </form>
        </div>

        <!-- Etiquetas populares -->
        <div class="etiquetas-rapidas">
            <a href="/?accion=servicios&oficio=Electricista" class="enlace-etiqueta">Electricista</a>
            <a href="/?accion=servicios&oficio=Plomero" class="enlace-etiqueta">Plomero</a>
            <a href="/?accion=servicios&oficio=Carpintero" class="enlace-etiqueta">Carpintero</a>
            <a href="/?accion=servicios&oficio=Técnico" class="enlace-etiqueta">Técnico A/C</a>
            <a href="/?accion=servicios&oficio=Pintor" class="enlace-etiqueta">Pintor</a>
            <a href="/?accion=servicios&oficio=Cerrajero" class="enlace-etiqueta">Cerrajero</a>
            <a href="/?accion=servicios&oficio=Albañil" class="enlace-etiqueta">Albañil</a>
        </div>
    </div>
</section>

<!-- Modal de ubicacion para Móvil -->
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
                <span class="icono-opcion">
                    <svg viewBox="0 0 24 24"><path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/></svg>
                </span>
                <span class="texto-opcion">En línea / Remoto</span>
            </button>

            <button type="button" class="item-opcion-ubicacion" data-tipo="gps">
                <span class="icono-opcion">
                    <svg viewBox="0 0 24 24"><path d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0 0 13 3.06V1h-2v2.06A8.994 8.994 0 0 0 3.06 11H1v2h2.06A8.994 8.994 0 0 0 11 20.94V23h2v-2.06A8.994 8.994 0 0 0 20.94 13H23v-2h-2.06zM12 19c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>
                </span>
                <span class="texto-opcion textoGpsEtiqueta">Cerca de mí</span>
            </button>
        </div>

        <div class="divisor-opciones"></div>

        <div id="listaUbicacionesMovil" class="lista-opciones-dinamicas">
            <!-- JS llena esto -->
        </div>
    </div>
</div>

<!-- Lo que esta abajo del buscador -->
<div class="envoltorio-contenido">
    <section class="bloque-seccion">
        <div class="cabecera-seccion">
            <h2>Profesionales recién incorporados</h2>
            <a href="/?accion=servicios">Ver todos &rarr;</a>
        </div>

        <div class="rejilla-especialistas">
            <a href="/?accion=servicios" class="tarjeta-especialista">
                <div class="avatar-miniatura">MT</div>
                <div class="info-especialista">
                    <h4>Mario Torres</h4>
                    <p>Electricista Residencial</p>
                </div>
            </a>
            <a href="/?accion=servicios" class="tarjeta-especialista">
                <div class="avatar-miniatura">JR</div>
                <div class="info-especialista">
                    <h4>Jorge Ramírez</h4>
                    <p>Técnico en Climas y A/C</p>
                </div>
            </a>
            <a href="/?accion=servicios" class="tarjeta-especialista">
                <div class="avatar-miniatura">LC</div>
                <div class="info-especialista">
                    <h4>Luis Castro</h4>
                    <p>Plomería y Gas</p>
                </div>
            </a>
            <a href="/?accion=servicios" class="tarjeta-especialista">
                <div class="avatar-miniatura">AS</div>
                <div class="info-especialista">
                    <h4>Arturo Salinas</h4>
                    <p>Carpintería Fina</p>
                </div>
            </a>
        </div>
    </section>

    <section class="bloque-seccion">
        <div class="cabecera-seccion">
            <h2>Especialidades y oficios más solicitados</h2>
            <a href="/?accion=servicios">Mostrar todas</a>
        </div>

        <div class="rejilla-pildoras">
            <a href="/?accion=servicios&oficio=Instalación eléctrica" class="item-pildora">Instalación eléctrica</a>
            <a href="/?accion=servicios&oficio=Fuga de gas" class="item-pildora">Fuga de gas</a>
            <a href="/?accion=servicios&oficio=Destape de cañerías" class="item-pildora">Destape de cañerías</a>
            <a href="/?accion=servicios&oficio=Cerrajería 24/7" class="item-pildora">Cerrajería 24/7</a>
            <a href="/?accion=servicios&oficio=Impermeabilización" class="item-pildora">Impermeabilización</a>
            <a href="/?accion=servicios&oficio=Herrería y soldadura" class="item-pildora">Herrería y soldadura</a>
            <a href="/?accion=servicios&oficio=Mantenimiento de Boiler" class="item-pildora">Mantenimiento de Boiler</a>
            <a href="/?accion=servicios&oficio=Instalación de minisplit" class="item-pildora">Instalación de minisplit</a>
            <a href="/?accion=servicios&oficio=Reparación de lavadoras" class="item-pildora">Reparación de lavadoras</a>
        </div>
    </section>
</div>
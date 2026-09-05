<section class="seccion-hero">
  <div class="contenedor-hero">
      <h1 class="titulo-hero">Encuentra al profesional que necesitas</h1>
      <p class="subtitulo-hero">Plomeros, electricistas, carpinteros, técnicos y más a domicilio</p>

      <div class="contenedor-buscador">
          <form action="<?php echo URL_BASE; ?>?accion=servicios" method="GET" class="caja-busqueda">
              <input type="hidden" name="accion" value="servicios">

              <div class="campo-busqueda">
                  <input type="text" name="oficio" class="input-busqueda" placeholder="Oficio, especialidad o técnico...">
              </div>

              <div class="campo-busqueda">
                  <input type="text" name="ubicacion" class="input-busqueda" placeholder="Ciudad, colonia o radio (km)">
              </div>

              <button type="submit" class="boton-buscar" aria-label="Buscar profesional">
                  <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
              </button>
          </form>
      </div>

      <div class="etiquetas-rapidas">
          <a href="<?php echo URL_BASE; ?>?accion=servicios&q=Electricista" class="enlace-etiqueta">Electricista</a>
          <a href="<?php echo URL_BASE; ?>?accion=servicios&q=Plomero" class="enlace-etiqueta">Plomero</a>
          <a href="<?php echo URL_BASE; ?>?accion=servicios&q=Carpintero" class="enlace-etiqueta">Carpintero</a>
          <a href="<?php echo URL_BASE; ?>?accion=servicios&q=Aire+Acondicionado" class="enlace-etiqueta">Técnico A/C</a>
          <a href="<?php echo URL_BASE; ?>?accion=servicios&q=Pintor" class="enlace-etiqueta">Pintor</a>
          <a href="<?php echo URL_BASE; ?>?accion=servicios&q=Cerrajero" class="enlace-etiqueta">Cerrajero</a>
          <a href="<?php echo URL_BASE; ?>?accion=servicios&q=Albañilería" class="enlace-etiqueta">Albañil</a>
      </div>
  </div>
</section>

<div class="envoltorio-contenido">
  <section class="bloque-seccion">
      <div class="cabecera-seccion">
          <h2>Profesionales recién incorporados</h2>
          <a href="<?php echo URL_BASE; ?>?accion=servicios">Ver todos &rarr;</a>
      </div>

      <div class="rejilla-especialistas">
          <a href="#" class="tarjeta-especialista">
              <div class="avatar-miniatura">MT</div>
              <div class="info-especialista">
                  <h4>Mario Torres</h4>
                  <p>Electricista Residencial</p>
              </div>
          </a>
          <a href="#" class="tarjeta-especialista">
              <div class="avatar-miniatura">JR</div>
              <div class="info-especialista">
                  <h4>Jorge Ramírez</h4>
                  <p>Técnico en Climas y A/C</p>
              </div>
          </a>
          <a href="#" class="tarjeta-especialista">
              <div class="avatar-miniatura">LC</div>
              <div class="info-especialista">
                  <h4>Luis Castro</h4>
                  <p>Plomería y Gas</p>
              </div>
          </a>
          <a href="#" class="tarjeta-especialista">
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
          <a href="<?php echo URL_BASE; ?>?accion=categorias">Mostrar todas</a>
      </div>

      <div class="rejilla-pildoras">
          <a href="#" class="item-pildora">Instalación eléctrica</a>
          <a href="#" class="item-pildora">Fuga de gas</a>
          <a href="#" class="item-pildora">Destape de cañerías</a>
          <a href="#" class="item-pildora">Cerrajería 24/7</a>
          <a href="#" class="item-pildora">Impermeabilización</a>
          <a href="#" class="item-pildora">Herrería y soldadura</a>
          <a href="#" class="item-pildora">Mantenimiento de Boiler</a>
          <a href="#" class="item-pildora">Instalación de minisplit</a>
          <a href="#" class="item-pildora">Reparación de lavadoras</a>
      </div>
  </section>
</div>
/*
    Mapa interactivo del buscador (Leaflet + OpenStreetMap).
      - Posicion por GPS, por direccion buscada o tocando / arrastrando el marcador
      - Slider de radio (el circulo del mapa cambia en vivo)
      - Al aplicar llena los campos ocultos del formulario del buscador:
        latitud, longitud, tipo_ubicacion ('gps' o 'lugar') y radio_km
    El filtrado real (caja delimitadora + Haversine) lo hace el servidor en
    buscarNegocios(); este archivo solo captura la zona.
    Leaflet se descarga la primera vez que se abre el mapa, no con la pagina.
*/
(function ()
{
    'use strict';

    var dialogo = document.getElementById('dialogoMapaBusqueda');

    if (!dialogo)
    {
        return;
    }

    var LEAFLET_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
    var LEAFLET_JS = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
    var NOMINATIM = 'https://nominatim.openstreetmap.org/';
    var CENTRO_PREDETERMINADO = { lat: 32.5149, lon: -117.0382 }; // Tijuana

    var RADIO_MIN = parseInt(dialogo.dataset.radioMin, 10) || 1;
    var RADIO_MAX = parseInt(dialogo.dataset.radioMax, 10) || 50;
    var RADIO_DEFECTO = parseInt(dialogo.dataset.radioDefecto, 10) || 25;

    var contenedorMapa = document.getElementById('mapaBusqueda');
    var entradaBusqueda = document.getElementById('mapaBuscarDireccion');
    var botonBuscar = document.getElementById('mapaBotonBuscar');
    var botonGps = document.getElementById('mapaBotonGps');
    var listaResultados = document.getElementById('mapaResultadosDireccion');
    var textoDireccion = document.getElementById('mapaDireccionElegida');
    var controlRadio = document.getElementById('mapaRadio');
    var salidaRadio = document.getElementById('mapaRadioValor');
    var textoMensaje = document.getElementById('mapaMensaje');
    var botonAplicar = document.getElementById('mapaAplicar');

    var mapa = null;
    var marcador = null;
    var circulo = null;
    var promesaLeaflet = null;

    var formulario = null;
    var posicion = null;       // { lat, lon } elegida
    var origen = 'lugar';      // 'gps' o 'lugar'
    var nombreLugar = '';
    var secuenciaDireccion = 0;

    // ---------- utilidades ----------
    function mensaje(texto, esError)
    {
        textoMensaje.textContent = texto || '';
        textoMensaje.classList.toggle('con-error', !!esError);
    }

    function radioActual()
    {
        return parseInt(controlRadio.value, 10) || RADIO_DEFECTO;
    }

    function campoDelFormulario(nombre)
    {
        return formulario ? formulario.querySelector('[name="' + nombre + '"]') : null;
    }

    function cargarLeaflet()
    {
        if (window.L)
        {
            return Promise.resolve();
        }

        if (promesaLeaflet)
        {
            return promesaLeaflet;
        }

        promesaLeaflet = new Promise(function (resolver, rechazar)
        {
            var estilos = document.createElement('link');
            estilos.rel = 'stylesheet';
            estilos.href = LEAFLET_CSS;
            document.head.appendChild(estilos);

            var script = document.createElement('script');
            script.src = LEAFLET_JS;
            script.async = true;
            script.onload = resolver;
            script.onerror = function () { promesaLeaflet = null; rechazar(new Error('leaflet')); };
            document.head.appendChild(script);
        });

        return promesaLeaflet;
    }

    function colorAcento()
    {
        return getComputedStyle(document.documentElement).getPropertyValue('--color-boton-principal').trim() || '#E8612D';
    }

    // ---------- mapa ----------
    function asegurarMapa()
    {
        return cargarLeaflet().then(function ()
        {
            var L = window.L;

            if (!mapa)
            {
                mapa = L.map(contenedorMapa).setView([CENTRO_PREDETERMINADO.lat, CENTRO_PREDETERMINADO.lon], 12);

                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                }).addTo(mapa);

                marcador = L.marker([CENTRO_PREDETERMINADO.lat, CENTRO_PREDETERMINADO.lon], {
                    draggable: true,
                    keyboard: true,
                    title: 'Tu ubicación. Arrástralo para moverlo.',
                    icon: L.divIcon({ className: 'pin-mapa', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 28] })
                });

                circulo = L.circle([CENTRO_PREDETERMINADO.lat, CENTRO_PREDETERMINADO.lon], {
                    radius: radioActual() * 1000,
                    color: colorAcento(),
                    weight: 2,
                    fillColor: colorAcento(),
                    fillOpacity: 0.12
                });

                mapa.on('click', function (evento)
                {
                    colocar(evento.latlng.lat, evento.latlng.lng, 'lugar', true);
                });

                marcador.on('dragend', function ()
                {
                    var punto = marcador.getLatLng();
                    colocar(punto.lat, punto.lng, 'lugar', true);
                });
            }

            // el contenedor estaba oculto cuando se creo el mapa
            setTimeout(function () { mapa.invalidateSize(); }, 60);
        });
    }

    function ajustarVista()
    {
        if (mapa && circulo && mapa.hasLayer(circulo))
        {
            mapa.fitBounds(circulo.getBounds(), { padding: [24, 24], animate: false });
        }
    }

    function colocar(lat, lon, origenNuevo, buscarDireccion)
    {
        posicion = { lat: lat, lon: lon };
        origen = origenNuevo;

        marcador.setLatLng([lat, lon]);
        circulo.setLatLng([lat, lon]);
        circulo.setRadius(radioActual() * 1000);

        if (!mapa.hasLayer(marcador)) { marcador.addTo(mapa); }
        if (!mapa.hasLayer(circulo)) { circulo.addTo(mapa); }

        botonAplicar.disabled = false;
        mensaje('');

        if (buscarDireccion)
        {
            obtenerDireccion(lat, lon);
        }
    }

    // ---------- direcciones (Nominatim) ----------
    function nombreDesdeDireccion(direccion)
    {
        if (!direccion)
        {
            return '';
        }

        var barrio = direccion.suburb || direccion.neighbourhood || direccion.quarter || direccion.road;
        var ciudad = direccion.city || direccion.town || direccion.village || direccion.municipality || direccion.county;

        return [barrio, ciudad].filter(Boolean).join(', ');
    }

    function obtenerDireccion(lat, lon)
    {
        var id = ++secuenciaDireccion;

        textoDireccion.textContent = 'Buscando la dirección...';
        nombreLugar = '';

        fetch(NOMINATIM + 'reverse?format=jsonv2&zoom=16&addressdetails=1&accept-language=es&lat=' + lat + '&lon=' + lon, { headers: { 'Accept': 'application/json' } })
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (datos)
            {
                if (id !== secuenciaDireccion) { return; }

                nombreLugar = nombreDesdeDireccion(datos.address) || 'Ubicación elegida';
                textoDireccion.textContent = datos.display_name || nombreLugar;
            })
            .catch(function ()
            {
                if (id !== secuenciaDireccion) { return; }

                nombreLugar = 'Ubicación elegida';
                textoDireccion.textContent = 'Ubicación elegida en el mapa';
            });
    }

    function buscarDireccion()
    {
        var consulta = entradaBusqueda.value.trim();

        if (consulta.length < 3)
        {
            mensaje('Escribe al menos 3 letras para buscar.', true);
            return;
        }

        mensaje('Buscando...');
        listaResultados.hidden = true;

        fetch(NOMINATIM + 'search?format=jsonv2&countrycodes=mx&limit=5&addressdetails=1&accept-language=es&q=' + encodeURIComponent(consulta), { headers: { 'Accept': 'application/json' } })
            .then(function (respuesta) { return respuesta.json(); })
            .then(function (lugares)
            {
                listaResultados.innerHTML = '';

                if (!lugares || lugares.length === 0)
                {
                    mensaje('No encontramos esa dirección. Prueba con otra o toca el mapa.', true);
                    return;
                }

                mensaje('');

                lugares.forEach(function (lugar)
                {
                    var elemento = document.createElement('li');
                    var boton = document.createElement('button');

                    boton.type = 'button';
                    boton.textContent = lugar.display_name;
                    boton.addEventListener('click', function ()
                    {
                        asegurarMapa().then(function ()
                        {
                            colocar(parseFloat(lugar.lat), parseFloat(lugar.lon), 'lugar', false);

                            nombreLugar = nombreDesdeDireccion(lugar.address) || lugar.name || 'Ubicación elegida';
                            textoDireccion.textContent = lugar.display_name;
                            listaResultados.hidden = true;
                            ajustarVista();
                        });
                    });

                    elemento.appendChild(boton);
                    listaResultados.appendChild(elemento);
                });

                listaResultados.hidden = false;
            })
            .catch(function ()
            {
                mensaje('No pudimos buscar la dirección. Revisa tu conexión.', true);
            });
    }

    // ---------- GPS ----------
    function usarGps()
    {
        if (!navigator.geolocation)
        {
            mensaje('Tu navegador no permite la geolocalización. Busca una dirección o toca el mapa.', true);
            return;
        }

        mensaje('Buscando tu ubicación...');
        botonGps.disabled = true;

        navigator.geolocation.getCurrentPosition(
            function (posicionGps)
            {
                botonGps.disabled = false;

                asegurarMapa().then(function ()
                {
                    colocar(posicionGps.coords.latitude, posicionGps.coords.longitude, 'gps', true);
                    listaResultados.hidden = true;
                    ajustarVista();
                });
            },
            function (error)
            {
                botonGps.disabled = false;

                if (error.code === 1)
                {
                    mensaje('No permitiste el acceso a tu ubicación. Actívalo en el navegador o elige un punto en el mapa.', true);
                }
                else if (error.code === 3)
                {
                    mensaje('Tardó demasiado en encontrar tu ubicación. Intenta de nuevo o elige un punto en el mapa.', true);
                }
                else
                {
                    mensaje('No pudimos determinar tu ubicación. Elige un punto en el mapa.', true);
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    }

    // ---------- abrir / aplicar ----------
    function abrir(formularioDestino)
    {
        formulario = formularioDestino || document.getElementById('formularioBusqueda');

        if (!formulario)
        {
            return;
        }

        listaResultados.hidden = true;
        entradaBusqueda.value = '';
        mensaje('Cargando el mapa...');

        var radioGuardado = parseInt((campoDelFormulario('radio_km') || {}).value, 10);
        controlRadio.value = radioGuardado >= RADIO_MIN && radioGuardado <= RADIO_MAX ? radioGuardado : RADIO_DEFECTO;
        salidaRadio.textContent = controlRadio.value;

        if (typeof dialogo.showModal === 'function' && !dialogo.open)
        {
            dialogo.showModal();
        }

        asegurarMapa().then(function ()
        {
            mensaje('');

            var lat = parseFloat((campoDelFormulario('latitud') || {}).value);
            var lon = parseFloat((campoDelFormulario('longitud') || {}).value);
            var textoPrevio = (campoDelFormulario('ubicacion') || {}).value || '';

            if (!isNaN(lat) && !isNaN(lon))
            {
                colocar(lat, lon, ((campoDelFormulario('tipo_ubicacion') || {}).value === 'gps') ? 'gps' : 'lugar', false);
                nombreLugar = textoPrevio;
                textoDireccion.textContent = textoPrevio || 'Ubicación elegida en el mapa';
                ajustarVista();
            }
            else if (posicion)
            {
                ajustarVista();
            }
            else
            {
                botonAplicar.disabled = true;
                textoDireccion.textContent = 'Toca el mapa, arrastra el marcador o usa tu ubicación.';
            }
        }).catch(function ()
        {
            mensaje('No pudimos cargar el mapa. Revisa tu conexión e intenta de nuevo.', true);
        });
    }

    function etiquetaUbicacion()
    {
        if (origen === 'gps')
        {
            return nombreLugar ? 'Cerca de mí (' + nombreLugar + ')' : 'Cerca de mí';
        }

        return nombreLugar || 'Ubicación elegida';
    }

    function aplicar()
    {
        if (!posicion || !formulario)
        {
            return;
        }

        var asignar = function (nombre, valor)
        {
            var campo = campoDelFormulario(nombre);

            if (campo) { campo.value = valor; }
        };

        asignar('latitud', posicion.lat.toFixed(6));
        asignar('longitud', posicion.lon.toFixed(6));
        asignar('tipo_ubicacion', origen);
        asignar('radio_km', String(radioActual()));

        var texto = etiquetaUbicacion();

        // se asigna el valor sin disparar "input" para que ubicacion.js no borre las coordenadas
        asignar('ubicacion', texto);

        var movil = document.getElementById('inputUbicacionMovil');

        if (movil) { movil.value = texto; }

        ['btnLimpiarUbicacion', 'btnLimpiarUbicacionMovil'].forEach(function (id)
        {
            var boton = document.getElementById(id);

            if (boton) { boton.classList.remove('oculto'); }
        });

        dialogo.close();

        if (formulario.dataset.autoenviarMapa === '1')
        {
            if (formulario.requestSubmit) { formulario.requestSubmit(); }
            else { formulario.submit(); }
        }
    }

    // ---------- eventos ----------
    controlRadio.addEventListener('input', function ()
    {
        salidaRadio.textContent = controlRadio.value;

        if (circulo)
        {
            circulo.setRadius(radioActual() * 1000);
            ajustarVista();
        }
    });

    botonBuscar.addEventListener('click', buscarDireccion);

    entradaBusqueda.addEventListener('keydown', function (evento)
    {
        if (evento.key === 'Enter')
        {
            evento.preventDefault();
            buscarDireccion();
        }
    });

    botonGps.addEventListener('click', usarGps);
    botonAplicar.addEventListener('click', aplicar);
    document.getElementById('mapaCancelar').addEventListener('click', function () { dialogo.close(); });
    document.getElementById('mapaCerrar').addEventListener('click', function () { dialogo.close(); });

    dialogo.addEventListener('click', function (evento)
    {
        if (evento.target === dialogo)
        {
            dialogo.close();
        }
    });

    document.querySelectorAll('[data-abrir-mapa]').forEach(function (boton)
    {
        boton.addEventListener('click', function () { abrir(document.getElementById('formularioBusqueda')); });
    });

    // slider de radio de la pagina de resultados: al soltarlo se repite la busqueda
    var radioListado = document.getElementById('radioListado');

    if (radioListado)
    {
        var salidaListado = document.getElementById('radioListadoValor');
        var formularioListado = document.getElementById('formularioBusqueda');

        radioListado.addEventListener('input', function () { salidaListado.textContent = radioListado.value; });

        radioListado.addEventListener('change', function ()
        {
            var campoRadio = formularioListado.querySelector('[name="radio_km"]');

            if (campoRadio) { campoRadio.value = radioListado.value; }

            if (formularioListado.requestSubmit) { formularioListado.requestSubmit(); }
            else { formularioListado.submit(); }
        });
    }

    window.abrirMapaBusqueda = abrir;
})();
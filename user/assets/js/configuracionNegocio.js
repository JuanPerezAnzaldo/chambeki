/*
    Configuracion de perfil: recorte de imagenes (Cropper.js), mapa para
    fijar la ubicacion (Leaflet + OpenStreetMap) y matriz de horarios.

    Solo mejora la experiencia: las validaciones reales siguen en el
    servidor (RS-05). Cropper.js y Leaflet se descargan solo cuando se
    necesitan; si no cargan, el formulario sigue funcionando sin ellos.
*/
(function ()
{
    'use strict';

    var CROPPER_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css';
    var CROPPER_JS = 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js';
    var LEAFLET_CSS = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
    var LEAFLET_JS = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
    var NOMINATIM = 'https://nominatim.openstreetmap.org/';

    var CENTRO_PREDETERMINADO = { lat: 32.5149, lon: -117.0382 }; //Tijuana
    var MB = 1024 * 1024;

    //configuracion de cada imagen que se recorta
    var IMAGENES = {
        foto: {
            titulo: 'Recorta tu foto de perfil',
            relacion: 1,
            maxAncho: 600,
            maxAlto: 600,
            minAncho: 200,
            pesoServidor: 2 * MB,
            nombreArchivo: 'foto_perfil.jpg',
            error: 'errorFotoCliente',
            vistaPrevia: 'vistaPreviaFoto'
        },
        banner: {
            titulo: 'Recorta tu banner de portada',
            relacion: 3,
            maxAncho: 1500,
            maxAlto: 500,
            minAncho: 600,
            pesoServidor: 3 * MB,
            nombreArchivo: 'banner_portada.jpg',
            error: 'errorBannerCliente',
            vistaPrevia: 'vistaPreviaBanner'
        }
    };

    var PESO_MAXIMO_ORIGINAL = 15 * MB; //el original puede ser grande: se recorta y se comprime aqui

    // ------------------------------------------------------------
    // Carga perezosa de librerias externas
    // ------------------------------------------------------------

    var promesas = {};

    function cargarLibreria(clave, urlCss, urlJs, comprobar)
    {
        if (comprobar())
        {
            return Promise.resolve();
        }

        if (promesas[clave])
        {
            return promesas[clave];
        }

        promesas[clave] = new Promise(function (resolver, rechazar)
        {
            var estilos = document.createElement('link');
            estilos.rel = 'stylesheet';
            estilos.href = urlCss;
            document.head.appendChild(estilos);

            var script = document.createElement('script');
            script.src = urlJs;
            script.async = true;
            script.onload = function () { comprobar() ? resolver() : rechazar(new Error(clave)); };
            script.onerror = function () { promesas[clave] = null; rechazar(new Error(clave)); };
            document.head.appendChild(script);
        });

        return promesas[clave];
    }

    function cargarCropper()
    {
        return cargarLibreria('cropper', CROPPER_CSS, CROPPER_JS, function () { return typeof window.Cropper === 'function'; });
    }

    function cargarLeaflet()
    {
        return cargarLibreria('leaflet', LEAFLET_CSS, LEAFLET_JS, function () { return typeof window.L === 'object' && typeof window.L.map === 'function'; });
    }

    function mostrarError(elemento, texto)
    {
        if (!elemento)
        {
            return;
        }

        elemento.textContent = texto;
        elemento.hidden = false;
    }

    // ------------------------------------------------------------
    // Recorte de imagenes (foto de perfil y banner)
    // ------------------------------------------------------------

    var dialogoRecorte = null;

    function soportaRecorte()
    {
        return typeof window.HTMLDialogElement === 'function'
            && typeof window.DataTransfer === 'function'
            && typeof window.File === 'function'
            && typeof URL.createObjectURL === 'function';
    }

    function obtenerDialogoRecorte()
    {
        if (dialogoRecorte)
        {
            return dialogoRecorte;
        }

        dialogoRecorte = document.createElement('dialog');
        dialogoRecorte.className = 'dialogo-recorte';
        dialogoRecorte.setAttribute('aria-labelledby', 'tituloRecorte');
        dialogoRecorte.innerHTML =
            '<div class="recorte-cabecera"><h3 id="tituloRecorte"></h3></div>' +
            '<div class="recorte-lienzo"><img alt="Imagen que se va a recortar"></div>' +
            '<p class="error-campo recorte-error" role="alert" hidden></p>' +
            '<div class="recorte-acciones">' +
                '<button type="button" class="boton-enlace" data-recorte-accion="girar">Girar</button>' +
                '<span class="recorte-espacio"></span>' +
                '<button type="button" class="boton-enlace" data-recorte-accion="cancelar">Cancelar</button>' +
                '<button type="button" class="boton-autenticacion boton-config" data-recorte-accion="aceptar">Usar recorte</button>' +
            '</div>';

        document.body.appendChild(dialogoRecorte);

        return dialogoRecorte;
    }

    //resuelve con un Blob JPEG recortado, o con null si el usuario cancela
    function abrirRecorte(archivo, configuracion)
    {
        return new Promise(function (resolver)
        {
            var dialogo = obtenerDialogoRecorte();
            var imagen = dialogo.querySelector('img');
            var errorRecorte = dialogo.querySelector('.recorte-error');
            var url = URL.createObjectURL(archivo);
            var recortador = null;

            function cerrar(resultado)
            {
                if (recortador)
                {
                    recortador.destroy();
                    recortador = null;
                }

                imagen.onload = null;
                imagen.removeAttribute('src');
                URL.revokeObjectURL(url);
                dialogo.oncancel = null;

                if (dialogo.open)
                {
                    dialogo.close();
                }

                resolver(resultado);
            }

            dialogo.querySelector('h3').textContent = configuracion.titulo;
            errorRecorte.hidden = true;

            imagen.onload = function ()
            {
                recortador = new window.Cropper(imagen, {
                    aspectRatio: configuracion.relacion,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    background: false,
                    responsive: true,
                    guides: true
                });
            };

            imagen.src = url;

            dialogo.oncancel = function (evento)
            {
                evento.preventDefault();
                cerrar(null);
            };

            dialogo.querySelectorAll('[data-recorte-accion]').forEach(function (boton)
            {
                boton.onclick = function ()
                {
                    var accion = boton.getAttribute('data-recorte-accion');

                    if (accion === 'cancelar')
                    {
                        cerrar(null);
                    }
                    else if (accion === 'girar' && recortador)
                    {
                        recortador.rotate(90);
                    }
                    else if (accion === 'aceptar' && recortador)
                    {
                        var lienzo = recortador.getCroppedCanvas({
                            maxWidth: configuracion.maxAncho,
                            maxHeight: configuracion.maxAlto,
                            fillColor: '#ffffff',
                            imageSmoothingQuality: 'high'
                        });

                        if (!lienzo || lienzo.width < configuracion.minAncho)
                        {
                            errorRecorte.textContent = 'La zona recortada es muy pequena: debe medir al menos ' + configuracion.minAncho + ' px de ancho. Amplia el recorte o elige una imagen mas grande.';
                            errorRecorte.hidden = false;
                            return;
                        }

                        lienzo.toBlob(function (blob) { cerrar(blob); }, 'image/jpeg', 0.9);
                    }
                };
            });

            dialogo.showModal();
        });
    }

    function mostrarVistaPrevia(clave, url)
    {
        var configuracion = IMAGENES[clave];
        var elemento = document.getElementById(configuracion.vistaPrevia);

        if (!elemento)
        {
            return;
        }

        if (clave === 'banner')
        {
            var vacia = document.getElementById('vistaBannerVacia');

            elemento.src = url;
            elemento.hidden = false;

            if (vacia)
            {
                vacia.hidden = true;
            }

            return;
        }

        if (elemento.tagName !== 'IMG')
        {
            var nueva = document.createElement('img');
            nueva.className = 'foto-perfil-grande';
            nueva.id = configuracion.vistaPrevia;
            nueva.alt = 'Vista previa de la nueva foto';
            elemento.replaceWith(nueva);
            elemento = nueva;
        }

        elemento.src = url;
    }

    function prepararRecorte(entrada)
    {
        var clave = entrada.getAttribute('data-recorte');
        var configuracion = IMAGENES[clave];

        if (!configuracion)
        {
            return;
        }

        var elementoError = document.getElementById(configuracion.error);

        //sin recorte (la libreria no cargo o el navegador es viejo): vista previa y limite de peso del servidor
        function usarOriginal(archivo)
        {
            if (archivo.size > configuracion.pesoServidor)
            {
                mostrarError(elementoError, 'La imagen pesa mas de ' + (configuracion.pesoServidor / MB) + 'MB. Usa una mas ligera.');
                entrada.value = '';
                return;
            }

            mostrarVistaPrevia(clave, URL.createObjectURL(archivo));
        }

        entrada.addEventListener('change', function ()
        {
            var archivo = entrada.files && entrada.files[0];

            if (elementoError)
            {
                elementoError.hidden = true;
            }

            if (!archivo)
            {
                return;
            }

            if (!/^image\/(jpeg|png|webp)$/.test(archivo.type))
            {
                mostrarError(elementoError, 'Elige una imagen JPG, PNG o WEBP.');
                entrada.value = '';
                return;
            }

            if (archivo.size > PESO_MAXIMO_ORIGINAL)
            {
                mostrarError(elementoError, 'La imagen original pesa mas de 15MB. Elige una mas ligera.');
                entrada.value = '';
                return;
            }

            if (!soportaRecorte())
            {
                usarOriginal(archivo);
                return;
            }

            cargarCropper().then(function ()
            {
                return abrirRecorte(archivo, configuracion);
            }).then(function (blob)
            {
                if (blob === null)
                {
                    entrada.value = '';
                    return;
                }

                var recortado = new File([blob], configuracion.nombreArchivo, { type: 'image/jpeg' });
                var transferencia = new DataTransfer();

                transferencia.items.add(recortado);
                entrada.files = transferencia.files;

                mostrarVistaPrevia(clave, URL.createObjectURL(blob));
            }).catch(function ()
            {
                //Cropper.js no se pudo descargar: se sube la imagen tal cual
                usarOriginal(archivo);
            });
        });
    }

    document.querySelectorAll('input[type="file"][data-recorte]').forEach(prepararRecorte);

    // ------------------------------------------------------------
    // Mapa para fijar la ubicacion y ver el radio de cobertura
    // ------------------------------------------------------------

    function iniciarMapa()
    {
        var contenedor = document.getElementById('cfgMapa');

        if (!contenedor)
        {
            return;
        }

        var bloque = document.getElementById('bloqueMapa');
        var campoLat = document.getElementById('cfgLat');
        var campoLon = document.getElementById('cfgLon');
        var radio = document.getElementById('cfgRadio');
        var modalidad = document.getElementById('cfgModalidad');
        var aviso = document.getElementById('avisoMapa');
        var campoBuscar = document.getElementById('cfgBuscarMapa');
        var botonBuscar = document.getElementById('btnBuscarMapa');
        var botonGps = document.getElementById('btnGpsMapa');
        var listaResultados = document.getElementById('resultadosMapa');
        var campoDireccion = document.getElementById('cfgDireccion');
        var campoCiudad = document.getElementById('cfgCiudad');

        var idEnLinea = modalidad ? parseInt(modalidad.getAttribute('data-en-linea'), 10) : 0;
        var mapa = null;
        var marcador = null;
        var circulo = null;
        var iniciado = false;

        function mostrarAviso(texto)
        {
            aviso.textContent = texto;
            aviso.hidden = texto === '';
        }

        function coordenadaValida(texto, minimo, maximo)
        {
            var numero = parseFloat(String(texto).replace(',', '.'));

            return isFinite(numero) && numero >= minimo && numero <= maximo ? numero : null;
        }

        function radioMetros()
        {
            return (parseInt(radio ? radio.value : '10', 10) || 10) * 1000;
        }

        function esEnLinea()
        {
            return modalidad && parseInt(modalidad.value, 10) === idEnLinea;
        }

        function colocar(lat, lon, centrar)
        {
            campoLat.value = lat.toFixed(6);
            campoLon.value = lon.toFixed(6);

            if (!mapa)
            {
                return;
            }

            if (!marcador)
            {
                marcador = window.L.marker([lat, lon], { draggable: true }).addTo(mapa);
                marcador.on('dragend', function ()
                {
                    var posicion = marcador.getLatLng();
                    colocar(posicion.lat, posicion.lng, false);
                });
            }
            else
            {
                marcador.setLatLng([lat, lon]);
            }

            if (!circulo)
            {
                circulo = window.L.circle([lat, lon], {
                    radius: radioMetros(),
                    color: '#E8612D',
                    weight: 2,
                    fillColor: '#E8612D',
                    fillOpacity: 0.12
                }).addTo(mapa);
            }
            else
            {
                circulo.setLatLng([lat, lon]);
            }

            if (centrar)
            {
                mapa.setView([lat, lon], Math.max(mapa.getZoom(), 15));
            }
        }

        function crearMapa()
        {
            if (iniciado)
            {
                mapa.invalidateSize();
                return;
            }

            iniciado = true;

            var latInicial = coordenadaValida(campoLat.value, -90, 90);
            var lonInicial = coordenadaValida(campoLon.value, -180, 180);
            var hayPunto = latInicial !== null && lonInicial !== null;

            mapa = window.L.map(contenedor).setView(
                [hayPunto ? latInicial : CENTRO_PREDETERMINADO.lat, hayPunto ? lonInicial : CENTRO_PREDETERMINADO.lon],
                hayPunto ? 15 : 12
            );

            window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(mapa);

            if (hayPunto)
            {
                colocar(latInicial, lonInicial, false);
            }

            mapa.on('click', function (evento)
            {
                colocar(evento.latlng.lat, evento.latlng.lng, false);
            });

            setTimeout(function () { mapa.invalidateSize(); }, 200);
        }

        function levantarMapa()
        {
            cargarLeaflet().then(crearMapa).catch(function ()
            {
                mostrarAviso('No se pudo cargar el mapa. Puedes escribir las coordenadas manualmente.');
            });
        }

        // coordenadas escritas a mano
        function alEscribirCoordenadas()
        {
            var lat = coordenadaValida(campoLat.value, -90, 90);
            var lon = coordenadaValida(campoLon.value, -180, 180);

            if (lat !== null && lon !== null && mapa)
            {
                colocar(lat, lon, true);
            }
        }

        campoLat.addEventListener('change', alEscribirCoordenadas);
        campoLon.addEventListener('change', alEscribirCoordenadas);

        // radio de cobertura
        if (radio)
        {
            radio.addEventListener('input', function ()
            {
                if (circulo)
                {
                    circulo.setRadius(radioMetros());
                }
            });
        }

        // GPS
        if (botonGps)
        {
            botonGps.addEventListener('click', function ()
            {
                if (!navigator.geolocation)
                {
                    mostrarAviso('Tu navegador no permite obtener la ubicacion.');
                    return;
                }

                mostrarAviso('Buscando tu ubicacion...');
                botonGps.disabled = true;

                navigator.geolocation.getCurrentPosition(function (posicion)
                {
                    botonGps.disabled = false;
                    mostrarAviso('');
                    colocar(posicion.coords.latitude, posicion.coords.longitude, true);
                }, function ()
                {
                    botonGps.disabled = false;
                    mostrarAviso('No pudimos obtener tu ubicacion. Revisa el permiso del navegador o busca tu direccion.');
                }, { enableHighAccuracy: true, timeout: 10000 });
            });
        }

        // busqueda de direcciones (Nominatim)
        function limpiarResultados()
        {
            listaResultados.innerHTML = '';
            listaResultados.hidden = true;
        }

        function elegirResultado(resultado)
        {
            var lat = parseFloat(resultado.lat);
            var lon = parseFloat(resultado.lon);

            limpiarResultados();

            if (!isFinite(lat) || !isFinite(lon))
            {
                return;
            }

            colocar(lat, lon, true);

            var direccion = resultado.address || {};
            var ciudad = direccion.city || direccion.town || direccion.village || direccion.municipality || direccion.county || '';

            //solo se rellenan los campos que estan vacios (no se pisa lo que escribio el usuario)
            if (campoCiudad && campoCiudad.value.trim() === '' && ciudad !== '')
            {
                campoCiudad.value = ciudad;
            }

            if (campoDireccion && campoDireccion.value.trim() === '' && resultado.display_name)
            {
                campoDireccion.value = String(resultado.display_name).split(',').slice(0, 3).join(',').trim();
            }
        }

        function buscarDireccion()
        {
            var consulta = campoBuscar.value.trim();

            if (consulta.length < 3)
            {
                mostrarAviso('Escribe al menos 3 letras para buscar.');
                return;
            }

            mostrarAviso('Buscando...');
            limpiarResultados();

            fetch(NOMINATIM + 'search?format=jsonv2&countrycodes=mx&limit=5&addressdetails=1&accept-language=es&q=' + encodeURIComponent(consulta), {
                headers: { 'Accept': 'application/json' }
            }).then(function (respuesta)
            {
                if (!respuesta.ok)
                {
                    throw new Error('busqueda');
                }

                return respuesta.json();
            }).then(function (resultados)
            {
                if (!Array.isArray(resultados) || resultados.length === 0)
                {
                    mostrarAviso('No encontramos esa direccion. Prueba con otra o haz clic en el mapa.');
                    return;
                }

                mostrarAviso('');

                resultados.forEach(function (resultado)
                {
                    var item = document.createElement('li');
                    var boton = document.createElement('button');

                    boton.type = 'button';
                    boton.textContent = resultado.display_name;
                    boton.addEventListener('click', function () { elegirResultado(resultado); });

                    item.appendChild(boton);
                    listaResultados.appendChild(item);
                });

                listaResultados.hidden = false;
            }).catch(function ()
            {
                mostrarAviso('No pudimos buscar la direccion ahora. Haz clic en el mapa o escribe las coordenadas.');
            });
        }

        if (botonBuscar)
        {
            botonBuscar.addEventListener('click', buscarDireccion);
        }

        if (campoBuscar)
        {
            campoBuscar.addEventListener('keydown', function (evento)
            {
                if (evento.key === 'Enter')
                {
                    evento.preventDefault();
                    buscarDireccion();
                }
            });
        }

        // "En linea" no necesita ubicacion: se oculta el bloque del mapa
        function alCambiarModalidad()
        {
            var enLinea = esEnLinea();

            bloque.hidden = enLinea;

            if (!enLinea)
            {
                levantarMapa();
            }
        }

        if (modalidad)
        {
            modalidad.addEventListener('change', alCambiarModalidad);
        }

        alCambiarModalidad();
    }

    iniciarMapa();

    // ------------------------------------------------------------
    // Matriz de horarios semanales
    // ------------------------------------------------------------

    function iniciarHorarios()
    {
        var formulario = document.getElementById('formHorarios');

        if (!formulario)
        {
            return;
        }

        var filas = formulario.querySelectorAll('[data-fila-horario]');

        function sincronizar(fila)
        {
            var cerrado = fila.querySelector('[data-cerrado]').checked;

            fila.querySelector('[data-abre]').disabled = cerrado;
            fila.querySelector('[data-cierra]').disabled = cerrado;
            fila.classList.toggle('cerrada', cerrado);
        }

        filas.forEach(function (fila)
        {
            fila.querySelector('[data-cerrado]').addEventListener('change', function ()
            {
                sincronizar(fila);
            });

            sincronizar(fila);
        });

        // copiar el lunes a toda la semana
        var botonCopiar = document.getElementById('btnCopiarLunes');

        if (botonCopiar && filas.length > 0)
        {
            botonCopiar.addEventListener('click', function ()
            {
                var lunes = filas[0];

                for (var i = 1; i < filas.length; i++)
                {
                    filas[i].querySelector('[data-cerrado]').checked = lunes.querySelector('[data-cerrado]').checked;
                    filas[i].querySelector('[data-abre]').value = lunes.querySelector('[data-abre]').value;
                    filas[i].querySelector('[data-cierra]').value = lunes.querySelector('[data-cierra]').value;
                    sincronizar(filas[i]);
                }
            });
        }

        // revision antes de enviar (el servidor repite todo)
        formulario.addEventListener('submit', function (evento)
        {
            var primerError = null;

            filas.forEach(function (fila)
            {
                var aviso = fila.querySelector('[data-error-horario]');
                var cerrado = fila.querySelector('[data-cerrado]').checked;
                var abre = fila.querySelector('[data-abre]').value;
                var cierra = fila.querySelector('[data-cierra]').value;
                var mensaje = '';

                if (!cerrado)
                {
                    if (abre === '' || cierra === '')
                    {
                        mensaje = 'Elige la hora de apertura y la de cierre.';
                    }
                    else if (abre >= cierra)
                    {
                        mensaje = 'El cierre debe ser despues de la apertura.';
                    }
                }

                aviso.textContent = mensaje;
                aviso.hidden = mensaje === '';

                if (mensaje !== '' && primerError === null)
                {
                    primerError = fila;
                }
            });

            if (primerError !== null)
            {
                evento.preventDefault();
                primerError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    }

    iniciarHorarios();
})();

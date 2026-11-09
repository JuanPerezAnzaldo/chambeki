/*
    Tarjetas de negocios y perfil publico:
      1. Etiqueta Abierto / Cerrado en tiempo real (se recalcula cada 30 s)
      2. Pestanas del perfil (con teclado y enlaces #servicios, #horario, etc.)
      3. Si en el listado se edita el texto de ubicacion, se descartan las
         coordenadas guardadas para no medir distancias desde otro lugar.
    El algoritmo de estado es el mismo de estadoNegocio() en funcionesNegocios.php.
*/
(function ()
{
    'use strict';

    var DIAS = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };
    var NOMBRES_DIAS = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    // ---------- 1. Abierto / Cerrado ----------
    function ahoraEn(zona)
    {
        try
        {
            var partes = new Intl.DateTimeFormat('en-US', {
                timeZone: zona, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23'
            }).formatToParts(new Date());

            var datos = {};

            partes.forEach(function (parte) { datos[parte.type] = parte.value; });

            return { dia: DIAS[datos.weekday], minutos: parseInt(datos.hour, 10) * 60 + parseInt(datos.minute, 10) };
        }
        catch (error)
        {
            return null;
        }
    }

    function hora(minutos)
    {
        var h = Math.floor(minutos / 60) % 24;
        var m = minutos % 60;

        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
    }

    function valido(dia)
    {
        return !!dia && !dia.x && dia.a !== null && dia.c !== null;
    }

    function estado(horario, dia, minutos)
    {
        var hoy = horario[dia];
        var ayer = horario[dia === 1 ? 7 : dia - 1];

        if (valido(hoy))
        {
            var cruza = hoy.c <= hoy.a;

            if ((cruza && minutos >= hoy.a) || (!cruza && minutos >= hoy.a && minutos < hoy.c))
            {
                return { abierto: true, detalle: 'Cierra a las ' + hora(hoy.c) };
            }
        }

        if (valido(ayer) && ayer.c <= ayer.a && minutos < ayer.c)
        {
            return { abierto: true, detalle: 'Cierra a las ' + hora(ayer.c) };
        }

        var detalle = '';

        if (valido(hoy) && minutos < hoy.a)
        {
            detalle = 'Abre hoy a las ' + hora(hoy.a);
        }
        else
        {
            for (var i = 1; i <= 7; i++)
            {
                var siguiente = ((dia - 1 + i) % 7) + 1;

                if (valido(horario[siguiente]))
                {
                    detalle = (i === 1 ? 'Abre mañana' : 'Abre el ' + NOMBRES_DIAS[siguiente]) + ' a las ' + hora(horario[siguiente].a);
                    break;
                }
            }
        }

        return { abierto: false, detalle: detalle };
    }

    function actualizarEstados()
    {
        document.querySelectorAll('[data-horario]').forEach(function (contenedor)
        {
            var horario;

            try { horario = JSON.parse(contenedor.dataset.horario); }
            catch (error) { return; }

            var ahora = ahoraEn(contenedor.dataset.zona || 'America/Tijuana');

            if (!horario || !ahora)
            {
                return;
            }

            var resultado = estado(horario, ahora.dia, ahora.minutos);
            var etiqueta = contenedor.querySelector('[data-estado-texto]');
            var detalle = contenedor.querySelector('[data-estado-detalle]');

            if (etiqueta)
            {
                etiqueta.textContent = resultado.abierto ? 'Abierto' : 'Cerrado';
                etiqueta.classList.toggle('estado-abierto', resultado.abierto);
                etiqueta.classList.toggle('estado-cerrado', !resultado.abierto);
                etiqueta.classList.remove('estado-sin-horario');
            }

            if (detalle)
            {
                detalle.textContent = resultado.detalle;
            }

            contenedor.querySelectorAll('[data-dia]').forEach(function (fila)
            {
                fila.classList.toggle('hoy', parseInt(fila.dataset.dia, 10) === ahora.dia);
            });
        });
    }

    actualizarEstados();
    setInterval(actualizarEstados, 30000);

    // ---------- 2. Pestanas ----------
    var contenedorPestanas = document.getElementById('contenedorPestanas');

    if (contenedorPestanas)
    {
        var pestanas = Array.prototype.slice.call(contenedorPestanas.querySelectorAll('[role="tab"]'));
        var paneles = Array.prototype.slice.call(contenedorPestanas.querySelectorAll('[role="tabpanel"]'));

        contenedorPestanas.classList.add('pestanas-activas');

        var activar = function (clave, actualizarHash)
        {
            var existe = pestanas.some(function (p) { return p.dataset.pestana === clave; });

            if (!existe)
            {
                clave = pestanas[0].dataset.pestana;
            }

            pestanas.forEach(function (pestana)
            {
                var activa = pestana.dataset.pestana === clave;

                pestana.setAttribute('aria-selected', activa ? 'true' : 'false');
                pestana.tabIndex = activa ? 0 : -1;

                if (activa && pestana.scrollIntoView)
                {
                    pestana.scrollIntoView({ block: 'nearest', inline: 'nearest' });
                }
            });

            paneles.forEach(function (panel)
            {
                panel.hidden = panel.id !== 'panel-' + clave;
            });

            if (actualizarHash && window.history && history.replaceState)
            {
                history.replaceState(null, '', '#' + clave);
            }
        };

        pestanas.forEach(function (pestana, indice)
        {
            pestana.addEventListener('click', function () { activar(pestana.dataset.pestana, true); });

            pestana.addEventListener('keydown', function (evento)
            {
                var destino = null;

                if (evento.key === 'ArrowRight') { destino = pestanas[(indice + 1) % pestanas.length]; }
                else if (evento.key === 'ArrowLeft') { destino = pestanas[(indice - 1 + pestanas.length) % pestanas.length]; }
                else if (evento.key === 'Home') { destino = pestanas[0]; }
                else if (evento.key === 'End') { destino = pestanas[pestanas.length - 1]; }

                if (destino)
                {
                    evento.preventDefault();
                    destino.focus();
                    activar(destino.dataset.pestana, true);
                }
            });
        });

        // enlaces internos como el de las estrellas de la cabecera
        document.querySelectorAll('[data-ir-pestana]').forEach(function (enlace)
        {
            enlace.addEventListener('click', function (evento)
            {
                evento.preventDefault();
                activar(enlace.dataset.irPestana, true);
                contenedorPestanas.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        activar((location.hash || '').replace('#', '') || 'servicios', false);
    }

    // ---------- 3. Coordenadas del listado ----------
    var campoUbicacion = document.getElementById('campoUbicacionListado');

    if (campoUbicacion)
    {
        var valorOriginal = campoUbicacion.value;

        campoUbicacion.addEventListener('input', function ()
        {
            if (campoUbicacion.value === valorOriginal)
            {
                return;
            }

            document.getElementById('campoLatitudListado').value = '';
            document.getElementById('campoLongitudListado').value = '';
            document.getElementById('campoTipoListado').value = 'texto';
        });
    }
})();
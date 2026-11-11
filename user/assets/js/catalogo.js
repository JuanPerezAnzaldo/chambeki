/*
    Catalogo de servicios: mejoras del formulario (monto segun modalidad
    de cobro, contador de caracteres y galeria de imagenes con vista
    previa y reduccion de tamano antes de subir).

    Solo mejora la experiencia: el servidor valida todo otra vez (RS-05).
*/
(function ()
{
    'use strict';

    var MB = 1024 * 1024;
    var LADO_MAXIMO = 1600;      //px: las fotos mas grandes se reducen
    var PESO_MAXIMO = 2 * MB;    //igual que el servidor
    var CALIDAD_JPEG = 0.85;

    var formulario = document.getElementById('formServicio');

    if (!formulario)
    {
        return;
    }

    // ---- monto: no aplica cuando el cobro es "A cotizar"
    var selectTipo = document.getElementById('catTipoPrecio');
    var grupoMonto = document.getElementById('grupoMontoServicio');

    function actualizarMonto()
    {
        var aCotizar = parseInt(selectTipo.getAttribute('data-a-cotizar'), 10);

        grupoMonto.hidden = parseInt(selectTipo.value, 10) === aCotizar;
    }

    if (selectTipo && grupoMonto)
    {
        selectTipo.addEventListener('change', actualizarMonto);
        actualizarMonto();
    }

    // ---- contador de la descripcion
    var area = document.getElementById('catDescripcion');
    var contador = document.getElementById('contadorServicio');

    if (area && contador)
    {
        var actualizarContador = function () { contador.textContent = area.value.length; };

        area.addEventListener('input', actualizarContador);
        actualizarContador();
    }

    // ---- galeria
    var bloque = document.getElementById('bloqueGaleria');
    var entrada = document.getElementById('catImagenes');
    var vistaPrevia = document.getElementById('vistaPreviaImagenes');
    var errorCliente = document.getElementById('errorImagenesCliente');

    if (!bloque || !entrada)
    {
        return;
    }

    var maximo = parseInt(bloque.getAttribute('data-maximo'), 10) || 6;
    var actuales = parseInt(bloque.getAttribute('data-actuales'), 10) || 0;

    function mostrarError(texto)
    {
        errorCliente.textContent = texto;
        errorCliente.hidden = texto === '';
    }

    //lugares libres = maximo - imagenes actuales que NO se van a quitar
    function lugaresLibres()
    {
        var quitadas = bloque.querySelectorAll('[data-quitar-imagen]:checked').length;

        return maximo - (actuales - quitadas);
    }

    function soportaReduccion()
    {
        return typeof window.DataTransfer === 'function' && typeof window.File === 'function' && typeof document.createElement('canvas').toBlob === 'function';
    }

    function cargarImagen(archivo)
    {
        return new Promise(function (resolver, rechazar)
        {
            var url = URL.createObjectURL(archivo);
            var imagen = new Image();

            imagen.onload = function () { URL.revokeObjectURL(url); resolver(imagen); };
            imagen.onerror = function () { URL.revokeObjectURL(url); rechazar(new Error('imagen')); };
            imagen.src = url;
        });
    }

    //reduce una foto grande a JPEG de maximo 1600 px; si no hace falta (o falla), devuelve la original
    function reducir(archivo)
    {
        if (!soportaReduccion())
        {
            return Promise.resolve(archivo);
        }

        return cargarImagen(archivo).then(function (imagen)
        {
            var mayor = Math.max(imagen.naturalWidth, imagen.naturalHeight);

            if (mayor <= LADO_MAXIMO && archivo.size <= PESO_MAXIMO)
            {
                return archivo;
            }

            var escala = Math.min(1, LADO_MAXIMO / mayor);
            var lienzo = document.createElement('canvas');

            lienzo.width = Math.round(imagen.naturalWidth * escala);
            lienzo.height = Math.round(imagen.naturalHeight * escala);

            var contexto = lienzo.getContext('2d');

            contexto.fillStyle = '#ffffff';   //los PNG con transparencia quedan sobre blanco
            contexto.fillRect(0, 0, lienzo.width, lienzo.height);
            contexto.drawImage(imagen, 0, 0, lienzo.width, lienzo.height);

            return new Promise(function (resolver)
            {
                lienzo.toBlob(function (blob)
                {
                    if (!blob || (blob.size >= archivo.size && mayor <= LADO_MAXIMO))
                    {
                        resolver(archivo);
                        return;
                    }

                    var nombre = archivo.name.replace(/\.[^.]+$/, '') + '.jpg';

                    resolver(new File([blob], nombre, { type: 'image/jpeg' }));
                }, 'image/jpeg', CALIDAD_JPEG);
            });
        }).catch(function () { return archivo; });
    }

    function pintarVistaPrevia(archivos)
    {
        vistaPrevia.innerHTML = '';

        archivos.forEach(function (archivo)
        {
            var item = document.createElement('li');
            var imagen = document.createElement('img');

            imagen.alt = 'Vista previa de ' + archivo.name;
            imagen.src = URL.createObjectURL(archivo);
            item.appendChild(imagen);
            vistaPrevia.appendChild(item);
        });

        vistaPrevia.hidden = archivos.length === 0;
    }

    function limpiarSeleccion()
    {
        entrada.value = '';
        pintarVistaPrevia([]);
    }

    entrada.addEventListener('change', function ()
    {
        var seleccion = Array.prototype.slice.call(entrada.files || []);

        mostrarError('');
        pintarVistaPrevia([]);

        if (seleccion.length === 0)
        {
            return;
        }

        var libres = lugaresLibres();

        if (seleccion.length > libres)
        {
            mostrarError(libres > 0
                ? 'Solo puedes subir ' + libres + ' imagen(es) mas en este servicio (maximo ' + maximo + ').'
                : 'Ya llegaste al maximo de ' + maximo + ' imagenes. Marca alguna como "Quitar" para subir otra.');
            limpiarSeleccion();
            return;
        }

        for (var i = 0; i < seleccion.length; i++)
        {
            if (!/^image\/(jpeg|png|webp)$/.test(seleccion[i].type))
            {
                mostrarError('"' + seleccion[i].name + '" no es JPG, PNG ni WEBP.');
                limpiarSeleccion();
                return;
            }
        }

        Promise.all(seleccion.map(reducir)).then(function (listas)
        {
            for (var j = 0; j < listas.length; j++)
            {
                if (listas[j].size > PESO_MAXIMO)
                {
                    mostrarError('"' + listas[j].name + '" pesa mas de 2MB incluso reducida. Elige otra imagen.');
                    limpiarSeleccion();
                    return;
                }
            }

            //se reemplazan los archivos del input por las versiones reducidas
            if (soportaReduccion())
            {
                try
                {
                    var transferencia = new DataTransfer();

                    listas.forEach(function (archivo) { transferencia.items.add(archivo); });
                    entrada.files = transferencia.files;
                }
                catch (error) { /* si no se puede, se suben las originales */ }
            }

            pintarVistaPrevia(listas);
        });
    });

    //si quita imagenes actuales, los lugares libres cambian: se revisa de nuevo lo ya elegido
    bloque.addEventListener('change', function (evento)
    {
        if (evento.target.hasAttribute && evento.target.hasAttribute('data-quitar-imagen') && entrada.files && entrada.files.length > lugaresLibres())
        {
            mostrarError('Con esa seleccion pasas de ' + maximo + ' imagenes. Vuelve a elegir las nuevas.');
            limpiarSeleccion();
        }
    });

    // ---- evita el doble envio (y no se bloquea si otra validacion cancela el envio)
    formulario.addEventListener('submit', function (evento)
    {
        var boton = formulario.querySelector('button[type="submit"]');

        setTimeout(function ()
        {
            if (evento.defaultPrevented || !boton)
            {
                return;
            }

            boton.disabled = true;
            boton.textContent = 'Guardando...';
        }, 0);
    });
})();

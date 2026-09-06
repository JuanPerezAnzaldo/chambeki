document.addEventListener('DOMContentLoaded', () =>
{
    const inputOficio = document.getElementById('inputOficio');
    const btnLimpiarOficio = document.getElementById('btnLimpiarOficio');

    const inputUbicacion = document.getElementById('inputUbicacion');
    const btnLimpiarUbicacion = document.getElementById('btnLimpiarUbicacion');
    const menuUbicaciones = document.getElementById('menuUbicaciones');
    const listaSugerenciasPC = document.getElementById('listaUbicacionesSugeridas');

    const modalUbicacionMovil = document.getElementById('modalUbicacionMovil');
    const btnCerrarModalMovil = document.getElementById('btnCerrarModalMovil');
    const inputUbicacionMovil = document.getElementById('inputUbicacionMovil');
    const btnLimpiarUbicacionMovil = document.getElementById('btnLimpiarUbicacionMovil');
    const listaUbicacionesMovil = document.getElementById('listaUbicacionesMovil');

    const campoLatitud = document.getElementById('campoLatitud');
    const campoLongitud = document.getElementById('campoLongitud');
    const campoTipoUbicacion = document.getElementById('campoTipoUbicacion');
    const etiquetasGps = document.querySelectorAll('.textoGpsEtiqueta');

    let temporizadorEscritura = null;

    function esDispositivoMovil()
    {
        return window.innerWidth <= 640;
    }

    // Configuración de borrado del campo Oficio
    if (inputOficio && btnLimpiarOficio)
    {
        inputOficio.addEventListener('input', () =>
        {
            btnLimpiarOficio.classList.toggle('oculto', inputOficio.value.trim() === '');
        });

        btnLimpiarOficio.addEventListener('click', () =>
        {
            inputOficio.value = '';
            btnLimpiarOficio.classList.add('oculto');
            inputOficio.focus();
        });
    }

    // Configuración de borrado del campo Ubicación Principal
    if (inputUbicacion && btnLimpiarUbicacion)
    {
        inputUbicacion.addEventListener('input', () =>
        {
            btnLimpiarUbicacion.classList.toggle('oculto', inputUbicacion.value.trim() === '');
        });

        btnLimpiarUbicacion.addEventListener('click', (evento) =>
        {
            evento.stopPropagation();
            limpiarUbicacionSeleccionada();
            if (!esDispositivoMovil())
            {
                inputUbicacion.focus();
            }
        });
    }

    // Control de apertura según dispositivo
    if (inputUbicacion)
    {
        inputUbicacion.addEventListener('click', () =>
        {
            if (esDispositivoMovil())
            {
                // En móvil evita abrir teclado del input principal
                inputUbicacion.blur();
                abrirModalMovil();
            }
            else
            {
                menuUbicaciones.classList.remove('oculto');
            }
        });

        inputUbicacion.addEventListener('focus', () =>
        {
            if (esDispositivoMovil())
            {
                inputUbicacion.blur();
                abrirModalMovil();
            }
            else
            {
                menuUbicaciones.classList.remove('oculto');
            }
        });
    }

    // Cierre en PC al hacer clic afuera
    document.addEventListener('click', (evento) =>
    {
        if (!evento.target.closest('.contenedor-desplegable-ubicacion') && menuUbicaciones)
        {
            menuUbicaciones.classList.add('oculto');
        }
    });

    // Delegación de clics en PC
    if (menuUbicaciones)
    {
        menuUbicaciones.addEventListener('click', procesarClicOpcion);
    }

    // Gestión del Modal Móvil
    function abrirModalMovil()
    {
        modalUbicacionMovil.classList.remove('oculto');
        document.body.style.overflow = 'hidden';
        inputUbicacionMovil.value = inputUbicacion.value;
        btnLimpiarUbicacionMovil.classList.toggle('oculto', inputUbicacionMovil.value.trim() === '');
    }

    function cerrarModalMovil()
    {
        modalUbicacionMovil.classList.add('oculto');
        document.body.style.overflow = '';
    }

    if (btnCerrarModalMovil)
    {
        btnCerrarModalMovil.addEventListener('click', cerrarModalMovil);
    }

    if (modalUbicacionMovil)
    {
        modalUbicacionMovil.addEventListener('click', (evento) =>
        {
            if (evento.target.closest('.item-opcion-ubicacion'))
            {
                procesarClicOpcion(evento);
                cerrarModalMovil();
            }
        });
    }

    // Borrado dentro del modal móvil
    if (inputUbicacionMovil && btnLimpiarUbicacionMovil)
    {
        inputUbicacionMovil.addEventListener('input', () =>
        {
            btnLimpiarUbicacionMovil.classList.toggle('oculto', inputUbicacionMovil.value.trim() === '');
            ejecutarAutocompletado(inputUbicacionMovil.value.trim(), listaUbicacionesMovil);
        });

        btnLimpiarUbicacionMovil.addEventListener('click', () =>
        {
            inputUbicacionMovil.value = '';
            btnLimpiarUbicacionMovil.classList.add('oculto');
            limpiarUbicacionSeleccionada();
            inputUbicacionMovil.focus();
        });
    }

    // Escritura en PC
    if (inputUbicacion)
    {
        inputUbicacion.addEventListener('input', () =>
        {
            if (!esDispositivoMovil())
            {
                ejecutarAutocompletado(inputUbicacion.value.trim(), listaSugerenciasPC);
            }
        });
    }

    function procesarClicOpcion(evento)
    {
        const botonOpcion = evento.target.closest('.item-opcion-ubicacion');
        if (!botonOpcion)
        {
            return;
        }

        const tipo = botonOpcion.getAttribute('data-tipo');

        if (tipo === 'online')
        {
            seleccionarOpcion('En línea', '', '', 'online');
        }
        else if (tipo === 'gps')
        {
            obtenerUbicacionActual();
        }
        else
        {
            const lat = botonOpcion.getAttribute('data-lat') || '';
            const lon = botonOpcion.getAttribute('data-lon') || '';
            const nombre = botonOpcion.querySelector('.texto-opcion').childNodes[0].textContent.trim();
            seleccionarOpcion(nombre, lat, lon, 'lugar');
        }
    }

    function seleccionarOpcion(texto, lat, lon, tipo)
    {
        inputUbicacion.value = texto;
        if (inputUbicacionMovil)
        {
            inputUbicacionMovil.value = texto;
        }

        campoLatitud.value = lat;
        campoLongitud.value = lon;
        campoTipoUbicacion.value = tipo;

        btnLimpiarUbicacion.classList.remove('oculto');
        if (btnLimpiarUbicacionMovil)
        {
            btnLimpiarUbicacionMovil.classList.remove('oculto');
        }

        if (menuUbicaciones)
        {
            menuUbicaciones.classList.add('oculto');
        }
    }

    function limpiarUbicacionSeleccionada()
    {
        inputUbicacion.value = '';
        if (inputUbicacionMovil)
        {
            inputUbicacionMovil.value = '';
        }

        campoLatitud.value = '';
        campoLongitud.value = '';
        campoTipoUbicacion.value = 'texto';

        btnLimpiarUbicacion.classList.add('oculto');
        if (btnLimpiarUbicacionMovil)
        {
            btnLimpiarUbicacionMovil.classList.add('oculto');
        }
    }

    function ejecutarAutocompletado(termino, contenedorLista)
    {
        campoLatitud.value = '';
        campoLongitud.value = '';
        campoTipoUbicacion.value = 'texto';

        clearTimeout(temporizadorEscritura);

        if (termino.length < 3)
        {
            return;
        }

        temporizadorEscritura = setTimeout(() =>
        {
            consultarNominatim(termino, contenedorLista);
        }, 350);
    }

    function obtenerUbicacionActual()
    {
        if (!navigator.geolocation)
        {
            alert('La geolocalización no es compatible con tu navegador');
            return;
        }

        etiquetasGps.forEach((etiqueta) =>
        {
            etiqueta.textContent = 'Localizando...';
        });

        navigator.geolocation.getCurrentPosition(
            (posicion) =>
            {
                const lat = posicion.coords.latitude;
                const lon = posicion.coords.longitude;

                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}&zoom=14&addressdetails=1`)
                    .then((resp) => resp.json())
                    .then((datos) =>
                    {
                        const ciudad = datos.address.city || datos.address.town || datos.address.suburb || 'Mi ubicación actual';
                        seleccionarOpcion(`Cerca de mí (${ciudad})`, lat, lon, 'gps');
                    })
                    .catch(() =>
                    {
                        seleccionarOpcion('Cerca de mí', lat, lon, 'gps');
                    })
                    .finally(() =>
                    {
                        etiquetasGps.forEach((etiqueta) =>
                        {
                            etiqueta.textContent = 'Cerca de mí';
                        });
                    });
            },
            () =>
            {
                etiquetasGps.forEach((etiqueta) =>
                {
                    etiqueta.textContent = 'Cerca de mí';
                });
                alert('No se pudo acceder a tu ubicación. Verifica los permisos de tu navegador.');
            },
            {
                enableHighAccuracy: true,
                timeout: 8000
            }
        );
    }

    function consultarNominatim(query, contenedorLista)
    {
        if (!contenedorLista)
        {
            return;
        }

        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=mx&addressdetails=1&limit=5`;

        fetch(url)
            .then((resp) => resp.json())
            .then((lugares) =>
            {
                if (!lugares || lugares.length === 0)
                {
                    return;
                }

                contenedorLista.innerHTML = '';

                lugares.forEach((lugar) =>
                {
                    const boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'item-opcion-ubicacion';
                    boton.setAttribute('data-tipo', 'lugar');
                    boton.setAttribute('data-lat', lugar.lat);
                    boton.setAttribute('data-lon', lugar.lon);

                    const nombrePrincipal = lugar.name || lugar.display_name.split(',')[0];

                    boton.innerHTML = `
                        <span class="icono-opcion pin">
                            <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        </span>
                        <span class="texto-opcion">
                            ${nombrePrincipal}
                            <span class="subtexto-opcion">${lugar.display_name}</span>
                        </span>
                    `;

                    contenedorLista.appendChild(boton);
                });
            })
            .catch((err) =>
            {
                console.error('Error al autocompletar ubicaciones:', err);
            });
    }
});
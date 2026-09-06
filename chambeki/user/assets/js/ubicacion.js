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

    // Catálogo inicial de ciudades predeterminadas
    const ciudadesFijas = [
        { nombre: 'Tijuana', lat: '32.5149', lon: '-117.0382' },
        { nombre: 'Ciudad de México', lat: '19.4326', lon: '-99.1332' },
        { nombre: 'Guadalajara', lat: '20.6597', lon: '-103.3496' },
        { nombre: 'Monterrey', lat: '25.6866', lon: '-100.3161' },
        { nombre: 'Puebla', lat: '19.0414', lon: '-98.2063' }
    ];

    function esDispositivoMovil()
    {
        return window.innerWidth <= 640;
    }

    function ajustarAtributoReadOnly()
    {
        if (inputUbicacion)
        {
            if (esDispositivoMovil())
            {
                inputUbicacion.setAttribute('readonly', 'true');
            }
            else
            {
                inputUbicacion.removeAttribute('readonly');
            }
        }
    }

    ajustarAtributoReadOnly();
    window.addEventListener('resize', ajustarAtributoReadOnly);

    // Renderizado de ciudades fijas en listas
    function renderizarCiudadesIniciales(contenedor)
    {
        if (!contenedor)
        {
            return;
        }

        contenedor.innerHTML = '';
        ciudadesFijas.forEach((ciudad) =>
        {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'item-opcion-ubicacion';
            boton.setAttribute('data-tipo', 'ciudad');
            boton.setAttribute('data-lat', ciudad.lat);
            boton.setAttribute('data-lon', ciudad.lon);

            boton.innerHTML = `
                <span class="icono-opcion pin">
                    <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                </span>
                <span class="texto-opcion">${ciudad.nombre}</span>
            `;
            contenedor.appendChild(boton);
        });
    }

    // Inicializar listas
    renderizarCiudadesIniciales(listaSugerenciasPC);
    renderizarCiudadesIniciales(listaUbicacionesMovil);

    // Botón borrar Oficio
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

    // Botón borrar Ubicación Principal
    if (inputUbicacion && btnLimpiarUbicacion)
    {
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

    // Control de apertura
    if (inputUbicacion)
    {
        inputUbicacion.addEventListener('click', () =>
        {
            if (esDispositivoMovil())
            {
                abrirModalMovil();
            }
            else
            {
                menuUbicaciones.classList.remove('oculto');
            }
        });
    }

    // Cierre en PC
    document.addEventListener('click', (evento) =>
    {
        if (!evento.target.closest('.contenedor-desplegable-ubicacion') && menuUbicaciones)
        {
            menuUbicaciones.classList.add('oculto');
        }
    });

    if (menuUbicaciones)
    {
        menuUbicaciones.addEventListener('click', procesarClicOpcion);
    }

    // Funciones del Modal Móvil
    function abrirModalMovil()
    {
        modalUbicacionMovil.classList.remove('oculto');
        document.body.style.overflow = 'hidden';
        inputUbicacionMovil.value = inputUbicacion.value;
        btnLimpiarUbicacionMovil.classList.toggle('oculto', inputUbicacionMovil.value.trim() === '');

        if (inputUbicacionMovil.value.trim() === '')
        {
            renderizarCiudadesIniciales(listaUbicacionesMovil);
        }
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

    // Input y botón borrar en modal móvil
    if (inputUbicacionMovil && btnLimpiarUbicacionMovil)
    {
        inputUbicacionMovil.addEventListener('input', () =>
        {
            const valor = inputUbicacionMovil.value.trim();
            btnLimpiarUbicacionMovil.classList.toggle('oculto', valor === '');

            if (valor.length === 0)
            {
                renderizarCiudadesIniciales(listaUbicacionesMovil);
            }
            else
            {
                ejecutarAutocompletado(valor, listaUbicacionesMovil);
            }
        });

        btnLimpiarUbicacionMovil.addEventListener('click', () =>
        {
            inputUbicacionMovil.value = '';
            btnLimpiarUbicacionMovil.classList.add('oculto');
            limpiarUbicacionSeleccionada();
            renderizarCiudadesIniciales(listaUbicacionesMovil);
            inputUbicacionMovil.focus();
        });
    }

    // Input en PC
    if (inputUbicacion)
    {
        inputUbicacion.addEventListener('input', () =>
        {
            if (!esDispositivoMovil())
            {
                const valor = inputUbicacion.value.trim();
                btnLimpiarUbicacion.classList.toggle('oculto', valor === '');

                if (valor.length === 0)
                {
                    renderizarCiudadesIniciales(listaSugerenciasPC);
                }
                else
                {
                    ejecutarAutocompletado(valor, listaSugerenciasPC);
                }
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
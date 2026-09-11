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

    // ciudades predeterminadas
    const ciudadesFijas = [
        { nombre: 'Tijuana', lat: '32.5149', lon: '-117.0382' },
        { nombre: 'Ciudad de México', lat: '19.4326', lon: '-99.1332' },
        { nombre: 'Guadalajara', lat: '20.6597', lon: '-103.3496' },
        { nombre: 'Monterrey', lat: '25.6866', lon: '-100.3161' },
        { nombre: 'Puebla', lat: '19.0414', lon: '-98.2063' }
    ];

    function esDispositivoMovil()
    {
        const pantallaChica = window.innerWidth <= 768;
        const pantallaTactil = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        return pantallaChica || pantallaTactil;
    }

    // Bloquea el teclado en el input de telefonoss
    function configurarModoInputPrincipal()
    {
        if (!inputUbicacion)
        {
            return;
        }

        if (esDispositivoMovil())
        {
            inputUbicacion.setAttribute('readonly', 'true');
            inputUbicacion.setAttribute('inputmode', 'none');
        }
        else
        {
            inputUbicacion.removeAttribute('readonly');
            inputUbicacion.removeAttribute('inputmode');
        }
    }

    configurarModoInputPrincipal();
    window.addEventListener('resize', configurarModoInputPrincipal);


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

    renderizarCiudadesIniciales(listaSugerenciasPC);
    renderizarCiudadesIniciales(listaUbicacionesMovil);

    // Botón borrar
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

    // Botón borrar 
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

    // Apertura de la ubicacion
    if (inputUbicacion)
    {
        const abrirSelectorUbicacion = (evento) =>
        {
            if (esDispositivoMovil())
            {
                if (evento.cancelable)
                {
                    evento.preventDefault();
                }
                inputUbicacion.blur();
                abrirModalMovil();
            }
            else
            {
                if (menuUbicaciones)
                {
                    menuUbicaciones.classList.remove('oculto');
                }
            }
        };

        inputUbicacion.addEventListener('pointerdown', (e) =>
        {
            if (esDispositivoMovil())
            {
                abrirSelectorUbicacion(e);
            }
        });

        inputUbicacion.addEventListener('click', abrirSelectorUbicacion);
    }

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

    function abrirModalMovil()
    {
        if (!modalUbicacionMovil)
        {
            return;
        }

        modalUbicacionMovil.classList.remove('oculto');
        document.body.style.overflow = 'hidden';

        if (inputUbicacionMovil)
        {
            inputUbicacionMovil.value = inputUbicacion ? inputUbicacion.value : '';
            if (btnLimpiarUbicacionMovil)
            {
                btnLimpiarUbicacionMovil.classList.toggle('oculto', inputUbicacionMovil.value.trim() === '');
            }

            if (inputUbicacionMovil.value.trim() === '')
            {
                renderizarCiudadesIniciales(listaUbicacionesMovil);
            }
        }
    }

    function cerrarModalMovil()
    {
        if (!modalUbicacionMovil)
        {
            return;
        }

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

    if (inputUbicacion)
    {
        inputUbicacion.addEventListener('input', () =>
        {
            if (!esDispositivoMovil())
            {
                const valor = inputUbicacion.value.trim();
                if (btnLimpiarUbicacion)
                {
                    btnLimpiarUbicacion.classList.toggle('oculto', valor === '');
                }

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
        if (inputUbicacion)
        {
            inputUbicacion.value = texto;
        }

        if (inputUbicacionMovil)
        {
            inputUbicacionMovil.value = texto;
        }

        if (campoLatitud)
        {
            campoLatitud.value = lat;
        }

        if (campoLongitud)
        {
            campoLongitud.value = lon;
        }

        if (campoTipoUbicacion)
        {
            campoTipoUbicacion.value = tipo;
        }

        if (btnLimpiarUbicacion)
        {
            btnLimpiarUbicacion.classList.remove('oculto');
        }

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
        if (inputUbicacion)
        {
            inputUbicacion.value = '';
        }

        if (inputUbicacionMovil)
        {
            inputUbicacionMovil.value = '';
        }

        if (campoLatitud)
        {
            campoLatitud.value = '';
        }

        if (campoLongitud)
        {
            campoLongitud.value = '';
        }

        if (campoTipoUbicacion)
        {
            campoTipoUbicacion.value = 'texto';
        }

        if (btnLimpiarUbicacion)
        {
            btnLimpiarUbicacion.classList.add('oculto');
        }

        if (btnLimpiarUbicacionMovil)
        {
            btnLimpiarUbicacionMovil.classList.add('oculto');
        }
    }

    function ejecutarAutocompletado(termino, contenedorLista)
    {
        if (campoLatitud)
        {
            campoLatitud.value = '';
        }

        if (campoLongitud)
        {
            campoLongitud.value = '';
        }

        if (campoTipoUbicacion)
        {
            campoTipoUbicacion.value = 'texto';
        }

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
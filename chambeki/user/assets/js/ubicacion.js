document.addEventListener('DOMContentLoaded', () =>
{
    const inputUbicacion = document.getElementById('inputUbicacion');
    const menuUbicaciones = document.getElementById('menuUbicaciones');
    const listaSugerencias = document.getElementById('listaUbicacionesSugeridas');
    const campoLatitud = document.getElementById('campoLatitud');
    const campoLongitud = document.getElementById('campoLongitud');
    const campoTipoUbicacion = document.getElementById('campoTipoUbicacion');
    const textoGps = document.getElementById('textoGps');

    let temporizadorEscritura = null;

    if (!inputUbicacion || !menuUbicaciones)
    {
        return;
    }

    // Abre el menú al enfocar o hacer clic
    inputUbicacion.addEventListener('focus', () =>
    {
        menuUbicaciones.classList.remove('oculto');
    });

    // Cierra el menú al hacer clic fuera del componente
    document.addEventListener('click', (evento) =>
    {
        if (!evento.target.closest('.contenedor-desplegable-ubicacion'))
        {
            menuUbicaciones.classList.add('oculto');
        }
    });

    // Delegación de clics en las opciones fijas o dinámicas
    menuUbicaciones.addEventListener('click', (evento) =>
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
    });

    // Autocompletado con Nominatim en tiempo real al escribir
    inputUbicacion.addEventListener('input', () =>
    {
        const busqueda = inputUbicacion.value.trim();

        campoLatitud.value = '';
        campoLongitud.value = '';
        campoTipoUbicacion.value = 'texto';

        clearTimeout(temporizadorEscritura);

        if (busqueda.length < 3)
        {
            return;
        }

        temporizadorEscritura = setTimeout(() =>
        {
            consultarNominatim(busqueda);
        }, 350); // Espera 350ms para evitar peticiones excesivas
    });

    function seleccionarOpcion(texto, lat, lon, tipo)
    {
        inputUbicacion.value = texto;
        campoLatitud.value = lat;
        campoLongitud.value = lon;
        campoTipoUbicacion.value = tipo;
        menuUbicaciones.classList.add('oculto');
    }

    // API de Geolocalización Nativa del Navegador
    function obtenerUbicacionActual()
    {
        if (!navigator.geolocation)
        {
            alert('La geolocalización no es compatible con tu navegador');
            return;
        }

        textoGps.textContent = 'Localizando...';

        navigator.geolocation.getCurrentPosition(
            (posicion) =>
            {
                const lat = posicion.coords.latitude;
                const lon = posicion.coords.longitude;

                // Geocodificación inversa para mostrar colonia/ciudad al usuario
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
                        textoGps.textContent = 'Cerca de mí';
                    });
            },
            (error) =>
            {
                textoGps.textContent = 'Cerca de mí';
                alert('No se pudo acceder a tu ubicación. Verifica los permisos de tu navegador.');
            },
            {
                enableHighAccuracy: true,
                timeout: 8000
            }
        );
    }

    // Consulta de Autocompletado a OpenStreetMap Nominatim
    function consultarNominatim(query)
    {
        // Prioriza resultados de México (countrycodes=mx)
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=mx&addressdetails=1&limit=5`;

        fetch(url)
            .then((resp) => resp.json())
            .then((lugares) =>
            {
                if (!lugares || lugares.length === 0)
                {
                    return;
                }

                listaSugerencias.innerHTML = '';

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

                    listaSugerencias.appendChild(boton);
                });
            })
            .catch((err) =>
            {
                console.error('Error al autocompletar ubicaciones:', err);
            });
    }
});
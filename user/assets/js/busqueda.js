/*
    Sugerencias de oficios para el buscador (home y resultados).
    Filtra mientras se escribe, sin importar acentos ni mayusculas.
    Teclado: flechas para moverse, Enter para elegir, Escape para cerrar.
*/
document.addEventListener('DOMContentLoaded', () =>
{
    const entradaOficio = document.getElementById('inputOficio');
    const menuOficios = document.getElementById('menuOficios');
    const botonLimpiar = document.getElementById('btnLimpiarOficio');

    if (!entradaOficio || !menuOficios)
    {
        return;
    }

    //Conviene que coincida con las categorias de la BD
    const oficios = [
        'Electricista', 'Plomero', 'Carpintero', 'Técnico A/C', 'Pintor', 'Cerrajero', 'Albañil',
        'Instalación eléctrica', 'Fuga de gas', 'Destape de cañerías', 'Cerrajería 24/7',
        'Impermeabilización', 'Herrería y soldadura', 'Mantenimiento de Boiler',
        'Instalación de minisplit', 'Reparación de lavadoras', 'Jardinero', 'Limpieza del hogar',
        'Fumigador', 'Vidriero', 'Instalación de pisos', 'Técnico en refrigeración',
        'Instalación de cámaras', 'Reparación de computadoras', 'Mecánico', 'Mudanzas'
    ];

    const MAXIMO_SUGERENCIAS = 8;
    let indiceActivo = -1;

    function normalizar(texto)
    {
        return texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function buscarCoincidencias(texto)
    {
        const consulta = normalizar(texto);

        if (consulta === '')
        {
            return oficios.slice(0, 6);
        }

        const empiezaCon = [];
        const contiene = [];

        oficios.forEach((oficio) =>
        {
            const normal = normalizar(oficio);

            if (normal.startsWith(consulta))
            {
                empiezaCon.push(oficio);
            }
            else if (normal.includes(consulta))
            {
                contiene.push(oficio);
            }
        });

        return empiezaCon.concat(contiene).slice(0, MAXIMO_SUGERENCIAS);
    }

    function abrirMenu()
    {
        menuOficios.classList.remove('oculto');
        entradaOficio.setAttribute('aria-expanded', 'true');
    }

    function cerrarMenu()
    {
        menuOficios.classList.add('oculto');
        entradaOficio.setAttribute('aria-expanded', 'false');
        indiceActivo = -1;
    }

    function elegirOficio(oficio)
    {
        entradaOficio.value = oficio;

        if (botonLimpiar)
        {
            botonLimpiar.classList.remove('oculto');
        }

        cerrarMenu();
        entradaOficio.focus();
    }

    function marcarActivo()
    {
        const items = menuOficios.querySelectorAll('.item-oficio');

        items.forEach((item, posicion) =>
        {
            item.classList.toggle('activo', posicion === indiceActivo);
            item.setAttribute('aria-selected', posicion === indiceActivo ? 'true' : 'false');
        });
    }

    function pintarMenu()
    {
        const lista = buscarCoincidencias(entradaOficio.value);

        menuOficios.innerHTML = '';
        indiceActivo = -1;

        if (lista.length === 0)
        {
            const aviso = document.createElement('div');
            aviso.className = 'sin-resultados-oficio';
            aviso.textContent = 'Sin coincidencias. Presiona buscar para intentar con ese texto.';
            menuOficios.appendChild(aviso);
            abrirMenu();
            return;
        }

        lista.forEach((oficio) =>
        {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'item-oficio';
            boton.setAttribute('role', 'option');
            boton.textContent = oficio;

            //mousedown (no click) para que el input no pierda el foco antes de elegir
            boton.addEventListener('mousedown', (evento) =>
            {
                evento.preventDefault();
                elegirOficio(oficio);
            });

            menuOficios.appendChild(boton);
        });

        abrirMenu();
    }

    entradaOficio.addEventListener('input', pintarMenu);
    entradaOficio.addEventListener('focus', pintarMenu);

    entradaOficio.addEventListener('keydown', (evento) =>
    {
        const items = menuOficios.querySelectorAll('.item-oficio');

        if (menuOficios.classList.contains('oculto') || items.length === 0)
        {
            return;
        }

        if (evento.key === 'ArrowDown')
        {
            evento.preventDefault();
            indiceActivo = (indiceActivo + 1) % items.length;
            marcarActivo();
        }
        else if (evento.key === 'ArrowUp')
        {
            evento.preventDefault();
            indiceActivo = (indiceActivo - 1 + items.length) % items.length;
            marcarActivo();
        }
        else if (evento.key === 'Enter' && indiceActivo >= 0)
        {
            evento.preventDefault();
            elegirOficio(items[indiceActivo].textContent);
        }
        else if (evento.key === 'Escape')
        {
            cerrarMenu();
        }
    });

    if (botonLimpiar)
    {
        botonLimpiar.addEventListener('click', cerrarMenu);
    }

    document.addEventListener('click', (evento) =>
    {
        if (!evento.target.closest('.campo-oficio'))
        {
            cerrarMenu();
        }
    });
});
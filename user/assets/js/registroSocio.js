/*
    Wizard "Ofrece tu servicio": muestra un paso a la vez y valida
    cada uno antes de avanzar. El servidor repite todas las
    validaciones (funcionesSocio.php), esto es solo comodidad.
*/
(function ()
{
    const formulario = document.getElementById('formularioSocio');

    if (!formulario)
    {
        return;
    }

    const pasos = Array.from(formulario.querySelectorAll('.paso-wizard'));
    const marcas = Array.from(document.querySelectorAll('.paso-progreso'));
    const botonAtras = document.getElementById('botonAtras');
    const botonSiguiente = document.getElementById('botonSiguiente');
    const botonEnviar = document.getElementById('botonEnviar');
    const maxCategorias = parseInt(formulario.dataset.maxCategorias, 10) || 5;

    let pasoActual = parseInt(formulario.dataset.pasoInicial, 10) || 1;

    function campo(nombre)
    {
        return formulario.elements[nombre];
    }

    function mostrarError(nombre, mensaje)
    {
        const zona = formulario.querySelector('[data-error-para="' + nombre + '"]');

        if (zona)
        {
            zona.textContent = mensaje;
        }
    }

    function limpiarErroresDelPaso(numero)
    {
        formulario.querySelectorAll('.paso-wizard[data-paso="' + numero + '"] .error-campo').forEach(function (zona)
        {
            zona.textContent = '';
        });
    }

    function modalidadElegida()
    {
        return formulario.querySelector('input[name="id_modalidad"]:checked');
    }

    // ---------- validacion por paso ----------
    function validarPaso(numero)
    {
        limpiarErroresDelPaso(numero);
        let valido = true;

        if (numero === 1)
        {
            const nombre = campo('nombre_negocio').value.trim();
            const descripcion = campo('descripcion').value.trim();

            if (nombre.length < 3 || nombre.length > 150)
            {
                mostrarError('nombre_negocio', 'Escribe el nombre de tu negocio o tu nombre profesional (3 a 150 caracteres).');
                valido = false;
            }

            if (descripcion.length < 20 || descripcion.length > 600)
            {
                mostrarError('descripcion', 'Describe tu servicio en 20 a 600 caracteres.');
                valido = false;
            }
        }

        if (numero === 2)
        {
            const elegidas = formulario.querySelectorAll('input[name="categorias[]"]:checked').length;

            if (elegidas < 1)
            {
                mostrarError('categorias', 'Elige al menos una categoria.');
                valido = false;
            }
        }

        if (numero === 3)
        {
            const modalidad = modalidadElegida();

            if (!modalidad)
            {
                mostrarError('id_modalidad', 'Elige como ofreces tu servicio.');
                valido = false;
            }
            else
            {
                const ciudad = campo('ciudad_limite').value.trim();
                const direccion = campo('direccion_texto').value.trim();

                if (modalidad.dataset.ciudad === '1' && (ciudad.length < 2 || ciudad.length > 100))
                {
                    mostrarError('ciudad_limite', 'Escribe la ciudad donde trabajas (2 a 100 caracteres).');
                    valido = false;
                }

                if (modalidad.dataset.direccion === '1' && (direccion.length < 5 || direccion.length > 255))
                {
                    mostrarError('direccion_texto', 'Escribe la direccion de tu local (5 a 255 caracteres).');
                    valido = false;
                }
            }
        }

        if (numero === 4)
        {
            if (campo('telefono').value.replace(/\D/g, '').length !== 10)
            {
                mostrarError('telefono', 'El telefono debe tener 10 digitos.');
                valido = false;
            }
        }

        return valido;
    }

    // ---------- navegacion ----------
    function irAlPaso(numero)
    {
        pasoActual = numero;

        formulario.classList.add('wizard-activo');

        pasos.forEach(function (paso)
        {
            paso.classList.toggle('visible', parseInt(paso.dataset.paso, 10) === numero);
        });

        marcas.forEach(function (marca)
        {
            const numeroMarca = parseInt(marca.dataset.pasoProgreso, 10);

            marca.classList.toggle('actual', numeroMarca === numero);
            marca.classList.toggle('completado', numeroMarca < numero);
        });

        botonAtras.hidden = numero === 1;
        botonSiguiente.hidden = numero === pasos.length;
        botonEnviar.hidden = numero !== pasos.length;

        const primerCampo = pasos[numero - 1].querySelector('input:not([type="hidden"]), textarea');

        if (primerCampo && numero !== 2 && numero !== 3)
        {
            primerCampo.focus({ preventScroll: true });
        }
    }

    botonSiguiente.addEventListener('click', function ()
    {
        if (validarPaso(pasoActual))
        {
            irAlPaso(pasoActual + 1);
        }
    });

    botonAtras.addEventListener('click', function ()
    {
        irAlPaso(pasoActual - 1);
    });

    // Enter avanza de paso en vez de enviar el formulario a medias
    formulario.addEventListener('keydown', function (evento)
    {
        if (evento.key === 'Enter' && evento.target.tagName !== 'TEXTAREA' && pasoActual < pasos.length)
        {
            evento.preventDefault();
            botonSiguiente.click();
        }
    });

    formulario.addEventListener('submit', function (evento)
    {
        for (let numero = 1; numero <= pasos.length; numero++)
        {
            if (!validarPaso(numero))
            {
                evento.preventDefault();
                irAlPaso(numero);
                return;
            }
        }

        botonEnviar.disabled = true;
    });

    // ---------- contador de la descripcion ----------
    const descripcion = campo('descripcion');
    const contador = document.getElementById('contadorDescripcion');

    function actualizarContador()
    {
        contador.textContent = descripcion.value.length;
    }

    descripcion.addEventListener('input', actualizarContador);
    actualizarContador();

    // ---------- limite de categorias ----------
    const casillasCategorias = Array.from(formulario.querySelectorAll('input[name="categorias[]"]'));

    function aplicarLimiteCategorias()
    {
        const elegidas = casillasCategorias.filter(function (casilla) { return casilla.checked; }).length;

        casillasCategorias.forEach(function (casilla)
        {
            casilla.disabled = !casilla.checked && elegidas >= maxCategorias;
        });
    }

    casillasCategorias.forEach(function (casilla)
    {
        casilla.addEventListener('change', aplicarLimiteCategorias);
    });

    aplicarLimiteCategorias();

    // ---------- campos que dependen de la modalidad ----------
    const grupoCiudad = document.getElementById('grupoCiudad');
    const grupoDireccion = document.getElementById('grupoDireccion');

    function actualizarCamposModalidad()
    {
        const modalidad = modalidadElegida();

        grupoCiudad.hidden = !modalidad || modalidad.dataset.ciudad !== '1';
        grupoDireccion.hidden = !modalidad || modalidad.dataset.direccion !== '1';
    }

    formulario.querySelectorAll('input[name="id_modalidad"]').forEach(function (radio)
    {
        radio.addEventListener('change', actualizarCamposModalidad);
    });

    actualizarCamposModalidad();
    irAlPaso(pasoActual);
})();
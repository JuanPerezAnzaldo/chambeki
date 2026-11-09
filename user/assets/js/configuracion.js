/*
    Comportamiento de la pantalla de configuracion de perfil.
    Solo mejora la experiencia (RU-01, RU-03); las validaciones reales
    siguen en el servidor (RS-05).
*/
(function()
{
    //la vista previa y el recorte de la foto de perfil y del banner viven en configuracionNegocio.js (Cropper.js)

    //--- contador de caracteres de la descripcion ---
    const area = document.getElementById('cfgDescripcion');
    const contador = document.getElementById('contadorDescripcion');

    if (area && contador)
    {
        const actualizarContador = function() { contador.textContent = area.value.length; };

        area.addEventListener('input', actualizarContador);
        actualizarContador();
    }

    //--- RFC siempre en mayusculas ---
    const campoRfc = document.getElementById('cfgRfc');

    if (campoRfc)
    {
        campoRfc.addEventListener('input', function()
        {
            campoRfc.value = campoRfc.value.toUpperCase().replace(/\s+/g, '');
        });
    }

    //--- control deslizante del radio de cobertura (RU-03) ---
    const radio = document.getElementById('cfgRadio');
    const valorRadio = document.getElementById('valorRadio');

    if (radio && valorRadio)
    {
        const actualizarRadio = function()
        {
            const porcentaje = ((radio.value - radio.min) / (radio.max - radio.min)) * 100;

            valorRadio.textContent = radio.value;
            radio.style.setProperty('--avance-radio', porcentaje + '%');
        };

        radio.addEventListener('input', actualizarRadio);
        actualizarRadio();
    }

    //--- retroalimentacion mientras se guarda y evita doble envio (RU-01) ---
    document.querySelectorAll('.tarjeta-config form').forEach(function(formulario)
    {
        formulario.addEventListener('submit', function(evento)
        {
            const boton = formulario.querySelector('button[type="submit"]');

            //se espera un instante: si otra validacion (p. ej. la de horarios) cancela el envio, el boton no se bloquea
            setTimeout(function()
            {
                if (evento.defaultPrevented || !boton)
                {
                    return;
                }

                boton.disabled = true;
                boton.textContent = 'Guardando...';
            }, 0);
        });
    });

    //--- resalta en el menu la seccion que se esta viendo ---
    const enlaces = document.querySelectorAll('.enlace-menu-config');

    if ('IntersectionObserver' in window && enlaces.length)
    {
        const observador = new IntersectionObserver(function(entradas)
        {
            entradas.forEach(function(entrada)
            {
                if (entrada.isIntersecting)
                {
                    enlaces.forEach(function(enlace)
                    {
                        enlace.classList.toggle('activo', enlace.getAttribute('href') === '#' + entrada.target.id);
                    });
                }
            });
        }, { rootMargin: '-20% 0px -60% 0px' });

        document.querySelectorAll('.tarjeta-config[id]').forEach(function(seccion)
        {
            observador.observe(seccion);
        });
    }
})();

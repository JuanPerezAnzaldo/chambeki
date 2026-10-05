/*
    Comportamiento de la pantalla de configuracion de perfil.
    Solo mejora la experiencia (RU-01, RU-03); las validaciones reales
    siguen en el servidor (RS-05).
*/
(function()
{
    const PESO_MAXIMO_FOTO = 2 * 1024 * 1024; //2MB (RNF-05)

    //--- vista previa de la foto y aviso de peso antes de subirla ---
    const entradaFoto = document.getElementById('cfgFoto');
    const errorFoto = document.getElementById('errorFotoCliente');

    if (entradaFoto)
    {
        entradaFoto.addEventListener('change', function()
        {
            const archivo = entradaFoto.files[0];
            let vistaPrevia = document.getElementById('vistaPreviaFoto');

            errorFoto.hidden = true;

            if (!archivo)
            {
                return;
            }

            if (!archivo.type.startsWith('image/') || archivo.size > PESO_MAXIMO_FOTO)
            {
                errorFoto.textContent = archivo.size > PESO_MAXIMO_FOTO
                    ? 'La imagen pesa mas de 2MB. Usa una mas ligera.'
                    : 'El archivo no es una imagen valida.';
                errorFoto.hidden = false;
                entradaFoto.value = '';
                return;
            }

            const lector = new FileReader();

            lector.onload = function(e)
            {
                if (vistaPrevia.tagName !== 'IMG')
                {
                    const imagen = document.createElement('img');
                    imagen.className = 'foto-perfil-grande';
                    imagen.id = 'vistaPreviaFoto';
                    imagen.alt = 'Vista previa de la nueva foto';
                    vistaPrevia.replaceWith(imagen);
                    vistaPrevia = imagen;
                }

                vistaPrevia.src = e.target.result;
            };

            lector.readAsDataURL(archivo);
        });
    }

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
        formulario.addEventListener('submit', function()
        {
            const boton = formulario.querySelector('button[type="submit"]');

            if (boton)
            {
                boton.disabled = true;
                boton.textContent = 'Guardando...';
            }
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

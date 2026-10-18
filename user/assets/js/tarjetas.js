/*
    Métodos de pago del perfil (tarjetas de crédito / débito con Stripe).
    Atiende ventanas modales, vista previa y captura segura con Stripe Elements.
*/
(function()
{
    const seccion = document.getElementById('metodos-pago');

    if (!seccion)
    {
        return;
    }

    const MARCAS = {
        visa:       { clase: 'visa',       texto: 'Visa' },
        mastercard: { clase: 'mastercard', texto: 'Mastercard' },
        amex:       { clase: 'amex',       texto: 'American Express' },
        discover:   { clase: 'discover',   texto: 'Discover' },
        diners:     { clase: 'diners',     texto: 'Diners Club' },
        jcb:        { clase: 'jcb',        texto: 'JCB' },
        unionpay:   { clase: 'unionpay',   texto: 'UnionPay' }
    };

    function ponerMarcaEnVistaPrevia(contenedor, claseMarca)
    {
        Array.from(contenedor.classList).forEach(function(clase)
        {
            if (clase.indexOf('marca-') === 0)
            {
                contenedor.classList.remove(clase);
            }
        });

        contenedor.classList.add('marca-' + claseMarca);
    }

    function abrirDialogo(dialogo)
    {
        if (dialogo && typeof dialogo.showModal === 'function' && !dialogo.open)
        {
            dialogo.showModal();
        }
    }

    document.querySelectorAll('[data-abrir-dialogo]').forEach(function(boton)
    {
        boton.addEventListener('click', function()
        {
            abrirDialogo(document.getElementById(boton.dataset.abrirDialogo));
        });
    });

    document.querySelectorAll('dialog.dialogo-tarjeta').forEach(function(dialogo)
    {
        dialogo.querySelectorAll('[data-cerrar-dialogo]').forEach(function(boton)
        {
            boton.addEventListener('click', function() { dialogo.close(); });
        });

        dialogo.addEventListener('click', function(evento)
        {
            if (evento.target === dialogo)
            {
                dialogo.close();
            }
        });
    });

    // Editar vencimiento
    const dialogoEditar = document.getElementById('dlgEditarTarjeta');

    if (dialogoEditar)
    {
        const selectorMes = document.getElementById('editarMes');
        const selectorAnio = document.getElementById('editarAnio');
        const textoVence = document.getElementById('editarVence');

        const actualizarVence = function()
        {
            textoVence.textContent = String(selectorMes.value).padStart(2, '0') + '/' + String(selectorAnio.value).slice(-2);
        };

        selectorMes.addEventListener('change', actualizarVence);
        selectorAnio.addEventListener('change', actualizarVence);

        document.querySelectorAll('[data-abrir-edicion]').forEach(function(boton)
        {
            boton.addEventListener('click', function()
            {
                document.getElementById('editarIdTarjeta').value = boton.dataset.id;
                document.getElementById('editarNumero').textContent = '•••• •••• •••• ' + boton.dataset.ultimos4;
                document.getElementById('editarMarca').textContent = boton.dataset.claseMarca === 'generica' ? 'TARJETA' : boton.dataset.marca;
                ponerMarcaEnVistaPrevia(document.getElementById('editarVistaPrevia'), boton.dataset.claseMarca);

                selectorMes.value = boton.dataset.mes;
                selectorAnio.value = boton.dataset.anio;

                if (selectorAnio.value !== boton.dataset.anio)
                {
                    selectorAnio.selectedIndex = 0;
                }

                actualizarVence();
                abrirDialogo(dialogoEditar);
            });
        });
    }

    // Eliminar tarjeta
    const dialogoEliminar = document.getElementById('dlgEliminarTarjeta');

    if (dialogoEliminar)
    {
        document.querySelectorAll('[data-abrir-eliminacion]').forEach(function(boton)
        {
            boton.addEventListener('click', function()
            {
                document.getElementById('eliminarIdTarjeta').value = boton.dataset.id;
                document.getElementById('eliminarDescripcion').textContent = boton.dataset.marca + ' terminada en ' + boton.dataset.ultimos4;
                abrirDialogo(dialogoEliminar);
            });
        });
    }

    // Agregar tarjeta (Stripe Elements)
    const dialogoAgregar = document.getElementById('dlgAgregarTarjeta');
    const formulario = document.getElementById('formAgregarTarjeta');

    if (!dialogoAgregar || !formulario)
    {
        return;
    }

    const campoTitular = document.getElementById('titularTarjeta');
    const vistaPrevia = document.getElementById('vistaPreviaTarjeta');
    const vistaPreviaMarca = document.getElementById('vistaPreviaMarca');
    const vistaPreviaTitular = document.getElementById('vistaPreviaTitular');
    const campoSetupIntent = document.getElementById('campoSetupIntent');
    const contenedorError = document.getElementById('errorTarjeta');
    const botonGuardar = document.getElementById('botonGuardarTarjeta');
    const TEXTO_BOTON = botonGuardar.textContent;

    let stripe = null;
    let elementos = null;
    let enviando = false;
    const campos = {};

    function mostrarError(mensaje)
    {
        contenedorError.textContent = mensaje;
        contenedorError.hidden = false;
    }

    function ocultarError()
    {
        contenedorError.hidden = true;
        contenedorError.textContent = '';
    }

    function marcarEnviando(valor)
    {
        enviando = valor;
        botonGuardar.disabled = valor;
        botonGuardar.textContent = valor ? 'Guardando...' : TEXTO_BOTON;
    }

    function estiloStripe()
    {
        const css = getComputedStyle(document.documentElement);
        const texto = css.getPropertyValue('--color-texto-principal').trim() || '#2B2320';
        const suave = css.getPropertyValue('--color-marcador-posicion').trim() || '#A6958E';

        return {
            base: {
                color: texto,
                iconColor: suave,
                fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                fontSize: '16px',
                '::placeholder': { color: suave }
            },
            invalid: { color: '#C0392B', iconColor: '#C0392B' }
        };
    }

    function montarCampo(nombre, tipo, contenedorId, opciones)
    {
        const contenedor = document.getElementById(contenedorId);
        const elemento = elementos.create(tipo, Object.assign({ style: estiloStripe() }, opciones || {}));

        elemento.mount(contenedor);

        elemento.on('focus', function() { contenedor.classList.add('enfocado'); });
        elemento.on('blur', function() { contenedor.classList.remove('enfocado'); });

        elemento.on('change', function(evento)
        {
            contenedor.classList.toggle('invalido', !!evento.error);

            if (evento.error)
            {
                mostrarError(evento.error.message);
            }
            else
            {
                ocultarError();
            }

            if (nombre === 'numero')
            {
                const marca = MARCAS[evento.brand] || { clase: 'generica', texto: 'TARJETA' };

                ponerMarcaEnVistaPrevia(vistaPrevia, marca.clase);
                vistaPreviaMarca.textContent = marca.texto;
            }
        });

        campos[nombre] = elemento;
    }

    function prepararStripe()
    {
        if (elementos)
        {
            return true;
        }

        if (typeof window.Stripe !== 'function' || !seccion.dataset.claveStripe)
        {
            mostrarError('No pudimos cargar el módulo de pagos. Revisa tu conexión y recarga la página.');
            return false;
        }

        stripe = window.Stripe(seccion.dataset.claveStripe, { locale: 'es' });
        elementos = stripe.elements({ locale: 'es' });

        montarCampo('numero', 'cardNumber', 'numeroTarjeta', { showIcon: true, placeholder: '1234 1234 1234 1234' });
        montarCampo('vencimiento', 'cardExpiry', 'vencimientoTarjeta');
        montarCampo('cvc', 'cardCvc', 'cvcTarjeta');

        new MutationObserver(function()
        {
            Object.keys(campos).forEach(function(nombre) { campos[nombre].update({ style: estiloStripe() }); });
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

        return true;
    }

    document.querySelectorAll('[data-abrir-dialogo="dlgAgregarTarjeta"]').forEach(function(boton)
    {
        boton.addEventListener('click', function()
        {
            ocultarError();
            prepararStripe();
        });
    });

    dialogoAgregar.addEventListener('close', function()
    {
        if (enviando)
        {
            return;
        }

        campoTitular.value = '';
        vistaPreviaTitular.textContent = 'NOMBRE APELLIDO';
        vistaPreviaMarca.textContent = 'TARJETA';
        ponerMarcaEnVistaPrevia(vistaPrevia, 'generica');
        ocultarError();

        Object.keys(campos).forEach(function(nombre) { campos[nombre].clear(); });
    });

    campoTitular.addEventListener('input', function()
    {
        vistaPreviaTitular.textContent = campoTitular.value.trim() !== '' ? campoTitular.value.toUpperCase() : 'NOMBRE APELLIDO';
    });

    formulario.addEventListener('submit', async function(evento)
    {
        evento.preventDefault();

        if (enviando)
        {
            return;
        }

        ocultarError();

        const titular = campoTitular.value.trim();

        if (titular.length < 3)
        {
            mostrarError('Escribe el nombre que aparece en tu tarjeta.');
            campoTitular.focus();
            return;
        }

        if (!prepararStripe())
        {
            return;
        }

        marcarEnviando(true);

        try
        {
            const respuesta = await fetch(seccion.dataset.urlIntent, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body: new URLSearchParams({ token_csrf: seccion.dataset.tokenCsrf })
            });

            const datos = await respuesta.json().catch(function() { return {}; });

            if (!respuesta.ok || !datos.client_secret)
            {
                throw new Error(datos.error || 'No pudimos iniciar el guardado de tu tarjeta. Intenta de nuevo.');
            }

            const resultado = await stripe.confirmCardSetup(datos.client_secret, {
                payment_method: {
                    card: campos.numero,
                    billing_details: { name: titular }
                }
            });

            if (resultado.error)
            {
                throw new Error(resultado.error.message);
            }

            campoSetupIntent.value = resultado.setupIntent.id;
            formulario.submit();
        }
        catch (error)
        {
            mostrarError(error.message || 'No pudimos guardar tu tarjeta. Intenta de nuevo.');
            marcarEnviando(false);
        }
    });
})();
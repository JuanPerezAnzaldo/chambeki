/*
    Ventana de suscripcion (MODO PRUEBA: no se cobra nada).
    - Cambia el total y el texto del boton al elegir mensual o anual.
    - Al pulsar "Pagar" muestra "Procesando pago..." un instante y envia el formulario.
    - Los campos de tarjeta son demostrativos: no tienen atributo name y no se envian.
    - Si la URL trae ?abrir=suscripcion abre la ventana sola (desde la pagina de Planes).
*/
(function ()
{
    var dialogo = document.getElementById('dlgSuscripcion');
    var formulario = document.getElementById('formSuscripcion');
    if (!dialogo || !formulario) { return; }

    var radios = Array.prototype.slice.call(formulario.querySelectorAll('input[name="periodo"]'));
    var total = document.getElementById('totalSuscripcion');
    var boton = document.getElementById('botonPagarSuscripcion');
    var textoBoton = boton.querySelector('[data-texto-pagar]');
    var enProceso = false;

    function precioElegido()
    {
        var marcado = radios.filter(function (r) { return r.checked; })[0];
        return marcado ? parseInt(marcado.getAttribute('data-precio'), 10) : 0;
    }

    function actualizarTotal()
    {
        var texto = '$' + precioElegido() + '.00 MXN';
        total.textContent = texto;
        textoBoton.textContent = 'Pagar ' + texto;
    }

    radios.forEach(function (r) { r.addEventListener('change', actualizarTotal); });
    actualizarTotal();

    //formato visual de los campos demostrativos
    var numero = document.getElementById('demoNumero');
    if (numero)
    {
        numero.addEventListener('input', function ()
        {
            var digitos = numero.value.replace(/\D/g, '').slice(0, 16);
            numero.value = digitos.replace(/(.{4})/g, '$1 ').trim();
        });
    }

    formulario.addEventListener('submit', function (evento)
    {
        if (enProceso) { evento.preventDefault(); return; }

        evento.preventDefault();
        enProceso = true;
        boton.disabled = true;
        boton.classList.add('procesando');
        textoBoton.textContent = 'Procesando pago…';

        //pequeña pausa para que se sienta como un pago real; luego se envia el formulario
        window.setTimeout(function () { formulario.submit(); }, 1100);
    });

    dialogo.addEventListener('close', function ()
    {
        if (!enProceso) { actualizarTotal(); }
    });

    //apertura automatica desde la pagina de planes
    try
    {
        var params = new URLSearchParams(window.location.search);
        var hayBoton = document.querySelector('[data-abrir-dialogo="dlgSuscripcion"]');

        if (params.get('abrir') === 'suscripcion' && hayBoton && typeof dialogo.showModal === 'function' && !dialogo.open)
        {
            dialogo.showModal();
        }
    }
    catch (e) { /* sin soporte: el usuario abre la ventana con el boton */ }
})();

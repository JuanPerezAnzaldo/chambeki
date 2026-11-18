/*
    Reportar a un usuario: abre/cierra la ventana, cuenta caracteres
    y evita enviar dos veces el mismo reporte.
*/
(function ()
{
    var dialogo = document.getElementById('dlgReportar');
    if (!dialogo || typeof dialogo.showModal !== 'function') { return; }

    var formulario = document.getElementById('formReportar');
    var descripcion = document.getElementById('descripcionReporte');
    var contador = document.getElementById('contadorReporte');
    var boton = document.getElementById('botonEnviarReporte');

    document.querySelectorAll('[data-abrir-reporte]').forEach(function (b)
    {
        b.addEventListener('click', function () { if (!dialogo.open) { dialogo.showModal(); } });
    });

    dialogo.querySelectorAll('[data-cerrar-reporte]').forEach(function (b)
    {
        b.addEventListener('click', function () { dialogo.close(); });
    });

    //clic en el fondo oscuro = cerrar
    dialogo.addEventListener('click', function (e) { if (e.target === dialogo) { dialogo.close(); } });

    function contar() { contador.textContent = descripcion.value.length; }
    descripcion.addEventListener('input', contar);
    contar();

    formulario.addEventListener('submit', function ()
    {
        boton.disabled = true;
        boton.textContent = 'Enviando…';
    });
})();

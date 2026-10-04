document.addEventListener('DOMContentLoaded', () =>
{
    const botonAlternarTema = document.getElementById('botonAlternarTema');
    const temaAlmacenado = localStorage.getItem('chambeki-tema') || 'light';

    document.documentElement.setAttribute('data-theme', temaAlmacenado);
    actualizarTextoBoton(temaAlmacenado);

    function actualizarTextoBoton(tema)
    {
        if (!botonAlternarTema)
        {
            return;
        }

        botonAlternarTema.textContent = (tema === 'dark') ? 'Modo Claro' : 'Modo Oscuro';
    }

    if (botonAlternarTema)
    {
        botonAlternarTema.addEventListener('click', () =>
        {
            const temaActual = document.documentElement.getAttribute('data-theme');
            const proximoTema = (temaActual === 'dark') ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', proximoTema);
            localStorage.setItem('chambeki-tema', proximoTema);
            actualizarTextoBoton(proximoTema);
        });
    }
});
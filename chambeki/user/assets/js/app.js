let peticionInstalacionPWA = null;
const botonDescargarPWA = document.getElementById('botonInstalar');

window.addEventListener('beforeinstallprompt', (evento) =>
{
    evento.preventDefault();
    peticionInstalacionPWA = evento;

    if (botonDescargarPWA)
    {
        botonDescargarPWA.style.display = 'inline-block';
    }
});

if (botonDescargarPWA)
{
    botonDescargarPWA.addEventListener('click', async () =>
    {
        if (!peticionInstalacionPWA)
        {
            return;
        }

        peticionInstalacionPWA.prompt();
        await peticionInstalacionPWA.userChoice;
        peticionInstalacionPWA = null;
        botonDescargarPWA.style.display = 'none';
    });
}


document.addEventListener('DOMContentLoaded', () =>
{
    const btnMenuOpciones = document.getElementById('btnMenuOpciones');
    const desplegableOpciones = document.getElementById('desplegableOpciones');

    if (btnMenuOpciones && desplegableOpciones)
    {
        btnMenuOpciones.addEventListener('click', (evento) =>
        {
            evento.stopPropagation();
            const estaOculto = desplegableOpciones.classList.toggle('oculto');
            btnMenuOpciones.setAttribute('aria-expanded', !estaOculto);
        });

        document.addEventListener('click', (evento) =>
        {
            if (!evento.target.closest('.contenedor-menu-opciones'))
            {
                desplegableOpciones.classList.add('oculto');
                btnMenuOpciones.setAttribute('aria-expanded', 'false');
            }
        });
    }
});
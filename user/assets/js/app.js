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
    const botonMenu = document.getElementById('btnMenuOpciones');
    const menuDesplegable = document.getElementById('desplegableOpciones');

    if (!botonMenu || !menuDesplegable)
    {
        return;
    }

    botonMenu.addEventListener('click', (evento) =>
    {
        evento.stopPropagation();
        const estaOculto = menuDesplegable.classList.toggle('oculto');
        botonMenu.setAttribute('aria-expanded', !estaOculto);
    });

    document.addEventListener('click', (evento) =>
    {
        if (!evento.target.closest('.contenedor-menu-opciones'))
        {
            menuDesplegable.classList.add('oculto');
            botonMenu.setAttribute('aria-expanded', 'false');
        }
    });
});
let peticionInstalacionPWA = null;
const botonDescargarPWA = document.getElementById('botonInstalar');

// Lógica de PWA
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

// Función accesible globalmente para abrir/cerrar el menú
function alternarMenuOpciones(evento)
{
    evento.stopPropagation();
    const menu = document.getElementById('desplegableOpciones');
    const boton = document.getElementById('btnMenuOpciones');

    if (!menu)
    {
        return;
    }

    const estaOculto = menu.classList.toggle('oculto');
    if (boton)
    {
        boton.setAttribute('aria-expanded', !estaOculto);
    }
}

// Cierre al dar clic fuera del menú
document.addEventListener('click', (evento) =>
{
    const menu = document.getElementById('desplegableOpciones');
    const boton = document.getElementById('btnMenuOpciones');

    if (menu && !evento.target.closest('.contenedor-menu-opciones'))
    {
        menu.classList.add('oculto');
        if (boton)
        {
            boton.setAttribute('aria-expanded', 'false');
        }
    }
});
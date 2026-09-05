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
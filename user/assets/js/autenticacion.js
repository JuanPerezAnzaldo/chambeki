/*
    Boton de "ver contrasena" para las pantallas de autenticacion.
    Se agrega por JavaScript para no repetir el mismo HTML en cada campo.
*/
(function()
{
    const ojoAbierto = "<svg viewBox='0 0 24 24'><path d='M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z'/><circle cx='12' cy='12' r='3'/></svg>";
    const ojoCerrado = "<svg viewBox='0 0 24 24'><path d='M3 3l18 18'/><path d='M10.6 5.2A9.9 9.9 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.5 4.3'/><path d='M6.5 7.3A17 17 0 0 0 2 12s3.5 7 10 7a9.6 9.6 0 0 0 4-.8'/></svg>";

    const camposContrasena = document.querySelectorAll('.formulario-autenticacion input[type="password"]');

    camposContrasena.forEach(function(campo)
    {
        const contenedor = document.createElement('div');
        contenedor.className = 'contenedor-campo-contrasena';

        campo.parentNode.insertBefore(contenedor, campo);
        contenedor.appendChild(campo);

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'boton-ver-contrasena';
        boton.innerHTML = ojoCerrado;
        boton.setAttribute('aria-label', 'Mostrar contrasena');

        boton.addEventListener('click', function()
        {
            const oculta = campo.type === 'password';

            campo.type = oculta ? 'text' : 'password';
            boton.innerHTML = oculta ? ojoAbierto : ojoCerrado;
            boton.setAttribute('aria-label', oculta ? 'Ocultar contrasena' : 'Mostrar contrasena');
        });

        contenedor.appendChild(boton);
    });
})();

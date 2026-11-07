/*
    Formulario de empleados: al cambiar el rol, las casillas de permisos
    se reinician a los permisos base de ese rol (los reservados al Super
    Administrador solo se muestran, no se pueden marcar a mano).
*/
(function ()
{
    const formulario = document.querySelector('form[data-permisos-por-rol]');

    if (!formulario)
    {
        return;
    }

    let permisosPorRol = {};

    try
    {
        permisosPorRol = JSON.parse(formulario.dataset.permisosPorRol);
    }
    catch (error)
    {
        return;
    }

    const selectorRol = document.getElementById('empRol');
    const casillas = formulario.querySelectorAll('input[name="permisos[]"]');

    if (!selectorRol)
    {
        return;
    }

    selectorRol.addEventListener('change', function ()
    {
        const base = permisosPorRol[selectorRol.value] || [];

        casillas.forEach(function (casilla)
        {
            casilla.checked = base.indexOf(casilla.value) !== -1;
        });
    });

    //evita el doble envio
    formulario.addEventListener('submit', function ()
    {
        const boton = formulario.querySelector('button[type="submit"]');

        if (boton)
        {
            boton.disabled = true;
            boton.textContent = 'Guardando...';
        }
    });
})();

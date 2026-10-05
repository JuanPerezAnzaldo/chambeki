<?php
    /*
        Se guarda TODA la salida en un buffer antes de imprimir nada.
        header.php ya imprime el <!DOCTYPE html>... antes de que cada
        pagina (login.php, recuperar.php, cerrarSesion.php, etc.) corra
        su logica y a veces necesite redirigir con header('Location').
        Sin este buffer, esos redirects fallan en cuanto el servidor ya
        envio el primer byte de HTML (dependia de si el hosting traia
        output_buffering activado en su php.ini, por eso fallaba "a
        veces" y no siempre). Con el buffer, nada sale al navegador
        hasta el final del script, asi que cualquier redirect en
        cualquier pagina SIEMPRE funciona.
    */
    ob_start();

    include $_SERVER['DOCUMENT_ROOT'] . '/shared/includes/configuration.php';

    $accion = isset($_GET['accion']) ? trim($_GET['accion']) : 'inicio';

    include(HEADER);

    switch ($accion)
    {
        case 'servicio':
        {
            include(PAGINAS . 'servicios.php');
            break;
        }
        case 'servicios':
        {
            include(PAGINAS . 'servicio.php');
            break;
        }
        case 'correo':
        {
            include(PAGINAS . 'correos.php');
            break;
        }
        case 'nuevo-archivo':
        {
            include(PAGINAS . 'nuevoArchivo.php');
            break;
        }

        //Proceso de inicio de sesion
        case 'login':
        {
            include(PAGINAS . 'login.php');
            break;
        }

        //Proceso de registro de usuario, paso 2: verificacion del codigo
        //(registro.php redirige aqui con ?accion=verificar-codigo)
        case 'verificar-codigo':
        {
            include(PAGINAS . 'verificarCodigo.php');
            break;
        }

        case 'cerrar_sesion':
        {
            include(PAGINAS . 'cerrarSesion.php');
            break;
        }

        //Proceso de registro de usuario
        case 'registro':
        {
            include(PAGINAS . 'registro.php');
            break;
        }

        //Proceso de cambio / recuperacion de contrasena
        case 'recuperar':
        {
            include(PAGINAS . 'recuperar.php');
            break;
        }

        //Perfil del usuario que inicio sesion (RSIS-01)
        case 'perfil':
        {
            include(PAGINAS . 'perfil.php');
            break;
        }

        case 'inicio':
        default:
        {
            include(PAGINAS . 'home.php');
            break;
        }

        case 'terminos':
        {
            include(PAGINAS . 'terminos.php');
            break;
        }

        case 'privacidad':
        {
            include(PAGINAS . 'privacidad.php');
            break;
        }

        case 'configuracion':
        {
            include(PAGINAS . 'configuracion.php');
            break;
        }

        //configuracion de usuario/freelancer
        case 'configuracion':
        {
           include(PAGINAS . 'configuracion.php');
          break;
        }
    }

    include(FOOTER);

    //se envia todo el buffer junto al navegador
    ob_end_flush();
?>

<?php
    //ingnoren esto, es para borrar los comentarios al inspeccionar la pagina
    ob_start(function($buffer)
    {
        return preg_replace('/<!--(.|\s)*?-->/', '', $buffer);
    });

    include $_SERVER['DOCUMENT_ROOT'] . '/shared/includes/configuration.php';
    
    $accion = isset($_GET['accion']) ? trim($_GET['accion']) : 'inicio';
    
    include(HEADER);
    
    switch ($accion)
    {
        case 'servicios':
        {
            include(PAGINAS . 'servicios.php');
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

        //Paso 2 del registro: captura del codigo de verificacion
        case 'verificar-codigo':
        {
            include(PAGINAS . 'verificarCodigo.php');
            break;
        }

        //Proceso de cambio / recuperacion de contrasena
        case 'recuperar':
        {
            include(PAGINAS . 'recuperar.php');
            break;
        }

        //Paso 2 de la recuperacion: codigo temporal
        case 'verificar-codigo-recuperacion':
        {
            include(PAGINAS . 'verificarCodigoRecuperacion.php');
            break;
        }

        //Paso 3 de la recuperacion: contrasena nueva
        case 'nueva-contrasena':
        {
            include(PAGINAS . 'nuevaContrasena.php');
            break;
        }

        //Terminos y condiciones
        case 'terminos':
        {
            include(PAGINAS . 'terminos.php');
            break;
        }
    
        case 'inicio':
        default:
        {
            include(PAGINAS . 'home.php');
            break;
        }
    }
    
    include(FOOTER);
?>

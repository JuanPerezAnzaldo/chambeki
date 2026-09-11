<?php
    //ingnoren esto, es para borrar los comentarios al inspeccionar la pagina
    ob_start(function($buffer)
    {
        return preg_replace('/<!--(.|\s)*?-->/', '', $buffer);
    });

    include $_SERVER['DOCUMENT_ROOT'] . '/chambeki/shared/includes/configuration.php';
    
    $accion = isset($_GET['accion']) ? trim($_GET['accion']) : 'inicio';
    
    include(HEADER);
    
    switch ($accion)
    {
        case 'servicios':
        {
            include(PAGINAS . 'servicios.php');
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
?><?php
    //ingnoren esto, es para borrar los comentarios al inspeccionar la pagina
    ob_start(function($buffer)
    {
        return preg_replace('/<!--(.|\s)*?-->/', '', $buffer);
    });

    include $_SERVER['DOCUMENT_ROOT'] . '/chambeki/shared/includes/configuration.php';
    
    $accion = isset($_GET['accion']) ? trim($_GET['accion']) : 'inicio';
    
    include(HEADER);
    
    switch ($accion)
    {
        case 'servicios':
        {
            include(PAGINAS . 'servicios.php');
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
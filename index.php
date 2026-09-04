<?php
    include $_SERVER['DOCUMENT_ROOT'] . '/chambeki/shared/includes/configuration.php';
    
    //Obtiene la accion que sale en URL
    $accion = isset($_GET['accion']) ? $_GET['accion'] : null;        
    
    include(HEADER);
    
    //Controlador de páginas
    switch($accion)
    {
        case 'servicios':
            include(PAGE . 'servicios.php'); 
            break;
            
        default:
            include(PAGE . 'servicios.php'); 
            break;
    }
    
    include(FOOTER);
?>
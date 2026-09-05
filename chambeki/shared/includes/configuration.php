<?php
    define('SITENAME', 'CHAMBEKI');
    
    define('DOCROOT', dirname(__DIR__, 2) . '/');
    define('HEADER', DOCROOT . 'user/includes/header.php');
    define('FOOTER', DOCROOT . 'user/includes/footer.php');
    define('FUNCIONES', DOCROOT . 'user/includes/functions.php');
    define('PAGINAS', DOCROOT . 'user/pages/');
    
    // Rutas web obligatorias con barra inicial
    define('URL_BASE', '/');
    define('CSS_RUTA', '/user/assets/css/');
    define('JS_RUTA', '/user/assets/js/');
    define('IMG_RUTA', '/user/assets/img/');
    
    if (file_exists(FUNCIONES))
    {
        include(FUNCIONES);
    }
    
    if (session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }
    //linear-gradient(135deg, #ff682e 0%, #D05221 100%)
?> 
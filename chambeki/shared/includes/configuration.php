<?php
    define('SITENAME', 'CHAMBEKI');
    
    // Rutas absolutas del servidor
    define('DOCROOT', $_SERVER['DOCUMENT_ROOT'] . '/chambeki/');
    define('HEADER', DOCROOT . 'user/includes/header.php');
    define('FOOTER', DOCROOT . 'user/includes/footer.php');
    define('FUNCIONES', DOCROOT . 'user/includes/functions.php');
    define('PAGINAS', DOCROOT . 'user/pages/');
    
    // Rutas relativas para navegador
    define('URL_BASE', '/');
    define('CSS_RUTA', URL_BASE . 'user/assets/css/');
    define('JS_RUTA', URL_BASE . 'user/assets/js/');
    define('IMG_RUTA', URL_BASE . 'user/assets/img/');
    
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
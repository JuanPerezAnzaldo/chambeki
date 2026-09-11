<?php
    define('SITENAME', 'CHAMBEKI');
    
    define('DOCROOT', dirname(__DIR__, 2) . '/');

    define('URL_BASE', '/chambeki/');
    define('HEADER', DOCROOT . 'user/includes/header.php');
    define('FOOTER', DOCROOT . 'user/includes/footer.php');
    define('FUNCIONES', DOCROOT . 'user/includes/functions.php');

    //Para aaceder a las paginas js, css y esas weas
    //se usan asi "echo JS_RUTA archivo.js
    define('PAGINAS', DOCROOT . 'user/pages/');
    define('CSS_RUTA', '/chambeki/user/assets/css/');
    define('JS_RUTA', '/chambeki/user/assets/js/');
    define('IMG_RUTA', '/chambeki/user/assets/img/');
    
    if (file_exists(FUNCIONES))
    {
        include(FUNCIONES);
    }
    
    if (session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }
?> <?php
    define('SITENAME', 'CHAMBEKI');
    
    define('DOCROOT', dirname(__DIR__, 2) . '/');

    define('URL_BASE', '/chambeki/');
    define('HEADER', DOCROOT . 'user/includes/header.php');
    define('FOOTER', DOCROOT . 'user/includes/footer.php');
    define('FUNCIONES', DOCROOT . 'user/includes/functions.php');

    //Para aaceder a las paginas js, css y esas weas
    //se usan asi "echo JS_RUTA archivo.js
    define('PAGINAS', DOCROOT . 'user/pages/');
    define('CSS_RUTA', '/chambeki/user/assets/css/');
    define('JS_RUTA', '/chambeki/user/assets/js/');
    define('IMG_RUTA', '/chambeki/user/assets/img/');
    
    if (file_exists(FUNCIONES))
    {
        include(FUNCIONES);
    }
    
    if (session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }
?> 
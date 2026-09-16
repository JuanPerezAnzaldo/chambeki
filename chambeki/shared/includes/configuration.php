<?php
    define('SITENAME', 'CHAMBEKI');
    
    define('DOCROOT', $_SERVER['DOCUMENT_ROOT'] . '/');

    define('URL_BASE', '/');
    define('HEADER', DOCROOT . 'user/includes/header.php');
    define('FOOTER', DOCROOT . 'user/includes/footer.php');
    define('FUNCIONES', DOCROOT . 'user/includes/functions.php');
    define('PAGINAS', DOCROOT . 'user/pages/');
    define('MAI','shared/libs/PHPMailer/src/');

    //Para aaceder a las paginas js, css y esas weas
    //se usan asi "echo JS_RUTA archivo.js
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
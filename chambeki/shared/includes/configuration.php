<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

define('SITENAME', 'CHAMBEKI');

//raíz del servidor
define('DOCROOT', $_SERVER['DOCUMENT_ROOT'] . '/');

//URL Base
define('URL_BASE', '/');

define('HEADER', DOCROOT . 'user/includes/header.php');
define('FOOTER', DOCROOT . 'user/includes/footer.php');
define('FUNCIONES', DOCROOT . 'user/includes/functions.php');
define('PAGINAS', DOCROOT . 'user/pages/');

//pa los correos
define('MAI', DOCROOT . 'shared/libs/PHPMailer/src/');

//si
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
?>
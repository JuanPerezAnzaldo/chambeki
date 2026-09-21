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

//conexion a la base de datos
define('CONEXION_BD', DOCROOT . 'shared/includes/connectionDB.php');

//pa los correos
define('MAI', DOCROOT . 'shared/libs/PHPMailer/src/');
define('MAILER', DOCROOT . 'shared/includes/mailer.php');

//si
define('CSS_RUTA', URL_BASE . 'user/assets/css/');
define('JS_RUTA', URL_BASE . 'user/assets/js/');
define('IMG_RUTA', URL_BASE . 'user/assets/img/');

if (file_exists(CONEXION_BD))
{
    include(CONEXION_BD);
}

if (file_exists(FUNCIONES))
{
    include(FUNCIONES);
}

if (session_status() === PHP_SESSION_NONE)
{
    //la cookie de sesion no se puede leer desde JavaScript y solo viaja por HTTPS (RS-04)
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS'])
    ]);

    session_start();
}

//RS-03: la sesion caduca despues de 30 minutos sin actividad
define('MINUTOS_INACTIVIDAD', 30);

if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > (MINUTOS_INACTIVIDAD * 60))
{
    session_unset();
    session_destroy();
    session_start();
}

$_SESSION['ultima_actividad'] = time();
?>
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

//conexion a la base de datos
define('CONEXION_BD', DOCROOT . 'shared/includes/connectionDB.php');

//pa los correos
define('MAI', DOCROOT . 'shared/libs/PHPMailer/src/');
define('MAILER', DOCROOT . 'shared/includes/mailer.php');

//si
define('CSS_RUTA', URL_BASE . 'user/assets/css/');
define('JS_RUTA', URL_BASE . 'user/assets/js/');
define('IMG_RUTA', URL_BASE . 'user/assets/img/');

if (file_exists(CONEXION_BD))
{
    include(CONEXION_BD);
}

if (file_exists(FUNCIONES))
{
    include(FUNCIONES);
}

if (session_status() === PHP_SESSION_NONE)
{
    //la cookie de sesion no se puede leer desde JavaScript y solo viaja por HTTPS (RS-04)
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS'])
    ]);

    session_start();
}

//RS-03: la sesion caduca despues de 30 minutos sin actividad
define('MINUTOS_INACTIVIDAD', 30);

if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > (MINUTOS_INACTIVIDAD * 60))
{
    session_unset();
    session_destroy();
    session_start();
}

$_SESSION['ultima_actividad'] = time();
?>
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

//conexion a la base de datos
define('CONEXION_BD', DOCROOT . 'shared/includes/connectionDB.php');

//pa los correos
define('MAI', DOCROOT . 'shared/libs/PHPMailer/src/');
define('MAILER', DOCROOT . 'shared/includes/mailer.php');

//si
define('CSS_RUTA', URL_BASE . 'user/assets/css/');
define('JS_RUTA', URL_BASE . 'user/assets/js/');
define('IMG_RUTA', URL_BASE . 'user/assets/img/');

if (file_exists(CONEXION_BD))
{
    include(CONEXION_BD);
}

if (file_exists(FUNCIONES))
{
    include(FUNCIONES);
}

if (session_status() === PHP_SESSION_NONE)
{
    //la cookie de sesion no se puede leer desde JavaScript y solo viaja por HTTPS (RS-04)
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS'])
    ]);

    session_start();
}

//RS-03: la sesion caduca despues de 30 minutos sin actividad
define('MINUTOS_INACTIVIDAD', 30);

if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > (MINUTOS_INACTIVIDAD * 60))
{
    session_unset();
    session_destroy();
    session_start();
}

$_SESSION['ultima_actividad'] = time();
?>
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

//conexion a la base de datos
define('CONEXION_BD', DOCROOT . 'shared/includes/connectionDB.php');

//pa los correos
define('MAI', DOCROOT . 'shared/libs/PHPMailer/src/');
define('MAILER', DOCROOT . 'shared/includes/mailer.php');

//si
define('CSS_RUTA', URL_BASE . 'user/assets/css/');
define('JS_RUTA', URL_BASE . 'user/assets/js/');
define('IMG_RUTA', URL_BASE . 'user/assets/img/');

if (file_exists(CONEXION_BD))
{
    include(CONEXION_BD);
}

if (file_exists(FUNCIONES))
{
    include(FUNCIONES);
}

if (session_status() === PHP_SESSION_NONE)
{
    //la cookie de sesion no se puede leer desde JavaScript y solo viaja por HTTPS (RS-04)
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => isset($_SERVER['HTTPS'])
    ]);

    session_start();
}

//RS-03: la sesion caduca despues de 30 minutos sin actividad
define('MINUTOS_INACTIVIDAD', 30);

if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > (MINUTOS_INACTIVIDAD * 60))
{
    session_unset();
    session_destroy();
    session_start();
}

$_SESSION['ultima_actividad'] = time();
?>

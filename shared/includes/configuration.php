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

/*
    RS-03: la sesion caduca despues de 30 minutos sin actividad.
    Excepcion: si el usuario marco "Recordarme" en login.php, su
    cookie de sesion dura 30 dias, asi que la sesion tambien se deja
    viva por 30 dias de inactividad en vez de solo 30 minutos; si no,
    la cookie "Recordarme" no serviria de nada.
*/
define('MINUTOS_INACTIVIDAD', 30);
define('MINUTOS_INACTIVIDAD_RECORDARME', 60 * 24 * 30);

$minutosPermitidos = !empty($_SESSION['recordarme']) ? MINUTOS_INACTIVIDAD_RECORDARME : MINUTOS_INACTIVIDAD;

if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > ($minutosPermitidos * 60))
{
    $_SESSION = [];

    if (ini_get('session.use_cookies'))
    {
        $parametrosCookieVencida = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $parametrosCookieVencida['path'],
            'domain'   => $parametrosCookieVencida['domain'],
            'secure'   => $parametrosCookieVencida['secure'],
            'httponly' => $parametrosCookieVencida['httponly'],
            'samesite' => $parametrosCookieVencida['samesite'] ?: 'Lax'
        ]);
    }

    session_destroy();
    session_start();
    session_regenerate_id(true);
}

$_SESSION['ultima_actividad'] = time();
?>
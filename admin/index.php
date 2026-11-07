<?php
/*
    PANEL DE ADMINISTRACION - punto de entrada unico (https://chambeki.com/admin/...)

    Orden obligatorio:
      1) se configura y arranca la sesion "session_admin" ANTES de cargar
         shared/includes/configuration.php. Ese archivo arranca la sesion
         normal del sitio solo si todavia no hay una activa, asi que al ir
         primero nosotros las dos sesiones quedan completamente separadas:
         cookie distinta (session_admin), path /admin (el navegador ni
         siquiera la manda al sitio publico) y variables distintas.
      2) se carga el resto del sistema (BD, funciones, correo...).
      3) se resuelve la ruta y el middleware decide si se puede entrar.
*/
ob_start();

//----- 1) sesion aislada del panel ---------------------------------
ini_set('session.use_strict_mode', '1');   // no acepta IDs de sesion inventados
session_name('session_admin');
session_set_cookie_params([
    'lifetime' => 0,                        // muere al cerrar el navegador
    'path'     => '/admin',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

//----- 2) resto del sistema ----------------------------------------
include $_SERVER['DOCUMENT_ROOT'] . '/shared/includes/configuration.php';

define('URL_ADMIN', '/admin/');
require_once DOCROOT . 'admin/includes/functions.php';

//el panel no debe guardarse en cache ni indexarse
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

//----- 3) ruta solicitada ------------------------------------------
$ruta = strtolower(trim($_GET['ruta'] ?? '', '/'));
$ruta = preg_replace('/[^a-z0-9\-]/', '', $ruta);   // solo letras, numeros y guion

$rutas = rutasAdmin();

//sin ruta: al panel si ya hay sesion valida, al login si no
if ($ruta === '')
{
    $ruta = (adminAutenticado() !== null) ? 'dashboard' : 'login';
}

//-- cerrar sesion (solo POST con token CSRF)
if ($ruta === 'logout')
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfAdminValido($_POST['csrf'] ?? ''))
    {
        $adminActual = obtenerAdminPorId($_SESSION['admin']['id_usuario'] ?? 0);

        if ($adminActual !== null)
        {
            registrarEnBitacora($adminActual['correo'], 'admin_logout');
        }

        destruirSesionAdmin();   //deja una sesion nueva y vacia
        guardarMensaje('exito', 'Cerraste sesion correctamente.');
    }

    redirigir(URL_ADMIN . 'login');
}

//ruta que no existe: se manda al inicio (no se da pista de que rutas hay)
if (!isset($rutas[$ruta]))
{
    redirigir(URL_ADMIN);
}

$configRuta  = $rutas[$ruta];
$rutaActual  = $ruta;
$tituloPagina = $configRuta['titulo'];

//-- pantalla publica (solo el login)
if (!empty($configRuta['publica']))
{
    //quien ya tiene sesion valida no necesita volver a entrar
    if (adminAutenticado() !== null)
    {
        redirigir(URL_ADMIN . 'dashboard');
    }

    $layoutAdmin = 'login';
    $admin = null;
}
//-- pantalla protegida: el middleware revisa sesion, rol y permiso
else
{
    $admin = exigirPermisoAdmin($configRuta['permiso'] ?? null);
    $layoutAdmin = 'panel';
}

include DOCROOT . 'admin/includes/header.php';
include DOCROOT . 'admin/pages/' . $configRuta['archivo'];
include DOCROOT . 'admin/includes/footer.php';

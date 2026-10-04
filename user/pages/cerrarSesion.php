<?php
/*
    Cierre de sesion seguro (RS-03, RS-04):
      1. se registra el evento en la bitacora antes de borrar nada
      2. se vacia toda la variable $_SESSION
      3. se borra la cookie de sesion del navegador (incluida la
         version de 30 dias que deja "Recordarme" en login.php; si
         no se borra, el navegador la sigue mandando despues de
         "cerrar sesion")
      4. se destruye la sesion en el servidor
      5. se arranca una sesion totalmente nueva, con un ID nuevo
         (nunca se reutiliza el ID viejo, para evitar robo/fijacion
         de sesion) solo para poder mostrar el mensaje de exito
*/

if (hayUsuarioEnSesion())
{
    registrarEnBitacora($_SESSION['correo_usuario'] ?? '', 'cierre_sesion');
}

$_SESSION = [];

//borra la cookie de sesion actual (y la de "recordarme" de 30 dias,
//porque usa el mismo nombre de cookie) antes de destruir la sesion
if (ini_get('session.use_cookies'))
{
    $parametrosCookie = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $parametrosCookie['path'],
        'domain'   => $parametrosCookie['domain'],
        'secure'   => $parametrosCookie['secure'],
        'httponly' => $parametrosCookie['httponly'],
        'samesite' => $parametrosCookie['samesite'] ?: 'Lax'
    ]);
}

session_destroy();

//sesion nueva con ID nuevo (no se reutiliza el anterior) para dejar
//el mensaje de confirmacion
session_start();
session_regenerate_id(true);

guardarMensaje('exito', 'Cerraste sesion correctamente.');

redirigir(URL_BASE . '?accion=login');
?>

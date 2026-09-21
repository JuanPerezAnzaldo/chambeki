<?php
/*
    Cierre de sesion: limpia las variables, destruye la sesion
    y regresa al inicio.
*/

$correo = $_SESSION['correo_usuario'] ?? null;

session_unset();
session_destroy();

//se arranca una sesion limpia para poder dejar el mensaje
session_start();
guardarMensaje('exito', 'Cerraste sesion correctamente.');

redirigir(URL_BASE . '?accion=login');
?>

<?php
/*
    PROCESO: Preparar el alta de una tarjeta (respuesta JSON)
    Lo llama user/assets/js/tarjetas.js justo antes de guardar la tarjeta con Stripe.js.
*/

require_once DOCROOT . 'user/includes/funcionesPerfil.php';
require_once DOCROOT . 'user/includes/funcionesTarjetas.php';

function responderJson($codigoHttp, $datos)
{
    while (ob_get_level() > 0)
    {
        ob_end_clean();
    }

    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode($datos);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    responderJson(405, ['error' => 'Metodo no permitido.']);
}

if (!hayUsuarioEnSesion())
{
    responderJson(401, ['error' => 'Tu sesion expiro. Inicia sesion de nuevo.']);
}

if (!validarCsrf($_POST['token_csrf'] ?? ''))
{
    responderJson(403, ['error' => 'La sesion del formulario expiro. Recarga la pagina.']);
}

if (!pagosConTarjetaDisponibles())
{
    responderJson(503, ['error' => 'Los pagos con tarjeta no estan disponibles por el momento.']);
}

$usuario = obtenerUsuarioPorId((int) $_SESSION['id_usuario']);

if ($usuario === null)
{
    responderJson(401, ['error' => 'No encontramos tu cuenta. Inicia sesion de nuevo.']);
}

if (contarTarjetas((int) $usuario['id_usuario']) >= MAXIMO_TARJETAS_USUARIO)
{
    responderJson(409, ['error' => 'Ya tienes ' . MAXIMO_TARJETAS_USUARIO . ' tarjetas guardadas. Elimina una para agregar otra.']);
}

try
{
    responderJson(200, ['client_secret' => crearSetupIntentTarjeta($usuario)]);
}
catch (\Throwable $excepcion)
{
    // Y cámbialo temporalmente por esto:
        responderJson(500, ['error' => 'ERROR REAL: ' . $excepcion->getMessage()]);
}
?>
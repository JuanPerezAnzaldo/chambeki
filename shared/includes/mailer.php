<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require DOCROOT . 'shared/libs/PHPMailer/src/Exception.php';
require DOCROOT . 'shared/libs/PHPMailer/src/PHPMailer.php';
require DOCROOT . 'shared/libs/PHPMailer/src/SMTP.php';

function enviarCorreoChambeki($tipoCorreo, $correoDestino, $nombreUsuario, $datosExtra = [])
{
    $correoEnviado = false;
    $mensajeError = "";

    $asunto = "";
    $cuerpoHtml = "";
    $enlaceBoton = "https://chambeki.com" . URL_BASE . "?accion=login";

    //colores
    $colorHero = "#ff682e";
    $colorBoton = "#E8612D";
    $colorFondo = "#FDFBF7";
    $colorTexto = "#4A3E3A";

    switch ($tipoCorreo)
    {
        case 'registro':
        {
            $asunto = "";
            $cuerpoHtml = "";
            break;
        }

        case 'codigoSeguridad':
        {
            $asunto = "";
            $cuerpoHtml = "";
            break;
        }

        default:
        {
            return ['exito' => false, 'error' => 'Tipo de correo no válido.'];
        }
    }

    $correoObj = new PHPMailer(true);

    try 
    {
        //configuracion smtp
        $correoObj->isSMTP();
        $correoObj->Host       = 'smtp.hostinger.com';
        $correoObj->SMTPAuth   = true;
        $correoObj->Username   = 'admin@laicales.com';
        $correoObj->Password   = '4antonio_AHV';
        $correoObj->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $correoObj->Port       = 465;

        //remitente/destinatari0
        $correoObj->setFrom('admin@laicales.com', 'Sistema CHAMBEKI');
        $correoObj->addAddress($correoDestino, $nombreUsuario);

        //contenido
        $correoObj->isHTML(true);
        $correoObj->CharSet = 'UTF-8';
        $correoObj->Subject = $asunto;
        $correoObj->Body    = $cuerpoHtml;

        $correoObj->send();
        $correoEnviado = true;
    } 
    catch (Exception $e) 
    {
        $correoEnviado = false;
        $mensajeError = $correoObj->ErrorInfo;
    }

    return [
        'exito' => $correoEnviado,
        'error' => $mensajeError
    ];
}
?>
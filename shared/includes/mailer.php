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

    // Colores corporativos de la plataforma
    $colorHero = "#ff682e";
    $colorBoton = "#E8612D";
    $colorFondo = "#FDFBF7";
    $colorTexto = "#4A3E3A";

    // Plantilla base HTML (Reutilizable para cualquier tipo de correo)
    $encabezadoHtml = "
    <div style='font-family: system-ui, -apple-system, sans-serif; background-color: {$colorFondo}; padding: 30px 15px; margin: 0;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
            <div style='background-color: {$colorHero}; padding: 25px; text-align: center;'>
                <h1 style='color: #ffffff; margin: 0; font-size: 26px; letter-spacing: 1px;'>CHAMBEKI</h1>
            </div>
            <div style='padding: 35px; color: {$colorTexto}; font-size: 16px; line-height: 1.6;'>
                <p>Hola <strong>{$nombreUsuario}</strong>,</p>
    ";

    $pieHtml = "
            </div>
            <div style='background-color: #f9f9f9; padding: 20px; text-align: center; border-top: 1px solid #eeeeee;'>
                <p style='margin: 0; color: #888888; font-size: 12px;'>
                    &copy; " . date('Y') . " CHAMBEKI. Todos los derechos reservados.<br>
                    Si no solicitaste este correo, puedes ignorarlo con seguridad.
                </p>
            </div>
        </div>
    </div>
    ";

    switch ($tipoCorreo)
    {
        case 'registro':
        {
            $asunto = "¡Bienvenido a CHAMBEKI, {$nombreUsuario}!";

            $cuerpoPrincipal = "
                <p>Nos emociona darte la bienvenida a la plataforma donde conectas con los mejores profesionales y técnicos para solucionar cualquier necesidad.</p>
                <p>Tu cuenta ha sido creada exitosamente. Ya puedes empezar a buscar servicios, agendar citas o publicar tu propio perfil profesional.</p>
                <div style='text-align: center; margin: 35px 0;'>
                    <a href='{$enlaceBoton}' style='background-color: {$colorBoton}; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-weight: bold; display: inline-block;'>Acceder a mi cuenta</a>
                </div>
            ";

            $cuerpoHtml = $encabezadoHtml . $cuerpoPrincipal . $pieHtml;
            break;
        }

        case 'codigoSeguridad':
        {
            $asunto = "Tu código de seguridad de CHAMBEKI";

            // Verificamos que el código venga en el arreglo, si no, ponemos uno de error
            $codigo = isset($datosExtra['codigo']) ? $datosExtra['codigo'] : '000000';

            $cuerpoPrincipal = "
                <p>Hemos recibido una solicitud para verificar tu identidad. Utiliza el siguiente código de 6 dígitos para continuar con el proceso:</p>
                <div style='text-align: center; margin: 35px 0;'>
                    <span style='background-color: #FDFBF7; border: 2px dashed {$colorHero}; color: {$colorHero}; font-size: 34px; font-weight: bold; padding: 15px 30px; border-radius: 8px; letter-spacing: 6px;'>{$codigo}</span>
                </div>
                <p style='font-size: 14px; color: #777777;'>Este código expirará en 15 minutos. Por tu seguridad, nunca compartas esta información con nadie.</p>
            ";

            $cuerpoHtml = $encabezadoHtml . $cuerpoPrincipal . $pieHtml;
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
        // Configuración SMTP del servidor
        $correoObj->isSMTP();
        $correoObj->Host       = 'smtp.hostinger.com';
        $correoObj->SMTPAuth   = true;
        $correoObj->Username   = 'admin@chambeki.com';
        $correoObj->Password   = 'chambeki_Pr0ces0s';
        $correoObj->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $correoObj->Port       = 465;

        // Remitente y Destinatario
        $correoObj->setFrom('admin@chambeki.com', 'Sistema CHAMBEKI');
        $correoObj->addAddress($correoDestino, $nombreUsuario);

        // Formato del correo
        $correoObj->isHTML(true);
        $correoObj->CharSet = 'UTF-8';
        $correoObj->Subject = $asunto;
        $correoObj->Body    = $cuerpoHtml;

        // Texto plano por si el cliente de correo del usuario bloquea el HTML
        $correoObj->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\r\n", "\r\n\r\n"], $cuerpoHtml));

        $correoObj->send();
        $correoEnviado = true;
    } 
    catch (Exception $excepcion) 
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
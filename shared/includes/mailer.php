<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once DOCROOT . 'shared/libs/PHPMailer/src/Exception.php';
require_once DOCROOT . 'shared/libs/PHPMailer/src/PHPMailer.php';
require_once DOCROOT . 'shared/libs/PHPMailer/src/SMTP.php';

/*
    Envio de correos automatizados (RSIS-05 protocolo SMTP, RI-03 PHPMailer)

    Tipos disponibles:
      'registro'            -> bienvenida despues de crear la cuenta
      'codigo'              -> codigo temporal de verificacion o recuperacion
      'contrasena_cambiada' -> aviso de que la contrasena fue actualizada
*/
function enviarCorreo($tipoCorreo, $correoDestino, $nombreUsuario, $datosExtra = [])
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

    //html
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
                <p>Tu cuenta quedo creada y verificada. Ya puedes iniciar sesion para buscar
                plomeros, electricistas, carpinteros y demas oficios cerca de ti.</p>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$enlaceBoton}' style='background-color: {$colorBoton}; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; display: inline-block;'>
                        Entrar a mi cuenta
                    </a>
                </p>

                <p style='font-size: 14px; color: #777777;'>Si quieres ofrecer tus servicios, puedes
                convertir tu cuenta en perfil de freelancer desde el menu de opciones.</p>
            ";

            $cuerpoHtml = $encabezadoHtml . $cuerpoPrincipal . $pieHtml;
            break;
        }

        case 'codigo':
        {
            $asunto = "Tu código de seguridad de CHAMBEKI";

            $codigo = isset($datosExtra['codigo']) ? $datosExtra['codigo'] : '';
            $vigencia = isset($datosExtra['vigencia']) ? (int) $datosExtra['vigencia'] : 15;

            $motivo = (isset($datosExtra['motivo']) && $datosExtra['motivo'] === 'recuperacion')
                ? "Recibimos una solicitud para restablecer la contrasena de tu cuenta."
                : "Usa este codigo para confirmar tu correo y terminar tu registro.";

            $cuerpoPrincipal = "
                <p>{$motivo}</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <div style='display: inline-block; background-color: {$colorFondo}; border: 2px dashed {$colorBoton}; border-radius: 8px; padding: 18px 34px;'>
                        <span style='font-size: 34px; font-weight: bold; letter-spacing: 10px; color: {$colorBoton};'>{$codigo}</span>
                    </div>
                </div>

                <p style='font-size: 14px; color: #777777;'>El codigo vence en {$vigencia} minutos y solo
                se puede usar una vez. No lo compartas con nadie: el equipo de CHAMBEKI nunca te lo va a pedir.</p>
            ";

            $cuerpoHtml = $encabezadoHtml . $cuerpoPrincipal . $pieHtml;
            break;
        }

        case 'contrasena_cambiada':
        {
            $asunto = "Tu contraseña de CHAMBEKI fue actualizada";

            $fechaCambio = date('d/m/Y H:i');

            $cuerpoPrincipal = "
                <p>Tu contrasena se actualizo correctamente el {$fechaCambio}. Ya puedes iniciar
                sesion con la nueva.</p>

                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$enlaceBoton}' style='background-color: {$colorBoton}; color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: bold; display: inline-block;'>
                        Iniciar sesion
                    </a>
                </p>

                <p style='font-size: 14px; color: #777777;'>Si no fuiste tu, escribenos de inmediato a
                admin@chambeki.com para bloquear la cuenta.</p>
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
        // Configuración SMTP optimizada para Hostinger
        $correoObj->isSMTP();
        $correoObj->Host       = 'smtp.hostinger.com';
        $correoObj->SMTPAuth   = true;
        $correoObj->Username   = 'admin@chambeki.com';
        $correoObj->Password   = 'chambeki_Pr0ces0s';
        $correoObj->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $correoObj->Port       = 465;

        // Limite de tiempo para evitar cuelgues (10 segundos maximo)
        $correoObj->Timeout       = 10;
        $correoObj->SMTPKeepAlive = false;

        // Opciones SSL para mayor rapidez de handshake en Hostinger
        $correoObj->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true
            ]
        ];

        // Remitente y Destinatario
        $correoObj->setFrom('admin@chambeki.com', 'Sistema CHAMBEKI');
        $correoObj->addAddress($correoDestino, $nombreUsuario);

        // Formato del correo
        $correoObj->isHTML(true);
        $correoObj->CharSet = 'UTF-8';
        $correoObj->Subject = $asunto;
        $correoObj->Body    = $cuerpoHtml;

        // Alternativa texto plano
        $correoObj->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\r\n", "\r\n\r\n"], $cuerpoHtml));

        $correoObj->send();
        $correoEnviado = true;
    } 
    catch (Exception $excepcion) 
    {
        $correoEnviado = false;
        $mensajeError = $correoObj->ErrorInfo;
        error_log('Error enviando correo SMTP: ' . $mensajeError);
    }

    return [
        'exito' => $correoEnviado,
        'error' => $mensajeError
    ];
}
?>
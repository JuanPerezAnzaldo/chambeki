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
    $enlaceBoton = "https://lavender-seal-170625.hostingersite.com" . URL_BASE . "?accion=login";

    //colores
    $colorHero = "#ff682e";
    $colorBoton = "#E8612D";
    $colorFondo = "#FDFBF7";
    $colorTexto = "#4A3E3A";

    switch ($tipoCorreo)
    {
        case 'registro':
        {
            $asunto = "¡Registro Exitoso en CHAMBEKI!";

            $cuerpoHtml = "
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: $colorFondo; padding: 20px; font-family: Arial, sans-serif;'>
                <tr>
                    <td align='center'>
                        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                            <tr>
                                <td style='background-color: $colorHero; padding: 25px 10px; text-align: center;'>
                                    <h2 style='margin: 0; font-size: 24px; color: #ffffff; letter-spacing: 1px;'>¡Bienvenido a CHAMBEKI!</h2>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 30px 20px; text-align: center;'>
                                    <h3 style='color: #2B2320; margin-top: 0; font-size: 20px;'>Hola, $nombreUsuario</h3>
                                    <p style='font-size: 16px; line-height: 1.6; color: $colorTexto; margin-bottom: 25px;'>Tu registro como usuario en nuestra plataforma se ha completado correctamente. Ahora puedes empezar a buscar profesionales y oficios cerca de ti.</p>

                                    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-top: 20px; margin-bottom: 20px;'>
                                        <tr>
                                            <td align='center'>
                                                <a href='$enlaceBoton' style='background-color: $colorBoton; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>Iniciar Sesión Ahora</a>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style='font-size: 13px; color: #A6958E; margin-top: 30px; border-top: 1px solid #F0D7CC; padding-top: 20px;'>
                                        Si no creaste esta cuenta, ignora este mensaje.<br><br>
                                        <strong>El Equipo de CHAMBEKI</strong>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            ";
            break;
        }

        case 'codigoSeguridad':
        {
            $asunto = "Tu código de un solo uso - CHAMBEKI";
            $codigoAcceso = isset($datosExtra['codigo']) ? $datosExtra['codigo'] : '000000';

            $cuerpoHtml = "
            <table width='100%' cellpadding='0' cellspacing='0' border='0' style='background-color: $colorFondo; padding: 20px; font-family: Arial, sans-serif;'>
                <tr>
                    <td align='center'>
                        <table width='100%' cellpadding='0' cellspacing='0' border='0' style='max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                            <tr>
                                <td style='background-color: $colorHero; padding: 25px 10px; text-align: center;'>
                                    <h2 style='margin: 0; font-size: 24px; color: #ffffff; letter-spacing: 1px;'>Verificación de Seguridad</h2>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 30px 20px; text-align: center;'>
                                    <h3 style='color: #2B2320; margin-top: 0; font-size: 20px;'>Hola, $nombreUsuario</h3>
                                    <p style='font-size: 16px; line-height: 1.6; color: $colorTexto; margin-bottom: 25px;'>Se ha solicitado un código de un solo uso (OTP) para acceder a tu cuenta o confirmar una acción. Utiliza el siguiente código de 6 dígitos:</p>

                                    <div style='background-color: #FDFBF7; border: 2px dashed $colorHero; padding: 15px; margin: 0 auto 25px auto; max-width: 250px; border-radius: 8px;'>
                                        <h1 style='margin: 0; font-size: 32px; color: $colorHero; letter-spacing: 5px;'>$codigoAcceso</h1>
                                    </div>

                                    <table width='100%' cellpadding='0' cellspacing='0' border='0' style='margin-bottom: 20px;'>
                                        <tr>
                                            <td align='center'>
                                                <a href='$enlaceBoton' style='background-color: $colorBoton; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>Ir a Verificar</a>
                                            </td>
                                        </tr>
                                    </table>

                                    <p style='font-size: 14px; color: #E8612D; font-weight: bold;'>Este código expirará en 10 minutos.</p>

                                    <p style='font-size: 13px; color: #A6958E; margin-top: 30px; border-top: 1px solid #F0D7CC; padding-top: 20px;'>
                                        Nunca compartas este código con nadie. Si no lo solicitaste, por favor cambia tu contraseña.<br><br>
                                        <strong>El Equipo de CHAMBEKI</strong>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            ";
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
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
      'oficial'             -> comunicado del personal de soporte (admin/correos)
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
            $motivoCorreo = isset($datosExtra['motivo']) ? $datosExtra['motivo'] : 'registro';

            if ($motivoCorreo === 'recuperacion')
            {
                $motivo = "Recibimos una solicitud para restablecer la contrasena de tu cuenta.";
            }
            elseif ($motivoCorreo === 'login')
            {
                $motivo = "Usa este codigo para confirmar que eres tu e iniciar sesion de forma segura.";
            }
            else
            {
                $motivo = "Usa este codigo para confirmar tu correo y terminar tu registro.";
            }

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

        case 'oficial':
        {
            //correo redactado por el personal desde /admin/correos
            //datosExtra: plantilla (aclaracion|suspension|aviso|reporte), asunto, mensaje (texto plano)
            $plantillasOficiales = [
                'aclaracion' => ['etiqueta' => 'Solicitud de aclaración', 'color' => '#2B6CB0', 'icono' => '&#128196;'],
                'suspension' => ['etiqueta' => 'Aviso de suspensión',     'color' => '#C0392B', 'icono' => '&#9888;&#65039;'],
                'aviso'      => ['etiqueta' => 'Aviso del sistema',       'color' => '#E8612D', 'icono' => '&#128276;'],
                'reporte'    => ['etiqueta' => 'Resolución de reporte',   'color' => '#2F855A', 'icono' => '&#9989;']
            ];

            $clavePlantilla = strtolower($datosExtra['plantilla'] ?? 'aviso');
            $plantilla = $plantillasOficiales[$clavePlantilla] ?? $plantillasOficiales['aviso'];

            $asunto = trim((string) ($datosExtra['asunto'] ?? ''));
            $asunto = str_replace(["\r", "\n"], ' ', $asunto);   //sin saltos: evita inyeccion de cabeceras
            if ($asunto === '') { $asunto = 'Comunicado oficial de CHAMBEKI'; }

            //el texto se escapa y luego se convierten los saltos de linea (sin HTML libre = sin XSS)
            $mensajeSeguro = nl2br(htmlspecialchars((string) ($datosExtra['mensaje'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $asuntoSeguro  = htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8');
            $nombreSeguro  = htmlspecialchars((string) $nombreUsuario, ENT_QUOTES, 'UTF-8');
            $etiqueta      = $plantilla['etiqueta'];
            $colorEtiqueta = $plantilla['color'];
            $icono         = $plantilla['icono'];
            $anio          = date('Y');

            $cuerpoHtml = "
            <div style='font-family: system-ui, -apple-system, \"Segoe UI\", Roboto, Arial, sans-serif; background-color: {$colorFondo}; padding: 32px 14px; margin: 0;'>
              <div style='max-width: 620px; margin: 0 auto;'>

                <div style='background-color: {$colorHero}; border-radius: 14px 14px 0 0; padding: 28px 30px; text-align: center;'>
                    <div style='color: #ffffff; font-size: 28px; font-weight: 800; letter-spacing: 3px;'>CHAMBEKI</div>
                    <div style='color: #ffe4d6; font-size: 12px; letter-spacing: 2px; text-transform: uppercase; margin-top: 4px;'>Comunicado oficial</div>
                </div>

                <div style='background-color: #ffffff; padding: 34px 34px 28px; color: {$colorTexto}; font-size: 16px; line-height: 1.65; border-left: 1px solid #f0d7cc; border-right: 1px solid #f0d7cc;'>
                    <div style='display: inline-block; background-color: {$colorEtiqueta}; color: #ffffff; font-size: 12px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; padding: 6px 14px; border-radius: 20px;'>{$icono}&nbsp; {$etiqueta}</div>

                    <h2 style='margin: 18px 0 6px; font-size: 21px; line-height: 1.3; color: #2B2320;'>{$asuntoSeguro}</h2>
                    <p style='margin: 0 0 20px; color: #8a7a73; font-size: 14px;'>Para: <strong style='color: {$colorTexto};'>{$nombreSeguro}</strong></p>

                    <div style='padding: 20px 22px; background-color: {$colorFondo}; border-left: 4px solid {$colorEtiqueta}; border-radius: 6px; font-size: 15.5px;'>
                        {$mensajeSeguro}
                    </div>

                    <p style='margin: 26px 0 0; font-size: 15px;'>Atentamente,<br><strong>Equipo de Soporte CHAMBEKI</strong></p>
                </div>

                <div style='background-color: #f7f1ec; border: 1px solid #f0d7cc; border-top: 0; border-radius: 0 0 14px 14px; padding: 20px 30px; text-align: center;'>
                    <p style='margin: 0 0 6px; color: #6b5d57; font-size: 13px;'>¿Tienes dudas? Responde a este correo o escríbenos a <a href='mailto:admin@chambeki.com' style='color: {$colorBoton}; text-decoration: none; font-weight: 600;'>admin@chambeki.com</a></p>
                    <p style='margin: 0; color: #a09089; font-size: 12px;'>&copy; {$anio} CHAMBEKI. Todos los derechos reservados.</p>
                </div>

              </div>
            </div>
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
        // Uso de puerto 587 + STARTTLS para respuesta instantanea en Hostinger
        $correoObj->isSMTP();
        $correoObj->Host       = 'smtp.hostinger.com';
        $correoObj->SMTPAuth   = true;
        $correoObj->Username   = 'admin@chambeki.com';
        $correoObj->Password   = 'chambeki_Pr0ces0s';
        $correoObj->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $correoObj->Port       = 587;

        // Limite de 5 segundos para no colgar la experiencia de usuario
        $correoObj->Timeout       = 5;
        $correoObj->SMTPKeepAlive = false;

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
        $correoObj->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\r\n", "\r\n\r\n"], $cuerpoHtml));

        $correoObj->send();
        $correoEnviado = true;
    } 
    catch (Exception $excepcion) 
    {
        $correoEnviado = false;
        $mensajeError = $correoObj->ErrorInfo;
        error_log('Error SMTP PHPMailer: ' . $mensajeError);
    }

    return [
        'exito' => $correoEnviado,
        'error' => $mensajeError
    ];
}
?>
<?php
require_once DOCROOT . 'shared/includes/mailer.php';

$mensajeEstado = "";
$claseMensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $correoDestino = isset($_POST['correo']) ? trim($_POST['correo']) : '';
    $nombrePrueba = 'Usuario de Prueba';

    if (empty($correoDestino))
    {
        $mensajeEstado = "Por favor, ingresa un corSreo válido.";
        $claseMensaje = "error";
    }
    else
    {
        if (isset($_POST['btn_enviar_registro']))
        {
            $resultado = enviarCorreoChambeki('registro', $correoDestino, $nombrePrueba);

            if ($resultado['exito'])
            {
                $mensajeEstado = "Correo de REGISTRO enviado correctamente a $correoDestino.";
                $claseMensaje = "exito";
            }
            else
            {
                $mensajeEstado = "Error al enviar registro: " . $resultado['error'];
                $claseMensaje = "error";
            }
        }
        else if (isset($_POST['btn_enviar_codigo']))
        {
            $codigoAleatorio = rand(100000, 999999);
            $datosExtra = ['codigo' => $codigoAleatorio];

            $resultado = enviarCorreoChambeki('codigoSeguridad', $correoDestino, $nombrePrueba, $datosExtra);

            if ($resultado['exito'])
            {
                $mensajeEstado = "Correo de CÓDIGO DE SEGURIDAD ($codigoAleatorio) enviado a $correoDestino.";
                $claseMensaje = "exito";
            }
            else
            {
                $mensajeEstado = "Error al enviar código: " . $resultado['error'];
                $claseMensaje = "error";
            }
        }
    }
}
?>

<div class="envoltorio-contenido" style="max-width: 500px; margin: 4rem auto; background: var(--color-fondo-tarjeta); padding: 2rem; border-radius: var(--radio-mediano); border: 1px solid var(--color-borde); box-shadow: var(--sombra-general);">
    <h2 style="color: var(--color-texto-principal); margin-bottom: 0.5rem; text-align: center;">Simulador de Correos</h2>
    <p style="font-size: 0.9rem; color: var(--color-marcador-posicion); text-align: center; margin-bottom: 2rem;">
        Escribe un correo para probar las plantillas de CHAMBEKI.
    </p>

    <form method="POST" action="">
        <div style="margin-bottom: 1.5rem;">
            <label for="correo" style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--color-texto-principal);">Correo de Destino:</label>
            <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" required style="width: 100%; padding: 0.8rem; border: 1px solid var(--color-borde); border-radius: var(--radio-pequeno); background: var(--color-fondo-input); color: var(--color-texto-principal); font-size: 1rem; outline: none;">
        </div>

        <div style="display: flex; gap: 10px; justify-content: space-between;">
            <button type="submit" name="btn_enviar_registro" style="flex: 1; padding: 0.8rem; border: none; border-radius: var(--radio-pequeno); background: var(--color-boton-principal); color: var(--color-boton-principal-texto); font-weight: 700; cursor: pointer; transition: 0.2s;">
                Enviar Registro
            </button>

            <button type="submit" name="btn_enviar_codigo" style="flex: 1; padding: 0.8rem; border: none; border-radius: var(--radio-pequeno); background: var(--color-texto-principal); color: #fff; font-weight: 700; cursor: pointer; transition: 0.2s;">
                Enviar Código
            </button>
        </div>
    </form>

    <?php if (!empty($mensajeEstado)): ?>
        <div style="margin-top: 1.5rem; padding: 1rem; border-radius: var(--radio-pequeno); font-size: 0.9rem; font-weight: 500; <?php echo ($claseMensaje === 'exito') ? 'background: rgba(46, 125, 50, 0.1); color: #2e7d32; border: 1px solid #c8e6c9;' : 'background: rgba(198, 40, 40, 0.1); color: #c62828; border: 1px solid #ffcdd2;'; ?>">
            <?php echo htmlspecialchars($mensajeEstado); ?>
        </div>
    <?php endif; ?>
</div>
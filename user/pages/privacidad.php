<?php
/*
    Aviso de Privacidad (RLN-03)
    Página de solo lectura, enlazada desde la casilla obligatoria
    del formulario de registro.
*/
?>

<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>legal.css?v=1">

<section class="seccion-legal">
    <div class="contenedor-legal">

        <p class="migaja-legal"><a href="<?php echo URL_BASE; ?>">CHAMBEKI</a> / Aviso de privacidad</p>

        <h1 class="titulo-legal">Aviso de Privacidad</h1>
        <p class="fecha-legal">Última actualización: septiembre de 2026</p>

        <div class="tarjeta-legal">

            <h2>1. Información que recopilamos</h2>
            <p>
                Para brindarte nuestros servicios, recopilamos tu nombre completo, 
                correo electrónico, número de teléfono de 10 dígitos y una fotografía de 
                perfil (o logotipo en caso de cuentas empresariales). También conservamos 
                un registro de las búsquedas y citas que agendas dentro de la plataforma 
                para mejorar tu experiencia.
            </p>

            <h2>2. Tratamiento de tu ubicación GPS</h2>
            <p>
                Tratamos tus datos de geolocalización con estricta confidencialidad. Tu 
                ubicación exacta solo se captura si otorgas tu consentimiento explícito 
                desde el navegador. Esta información se utiliza exclusivamente para 
                calcular la distancia con los profesionales o, si eres freelancer, para 
                trazar tu radio de cobertura de trabajo.
            </p>

            <h2>3. Seguridad de contraseñas y códigos</h2>
            <p>
                Tu seguridad es nuestra prioridad. Tu contraseña es procesada mediante 
                algoritmos de encriptación de alta seguridad desde el momento de tu registro, 
                por lo que ni siquiera nuestro equipo técnico tiene acceso a ella en texto plano. 
                Toda recuperación de cuenta se gestiona mediante códigos de verificación 
                temporales de 6 dígitos enviados directamente a tu correo.
            </p>

            <h2>4. Datos financieros y pasarela de pago</h2>
            <p>
                CHAMBEKI no almacena números de tarjetas de crédito o débito, ni códigos CVV 
                en sus servidores. Todo el procesamiento financiero se realiza mediante una 
                pasarela de pagos externa certificada. Únicamente conservamos un token de 
                seguridad y los últimos 4 dígitos de tu tarjeta para facilitar futuras 
                contrataciones o gestionar reembolsos autorizados.
            </p>

            <h2>5. Derechos ARCO y contacto</h2>
            <p>
                Tienes derecho a acceder, rectificar, cancelar u oponerte al tratamiento 
                de tus datos personales en cualquier momento. Si deseas dar de baja tu cuenta 
                o ejercer tus derechos, por favor envíanos un correo electrónico a 
                <strong>admin@chambeki.com</strong>.
            </p>

        </div>

        <div class="acciones-legal">
            <!-- Botón para regresar al registro o a la página anterior -->
            <a href="<?php echo URL_BASE; ?>?accion=registro">Volver al registro</a>
        </div>

    </div>
</section>
<?php
/*
    Planes de CHAMBEKI: Basico y Premium.
    Pagina informativa. Todavia no cobra ni activa beneficios:
    el boton de Premium queda deshabilitado hasta implementarlo.
*/

$haySesion = hayUsuarioEnSesion();
?>

<section class="seccion-planes">
    <div class="contenedor-planes">

        <header class="cabecera-planes">
            <h1>Elige cómo quieres usar CHAMBEKI</h1>
            <p>Buscar y contratar servicios es gratis para todos. Si ofreces tus servicios como freelancer, Premium te ayuda a destacar.</p>
        </header>

        <div class="rejilla-planes">

            <!-- Plan Basico -->
            <article class="plan plan-basico" aria-labelledby="tituloPlanBasico">
                <h2 id="tituloPlanBasico">Básico</h2>
                <p class="descripcion-plan">Todo lo que ya puedes hacer en CHAMBEKI.</p>

                <p class="precio-plan"><strong>Gratis</strong></p>

                <ul class="lista-beneficios">
                    <li>Buscar servicios por oficio y ubicación</li>
                    <li>Crear tu cuenta personal o empresarial</li>
                    <li>Configurar tu perfil y tu radio de cobertura</li>
                    <li>Guardar tus métodos de pago</li>
                    <li>Publicar tus servicios como freelancer</li>
                </ul>

                <?php if ($haySesion): ?>
                    <a href="<?php echo URL_BASE; ?>?accion=configuracion" class="boton-plan boton-plan-secundario">Ir a mi perfil</a>
                <?php else: ?>
                    <a href="<?php echo URL_BASE; ?>?accion=registro" class="boton-plan boton-plan-secundario">Crear mi cuenta</a>
                <?php endif; ?>
            </article>

            <!-- Plan Premium -->
            <article class="plan plan-premium" aria-labelledby="tituloPlanPremium">
                <span class="insignia-plan">Para freelancers</span>

                <h2 id="tituloPlanPremium">Premium</h2>
                <p class="descripcion-plan">Todo lo del plan Básico, más herramientas para conseguir más clientes.</p>

                <p class="precio-plan">
                    <strong>$39</strong>
                    <span>MXN al mes</span>
                </p>

                <ul class="lista-beneficios">
                    <li>
                        <strong>Mayor prioridad en las búsquedas.</strong>
                        Tus servicios aparecen antes cuando un cliente busca tu oficio.
                    </li>
                    <li>
                        <strong>Perfil con más libertad de personalización.</strong>
                        Haz que tu perfil refleje tu trabajo y tu estilo.
                    </li>
                </ul>

                <button type="button" class="boton-plan boton-plan-principal" disabled>Próximamente</button>
                <p class="nota-plan">Premium es exclusivo para cuentas de freelancer.</p>
            </article>

        </div>

        <p class="pie-planes">
            ¿Dudas sobre los planes? Escríbenos a <strong>admin@chambeki.com</strong>.
        </p>

    </div>
</section>
<?php
/*
    PROCESO: Cambio / recuperacion de contrasena
    Requerimientos: RUS-01, RSIS-05, RS-01, RI-03, RS-05

    Pasos dentro de la misma pantalla:
      'correo' -> captura del correo y verificacion de existencia en BD
      'codigo' -> validacion del codigo temporal
      'nueva'  -> captura, cifrado y actualizacion de la contrasena
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

//ventana para definir la contrasena despues de validar el codigo
define('MINUTOS_VENTANA_CAMBIO', 10);

$errores = [];
$cambioCompletado = false;
$correoCapturado = '';

$paso = 'correo';

if (isset($_SESSION['recuperacion']))
{
    $paso = $_SESSION['recuperacion']['paso'];
    $correoCapturado = $_SESSION['recuperacion']['correo'];
}

if (isset($_SESSION['recuperacion_exito']))
{
    $cambioCompletado = true;
    unset($_SESSION['recuperacion_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = isset($_POST['operacion']) ? $_POST['operacion'] : '';

    switch ($operacion)
    {
        //-----------------------------------------------------
        // PASO 1: existe el correo en la base de datos?
        //-----------------------------------------------------
        case 'solicitar_codigo':
        {
            $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));

            if (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL))
            {
                $errores['correo'] = 'Escribe un correo valido.';
                break;
            }

            $usuario = obtenerUsuarioPorCorreo($correoCapturado);

            if ($usuario === null)
            {
                //el diagrama regresa al paso de capturar el correo
                $errores['correo'] = 'No encontramos una cuenta con ese correo.';
                break;
            }

            //RSIS-05 + RI-03: codigo temporal por SMTP con PHPMailer
            $codigo = generarCodigoVerificacion($correoCapturado, 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $correoCapturado, $usuario['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if (!$resultadoCorreo['exito'])
            {
                $errores['correo'] = 'No pudimos enviar el codigo a ese correo. Intenta de nuevo en un momento.';
                break;
            }

            $_SESSION['recuperacion'] = [
                'correo' => $correoCapturado,
                'nombre' => $usuario['nombre'],
                'paso'   => 'codigo'
            ];

            registrarEnBitacora($correoCapturado, 'codigo_recuperacion_enviado');

            guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $correoCapturado . '.');
            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // PASO 2: validacion del codigo temporal
        //-----------------------------------------------------
        case 'validar_codigo':
        {
            if (!isset($_SESSION['recuperacion']))
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $correoCapturado = $_SESSION['recuperacion']['correo'];
            $codigoCapturado = preg_replace('/[^0-9]/', '', limpiarEntrada($_POST['codigo'] ?? ''));

            $resultadoCodigo = validarCodigoVerificacion($correoCapturado, 'recuperacion', $codigoCapturado);

            if (!$resultadoCodigo['valido'])
            {
                $errores['codigo'] = $resultadoCodigo['mensaje'];
                $paso = 'codigo';
                break;
            }

            $_SESSION['recuperacion']['paso'] = 'nueva';
            $_SESSION['recuperacion']['verificado_en'] = time();

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // PASO 3: cifrado y actualizacion de la contrasena
        //-----------------------------------------------------
        case 'guardar_contrasena':
        {
            //solo se llega aqui con un codigo ya validado
            if (!isset($_SESSION['recuperacion']) || $_SESSION['recuperacion']['paso'] !== 'nueva')
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $recuperacion = $_SESSION['recuperacion'];
            $correoCapturado = $recuperacion['correo'];

            if ((time() - $recuperacion['verificado_en']) > (MINUTOS_VENTANA_CAMBIO * 60))
            {
                unset($_SESSION['recuperacion']);

                guardarMensaje('error', 'Se acabo el tiempo para cambiar la contrasena. Empieza de nuevo.');
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $contrasena = (string) ($_POST['contrasena'] ?? '');
            $confirmacion = (string) ($_POST['contrasena_confirmar'] ?? '');

            //RS-05: validacion del lado del servidor
            $errorContrasena = validarContrasena($contrasena, $confirmacion);

            if ($errorContrasena !== null)
            {
                $errores['contrasena'] = $errorContrasena;
                $paso = 'nueva';
                break;
            }

            //RS-01: se guarda el hash, nunca la contrasena en claro
            actualizarContrasena($correoCapturado, $contrasena);
            registrarEnBitacora($correoCapturado, 'contrasena_actualizada');

            //notificacion de exito al usuario
            enviarCorreo('contrasena_cambiada', $correoCapturado, $recuperacion['nombre']);

            unset($_SESSION['recuperacion']);
            $_SESSION['recuperacion_exito'] = true;

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // Reenviar el codigo
        //-----------------------------------------------------
        case 'reenviar_codigo':
        {
            if (!isset($_SESSION['recuperacion']))
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $recuperacion = $_SESSION['recuperacion'];

            $codigo = generarCodigoVerificacion($recuperacion['correo'], 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $recuperacion['correo'], $recuperacion['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if ($resultadoCorreo['exito'])
            {
                $_SESSION['recuperacion']['paso'] = 'codigo';
                guardarMensaje('exito', 'Listo, te enviamos un codigo nuevo.');
            }
            else
            {
                guardarMensaje('error', 'No se pudo reenviar el codigo. Intenta en un momento.');
            }

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // Cancelar
        //-----------------------------------------------------
        case 'cancelar_recuperacion':
        {
            unset($_SESSION['recuperacion']);
            redirigir(URL_BASE . '?accion=recuperar');
        }
    }
}

$mensajeFlash = obtenerMensaje();
?>

<?php abrirPantallaAutenticacion(null); ?>

        <?php if ($cambioCompletado): ?>

            <div class="bloque-confirmacion">
                <div class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h1 class="titulo-autenticacion">Contrasena actualizada</h1>
                <p class="texto-autenticacion">Te enviamos un correo confirmando el cambio. Ya puedes entrar con tu contrasena nueva.</p>
                <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-autenticacion">Iniciar sesion</a>
            </div>

        <?php elseif ($paso === 'correo'): ?>

            <h1 class="titulo-autenticacion">Recupera tu acceso</h1>
            <p class="texto-autenticacion">Escribe el correo de tu cuenta y te mandamos un codigo temporal para verificar que eres tu.</p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="solicitar_codigo">

                <div class="campo-formulario">
                    <label for="campoCorreoRecuperacion">Correo electronico</label>
                    <input type="email" id="campoCorreoRecuperacion" name="correo" class="icono-correo" value="<?php echo escaparSalida($correoCapturado); ?>" placeholder="tucorreo@ejemplo.com" required>
                    <?php if (isset($errores['correo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['correo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Enviar codigo</button>
            </form>

            <p class="pie-autenticacion">
                ¿Te acordaste? <a href="<?php echo URL_BASE; ?>?accion=login">Volver al inicio de sesion</a>
            </p>

        <?php elseif ($paso === 'codigo'): ?>

            <h1 class="titulo-autenticacion">Verifica que eres tu</h1>
            <p class="texto-autenticacion">
                Escribe el codigo de 6 digitos que enviamos a
                <strong><?php echo escaparSalida($correoCapturado); ?></strong>.
                Vence en <?php echo MINUTOS_VIGENCIA_CODIGO; ?> minutos.
            </p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="validar_codigo">

                <div class="campo-formulario">
                    <label for="campoCodigoRecuperacion">Codigo temporal</label>
                    <input type="text" id="campoCodigoRecuperacion" name="codigo" class="campo-codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
                    <?php if (isset($errores['codigo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['codigo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Continuar</button>
            </form>

            <div class="acciones-secundarias">
                <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST">
                    <input type="hidden" name="operacion" value="reenviar_codigo">
                    <button type="submit" class="boton-enlace">Reenviar codigo</button>
                </form>

                <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST">
                    <input type="hidden" name="operacion" value="cancelar_recuperacion">
                    <button type="submit" class="boton-enlace">Usar otro correo</button>
                </form>
            </div>

        <?php else: ?>

            <h1 class="titulo-autenticacion">Define tu contrasena nueva</h1>
            <p class="texto-autenticacion">Tienes <?php echo MINUTOS_VENTANA_CAMBIO; ?> minutos para terminar el cambio.</p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="guardar_contrasena">

                <div class="campo-formulario">
                    <label for="campoContrasenaNueva">Contrasena nueva</label>
                    <input type="password" id="campoContrasenaNueva" name="contrasena" class="icono-candado" placeholder="Minimo 8 caracteres" required>
                </div>

                <div class="campo-formulario">
                    <label for="campoContrasenaNuevaConfirmar">Confirma la contrasena</label>
                    <input type="password" id="campoContrasenaNuevaConfirmar" name="contrasena_confirmar" class="icono-candado" placeholder="Repitela" required>
                </div>

                <?php if (isset($errores['contrasena'])): ?>
                    <span class="error-campo"><?php echo escaparSalida($errores['contrasena']); ?></span>
                <?php else: ?>
                    <small class="ayuda-campo">Combina letras y numeros, minimo 8 caracteres.</small>
                <?php endif; ?>

                <button type="submit" class="boton-autenticacion">Guardar contrasena</button>
            </form>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>
<?php
/*
    PROCESO: Cambio / recuperacion de contrasena
    Requerimientos: RUS-01, RSIS-05, RS-01, RI-03, RS-05

    Pasos dentro de la misma pantalla:
      'correo' -> captura del correo y verificacion de existencia en BD
      'codigo' -> validacion del codigo temporal
      'nueva'  -> captura, cifrado y actualizacion de la contrasena
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

//ventana para definir la contrasena despues de validar el codigo
define('MINUTOS_VENTANA_CAMBIO', 10);

$errores = [];
$cambioCompletado = false;
$correoCapturado = '';

$paso = 'correo';

if (isset($_SESSION['recuperacion']))
{
    $paso = $_SESSION['recuperacion']['paso'];
    $correoCapturado = $_SESSION['recuperacion']['correo'];
}

if (isset($_SESSION['recuperacion_exito']))
{
    $cambioCompletado = true;
    unset($_SESSION['recuperacion_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = isset($_POST['operacion']) ? $_POST['operacion'] : '';

    switch ($operacion)
    {
        //-----------------------------------------------------
        // PASO 1: existe el correo en la base de datos?
        //-----------------------------------------------------
        case 'solicitar_codigo':
        {
            $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));

            if (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL))
            {
                $errores['correo'] = 'Escribe un correo valido.';
                break;
            }

            $usuario = obtenerUsuarioPorCorreo($correoCapturado);

            if ($usuario === null)
            {
                //el diagrama regresa al paso de capturar el correo
                $errores['correo'] = 'No encontramos una cuenta con ese correo.';
                break;
            }

            //RSIS-05 + RI-03: codigo temporal por SMTP con PHPMailer
            $codigo = generarCodigoVerificacion($correoCapturado, 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $correoCapturado, $usuario['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if (!$resultadoCorreo['exito'])
            {
                $errores['correo'] = 'No pudimos enviar el codigo a ese correo. Intenta de nuevo en un momento.';
                break;
            }

            $_SESSION['recuperacion'] = [
                'correo' => $correoCapturado,
                'nombre' => $usuario['nombre'],
                'paso'   => 'codigo'
            ];

            registrarEnBitacora($correoCapturado, 'codigo_recuperacion_enviado');

            guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $correoCapturado . '.');
            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // PASO 2: validacion del codigo temporal
        //-----------------------------------------------------
        case 'validar_codigo':
        {
            if (!isset($_SESSION['recuperacion']))
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $correoCapturado = $_SESSION['recuperacion']['correo'];
            $codigoCapturado = preg_replace('/[^0-9]/', '', limpiarEntrada($_POST['codigo'] ?? ''));

            $resultadoCodigo = validarCodigoVerificacion($correoCapturado, 'recuperacion', $codigoCapturado);

            if (!$resultadoCodigo['valido'])
            {
                $errores['codigo'] = $resultadoCodigo['mensaje'];
                $paso = 'codigo';
                break;
            }

            $_SESSION['recuperacion']['paso'] = 'nueva';
            $_SESSION['recuperacion']['verificado_en'] = time();

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // PASO 3: cifrado y actualizacion de la contrasena
        //-----------------------------------------------------
        case 'guardar_contrasena':
        {
            //solo se llega aqui con un codigo ya validado
            if (!isset($_SESSION['recuperacion']) || $_SESSION['recuperacion']['paso'] !== 'nueva')
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $recuperacion = $_SESSION['recuperacion'];
            $correoCapturado = $recuperacion['correo'];

            if ((time() - $recuperacion['verificado_en']) > (MINUTOS_VENTANA_CAMBIO * 60))
            {
                unset($_SESSION['recuperacion']);

                guardarMensaje('error', 'Se acabo el tiempo para cambiar la contrasena. Empieza de nuevo.');
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $contrasena = (string) ($_POST['contrasena'] ?? '');
            $confirmacion = (string) ($_POST['contrasena_confirmar'] ?? '');

            //RS-05: validacion del lado del servidor
            $errorContrasena = validarContrasena($contrasena, $confirmacion);

            if ($errorContrasena !== null)
            {
                $errores['contrasena'] = $errorContrasena;
                $paso = 'nueva';
                break;
            }

            //RS-01: se guarda el hash, nunca la contrasena en claro
            actualizarContrasena($correoCapturado, $contrasena);
            registrarEnBitacora($correoCapturado, 'contrasena_actualizada');

            //notificacion de exito al usuario
            enviarCorreo('contrasena_cambiada', $correoCapturado, $recuperacion['nombre']);

            unset($_SESSION['recuperacion']);
            $_SESSION['recuperacion_exito'] = true;

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // Reenviar el codigo
        //-----------------------------------------------------
        case 'reenviar_codigo':
        {
            if (!isset($_SESSION['recuperacion']))
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $recuperacion = $_SESSION['recuperacion'];

            $codigo = generarCodigoVerificacion($recuperacion['correo'], 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $recuperacion['correo'], $recuperacion['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if ($resultadoCorreo['exito'])
            {
                $_SESSION['recuperacion']['paso'] = 'codigo';
                guardarMensaje('exito', 'Listo, te enviamos un codigo nuevo.');
            }
            else
            {
                guardarMensaje('error', 'No se pudo reenviar el codigo. Intenta en un momento.');
            }

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // Cancelar
        //-----------------------------------------------------
        case 'cancelar_recuperacion':
        {
            unset($_SESSION['recuperacion']);
            redirigir(URL_BASE . '?accion=recuperar');
        }
    }
}

$mensajeFlash = obtenerMensaje();
?>

<?php abrirPantallaAutenticacion(null); ?>

        <?php if ($cambioCompletado): ?>

            <div class="bloque-confirmacion">
                <div class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h1 class="titulo-autenticacion">Contrasena actualizada</h1>
                <p class="texto-autenticacion">Te enviamos un correo confirmando el cambio. Ya puedes entrar con tu contrasena nueva.</p>
                <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-autenticacion">Iniciar sesion</a>
            </div>

        <?php elseif ($paso === 'correo'): ?>

            <h1 class="titulo-autenticacion">Recupera tu acceso</h1>
            <p class="texto-autenticacion">Escribe el correo de tu cuenta y te mandamos un codigo temporal para verificar que eres tu.</p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="solicitar_codigo">

                <div class="campo-formulario">
                    <label for="campoCorreoRecuperacion">Correo electronico</label>
                    <input type="email" id="campoCorreoRecuperacion" name="correo" class="icono-correo" value="<?php echo escaparSalida($correoCapturado); ?>" placeholder="tucorreo@ejemplo.com" required>
                    <?php if (isset($errores['correo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['correo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Enviar codigo</button>
            </form>

            <p class="pie-autenticacion">
                ¿Te acordaste? <a href="<?php echo URL_BASE; ?>?accion=login">Volver al inicio de sesion</a>
            </p>

        <?php elseif ($paso === 'codigo'): ?>

            <h1 class="titulo-autenticacion">Verifica que eres tu</h1>
            <p class="texto-autenticacion">
                Escribe el codigo de 6 digitos que enviamos a
                <strong><?php echo escaparSalida($correoCapturado); ?></strong>.
                Vence en <?php echo MINUTOS_VIGENCIA_CODIGO; ?> minutos.
            </p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="validar_codigo">

                <div class="campo-formulario">
                    <label for="campoCodigoRecuperacion">Codigo temporal</label>
                    <input type="text" id="campoCodigoRecuperacion" name="codigo" class="campo-codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
                    <?php if (isset($errores['codigo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['codigo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Continuar</button>
            </form>

            <div class="acciones-secundarias">
                <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST">
                    <input type="hidden" name="operacion" value="reenviar_codigo">
                    <button type="submit" class="boton-enlace">Reenviar codigo</button>
                </form>

                <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST">
                    <input type="hidden" name="operacion" value="cancelar_recuperacion">
                    <button type="submit" class="boton-enlace">Usar otro correo</button>
                </form>
            </div>

        <?php else: ?>

            <h1 class="titulo-autenticacion">Define tu contrasena nueva</h1>
            <p class="texto-autenticacion">Tienes <?php echo MINUTOS_VENTANA_CAMBIO; ?> minutos para terminar el cambio.</p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="guardar_contrasena">

                <div class="campo-formulario">
                    <label for="campoContrasenaNueva">Contrasena nueva</label>
                    <input type="password" id="campoContrasenaNueva" name="contrasena" class="icono-candado" placeholder="Minimo 8 caracteres" required>
                </div>

                <div class="campo-formulario">
                    <label for="campoContrasenaNuevaConfirmar">Confirma la contrasena</label>
                    <input type="password" id="campoContrasenaNuevaConfirmar" name="contrasena_confirmar" class="icono-candado" placeholder="Repitela" required>
                </div>

                <?php if (isset($errores['contrasena'])): ?>
                    <span class="error-campo"><?php echo escaparSalida($errores['contrasena']); ?></span>
                <?php else: ?>
                    <small class="ayuda-campo">Combina letras y numeros, minimo 8 caracteres.</small>
                <?php endif; ?>

                <button type="submit" class="boton-autenticacion">Guardar contrasena</button>
            </form>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>
<?php
/*
    PROCESO: Cambio / recuperacion de contrasena
    Requerimientos: RUS-01, RSIS-05, RS-01, RI-03, RS-05

    Pasos dentro de la misma pantalla:
      'correo' -> captura del correo y verificacion de existencia en BD
      'codigo' -> validacion del codigo temporal
      'nueva'  -> captura, cifrado y actualizacion de la contrasena
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

//ventana para definir la contrasena despues de validar el codigo
define('MINUTOS_VENTANA_CAMBIO', 10);

$errores = [];
$cambioCompletado = false;
$correoCapturado = '';

$paso = 'correo';

if (isset($_SESSION['recuperacion']))
{
    $paso = $_SESSION['recuperacion']['paso'];
    $correoCapturado = $_SESSION['recuperacion']['correo'];
}

if (isset($_SESSION['recuperacion_exito']))
{
    $cambioCompletado = true;
    unset($_SESSION['recuperacion_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = isset($_POST['operacion']) ? $_POST['operacion'] : '';

    switch ($operacion)
    {
        //-----------------------------------------------------
        // PASO 1: existe el correo en la base de datos?
        //-----------------------------------------------------
        case 'solicitar_codigo':
        {
            $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));

            if (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL))
            {
                $errores['correo'] = 'Escribe un correo valido.';
                break;
            }

            $usuario = obtenerUsuarioPorCorreo($correoCapturado);

            if ($usuario === null)
            {
                //el diagrama regresa al paso de capturar el correo
                $errores['correo'] = 'No encontramos una cuenta con ese correo.';
                break;
            }

            //RSIS-05 + RI-03: codigo temporal por SMTP con PHPMailer
            $codigo = generarCodigoVerificacion($correoCapturado, 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $correoCapturado, $usuario['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if (!$resultadoCorreo['exito'])
            {
                $errores['correo'] = 'No pudimos enviar el codigo a ese correo. Intenta de nuevo en un momento.';
                break;
            }

            $_SESSION['recuperacion'] = [
                'correo' => $correoCapturado,
                'nombre' => $usuario['nombre'],
                'paso'   => 'codigo'
            ];

            registrarEnBitacora($correoCapturado, 'codigo_recuperacion_enviado');

            guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $correoCapturado . '.');
            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // PASO 2: validacion del codigo temporal
        //-----------------------------------------------------
        case 'validar_codigo':
        {
            if (!isset($_SESSION['recuperacion']))
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $correoCapturado = $_SESSION['recuperacion']['correo'];
            $codigoCapturado = preg_replace('/[^0-9]/', '', limpiarEntrada($_POST['codigo'] ?? ''));

            $resultadoCodigo = validarCodigoVerificacion($correoCapturado, 'recuperacion', $codigoCapturado);

            if (!$resultadoCodigo['valido'])
            {
                $errores['codigo'] = $resultadoCodigo['mensaje'];
                $paso = 'codigo';
                break;
            }

            $_SESSION['recuperacion']['paso'] = 'nueva';
            $_SESSION['recuperacion']['verificado_en'] = time();

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // PASO 3: cifrado y actualizacion de la contrasena
        //-----------------------------------------------------
        case 'guardar_contrasena':
        {
            //solo se llega aqui con un codigo ya validado
            if (!isset($_SESSION['recuperacion']) || $_SESSION['recuperacion']['paso'] !== 'nueva')
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $recuperacion = $_SESSION['recuperacion'];
            $correoCapturado = $recuperacion['correo'];

            if ((time() - $recuperacion['verificado_en']) > (MINUTOS_VENTANA_CAMBIO * 60))
            {
                unset($_SESSION['recuperacion']);

                guardarMensaje('error', 'Se acabo el tiempo para cambiar la contrasena. Empieza de nuevo.');
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $contrasena = (string) ($_POST['contrasena'] ?? '');
            $confirmacion = (string) ($_POST['contrasena_confirmar'] ?? '');

            //RS-05: validacion del lado del servidor
            $errorContrasena = validarContrasena($contrasena, $confirmacion);

            if ($errorContrasena !== null)
            {
                $errores['contrasena'] = $errorContrasena;
                $paso = 'nueva';
                break;
            }

            //RS-01: se guarda el hash, nunca la contrasena en claro
            actualizarContrasena($correoCapturado, $contrasena);
            registrarEnBitacora($correoCapturado, 'contrasena_actualizada');

            //notificacion de exito al usuario
            enviarCorreo('contrasena_cambiada', $correoCapturado, $recuperacion['nombre']);

            unset($_SESSION['recuperacion']);
            $_SESSION['recuperacion_exito'] = true;

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // Reenviar el codigo
        //-----------------------------------------------------
        case 'reenviar_codigo':
        {
            if (!isset($_SESSION['recuperacion']))
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $recuperacion = $_SESSION['recuperacion'];

            $codigo = generarCodigoVerificacion($recuperacion['correo'], 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $recuperacion['correo'], $recuperacion['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if ($resultadoCorreo['exito'])
            {
                $_SESSION['recuperacion']['paso'] = 'codigo';
                guardarMensaje('exito', 'Listo, te enviamos un codigo nuevo.');
            }
            else
            {
                guardarMensaje('error', 'No se pudo reenviar el codigo. Intenta en un momento.');
            }

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // Cancelar
        //-----------------------------------------------------
        case 'cancelar_recuperacion':
        {
            unset($_SESSION['recuperacion']);
            redirigir(URL_BASE . '?accion=recuperar');
        }
    }
}

$mensajeFlash = obtenerMensaje();
?>

<?php abrirPantallaAutenticacion(null); ?>

        <?php if ($cambioCompletado): ?>

            <div class="bloque-confirmacion">
                <div class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h1 class="titulo-autenticacion">Contrasena actualizada</h1>
                <p class="texto-autenticacion">Te enviamos un correo confirmando el cambio. Ya puedes entrar con tu contrasena nueva.</p>
                <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-autenticacion">Iniciar sesion</a>
            </div>

        <?php elseif ($paso === 'correo'): ?>

            <h1 class="titulo-autenticacion">Recupera tu acceso</h1>
            <p class="texto-autenticacion">Escribe el correo de tu cuenta y te mandamos un codigo temporal para verificar que eres tu.</p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="solicitar_codigo">

                <div class="campo-formulario">
                    <label for="campoCorreoRecuperacion">Correo electronico</label>
                    <input type="email" id="campoCorreoRecuperacion" name="correo" class="icono-correo" value="<?php echo escaparSalida($correoCapturado); ?>" placeholder="tucorreo@ejemplo.com" required>
                    <?php if (isset($errores['correo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['correo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Enviar codigo</button>
            </form>

            <p class="pie-autenticacion">
                ¿Te acordaste? <a href="<?php echo URL_BASE; ?>?accion=login">Volver al inicio de sesion</a>
            </p>

        <?php elseif ($paso === 'codigo'): ?>

            <h1 class="titulo-autenticacion">Verifica que eres tu</h1>
            <p class="texto-autenticacion">
                Escribe el codigo de 6 digitos que enviamos a
                <strong><?php echo escaparSalida($correoCapturado); ?></strong>.
                Vence en <?php echo MINUTOS_VIGENCIA_CODIGO; ?> minutos.
            </p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="validar_codigo">

                <div class="campo-formulario">
                    <label for="campoCodigoRecuperacion">Codigo temporal</label>
                    <input type="text" id="campoCodigoRecuperacion" name="codigo" class="campo-codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
                    <?php if (isset($errores['codigo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['codigo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Continuar</button>
            </form>

            <div class="acciones-secundarias">
                <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST">
                    <input type="hidden" name="operacion" value="reenviar_codigo">
                    <button type="submit" class="boton-enlace">Reenviar codigo</button>
                </form>

                <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST">
                    <input type="hidden" name="operacion" value="cancelar_recuperacion">
                    <button type="submit" class="boton-enlace">Usar otro correo</button>
                </form>
            </div>

        <?php else: ?>

            <h1 class="titulo-autenticacion">Define tu contrasena nueva</h1>
            <p class="texto-autenticacion">Tienes <?php echo MINUTOS_VENTANA_CAMBIO; ?> minutos para terminar el cambio.</p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="guardar_contrasena">

                <div class="campo-formulario">
                    <label for="campoContrasenaNueva">Contrasena nueva</label>
                    <input type="password" id="campoContrasenaNueva" name="contrasena" class="icono-candado" placeholder="Minimo 8 caracteres" required>
                </div>

                <div class="campo-formulario">
                    <label for="campoContrasenaNuevaConfirmar">Confirma la contrasena</label>
                    <input type="password" id="campoContrasenaNuevaConfirmar" name="contrasena_confirmar" class="icono-candado" placeholder="Repitela" required>
                </div>

                <?php if (isset($errores['contrasena'])): ?>
                    <span class="error-campo"><?php echo escaparSalida($errores['contrasena']); ?></span>
                <?php else: ?>
                    <small class="ayuda-campo">Combina letras y numeros, minimo 8 caracteres.</small>
                <?php endif; ?>

                <button type="submit" class="boton-autenticacion">Guardar contrasena</button>
            </form>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>
<?php
/*
    PROCESO: Cambio / recuperacion de contrasena (paso 1 de 3)
    Requerimientos: RUS-01, RSIS-05, RI-03

    Esta pantalla solo captura el correo y envia el codigo.
    Los otros dos pasos viven en sus propias paginas:
      user/pages/verificarCodigoRecuperacion.php (accion=verificar-codigo-recuperacion)
      user/pages/nuevaContrasena.php             (accion=nueva-contrasena)
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

//si ya se envio un codigo, esta pantalla no tiene nada que hacer
if (isset($_SESSION['recuperacion']) && $_SESSION['recuperacion']['paso'] === 'codigo')
{
    redirigir(URL_BASE . '?accion=verificar-codigo-recuperacion');
}

if (isset($_SESSION['recuperacion']) && $_SESSION['recuperacion']['paso'] === 'nueva')
{
    redirigir(URL_BASE . '?accion=nueva-contrasena');
}

$errores = [];
$cambioCompletado = false;
$correoCapturado = '';

if (isset($_SESSION['recuperacion_exito']))
{
    $cambioCompletado = true;
    unset($_SESSION['recuperacion_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'solicitar_codigo')
{
    $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));

    if (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL))
    {
        $errores['correo'] = 'Escribe un correo valido.';
    }
    else
    {
        $usuario = obtenerUsuarioPorCorreo($correoCapturado);

        if ($usuario === null)
        {
            //el diagrama regresa al paso de capturar el correo
            $errores['correo'] = 'No encontramos una cuenta con ese correo.';
        }
        else
        {
            //RSIS-05 + RI-03: codigo temporal por SMTP con PHPMailer
            $codigo = generarCodigoVerificacion($correoCapturado, 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $correoCapturado, $usuario['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if (!$resultadoCorreo['exito'])
            {
                $errores['correo'] = 'No pudimos enviar el codigo a ese correo. Intenta de nuevo en un momento.';
            }
            else
            {
                $_SESSION['recuperacion'] = [
                    'correo' => $correoCapturado,
                    'nombre' => $usuario['nombre'],
                    'paso'   => 'codigo'
                ];

                registrarEnBitacora($correoCapturado, 'codigo_recuperacion_enviado');

                guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $correoCapturado . '.');

                //aqui esta el reenvio a la pagina dedicada del codigo
                redirigir(URL_BASE . '?accion=verificar-codigo-recuperacion');
            }
        }
    }
}

abrirPantallaAutenticacion(null);
?>

        <?php if ($cambioCompletado): ?>

            <div class="bloque-confirmacion">
                <div class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h1 class="titulo-autenticacion">Contrasena actualizada</h1>
                <p class="texto-autenticacion">Te enviamos un correo confirmando el cambio. Ya puedes entrar con tu contrasena nueva.</p>
                <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-autenticacion">Iniciar sesion</a>
            </div>

        <?php else: ?>

            <h1 class="titulo-autenticacion">Recupera tu acceso</h1>
            <p class="texto-autenticacion">Escribe el correo de tu cuenta y te mandamos un codigo temporal para verificar que eres tu.</p>

            <?php if (isset($errores['correo'])): ?>
                <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($errores['correo']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="solicitar_codigo">

                <div class="campo-formulario">
                    <label for="campoCorreoRecuperacion">Correo electronico</label>
                    <input type="email" id="campoCorreoRecuperacion" name="correo" class="icono-correo" value="<?php echo escaparSalida($correoCapturado); ?>" placeholder="tucorreo@ejemplo.com" required>
                </div>

                <button type="submit" class="boton-autenticacion">Enviar codigo</button>
            </form>

            <p class="pie-autenticacion">
                ¿Te acordaste? <a href="<?php echo URL_BASE; ?>?accion=login">Volver al inicio de sesion</a>
            </p>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>
<?php
/*
    PROCESO: Cambio / recuperacion de contrasena
    Requerimientos: RUS-01, RSIS-05, RS-01, RI-03, RS-05

    Pasos dentro de la misma pantalla:
      'correo' -> captura del correo y verificacion de existencia en BD
      'codigo' -> validacion del codigo temporal
      'nueva'  -> captura, cifrado y actualizacion de la contrasena
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

//ventana para definir la contrasena despues de validar el codigo
define('MINUTOS_VENTANA_CAMBIO', 10);

$errores = [];
$cambioCompletado = false;
$correoCapturado = '';

$paso = 'correo';

if (isset($_SESSION['recuperacion']))
{
    $paso = $_SESSION['recuperacion']['paso'];
    $correoCapturado = $_SESSION['recuperacion']['correo'];
}

if (isset($_SESSION['recuperacion_exito']))
{
    $cambioCompletado = true;
    unset($_SESSION['recuperacion_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = isset($_POST['operacion']) ? $_POST['operacion'] : '';

    switch ($operacion)
    {
        //-----------------------------------------------------
        // PASO 1: existe el correo en la base de datos?
        //-----------------------------------------------------
        case 'solicitar_codigo':
        {
            $correoCapturado = strtolower(limpiarEntrada($_POST['correo'] ?? ''));

            if (!filter_var($correoCapturado, FILTER_VALIDATE_EMAIL))
            {
                $errores['correo'] = 'Escribe un correo valido.';
                break;
            }

            $usuario = obtenerUsuarioPorCorreo($correoCapturado);

            if ($usuario === null)
            {
                //el diagrama regresa al paso de capturar el correo
                $errores['correo'] = 'No encontramos una cuenta con ese correo.';
                break;
            }

            //RSIS-05 + RI-03: codigo temporal por SMTP con PHPMailer
            $codigo = generarCodigoVerificacion($correoCapturado, 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $correoCapturado, $usuario['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if (!$resultadoCorreo['exito'])
            {
                $errores['correo'] = 'No pudimos enviar el codigo a ese correo. Intenta de nuevo en un momento.';
                break;
            }

            $_SESSION['recuperacion'] = [
                'correo' => $correoCapturado,
                'nombre' => $usuario['nombre'],
                'paso'   => 'codigo'
            ];

            registrarEnBitacora($correoCapturado, 'codigo_recuperacion_enviado');

            guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $correoCapturado . '.');
            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // PASO 2: validacion del codigo temporal
        //-----------------------------------------------------
        case 'validar_codigo':
        {
            if (!isset($_SESSION['recuperacion']))
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $correoCapturado = $_SESSION['recuperacion']['correo'];
            $codigoCapturado = preg_replace('/[^0-9]/', '', limpiarEntrada($_POST['codigo'] ?? ''));

            $resultadoCodigo = validarCodigoVerificacion($correoCapturado, 'recuperacion', $codigoCapturado);

            if (!$resultadoCodigo['valido'])
            {
                $errores['codigo'] = $resultadoCodigo['mensaje'];
                $paso = 'codigo';
                break;
            }

            $_SESSION['recuperacion']['paso'] = 'nueva';
            $_SESSION['recuperacion']['verificado_en'] = time();

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // PASO 3: cifrado y actualizacion de la contrasena
        //-----------------------------------------------------
        case 'guardar_contrasena':
        {
            //solo se llega aqui con un codigo ya validado
            if (!isset($_SESSION['recuperacion']) || $_SESSION['recuperacion']['paso'] !== 'nueva')
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $recuperacion = $_SESSION['recuperacion'];
            $correoCapturado = $recuperacion['correo'];

            if ((time() - $recuperacion['verificado_en']) > (MINUTOS_VENTANA_CAMBIO * 60))
            {
                unset($_SESSION['recuperacion']);

                guardarMensaje('error', 'Se acabo el tiempo para cambiar la contrasena. Empieza de nuevo.');
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $contrasena = (string) ($_POST['contrasena'] ?? '');
            $confirmacion = (string) ($_POST['contrasena_confirmar'] ?? '');

            //RS-05: validacion del lado del servidor
            $errorContrasena = validarContrasena($contrasena, $confirmacion);

            if ($errorContrasena !== null)
            {
                $errores['contrasena'] = $errorContrasena;
                $paso = 'nueva';
                break;
            }

            //RS-01: se guarda el hash, nunca la contrasena en claro
            actualizarContrasena($correoCapturado, $contrasena);
            registrarEnBitacora($correoCapturado, 'contrasena_actualizada');

            //notificacion de exito al usuario
            enviarCorreo('contrasena_cambiada', $correoCapturado, $recuperacion['nombre']);

            unset($_SESSION['recuperacion']);
            $_SESSION['recuperacion_exito'] = true;

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // Reenviar el codigo
        //-----------------------------------------------------
        case 'reenviar_codigo':
        {
            if (!isset($_SESSION['recuperacion']))
            {
                redirigir(URL_BASE . '?accion=recuperar');
            }

            $recuperacion = $_SESSION['recuperacion'];

            $codigo = generarCodigoVerificacion($recuperacion['correo'], 'recuperacion');

            $resultadoCorreo = enviarCorreo('codigo', $recuperacion['correo'], $recuperacion['nombre'], [
                'codigo'   => $codigo,
                'motivo'   => 'recuperacion',
                'vigencia' => MINUTOS_VIGENCIA_CODIGO
            ]);

            if ($resultadoCorreo['exito'])
            {
                $_SESSION['recuperacion']['paso'] = 'codigo';
                guardarMensaje('exito', 'Listo, te enviamos un codigo nuevo.');
            }
            else
            {
                guardarMensaje('error', 'No se pudo reenviar el codigo. Intenta en un momento.');
            }

            redirigir(URL_BASE . '?accion=recuperar');
        }

        //-----------------------------------------------------
        // Cancelar
        //-----------------------------------------------------
        case 'cancelar_recuperacion':
        {
            unset($_SESSION['recuperacion']);
            redirigir(URL_BASE . '?accion=recuperar');
        }
    }
}

$mensajeFlash = obtenerMensaje();
?>

<?php abrirPantallaAutenticacion(null); ?>

        <?php if ($cambioCompletado): ?>

            <div class="bloque-confirmacion">
                <div class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h1 class="titulo-autenticacion">Contrasena actualizada</h1>
                <p class="texto-autenticacion">Te enviamos un correo confirmando el cambio. Ya puedes entrar con tu contrasena nueva.</p>
                <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-autenticacion">Iniciar sesion</a>
            </div>

        <?php elseif ($paso === 'correo'): ?>

            <h1 class="titulo-autenticacion">Recupera tu acceso</h1>
            <p class="texto-autenticacion">Escribe el correo de tu cuenta y te mandamos un codigo temporal para verificar que eres tu.</p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="solicitar_codigo">

                <div class="campo-formulario">
                    <label for="campoCorreoRecuperacion">Correo electronico</label>
                    <input type="email" id="campoCorreoRecuperacion" name="correo" class="icono-correo" value="<?php echo escaparSalida($correoCapturado); ?>" placeholder="tucorreo@ejemplo.com" required>
                    <?php if (isset($errores['correo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['correo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Enviar codigo</button>
            </form>

            <p class="pie-autenticacion">
                ¿Te acordaste? <a href="<?php echo URL_BASE; ?>?accion=login">Volver al inicio de sesion</a>
            </p>

        <?php elseif ($paso === 'codigo'): ?>

            <h1 class="titulo-autenticacion">Verifica que eres tu</h1>
            <p class="texto-autenticacion">
                Escribe el codigo de 6 digitos que enviamos a
                <strong><?php echo escaparSalida($correoCapturado); ?></strong>.
                Vence en <?php echo MINUTOS_VIGENCIA_CODIGO; ?> minutos.
            </p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="validar_codigo">

                <div class="campo-formulario">
                    <label for="campoCodigoRecuperacion">Codigo temporal</label>
                    <input type="text" id="campoCodigoRecuperacion" name="codigo" class="campo-codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" required>
                    <?php if (isset($errores['codigo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['codigo']); ?></span><?php endif; ?>
                </div>

                <button type="submit" class="boton-autenticacion">Continuar</button>
            </form>

            <div class="acciones-secundarias">
                <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST">
                    <input type="hidden" name="operacion" value="reenviar_codigo">
                    <button type="submit" class="boton-enlace">Reenviar codigo</button>
                </form>

                <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST">
                    <input type="hidden" name="operacion" value="cancelar_recuperacion">
                    <button type="submit" class="boton-enlace">Usar otro correo</button>
                </form>
            </div>

        <?php else: ?>

            <h1 class="titulo-autenticacion">Define tu contrasena nueva</h1>
            <p class="texto-autenticacion">Tienes <?php echo MINUTOS_VENTANA_CAMBIO; ?> minutos para terminar el cambio.</p>

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=recuperar" method="POST" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="guardar_contrasena">

                <div class="campo-formulario">
                    <label for="campoContrasenaNueva">Contrasena nueva</label>
                    <input type="password" id="campoContrasenaNueva" name="contrasena" class="icono-candado" placeholder="Minimo 8 caracteres" required>
                </div>

                <div class="campo-formulario">
                    <label for="campoContrasenaNuevaConfirmar">Confirma la contrasena</label>
                    <input type="password" id="campoContrasenaNuevaConfirmar" name="contrasena_confirmar" class="icono-candado" placeholder="Repitela" required>
                </div>

                <?php if (isset($errores['contrasena'])): ?>
                    <span class="error-campo"><?php echo escaparSalida($errores['contrasena']); ?></span>
                <?php else: ?>
                    <small class="ayuda-campo">Combina letras y numeros, minimo 8 caracteres.</small>
                <?php endif; ?>

                <button type="submit" class="boton-autenticacion">Guardar contrasena</button>
            </form>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>

<?php
/*
    PROCESO: Registro de usuario (paso 1 de 2)
    Requerimientos: RF-04, RNF-01, RSIS-05, RS-01, RS-05, RI-03, RLN-02, RNF-05

    Esta pantalla solo captura los datos y envia el codigo.
    La validacion del codigo vive en su propia pagina:
    user/pages/verificarCodigo.php (accion=verificar-codigo).
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

//si ya hay sesion no tiene caso registrarse otra vez
if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

//si ya se envio un codigo y todavia no se valida, no hay nada que hacer aqui
if (isset($_SESSION['registro_pendiente']))
{
    redirigir(URL_BASE . '?accion=verificar-codigo');
}

$errores = [];
$avisoGeneral = null;
$registroCompletado = false;

//RU-02: si algo falla, los datos correctos se conservan en el formulario
$valores = [
    'nombre'      => '',
    'correo'      => '',
    'telefono'    => '',
    'tipo_cuenta' => 'personal'
];

if (isset($_SESSION['registro_exito']))
{
    $registroCompletado = true;
    $valores['nombre'] = $_SESSION['registro_exito'];
    unset($_SESSION['registro_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'capturar_datos')
{
    $datos = [
        'nombre'               => limpiarEntrada($_POST['nombre'] ?? ''),
        'correo'               => strtolower(limpiarEntrada($_POST['correo'] ?? '')),
        'telefono'             => limpiarEntrada($_POST['telefono'] ?? ''),
        'contrasena'           => (string) ($_POST['contrasena'] ?? ''),
        'contrasena_confirmar' => (string) ($_POST['contrasena_confirmar'] ?? ''),
        'tipo_cuenta'          => ($_POST['tipo_cuenta'] ?? 'personal') === 'empresarial' ? 'empresarial' : 'personal',
        'terminos'             => isset($_POST['terminos'])
    ];

    $valores['nombre']      = $datos['nombre'];
    $valores['correo']      = $datos['correo'];
    $valores['telefono']    = $datos['telefono'];
    $valores['tipo_cuenta'] = $datos['tipo_cuenta'];

    //RS-05: toda la validacion se repite en el servidor
    $errores = validarDatosRegistro($datos);

    //validacion de duplicidad contra la base de datos
    if (!isset($errores['correo']) && correoYaRegistrado($datos['correo']))
    {
        $errores['correo'] = 'Ese correo ya tiene una cuenta en CHAMBEKI. Inicia sesion o recupera tu contrasena.';
    }

    //RNF-01 y RNF-05: foto de perfil de maximo 2MB
    $resultadoFoto = null;

    if (empty($errores))
    {
        $resultadoFoto = guardarFotoPerfil($_FILES['foto_perfil'] ?? null);

        if (!$resultadoFoto['exito'])
        {
            $errores['foto_perfil'] = $resultadoFoto['error'];
        }
    }

    if (empty($errores))
    {
        //RSIS-05 + RI-03: codigo temporal enviado por SMTP con PHPMailer
        $codigo = generarCodigoVerificacion($datos['correo'], 'registro');

        $resultadoCorreo = enviarCorreo('codigo', $datos['correo'], $datos['nombre'], [
            'codigo'   => $codigo,
            'motivo'   => 'registro',
            'vigencia' => MINUTOS_VIGENCIA_CODIGO
        ]);

        if (!$resultadoCorreo['exito'])
        {
            eliminarFotoPerfil($resultadoFoto['ruta']);
            $avisoGeneral = ['tipo' => 'error', 'texto' => 'No pudimos enviar el codigo a ese correo. Revisalo e intenta de nuevo.'];
        }
        else
        {
            /*
                El usuario todavia NO se guarda en la tabla: el diagrama
                indica que el registro se inserta hasta que el codigo es
                correcto. Mientras tanto queda en la sesion, ya con la
                contrasena cifrada (RS-01).
            */
            $_SESSION['registro_pendiente'] = [
                'nombre'          => $datos['nombre'],
                'correo'          => $datos['correo'],
                'telefono'        => preg_replace('/[^0-9]/', '', $datos['telefono']),
                'contrasena_hash' => password_hash($datos['contrasena'], PASSWORD_DEFAULT),
                'foto_perfil'     => $resultadoFoto['ruta'],
                'tipo_cuenta'     => $datos['tipo_cuenta']
            ];

            guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $datos['correo'] . '.');

            //aqui esta el reenvio a la pagina dedicada de verificacion
            redirigir(URL_BASE . '?accion=verificar-codigo');
        }
    }
}

abrirPantallaAutenticacion($registroCompletado ? null : 'registro');
?>

        <?php if ($registroCompletado): ?>

            <!-- Evento final del proceso: registro completado -->
            <div class="bloque-confirmacion">
                <div class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h1 class="titulo-autenticacion">Cuenta creada</h1>
                <p class="texto-autenticacion">
                    Ya verificamos tu correo, <?php echo escaparSalida($valores['nombre']); ?>.
                    Inicia sesion para empezar a contratar servicios cerca de ti.
                </p>
                <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-autenticacion">Iniciar sesion</a>
            </div>

        <?php else: ?>

            <h1 class="titulo-autenticacion">Crea tu cuenta</h1>
            <p class="texto-autenticacion">Solo necesitamos tus datos basicos y una foto donde se vea tu rostro.</p>

            <?php if ($avisoGeneral !== null): ?>
                <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($avisoGeneral['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=registro" method="POST" enctype="multipart/form-data" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="capturar_datos">

                <div class="campo-formulario">
                    <label for="campoNombre">Nombre completo</label>
                    <input type="text" id="campoNombre" name="nombre" class="icono-usuario" value="<?php echo escaparSalida($valores['nombre']); ?>" placeholder="Ana Garcia Lopez" required>
                    <?php if (isset($errores['nombre'])): ?><span class="error-campo"><?php echo escaparSalida($errores['nombre']); ?></span><?php endif; ?>
                </div>

                <div class="campo-formulario">
                    <label for="campoCorreo">Correo electronico</label>
                    <input type="email" id="campoCorreo" name="correo" class="icono-correo" value="<?php echo escaparSalida($valores['correo']); ?>" placeholder="tucorreo@ejemplo.com" required>
                    <small class="ayuda-campo">Aqui llegara tu codigo de verificacion.</small>
                    <?php if (isset($errores['correo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['correo']); ?></span><?php endif; ?>
                </div>

                <div class="campo-formulario">
                    <label for="campoTelefono">Telefono</label>
                    <input type="tel" id="campoTelefono" name="telefono" class="icono-telefono" value="<?php echo escaparSalida($valores['telefono']); ?>" placeholder="664 123 4567" required>
                    <?php if (isset($errores['telefono'])): ?><span class="error-campo"><?php echo escaparSalida($errores['telefono']); ?></span><?php endif; ?>
                </div>

                <div class="fila-campos">
                    <div class="campo-formulario">
                        <label for="campoContrasena">Contrasena</label>
                        <input type="password" id="campoContrasena" name="contrasena" class="icono-candado" placeholder="Minimo 8 caracteres" required>
                    </div>

                    <div class="campo-formulario">
                        <label for="campoContrasenaConfirmar">Confirma tu contrasena</label>
                        <input type="password" id="campoContrasenaConfirmar" name="contrasena_confirmar" class="icono-candado" placeholder="Repitela" required>
                    </div>
                </div>

                <?php if (isset($errores['contrasena'])): ?>
                    <span class="error-campo"><?php echo escaparSalida($errores['contrasena']); ?></span>
                <?php else: ?>
                    <small class="ayuda-campo">Combina letras y numeros, minimo 8 caracteres.</small>
                <?php endif; ?>

                <div class="campo-formulario">
                    <label for="campoTipoCuenta">Tipo de cuenta</label>
                    <select id="campoTipoCuenta" name="tipo_cuenta">
                        <option value="personal" <?php echo $valores['tipo_cuenta'] === 'personal' ? 'selected' : ''; ?>>Personal</option>
                        <option value="empresarial" <?php echo $valores['tipo_cuenta'] === 'empresarial' ? 'selected' : ''; ?>>Empresarial</option>
                    </select>
                    <small class="ayuda-campo">En cuentas personales la foto debe mostrar tu rostro; en empresariales puedes usar tu logotipo.</small>
                </div>

                <div class="campo-formulario">
                    <label for="campoFoto">Foto de perfil</label>
                    <input type="file" id="campoFoto" name="foto_perfil" accept="image/jpeg,image/png,image/webp" required>
                    <small class="ayuda-campo">JPG, PNG o WEBP de maximo 2MB.</small>
                    <?php if (isset($errores['foto_perfil'])): ?><span class="error-campo"><?php echo escaparSalida($errores['foto_perfil']); ?></span><?php endif; ?>
                </div>

                <div class="campo-terminos">
                    <input type="checkbox" id="campoTerminos" name="terminos" value="1">
                    <label for="campoTerminos">
                        Acepto los <a href="<?php echo URL_BASE; ?>?accion=terminos">terminos y condiciones</a>
                        y el <a href="<?php echo URL_BASE; ?>?accion=privacidad">aviso de privacidad</a>.
                    </label>
                </div>
                <?php if (isset($errores['terminos'])): ?><span class="error-campo"><?php echo escaparSalida($errores['terminos']); ?></span><?php endif; ?>

                <button type="submit" class="boton-autenticacion">Enviar codigo de verificacion</button>
            </form>

            <p class="pie-autenticacion">
                ¿Ya tienes cuenta? <a href="<?php echo URL_BASE; ?>?accion=login">Inicia sesion</a>
            </p>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>
<?php
/*
    PROCESO: Registro de usuario (paso 1 de 2)
    Requerimientos: RF-04, RNF-01, RSIS-05, RS-01, RS-05, RI-03, RLN-02, RNF-05

    Esta pantalla solo captura los datos y envia el codigo.
    La validacion del codigo vive en su propia pagina:
    user/pages/verificarCodigo.php (accion=verificar-codigo).
*/

require_once MAILER;
require_once DOCROOT . 'user/includes/plantillaAutenticacion.php';

//si ya hay sesion no tiene caso registrarse otra vez
if (hayUsuarioEnSesion())
{
    redirigir(URL_BASE);
}

//si ya se envio un codigo y todavia no se valida, no hay nada que hacer aqui
if (isset($_SESSION['registro_pendiente']))
{
    redirigir(URL_BASE . '?accion=verificar-codigo');
}

$errores = [];
$avisoGeneral = null;
$registroCompletado = false;

//RU-02: si algo falla, los datos correctos se conservan en el formulario
$valores = [
    'nombre'      => '',
    'correo'      => '',
    'telefono'    => '',
    'tipo_cuenta' => 'personal'
];

if (isset($_SESSION['registro_exito']))
{
    $registroCompletado = true;
    $valores['nombre'] = $_SESSION['registro_exito'];
    unset($_SESSION['registro_exito']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'capturar_datos')
{
    $datos = [
        'nombre'               => limpiarEntrada($_POST['nombre'] ?? ''),
        'correo'               => strtolower(limpiarEntrada($_POST['correo'] ?? '')),
        'telefono'             => limpiarEntrada($_POST['telefono'] ?? ''),
        'contrasena'           => (string) ($_POST['contrasena'] ?? ''),
        'contrasena_confirmar' => (string) ($_POST['contrasena_confirmar'] ?? ''),
        'tipo_cuenta'          => ($_POST['tipo_cuenta'] ?? 'personal') === 'empresarial' ? 'empresarial' : 'personal',
        'terminos'             => isset($_POST['terminos'])
    ];

    $valores['nombre']      = $datos['nombre'];
    $valores['correo']      = $datos['correo'];
    $valores['telefono']    = $datos['telefono'];
    $valores['tipo_cuenta'] = $datos['tipo_cuenta'];

    //RS-05: toda la validacion se repite en el servidor
    $errores = validarDatosRegistro($datos);

    //validacion de duplicidad contra la base de datos
    if (!isset($errores['correo']) && correoYaRegistrado($datos['correo']))
    {
        $errores['correo'] = 'Ese correo ya tiene una cuenta en CHAMBEKI. Inicia sesion o recupera tu contrasena.';
    }

    //RNF-01 y RNF-05: foto de perfil de maximo 2MB
    $resultadoFoto = null;

    if (empty($errores))
    {
        $resultadoFoto = guardarFotoPerfil($_FILES['foto_perfil'] ?? null);

        if (!$resultadoFoto['exito'])
        {
            $errores['foto_perfil'] = $resultadoFoto['error'];
        }
    }

    if (empty($errores))
    {
        //RSIS-05 + RI-03: codigo temporal enviado por SMTP con PHPMailer
        $codigo = generarCodigoVerificacion($datos['correo'], 'registro');

        $resultadoCorreo = enviarCorreo('codigo', $datos['correo'], $datos['nombre'], [
            'codigo'   => $codigo,
            'motivo'   => 'registro',
            'vigencia' => MINUTOS_VIGENCIA_CODIGO
        ]);

        if (!$resultadoCorreo['exito'])
        {
            eliminarFotoPerfil($resultadoFoto['ruta']);
            $avisoGeneral = ['tipo' => 'error', 'texto' => 'No pudimos enviar el codigo a ese correo. Revisalo e intenta de nuevo.'];
        }
        else
        {
            /*
                El usuario todavia NO se guarda en la tabla: el diagrama
                indica que el registro se inserta hasta que el codigo es
                correcto. Mientras tanto queda en la sesion, ya con la
                contrasena cifrada (RS-01).
            */
            $_SESSION['registro_pendiente'] = [
                'nombre'          => $datos['nombre'],
                'correo'          => $datos['correo'],
                'telefono'        => preg_replace('/[^0-9]/', '', $datos['telefono']),
                'contrasena_hash' => password_hash($datos['contrasena'], PASSWORD_DEFAULT),
                'foto_perfil'     => $resultadoFoto['ruta'],
                'tipo_cuenta'     => $datos['tipo_cuenta']
            ];

            guardarMensaje('exito', 'Te enviamos un codigo de 6 digitos a ' . $datos['correo'] . '.');

            //aqui esta el reenvio a la pagina dedicada de verificacion
            redirigir(URL_BASE . '?accion=verificar-codigo');
        }
    }
}

abrirPantallaAutenticacion($registroCompletado ? null : 'registro');
?>

        <?php if ($registroCompletado): ?>

            <!-- Evento final del proceso: registro completado -->
            <div class="bloque-confirmacion">
                <div class="icono-confirmacion" aria-hidden="true">
                    <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <h1 class="titulo-autenticacion">Cuenta creada</h1>
                <p class="texto-autenticacion">
                    Ya verificamos tu correo, <?php echo escaparSalida($valores['nombre']); ?>.
                    Inicia sesion para empezar a contratar servicios cerca de ti.
                </p>
                <a href="<?php echo URL_BASE; ?>?accion=login" class="boton-autenticacion">Iniciar sesion</a>
            </div>

        <?php else: ?>

            <h1 class="titulo-autenticacion">Crea tu cuenta</h1>
            <p class="texto-autenticacion">Solo necesitamos tus datos basicos y una foto donde se vea tu rostro.</p>

            <?php if ($avisoGeneral !== null): ?>
                <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($avisoGeneral['texto']); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=registro" method="POST" enctype="multipart/form-data" class="formulario-autenticacion" novalidate>
                <input type="hidden" name="operacion" value="capturar_datos">

                <div class="campo-formulario">
                    <label for="campoNombre">Nombre completo</label>
                    <input type="text" id="campoNombre" name="nombre" class="icono-usuario" value="<?php echo escaparSalida($valores['nombre']); ?>" placeholder="Ana Garcia Lopez" required>
                    <?php if (isset($errores['nombre'])): ?><span class="error-campo"><?php echo escaparSalida($errores['nombre']); ?></span><?php endif; ?>
                </div>

                <div class="campo-formulario">
                    <label for="campoCorreo">Correo electronico</label>
                    <input type="email" id="campoCorreo" name="correo" class="icono-correo" value="<?php echo escaparSalida($valores['correo']); ?>" placeholder="tucorreo@ejemplo.com" required>
                    <small class="ayuda-campo">Aqui llegara tu codigo de verificacion.</small>
                    <?php if (isset($errores['correo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['correo']); ?></span><?php endif; ?>
                </div>

                <div class="campo-formulario">
                    <label for="campoTelefono">Telefono</label>
                    <input type="tel" id="campoTelefono" name="telefono" class="icono-telefono" value="<?php echo escaparSalida($valores['telefono']); ?>" placeholder="664 123 4567" required>
                    <?php if (isset($errores['telefono'])): ?><span class="error-campo"><?php echo escaparSalida($errores['telefono']); ?></span><?php endif; ?>
                </div>

                <div class="fila-campos">
                    <div class="campo-formulario">
                        <label for="campoContrasena">Contrasena</label>
                        <input type="password" id="campoContrasena" name="contrasena" class="icono-candado" placeholder="Minimo 8 caracteres" required>
                    </div>

                    <div class="campo-formulario">
                        <label for="campoContrasenaConfirmar">Confirma tu contrasena</label>
                        <input type="password" id="campoContrasenaConfirmar" name="contrasena_confirmar" class="icono-candado" placeholder="Repitela" required>
                    </div>
                </div>

                <?php if (isset($errores['contrasena'])): ?>
                    <span class="error-campo"><?php echo escaparSalida($errores['contrasena']); ?></span>
                <?php else: ?>
                    <small class="ayuda-campo">Combina letras y numeros, minimo 8 caracteres.</small>
                <?php endif; ?>

                <div class="campo-formulario">
                    <label for="campoTipoCuenta">Tipo de cuenta</label>
                    <select id="campoTipoCuenta" name="tipo_cuenta">
                        <option value="personal" <?php echo $valores['tipo_cuenta'] === 'personal' ? 'selected' : ''; ?>>Personal</option>
                        <option value="empresarial" <?php echo $valores['tipo_cuenta'] === 'empresarial' ? 'selected' : ''; ?>>Empresarial</option>
                    </select>
                    <small class="ayuda-campo">En cuentas personales la foto debe mostrar tu rostro; en empresariales puedes usar tu logotipo.</small>
                </div>

                <div class="campo-formulario">
                    <label for="campoFoto">Foto de perfil</label>
                    <input type="file" id="campoFoto" name="foto_perfil" accept="image/jpeg,image/png,image/webp" required>
                    <small class="ayuda-campo">JPG, PNG o WEBP de maximo 2MB.</small>
                    <?php if (isset($errores['foto_perfil'])): ?><span class="error-campo"><?php echo escaparSalida($errores['foto_perfil']); ?></span><?php endif; ?>
                </div>

                <div class="campo-terminos">
                    <input type="checkbox" id="campoTerminos" name="terminos" value="1">
                    <label for="campoTerminos">
                        Acepto los <a href="<?php echo URL_BASE; ?>?accion=terminos">terminos y condiciones</a>
                        y el <a href="<?php echo URL_BASE; ?>?accion=privacidad">aviso de privacidad</a>.
                    </label>
                </div>
                <?php if (isset($errores['terminos'])): ?><span class="error-campo"><?php echo escaparSalida($errores['terminos']); ?></span><?php endif; ?>

                <button type="submit" class="boton-autenticacion">Enviar codigo de verificacion</button>
            </form>

            <p class="pie-autenticacion">
                ¿Ya tienes cuenta? <a href="<?php echo URL_BASE; ?>?accion=login">Inicia sesion</a>
            </p>

        <?php endif; ?>

<?php cerrarPantallaAutenticacion(); ?>

<?php
/*
    PROCESO: Ofrece tu servicio (wizard de 4 pasos, Cliente -> Freelancer)
    Ruta: ?accion=registro_socio

    Paso 1: datos del negocio     Paso 2: categorias de servicio
    Paso 3: modalidad de trabajo  Paso 4: telefono de contacto

    Los pasos se muestran de uno en uno con registroSocio.js; el
    formulario se envia una sola vez al final y el servidor valida todo.
    Al guardar: rol -> Freelancer, se crea el perfil y su slug publico.
*/

require_once DOCROOT . 'user/includes/funcionesSocio.php';

if (!hayUsuarioEnSesion())
{
    guardarMensaje('error', 'Inicia sesion para ofrecer tus servicios.');
    redirigir(URL_BASE . '?accion=login');
}

$idUsuario  = (int) $_SESSION['id_usuario'];
$completado = false;
$resultado  = null;

//pantalla final (se llega por redireccion despues de guardar)
if (isset($_SESSION['socio_exito']))
{
    $completado = true;
    $resultado  = $_SESSION['socio_exito'];
    unset($_SESSION['socio_exito']);
}
else
{
    $usuario = obtenerUsuarioPorId($idUsuario);

    if ($usuario === null)
    {
        redirigir(URL_BASE . '?accion=login');
    }

    if ((int) $usuario['rol'] === ROL_FREELANCER)
    {
        guardarMensaje('exito', 'Tu cuenta ya es de freelancer. Aqui puedes editar tu perfil.');
        redirigir(URL_BASE . '?accion=configuracion');
    }

    if ((int) $usuario['rol'] !== ROL_CLIENTE)
    {
        guardarMensaje('error', 'Esta cuenta no puede registrarse como freelancer.');
        redirigir(URL_BASE);
    }
}

$errores        = [];
$avisoGeneral   = null;
$pasoInicial    = 1;
$modalidades    = [];
$categorias     = [];
$valores        = [];

if (!$completado)
{
    $modalidades = obtenerModalidades();
    $categorias  = obtenerCategorias();

    $valores = [
        'nombre_negocio'  => '',
        'es_empresa'      => (int) $usuario['tipo_cuenta'] === 2,
        'descripcion'     => '',
        'categorias'      => [],
        'id_modalidad'    => 0,
        'ciudad_limite'   => '',
        'direccion_texto' => '',
        'telefono'        => (string) ($usuario['telefono'] ?? '')
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['operacion'] ?? '') === 'registrar_socio')
    {
        $categoriasEnviadas = isset($_POST['categorias']) && is_array($_POST['categorias']) ? $_POST['categorias'] : [];

        $valores = [
            'nombre_negocio'  => limpiarEntrada($_POST['nombre_negocio'] ?? ''),
            'es_empresa'      => ($_POST['tipo_negocio'] ?? 'independiente') === 'empresa',
            'descripcion'     => limpiarEntrada($_POST['descripcion'] ?? ''),
            'categorias'      => array_values(array_unique(array_map('intval', $categoriasEnviadas))),
            'id_modalidad'    => (int) ($_POST['id_modalidad'] ?? 0),
            'ciudad_limite'   => limpiarEntrada($_POST['ciudad_limite'] ?? ''),
            'direccion_texto' => limpiarEntrada($_POST['direccion_texto'] ?? ''),
            'telefono'        => limpiarEntrada($_POST['telefono'] ?? '')
        ];

        if (!validarCsrf($_POST['token_csrf'] ?? ''))
        {
            $avisoGeneral = 'La sesion del formulario expiro. Revisa tus datos y vuelve a enviarlos.';
        }
        else
        {
            $errores = validarDatosSocio($valores, $modalidades, $categorias);

            if (empty($errores))
            {
                $alta = registrarSocio($idUsuario, $valores);

                if ($alta['exito'])
                {
                    $_SESSION['rol_usuario'] = ROL_FREELANCER;
                    $_SESSION['socio_exito'] = ['slug' => $alta['slug'], 'nombre' => $valores['nombre_negocio']];

                    registrarEnBitacora($_SESSION['correo_usuario'] ?? '', 'cambio_rol_freelancer');

                    redirigir(URL_BASE . '?accion=registro_socio');
                }

                $avisoGeneral = $alta['error'];
            }
            else
            {
                //el wizard se reabre en el primer paso que tenga un error
                $pasoInicial = min(array_map('pasoDelError', array_keys($errores)));
            }
        }
    }
}

$mensajeFlash = obtenerMensaje();

$nombresPasos = ['Tu negocio', 'Servicios', 'Modalidad', 'Contacto'];
?>

<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>autenticacion.css?v=1">
<link rel="stylesheet" href="<?php echo CSS_RUTA; ?>registroSocio.css?v=1">

<section class="seccion-wizard">
    <div class="contenedor-wizard">

    <?php if ($completado): ?>

        <div class="tarjeta-wizard bloque-confirmacion">
            <div class="icono-confirmacion" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <h1 class="titulo-wizard">Ya eres freelancer en CHAMBEKI</h1>
            <p class="texto-wizard"><?php echo escaparSalida($resultado['nombre']); ?> ya tiene perfil profesional. Este sera tu enlace publico:</p>

            <p class="enlace-publico-socio"><?php echo escaparSalida(URL_BASE . '?accion=profesional&slug=' . $resultado['slug']); ?></p>

            <div class="acciones-wizard acciones-wizard-final">
                <a href="<?php echo URL_BASE; ?>?accion=configuracion" class="boton-autenticacion">Completar mi perfil</a>
                <a href="<?php echo URL_BASE; ?>" class="boton-wizard-secundario">Ir al inicio</a>
            </div>
        </div>

    <?php else: ?>

        <header class="cabecera-wizard">
            <h1 class="titulo-wizard">Ofrece tu servicio</h1>
            <p class="texto-wizard">En cuatro pasos tu cuenta pasa a freelancer y los clientes pueden encontrarte.</p>
        </header>

        <ol class="progreso-wizard" id="progresoWizard" aria-label="Progreso">
            <?php foreach ($nombresPasos as $indice => $nombrePaso): ?>
                <li class="paso-progreso <?php echo ($indice + 1) === $pasoInicial ? 'actual' : ''; ?>" data-paso-progreso="<?php echo $indice + 1; ?>">
                    <span class="marca-paso"><?php echo $indice + 1; ?></span>
                    <span class="nombre-paso"><?php echo escaparSalida($nombrePaso); ?></span>
                </li>
            <?php endforeach; ?>
        </ol>

        <div class="tarjeta-wizard">

            <?php if ($mensajeFlash !== null): ?>
                <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
            <?php endif; ?>

            <?php if ($avisoGeneral !== null): ?>
                <p class="aviso-autenticacion aviso-error"><?php echo escaparSalida($avisoGeneral); ?></p>
            <?php endif; ?>

            <form action="<?php echo URL_BASE; ?>?accion=registro_socio" method="POST" class="formulario-wizard" id="formularioSocio" data-paso-inicial="<?php echo (int) $pasoInicial; ?>" data-max-categorias="<?php echo MAXIMO_CATEGORIAS_SOCIO; ?>" novalidate>
                <input type="hidden" name="operacion" value="registrar_socio">
                <input type="hidden" name="token_csrf" value="<?php echo escaparSalida(tokenCsrf()); ?>">

                <!-- PASO 1: negocio -->
                <fieldset class="paso-wizard" data-paso="1">
                    <legend>Datos de tu negocio</legend>

                    <div class="campo-formulario">
                        <label for="campoNombreNegocio">Nombre del negocio o nombre profesional</label>
                        <input type="text" id="campoNombreNegocio" name="nombre_negocio" maxlength="150" value="<?php echo escaparSalida($valores['nombre_negocio']); ?>" placeholder="Plomeria Lopez" required>
                        <span class="error-campo" data-error-para="nombre_negocio"><?php echo isset($errores['nombre_negocio']) ? escaparSalida($errores['nombre_negocio']) : ''; ?></span>
                    </div>

                    <div class="campo-formulario">
                        <span class="etiqueta-grupo">Como trabajas</span>
                        <div class="opciones-en-fila">
                            <label class="opcion-pastilla">
                                <input type="radio" name="tipo_negocio" value="independiente" <?php echo !$valores['es_empresa'] ? 'checked' : ''; ?>>
                                <span>Por mi cuenta</span>
                            </label>
                            <label class="opcion-pastilla">
                                <input type="radio" name="tipo_negocio" value="empresa" <?php echo $valores['es_empresa'] ? 'checked' : ''; ?>>
                                <span>Soy una empresa</span>
                            </label>
                        </div>
                    </div>

                    <div class="campo-formulario">
                        <label for="campoDescripcion">Describe tu servicio</label>
                        <textarea id="campoDescripcion" name="descripcion" rows="5" maxlength="600" placeholder="Que haces, cuantos anos de experiencia tienes y que te hace confiable." required><?php echo escaparSalida($valores['descripcion']); ?></textarea>
                        <small class="ayuda-campo"><span id="contadorDescripcion">0</span> / 600 caracteres (minimo 20).</small>
                        <span class="error-campo" data-error-para="descripcion"><?php echo isset($errores['descripcion']) ? escaparSalida($errores['descripcion']) : ''; ?></span>
                    </div>
                </fieldset>

                <!-- PASO 2: categorias -->
                <fieldset class="paso-wizard" data-paso="2">
                    <legend>Que servicios ofreces</legend>
                    <p class="texto-wizard">Elige de 1 a <?php echo MAXIMO_CATEGORIAS_SOCIO; ?> categorias. Los clientes te encontraran por ellas.</p>

                    <div class="rejilla-categorias">
                        <?php foreach ($categorias as $categoria): ?>
                            <label class="opcion-pastilla">
                                <input type="checkbox" name="categorias[]" value="<?php echo (int) $categoria['id_categoria']; ?>" <?php echo in_array((int) $categoria['id_categoria'], $valores['categorias'], true) ? 'checked' : ''; ?>>
                                <span><?php echo escaparSalida($categoria['nombre_categoria']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <span class="error-campo" data-error-para="categorias"><?php echo isset($errores['categorias']) ? escaparSalida($errores['categorias']) : ''; ?></span>
                </fieldset>

                <!-- PASO 3: modalidad -->
                <fieldset class="paso-wizard" data-paso="3">
                    <legend>Como ofreces tu servicio</legend>

                    <div class="rejilla-modalidades">
                        <?php foreach ($modalidades as $modalidad): ?>
                            <label class="tarjeta-modalidad">
                                <input type="radio" name="id_modalidad" value="<?php echo (int) $modalidad['id_modalidad']; ?>"
                                    data-ciudad="<?php echo modalidadRequiereCiudad($modalidad['id_modalidad']) ? '1' : '0'; ?>"
                                    data-direccion="<?php echo modalidadRequiereDireccion($modalidad['id_modalidad']) ? '1' : '0'; ?>"
                                    <?php echo (int) $valores['id_modalidad'] === (int) $modalidad['id_modalidad'] ? 'checked' : ''; ?>>
                                <span><?php echo escaparSalida($modalidad['nombre']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <span class="error-campo" data-error-para="id_modalidad"><?php echo isset($errores['id_modalidad']) ? escaparSalida($errores['id_modalidad']) : ''; ?></span>

                    <div class="campo-formulario" id="grupoCiudad">
                        <label for="campoCiudad">Ciudad donde trabajas</label>
                        <input type="text" id="campoCiudad" name="ciudad_limite" maxlength="100" value="<?php echo escaparSalida($valores['ciudad_limite']); ?>" placeholder="Tijuana">
                        <span class="error-campo" data-error-para="ciudad_limite"><?php echo isset($errores['ciudad_limite']) ? escaparSalida($errores['ciudad_limite']) : ''; ?></span>
                    </div>

                    <div class="campo-formulario" id="grupoDireccion">
                        <label for="campoDireccion">Direccion de tu local</label>
                        <input type="text" id="campoDireccion" name="direccion_texto" maxlength="255" value="<?php echo escaparSalida($valores['direccion_texto']); ?>" placeholder="Calle, numero y colonia">
                        <span class="error-campo" data-error-para="direccion_texto"><?php echo isset($errores['direccion_texto']) ? escaparSalida($errores['direccion_texto']) : ''; ?></span>
                    </div>
                </fieldset>

                <!-- PASO 4: contacto -->
                <fieldset class="paso-wizard" data-paso="4">
                    <legend>Telefono de contacto</legend>

                    <div class="campo-formulario">
                        <label for="campoTelefonoSocio">Telefono o WhatsApp de 10 digitos</label>
                        <input type="tel" id="campoTelefonoSocio" name="telefono" class="icono-telefono" value="<?php echo escaparSalida($valores['telefono']); ?>" placeholder="664 123 4567" required>
                        <small class="ayuda-campo">Es el numero con el que te contactaran los clientes.</small>
                        <span class="error-campo" data-error-para="telefono"><?php echo isset($errores['telefono']) ? escaparSalida($errores['telefono']) : ''; ?></span>
                    </div>

                    <p class="texto-wizard">Al terminar, tu cuenta cambia a freelancer y se crea el enlace publico de tu perfil.</p>
                </fieldset>

                <div class="acciones-wizard">
                    <button type="button" class="boton-wizard-secundario" id="botonAtras" hidden>Atras</button>
                    <button type="button" class="boton-autenticacion" id="botonSiguiente">Siguiente</button>
                    <button type="submit" class="boton-autenticacion" id="botonEnviar" hidden>Crear mi perfil de freelancer</button>
                </div>
            </form>
        </div>

    <?php endif; ?>

    </div>
</section>

<?php if (!$completado): ?>
    <script src="<?php echo JS_RUTA; ?>registroSocio.js?v=1"></script>
<?php endif; ?>
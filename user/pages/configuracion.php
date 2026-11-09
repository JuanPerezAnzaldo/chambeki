<?php
/*
    PROCESO: Configuracion de perfil (usuario / freelancer)
    Requerimientos: RSIS-01, RS-01, RS-02, RS-05, RNF-01, RNF-05, RUS-02,
                    RU-01, RU-02, RU-03, RU-04, RF-06, RLN-03, RLN-07

    Secciones solo para freelancers (identidad comercial, banner, redes
    sociales, perfil profesional, cobertura con mapa y horarios): ni el
    HTML ni las operaciones existen para un usuario que no lo sea.

    Todo se hace con el id_usuario de la sesion, nunca con uno que
    venga de la URL o del formulario. Cada seccion es un formulario
    independiente identificado por el campo oculto "operacion".
    Patron Post/Redirect/Get: si todo sale bien se guarda un mensaje
    flash y se redirige, asi un F5 no reenvia el formulario.
*/

require_once DOCROOT . 'user/includes/funcionesPerfil.php';
require_once DOCROOT . 'user/includes/funcionesIdentidad.php';

if (!hayUsuarioEnSesion())
{
    guardarMensaje('error', 'Inicia sesion para configurar tu perfil.');
    redirigir(URL_BASE . '?accion=login');
}

$idUsuario = (int) $_SESSION['id_usuario'];
$usuario = obtenerUsuarioPorId($idUsuario);

if ($usuario === null)
{
    session_unset();
    session_destroy();
    session_start();

    guardarMensaje('error', 'No encontramos tu cuenta. Inicia sesion de nuevo.');
    redirigir(URL_BASE . '?accion=login');
}

$esFreelancer = (int) $usuario['rol'] === ROL_FREELANCER;
$urlConfig = URL_BASE . '?accion=configuracion';

$perfilFreelancer = $esFreelancer ? obtenerPerfilFreelancer($idUsuario) : null;
$cobertura = $esFreelancer ? obtenerCoberturaFreelancer($idUsuario) : null;
$modalidades = $esFreelancer ? obtenerModalidades() : [];
$identidad = $esFreelancer ? obtenerIdentidadComercial($idUsuario) : null;
$bannerActual = $esFreelancer ? obtenerBannerFreelancer($idUsuario) : null;
$redesActuales = $esFreelancer ? obtenerRedesFreelancer($idUsuario) : [];
$horarioActual = $esFreelancer ? obtenerHorarioFreelancer($idUsuario) : [];
$redesDisponibles = redesSocialesDisponibles();
$diasSemana = nombresDiasSemana();

//errores por seccion y valores conservados por si algo falla (RU-02)
$errores = [
    'datos' => [], 'foto' => [], 'contrasena' => [], 'freelancer' => [], 'cobertura' => [],
    'identidad' => [], 'banner' => [], 'redes' => [], 'horarios' => []
];

$valoresDatos = [
    'nombre'      => $usuario['nombre'],
    'telefono'    => $usuario['telefono'] ?? '',
    'tipo_cuenta' => (int) $usuario['tipo_cuenta'] === 2 ? 'empresarial' : 'personal'
];

$valoresFreelancer = $perfilFreelancer;
$valoresCobertura = $cobertura;
$valoresIdentidad = $identidad;
$valoresRedes = $redesActuales;
$valoresHorario = $horarioActual;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = $_POST['operacion'] ?? '';

    if (!validarCsrf($_POST['token_csrf'] ?? ''))
    {
        guardarMensaje('error', 'La sesion del formulario expiro. Intenta de nuevo.');
        redirigir($urlConfig);
    }

    try
    {
        switch ($operacion)
        {
            //-----------------------------------------------------
            // Datos generales
            //-----------------------------------------------------
            case 'actualizar_datos':
            {
                $datos = [
                    'nombre'      => limpiarEntrada($_POST['nombre'] ?? ''),
                    'telefono'    => limpiarEntrada($_POST['telefono'] ?? ''),
                    'tipo_cuenta' => ($_POST['tipo_cuenta'] ?? 'personal') === 'empresarial' ? 'empresarial' : 'personal'
                ];

                $valoresDatos = $datos;
                $errores['datos'] = validarDatosGenerales($datos);

                if (empty($errores['datos']))
                {
                    actualizarDatosGenerales($idUsuario, $datos);
                    registrarEnBitacora($usuario['correo'], 'perfil_actualizado');
                    $_SESSION['nombre_usuario'] = $datos['nombre'];

                    guardarMensaje('exito', 'Tus datos se actualizaron correctamente.');
                    redirigir($urlConfig . '#datos');
                }

                break;
            }

            //-----------------------------------------------------
            // Foto de perfil (maximo 2MB, se valida el tipo real)
            //-----------------------------------------------------
            case 'cambiar_foto':
            {
                $resultadoFoto = guardarFotoPerfil($_FILES['foto_perfil'] ?? null);

                if (!$resultadoFoto['exito'])
                {
                    $errores['foto']['foto_perfil'] = $resultadoFoto['error'];
                    break;
                }

                $fotoAnterior = $usuario['foto_perfil_url'];

                actualizarFotoUsuario($idUsuario, $resultadoFoto['ruta']);
                eliminarFotoPerfil($fotoAnterior);
                registrarEnBitacora($usuario['correo'], 'foto_actualizada');

                $_SESSION['foto_usuario'] = $resultadoFoto['ruta'];

                guardarMensaje('exito', 'Tu foto de perfil se actualizo.');
                redirigir($urlConfig . '#foto');
            }

            //-----------------------------------------------------
            // Cambio de contrasena (pide la actual)
            //-----------------------------------------------------
            case 'cambiar_contrasena':
            {
                $actual    = (string) ($_POST['contrasena_actual'] ?? '');
                $nueva     = (string) ($_POST['contrasena_nueva'] ?? '');
                $confirmar = (string) ($_POST['contrasena_confirmar'] ?? '');

                $hashActual = obtenerHashContrasena($idUsuario);

                if ($hashActual === null || !password_verify($actual, $hashActual))
                {
                    $errores['contrasena']['actual'] = 'La contrasena actual no es correcta.';
                    registrarEnBitacora($usuario['correo'], 'cambio_contrasena_fallido');
                    break;
                }

                $errorNueva = validarContrasena($nueva, $confirmar);

                if ($errorNueva !== null)
                {
                    $errores['contrasena']['nueva'] = $errorNueva;
                    break;
                }

                if (password_verify($nueva, $hashActual))
                {
                    $errores['contrasena']['nueva'] = 'La contrasena nueva debe ser distinta a la actual.';
                    break;
                }

                actualizarContrasena($usuario['correo'], $nueva);
                registrarEnBitacora($usuario['correo'], 'cambio_contrasena');

                //se renueva el id de sesion tras un cambio de credenciales
                session_regenerate_id(true);

                guardarMensaje('exito', 'Tu contrasena se cambio correctamente.');
                redirigir($urlConfig . '#seguridad');
            }

            //-----------------------------------------------------
            // Perfil profesional (solo freelancers)
            //-----------------------------------------------------
            case 'actualizar_freelancer':
            {
                if (!$esFreelancer)
                {
                    guardarMensaje('error', 'Esta seccion es solo para freelancers.');
                    redirigir($urlConfig);
                }

                $datos = [
                    'identificador_fiscal' => mb_strtoupper(preg_replace('/\s+/', '', limpiarEntrada($_POST['identificador_fiscal'] ?? ''))),
                    'acepto_efectivo'      => isset($_POST['acepto_efectivo']),
                    'acepto_tarjeta'       => isset($_POST['acepto_tarjeta'])
                ];

                $valoresFreelancer = array_merge($perfilFreelancer, $datos);
                $errores['freelancer'] = validarPerfilFreelancer($datos);

                if (empty($errores['freelancer']))
                {
                    guardarPerfilFreelancer($idUsuario, $datos);
                    registrarEnBitacora($usuario['correo'], 'perfil_freelancer_actualizado');

                    guardarMensaje('exito', 'Tu perfil profesional se actualizo.');
                    redirigir($urlConfig . '#profesional');
                }

                break;
            }

            //-----------------------------------------------------
            // Cobertura de trabajo (solo freelancers, RUS-02)
            //-----------------------------------------------------
            case 'actualizar_cobertura':
            {
                if (!$esFreelancer)
                {
                    guardarMensaje('error', 'Esta seccion es solo para freelancers.');
                    redirigir($urlConfig);
                }

                $datos = [
                    'id_modalidad'       => (int) ($_POST['id_modalidad'] ?? 0),
                    'ciudad_limite'      => limpiarEntrada($_POST['ciudad_limite'] ?? ''),
                    'direccion_texto'    => limpiarEntrada($_POST['direccion_texto'] ?? ''),
                    'radio_cobertura_km' => (int) ($_POST['radio_cobertura_km'] ?? 0),
                    'latitud_base'       => leerCoordenada($_POST['latitud_base'] ?? ''),
                    'longitud_base'      => leerCoordenada($_POST['longitud_base'] ?? '')
                ];

                $valoresCobertura = $datos;
                $errores['cobertura'] = array_merge(
                    validarCobertura($datos, $modalidades),
                    validarCoordenadasCobertura($datos)
                );

                if (empty($errores['cobertura']))
                {
                    guardarCobertura($idUsuario, $datos);
                    registrarEnBitacora($usuario['correo'], 'cobertura_actualizada');

                    guardarMensaje('exito', 'Tu zona de cobertura se guardo.');
                    redirigir($urlConfig . '#cobertura');
                }

                break;
            }

            //-----------------------------------------------------
            // Identidad comercial: nombre y biografia (solo freelancers)
            //-----------------------------------------------------
            case 'guardar_identidad':
            {
                if (!$esFreelancer)
                {
                    guardarMensaje('error', 'Esta seccion es solo para freelancers.');
                    redirigir($urlConfig);
                }

                $datos = [
                    'nombre_negocio' => limpiarEntrada($_POST['nombre_negocio'] ?? ''),
                    'descripcion'    => limpiarEntrada($_POST['descripcion'] ?? '')
                ];

                $valoresIdentidad = array_merge($identidad, $datos);
                $errores['identidad'] = validarIdentidadComercial($datos);

                if (empty($errores['identidad']))
                {
                    guardarIdentidadComercial($idUsuario, $datos);
                    registrarEnBitacora($usuario['correo'], 'identidad_comercial_actualizada');

                    guardarMensaje('exito', 'Tu identidad comercial se actualizo.');
                    redirigir($urlConfig . '#identidad');
                }

                break;
            }

            //-----------------------------------------------------
            // Banner de portada (solo freelancers, maximo 3MB)
            //-----------------------------------------------------
            case 'cambiar_banner':
            {
                if (!$esFreelancer)
                {
                    guardarMensaje('error', 'Esta seccion es solo para freelancers.');
                    redirigir($urlConfig);
                }

                $resultadoBanner = guardarImagenBanner($_FILES['banner_portada'] ?? null);

                if (!$resultadoBanner['exito'])
                {
                    $errores['banner']['banner_portada'] = $resultadoBanner['error'];
                    break;
                }

                actualizarBannerFreelancer($idUsuario, $resultadoBanner['ruta']);
                eliminarArchivoBanner($bannerActual);
                registrarEnBitacora($usuario['correo'], 'banner_actualizado');

                guardarMensaje('exito', 'Tu banner de portada se actualizo.');
                redirigir($urlConfig . '#banner');
            }

            case 'quitar_banner':
            {
                if (!$esFreelancer)
                {
                    guardarMensaje('error', 'Esta seccion es solo para freelancers.');
                    redirigir($urlConfig);
                }

                actualizarBannerFreelancer($idUsuario, null);
                eliminarArchivoBanner($bannerActual);
                registrarEnBitacora($usuario['correo'], 'banner_eliminado');

                guardarMensaje('exito', 'Quitaste tu banner de portada.');
                redirigir($urlConfig . '#banner');
            }

            //-----------------------------------------------------
            // Redes sociales (solo freelancers)
            //-----------------------------------------------------
            case 'guardar_redes':
            {
                if (!$esFreelancer)
                {
                    guardarMensaje('error', 'Esta seccion es solo para freelancers.');
                    redirigir($urlConfig);
                }

                $entradaRedes = isset($_POST['redes']) && is_array($_POST['redes']) ? $_POST['redes'] : [];
                $resultadoRedes = validarRedesSociales($entradaRedes);

                //se conserva lo escrito aunque haya errores (RU-02)
                $valoresRedes = [];

                foreach ($redesDisponibles as $clave => $datosRed)
                {
                    $valoresRedes[$clave] = isset($entradaRedes[$clave]) && is_string($entradaRedes[$clave]) ? limpiarEntrada($entradaRedes[$clave]) : '';
                }

                $errores['redes'] = $resultadoRedes['errores'];

                if (empty($errores['redes']))
                {
                    guardarRedesFreelancer($idUsuario, $resultadoRedes['redes']);
                    registrarEnBitacora($usuario['correo'], 'redes_actualizadas');

                    guardarMensaje('exito', 'Tus redes sociales se guardaron.');
                    redirigir($urlConfig . '#redes');
                }

                break;
            }

            //-----------------------------------------------------
            // Horarios semanales (solo freelancers)
            //-----------------------------------------------------
            case 'guardar_horarios':
            {
                if (!$esFreelancer)
                {
                    guardarMensaje('error', 'Esta seccion es solo para freelancers.');
                    redirigir($urlConfig);
                }

                $resultadoHorario = validarHorarios($_POST['horario'] ?? []);

                $valoresHorario = $resultadoHorario['horario'];
                $errores['horarios'] = $resultadoHorario['errores'];

                if (empty($errores['horarios']))
                {
                    guardarHorariosFreelancer($idUsuario, $resultadoHorario['horario']);
                    registrarEnBitacora($usuario['correo'], 'horarios_actualizados');

                    guardarMensaje('exito', 'Tus horarios se guardaron.');
                    redirigir($urlConfig . '#horarios');
                }

                break;
            }
        }
    }
    catch (PDOException $excepcion)
    {
        //nunca se le muestra el detalle del error al usuario final
        error_log('Error en configuracion de perfil: ' . $excepcion->getMessage());

        guardarMensaje('error', 'No pudimos guardar los cambios. Intenta de nuevo en unos minutos.');
        redirigir($urlConfig);
    }
}

$mensajeFlash = obtenerMensaje();
$token = tokenCsrf();
$inicialNombre = mb_strtoupper(mb_substr($usuario['nombre'], 0, 1));
?>

<section class="seccion-config">
  <div class="contenedor-config">

      <aside class="menu-config" aria-label="Secciones de configuracion">
          <a href="#datos" class="enlace-menu-config">Datos personales</a>
          <a href="#foto" class="enlace-menu-config">Foto de perfil</a>
          <?php if ($esFreelancer): ?>
              <a href="#identidad" class="enlace-menu-config">Identidad comercial</a>
              <a href="#banner" class="enlace-menu-config">Banner de portada</a>
              <a href="#redes" class="enlace-menu-config">Redes sociales</a>
              <a href="#profesional" class="enlace-menu-config">Perfil profesional</a>
              <a href="#cobertura" class="enlace-menu-config">Zona de cobertura</a>
              <a href="#horarios" class="enlace-menu-config">Horarios</a>
          <?php endif; ?>
          <a href="#seguridad" class="enlace-menu-config">Seguridad</a>
      </aside>

      <div class="columna-config">

          <header class="encabezado-config">
              <div class="resumen-config">
                  <?php if (!empty($usuario['foto_perfil_url'])): ?>
                      <img src="<?php echo URL_BASE . escaparSalida($usuario['foto_perfil_url']); ?>" alt="" class="foto-resumen-config">
                  <?php else: ?>
                      <span class="foto-resumen-config foto-resumen-iniciales" aria-hidden="true"><?php echo escaparSalida($inicialNombre); ?></span>
                  <?php endif; ?>

                  <div>
                      <h1>Configuracion de perfil</h1>
                      <p class="subtitulo-config">
                          <?php echo escaparSalida($usuario['nombre']); ?>
                          <span class="etiqueta-rol-perfil"><?php echo escaparSalida(nombreRol($usuario['rol'])); ?></span>
                          <?php if ($esFreelancer): ?>
                              <span class="etiqueta-verificacion <?php echo !empty($perfilFreelancer['perfil_verificado']) ? 'verificado' : 'pendiente'; ?>">
                                  <?php echo !empty($perfilFreelancer['perfil_verificado']) ? 'Perfil verificado' : 'Verificacion pendiente'; ?>
                              </span>
                          <?php endif; ?>
                      </p>
                  </div>
              </div>

              <a href="<?php echo URL_BASE; ?>?accion=perfil" class="boton-enlace">Volver a mi perfil</a>
          </header>

          <?php if ($mensajeFlash !== null): ?>
              <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>" role="status"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
          <?php endif; ?>

          <!-- ============ Datos personales ============ -->
          <section class="tarjeta-config" id="datos">
              <h2>Datos personales</h2>
              <p class="descripcion-config">Asi te veran las personas que contraten tus servicios.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="actualizar_datos">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="campo-formulario">
                      <label for="cfgNombre">Nombre completo</label>
                      <input type="text" id="cfgNombre" name="nombre" class="icono-usuario" maxlength="100" value="<?php echo escaparSalida($valoresDatos['nombre']); ?>" required>
                      <?php if (isset($errores['datos']['nombre'])): ?><span class="error-campo"><?php echo escaparSalida($errores['datos']['nombre']); ?></span><?php endif; ?>
                  </div>

                  <div class="campo-formulario">
                      <label for="cfgCorreo">Correo electronico</label>
                      <input type="email" id="cfgCorreo" class="icono-correo" value="<?php echo escaparSalida($usuario['correo']); ?>" disabled>
                      <small class="ayuda-campo">El correo no se puede cambiar desde aqui.</small>
                  </div>

                  <div class="fila-campos">
                      <div class="campo-formulario">
                          <label for="cfgTelefono">Telefono</label>
                          <input type="tel" id="cfgTelefono" name="telefono" class="icono-telefono" value="<?php echo escaparSalida($valoresDatos['telefono']); ?>" placeholder="664 123 4567" required>
                          <small class="ayuda-campo">10 digitos.</small>
                          <?php if (isset($errores['datos']['telefono'])): ?><span class="error-campo"><?php echo escaparSalida($errores['datos']['telefono']); ?></span><?php endif; ?>
                      </div>

                      <div class="campo-formulario">
                          <label for="cfgTipoCuenta">Tipo de cuenta</label>
                          <select id="cfgTipoCuenta" name="tipo_cuenta">
                              <option value="personal" <?php echo $valoresDatos['tipo_cuenta'] === 'personal' ? 'selected' : ''; ?>>Personal</option>
                              <option value="empresarial" <?php echo $valoresDatos['tipo_cuenta'] === 'empresarial' ? 'selected' : ''; ?>>Empresarial</option>
                          </select>
                      </div>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Guardar datos</button>
              </form>
          </section>

          <!-- ============ Foto de perfil ============ -->
          <section class="tarjeta-config" id="foto">
              <h2>Foto de perfil</h2>
              <p class="descripcion-config">JPG, PNG o WEBP. Podras recortarla antes de subirla (maximo 2MB). En cuentas personales debe verse tu rostro; las empresariales pueden usar su logotipo.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" enctype="multipart/form-data" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="cambiar_foto">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="editor-foto-config">
                      <?php if (!empty($usuario['foto_perfil_url'])): ?>
                          <img src="<?php echo URL_BASE . escaparSalida($usuario['foto_perfil_url']); ?>" alt="Tu foto de perfil actual" class="foto-perfil-grande" id="vistaPreviaFoto">
                      <?php else: ?>
                          <div class="foto-perfil-grande foto-perfil-iniciales" id="vistaPreviaFoto" aria-hidden="true"><?php echo escaparSalida($inicialNombre); ?></div>
                      <?php endif; ?>

                      <div class="campo-formulario">
                          <label for="cfgFoto">Elegir nueva foto</label>
                          <input type="file" id="cfgFoto" name="foto_perfil" accept="image/jpeg,image/png,image/webp" data-recorte="foto" required>
                          <span class="error-campo" id="errorFotoCliente" hidden></span>
                          <?php if (isset($errores['foto']['foto_perfil'])): ?><span class="error-campo"><?php echo escaparSalida($errores['foto']['foto_perfil']); ?></span><?php endif; ?>
                      </div>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Actualizar foto</button>
              </form>
          </section>

          <?php if ($esFreelancer): ?>
          <!-- ============ Identidad comercial (freelancer) ============ -->
          <section class="tarjeta-config" id="identidad">
              <h2>Identidad comercial</h2>
              <p class="descripcion-config">Asi se presenta tu negocio ante los clientes. El nombre y la descripcion pasan por un filtro automatico antes de publicarse.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="guardar_identidad">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="campo-formulario">
                      <label for="cfgNombreNegocio">Nombre comercial</label>
                      <input type="text" id="cfgNombreNegocio" name="nombre_negocio" maxlength="150" value="<?php echo escaparSalida($valoresIdentidad['nombre_negocio']); ?>" placeholder="Plomeria Lopez" required>
                      <?php if (isset($errores['identidad']['nombre_negocio'])): ?><span class="error-campo"><?php echo escaparSalida($errores['identidad']['nombre_negocio']); ?></span><?php endif; ?>
                  </div>

                  <div class="campo-formulario">
                      <label for="cfgDescripcion">Descripcion o biografia</label>
                      <textarea id="cfgDescripcion" name="descripcion" class="area-texto-config" rows="5" maxlength="600" placeholder="Cuenta que haces, cuantos anos de experiencia tienes, con que materiales trabajas, garantias..." required><?php echo escaparSalida($valoresIdentidad['descripcion']); ?></textarea>
                      <small class="ayuda-campo"><span id="contadorDescripcion">0</span>/600 caracteres (minimo 20)</small>
                      <?php if (isset($errores['identidad']['descripcion'])): ?><span class="error-campo"><?php echo escaparSalida($errores['identidad']['descripcion']); ?></span><?php endif; ?>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Guardar identidad</button>
              </form>
          </section>

          <!-- ============ Banner de portada (freelancer) ============ -->
          <section class="tarjeta-config" id="banner">
              <h2>Banner de portada</h2>
              <p class="descripcion-config">Imagen panoramica que aparece arriba de tu perfil publico. JPG, PNG o WEBP; podras recortarla antes de subirla (proporcion 3:1, maximo 3MB).</p>

              <div class="vista-banner-config">
                  <img id="vistaPreviaBanner" class="vista-banner" src="<?php echo escaparSalida(urlBanner($bannerActual)); ?>" alt="Tu banner de portada actual" <?php echo $bannerActual === null ? 'hidden' : ''; ?>>
                  <div id="vistaBannerVacia" class="vista-banner vista-banner-vacia" <?php echo $bannerActual !== null ? 'hidden' : ''; ?>>Aun no tienes banner de portada</div>
              </div>

              <form action="<?php echo $urlConfig; ?>" method="POST" enctype="multipart/form-data" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="cambiar_banner">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="campo-formulario">
                      <label for="cfgBanner">Elegir imagen</label>
                      <input type="file" id="cfgBanner" name="banner_portada" accept="image/jpeg,image/png,image/webp" data-recorte="banner" required>
                      <span class="error-campo" id="errorBannerCliente" hidden></span>
                      <?php if (isset($errores['banner']['banner_portada'])): ?><span class="error-campo"><?php echo escaparSalida($errores['banner']['banner_portada']); ?></span><?php endif; ?>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Actualizar banner</button>
              </form>

              <?php if ($bannerActual !== null): ?>
              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-quitar-banner">
                  <input type="hidden" name="operacion" value="quitar_banner">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
                  <button type="submit" class="boton-enlace">Quitar banner</button>
              </form>
              <?php endif; ?>
          </section>

          <!-- ============ Redes sociales (freelancer) ============ -->
          <section class="tarjeta-config" id="redes">
              <h2>Redes sociales</h2>
              <p class="descripcion-config">Opcional. Deja vacio lo que no uses. Se muestran como enlaces en tu perfil publico.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="guardar_redes">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="rejilla-redes-config">
                      <?php foreach ($redesDisponibles as $claveRed => $datosRed): ?>
                          <div class="campo-formulario">
                              <label for="cfgRed_<?php echo escaparSalida($claveRed); ?>"><?php echo escaparSalida($datosRed['etiqueta']); ?></label>
                              <input type="text" id="cfgRed_<?php echo escaparSalida($claveRed); ?>" name="redes[<?php echo escaparSalida($claveRed); ?>]" inputmode="url" maxlength="255" autocomplete="off" value="<?php echo escaparSalida($valoresRedes[$claveRed] ?? ''); ?>" placeholder="<?php echo escaparSalida($datosRed['ejemplo']); ?>">
                              <?php if (isset($errores['redes'][$claveRed])): ?><span class="error-campo"><?php echo escaparSalida($errores['redes'][$claveRed]); ?></span><?php endif; ?>
                          </div>
                      <?php endforeach; ?>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Guardar redes sociales</button>
              </form>
          </section>

          <!-- ============ Perfil profesional (freelancer) ============ -->
          <section class="tarjeta-config" id="profesional">
              <h2>Perfil profesional</h2>
              <p class="descripcion-config">Formas de cobro que aceptas y RFC para verificar tu perfil. Tu nombre y descripcion se editan en "Identidad comercial".</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="actualizar_freelancer">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="campo-formulario">
                      <label for="cfgRfc">RFC (opcional)</label>
                      <input type="text" id="cfgRfc" name="identificador_fiscal" maxlength="13" autocomplete="off" value="<?php echo escaparSalida($valoresFreelancer['identificador_fiscal']); ?>" placeholder="XAXX010101000">
                      <small class="ayuda-campo">Lo usa el equipo de CHAMBEKI para verificar tu perfil. No se muestra a los clientes.</small>
                      <?php if (isset($errores['freelancer']['identificador_fiscal'])): ?><span class="error-campo"><?php echo escaparSalida($errores['freelancer']['identificador_fiscal']); ?></span><?php endif; ?>
                  </div>

                  <fieldset class="grupo-cobro-config">
                      <legend>Formas de cobro que aceptas</legend>

                      <label class="interruptor-config">
                          <input type="checkbox" name="acepto_efectivo" value="1" <?php echo !empty($valoresFreelancer['acepto_efectivo']) ? 'checked' : ''; ?>>
                          <span class="pista-interruptor" aria-hidden="true"></span>
                          <span>Efectivo</span>
                      </label>

                      <label class="interruptor-config">
                          <input type="checkbox" name="acepto_tarjeta" value="1" <?php echo !empty($valoresFreelancer['acepto_tarjeta']) ? 'checked' : ''; ?>>
                          <span class="pista-interruptor" aria-hidden="true"></span>
                          <span>Tarjeta de credito o debito</span>
                      </label>

                      <?php if (isset($errores['freelancer']['cobro'])): ?><span class="error-campo"><?php echo escaparSalida($errores['freelancer']['cobro']); ?></span><?php endif; ?>
                  </fieldset>

                  <button type="submit" class="boton-autenticacion boton-config">Guardar perfil profesional</button>
              </form>
          </section>

          <!-- ============ Zona de cobertura (freelancer) ============ -->
          <section class="tarjeta-config" id="cobertura">
              <h2>Zona de cobertura</h2>
              <p class="descripcion-config">Los clientes dentro de tu radio podran encontrarte en las busquedas.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="actualizar_cobertura">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="fila-campos">
                      <div class="campo-formulario">
                          <label for="cfgModalidad">Como ofreces tu servicio</label>
                          <select id="cfgModalidad" name="id_modalidad" data-en-linea="<?php echo (int) MODALIDAD_EN_LINEA; ?>" required>
                              <option value="0">Elige una opcion</option>
                              <?php foreach ($modalidades as $modalidad): ?>
                                  <option value="<?php echo (int) $modalidad['id_modalidad']; ?>" <?php echo (int) $valoresCobertura['id_modalidad'] === (int) $modalidad['id_modalidad'] ? 'selected' : ''; ?>><?php echo escaparSalida($modalidad['nombre']); ?></option>
                              <?php endforeach; ?>
                          </select>
                          <?php if (isset($errores['cobertura']['id_modalidad'])): ?><span class="error-campo"><?php echo escaparSalida($errores['cobertura']['id_modalidad']); ?></span><?php endif; ?>
                      </div>

                      <div class="campo-formulario">
                          <label for="cfgCiudad">Ciudad</label>
                          <input type="text" id="cfgCiudad" name="ciudad_limite" maxlength="100" value="<?php echo escaparSalida($valoresCobertura['ciudad_limite']); ?>" placeholder="Tijuana" required>
                          <?php if (isset($errores['cobertura']['ciudad_limite'])): ?><span class="error-campo"><?php echo escaparSalida($errores['cobertura']['ciudad_limite']); ?></span><?php endif; ?>
                      </div>
                  </div>

                  <div class="campo-formulario">
                      <label for="cfgDireccion">Direccion o zona base</label>
                      <input type="text" id="cfgDireccion" name="direccion_texto" maxlength="255" value="<?php echo escaparSalida($valoresCobertura['direccion_texto']); ?>" placeholder="Col. Otay Universidad, cerca de UABC">
                      <small class="ayuda-campo">Opcional. Solo se usa como referencia de tu zona.</small>
                      <?php if (isset($errores['cobertura']['direccion_texto'])): ?><span class="error-campo"><?php echo escaparSalida($errores['cobertura']['direccion_texto']); ?></span><?php endif; ?>
                  </div>

                  <div class="campo-formulario bloque-mapa-config" id="bloqueMapa">
                      <label for="cfgBuscarMapa">Ubicacion exacta en el mapa</label>

                      <div class="barra-mapa-config">
                          <input type="search" id="cfgBuscarMapa" placeholder="Busca tu direccion, colonia o negocio" autocomplete="off">
                          <button type="button" class="boton-mapa-config" id="btnBuscarMapa">Buscar</button>
                          <button type="button" class="boton-mapa-config" id="btnGpsMapa">Usar mi ubicacion</button>
                      </div>

                      <ul class="resultados-mapa-config" id="resultadosMapa" hidden></ul>

                      <div class="mapa-config" id="cfgMapa" role="application" aria-label="Mapa para fijar la ubicacion de tu negocio"></div>
                      <p class="aviso-mapa-config" id="avisoMapa" hidden></p>

                      <div class="fila-campos">
                          <div class="campo-formulario">
                              <label for="cfgLat">Latitud</label>
                              <input type="text" id="cfgLat" name="latitud_base" inputmode="decimal" autocomplete="off" value="<?php echo escaparSalida(textoCoordenada($valoresCobertura['latitud_base'] ?? null)); ?>" placeholder="32.514900">
                          </div>

                          <div class="campo-formulario">
                              <label for="cfgLon">Longitud</label>
                              <input type="text" id="cfgLon" name="longitud_base" inputmode="decimal" autocomplete="off" value="<?php echo escaparSalida(textoCoordenada($valoresCobertura['longitud_base'] ?? null)); ?>" placeholder="-117.038200">
                          </div>
                      </div>

                      <small class="ayuda-campo">Haz clic en el mapa o arrastra el marcador. Tambien puedes escribir las coordenadas exactas. El circulo muestra tu radio de cobertura.</small>
                      <?php if (isset($errores['cobertura']['ubicacion'])): ?><span class="error-campo"><?php echo escaparSalida($errores['cobertura']['ubicacion']); ?></span><?php endif; ?>
                  </div>

                  <div class="campo-formulario">
                      <label for="cfgRadio">Radio de cobertura: <output id="valorRadio" for="cfgRadio"><?php echo (int) $valoresCobertura['radio_cobertura_km']; ?></output> km</label>
                      <input type="range" id="cfgRadio" name="radio_cobertura_km" class="control-radio-config" min="1" max="100" step="1" value="<?php echo (int) $valoresCobertura['radio_cobertura_km']; ?>">
                      <?php if (isset($errores['cobertura']['radio_cobertura_km'])): ?><span class="error-campo"><?php echo escaparSalida($errores['cobertura']['radio_cobertura_km']); ?></span><?php endif; ?>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Guardar cobertura</button>
              </form>
          </section>

          <!-- ============ Horarios semanales (freelancer) ============ -->
          <section class="tarjeta-config" id="horarios">
              <h2>Horarios de atencion</h2>
              <p class="descripcion-config">Elige la hora de apertura y de cierre de cada dia, o marcalo como no laborable. Con esto se calcula el aviso "Abierto ahora" de tu perfil.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" id="formHorarios" novalidate>
                  <input type="hidden" name="operacion" value="guardar_horarios">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <?php if (isset($errores['horarios']['general'])): ?>
                      <p class="aviso-autenticacion aviso-error" role="alert"><?php echo escaparSalida($errores['horarios']['general']); ?></p>
                  <?php endif; ?>

                  <div class="matriz-horario-config">
                      <?php foreach ($diasSemana as $numeroDia => $nombreDia): ?>
                          <?php $filaHorario = $valoresHorario[$numeroDia]; ?>
                          <div class="fila-horario-config<?php echo !empty($filaHorario['cerrado']) ? ' cerrada' : ''; ?>" data-fila-horario>
                              <span class="dia-horario-config"><?php echo escaparSalida($nombreDia); ?></span>

                              <label class="interruptor-config interruptor-horario">
                                  <input type="checkbox" name="horario[<?php echo (int) $numeroDia; ?>][cerrado]" value="1" data-cerrado <?php echo !empty($filaHorario['cerrado']) ? 'checked' : ''; ?>>
                                  <span class="pista-interruptor" aria-hidden="true"></span>
                                  <span>No laborable</span>
                              </label>

                              <div class="horas-horario-config">
                                  <input type="time" name="horario[<?php echo (int) $numeroDia; ?>][abre]" step="900" value="<?php echo escaparSalida($filaHorario['abre']); ?>" aria-label="<?php echo escaparSalida($nombreDia); ?>: hora de apertura" data-abre>
                                  <span aria-hidden="true">a</span>
                                  <input type="time" name="horario[<?php echo (int) $numeroDia; ?>][cierra]" step="900" value="<?php echo escaparSalida($filaHorario['cierra']); ?>" aria-label="<?php echo escaparSalida($nombreDia); ?>: hora de cierre" data-cierra>
                              </div>

                              <span class="error-campo error-horario-config" data-error-horario <?php echo isset($errores['horarios'][$numeroDia]) ? '' : 'hidden'; ?>><?php echo isset($errores['horarios'][$numeroDia]) ? escaparSalida($errores['horarios'][$numeroDia]) : ''; ?></span>
                          </div>
                      <?php endforeach; ?>
                  </div>

                  <div class="acciones-horario-config">
                      <button type="submit" class="boton-autenticacion boton-config">Guardar horarios</button>
                      <button type="button" class="boton-enlace" id="btnCopiarLunes">Copiar el horario del lunes a toda la semana</button>
                  </div>
              </form>
          </section>
          <?php else: ?>
          <section class="tarjeta-config tarjeta-invitacion-config">
              <h2>¿Quieres ofrecer tus servicios?</h2>
              <p class="descripcion-config">Tu cuenta es de cliente. Completa el registro de freelancer para publicar servicios y recibir citas.</p>
              <a href="<?php echo URL_BASE; ?>?accion=registro_socio" class="boton-autenticacion boton-config boton-perfil-enlace">Ofrece tu servicio</a>
          </section>
          <?php endif; ?>

          <!-- ============ Seguridad ============ -->
          <section class="tarjeta-config" id="seguridad">
              <h2>Seguridad</h2>
              <p class="descripcion-config">Cambia tu contrasena. Combina letras y numeros, minimo 8 caracteres.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="cambiar_contrasena">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="campo-formulario">
                      <label for="cfgContrasenaActual">Contrasena actual</label>
                      <input type="password" id="cfgContrasenaActual" name="contrasena_actual" class="icono-candado" autocomplete="current-password" required>
                      <?php if (isset($errores['contrasena']['actual'])): ?><span class="error-campo"><?php echo escaparSalida($errores['contrasena']['actual']); ?></span><?php endif; ?>
                  </div>

                  <div class="fila-campos">
                      <div class="campo-formulario">
                          <label for="cfgContrasenaNueva">Contrasena nueva</label>
                          <input type="password" id="cfgContrasenaNueva" name="contrasena_nueva" class="icono-candado" autocomplete="new-password" required>
                      </div>

                      <div class="campo-formulario">
                          <label for="cfgContrasenaConfirmar">Confirma la nueva</label>
                          <input type="password" id="cfgContrasenaConfirmar" name="contrasena_confirmar" class="icono-candado" autocomplete="new-password" required>
                      </div>
                  </div>
                  <?php if (isset($errores['contrasena']['nueva'])): ?><span class="error-campo"><?php echo escaparSalida($errores['contrasena']['nueva']); ?></span><?php endif; ?>

                  <button type="submit" class="boton-autenticacion boton-config">Cambiar contrasena</button>
              </form>

              <div class="zona-sesion-config">
                  <a href="<?php echo URL_BASE; ?>?accion=cerrar_sesion" class="boton-enlace">Cerrar sesion</a>
              </div>
          </section>

      </div>
  </div>
</section>

<script src="<?php echo JS_RUTA; ?>autenticacion.js"></script>
<script src="<?php echo JS_RUTA; ?>configuracion.js?v=2"></script>
<script src="<?php echo JS_RUTA; ?>configuracionNegocio.js?v=1"></script>

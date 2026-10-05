<?php
/*
    PROCESO: Configuracion de perfil (usuario / freelancer)
    Requerimientos: RSIS-01, RS-01, RS-02, RS-05, RNF-01, RNF-05, RUS-02,
                    RU-01, RU-02, RU-03, RU-04, RF-06, RLN-03, RLN-07

    Todo se hace con el id_usuario de la sesion, nunca con uno que
    venga de la URL o del formulario. Cada seccion es un formulario
    independiente identificado por el campo oculto "operacion".
    Patron Post/Redirect/Get: si todo sale bien se guarda un mensaje
    flash y se redirige, asi un F5 no reenvia el formulario.
*/

require_once DOCROOT . 'user/includes/funcionesPerfil.php';

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

//errores por seccion y valores conservados por si algo falla (RU-02)
$errores = ['datos' => [], 'foto' => [], 'contrasena' => [], 'freelancer' => [], 'cobertura' => []];

$valoresDatos = [
    'nombre'      => $usuario['nombre'],
    'telefono'    => $usuario['telefono'] ?? '',
    'tipo_cuenta' => (int) $usuario['tipo_cuenta'] === 2 ? 'empresarial' : 'personal'
];

$valoresFreelancer = $perfilFreelancer;
$valoresCobertura = $cobertura;

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
                    'descripcion'          => limpiarEntrada($_POST['descripcion'] ?? ''),
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
                    'radio_cobertura_km' => (int) ($_POST['radio_cobertura_km'] ?? 0)
                ];

                $valoresCobertura = $datos;
                $errores['cobertura'] = validarCobertura($datos, $modalidades);

                if (empty($errores['cobertura']))
                {
                    guardarCobertura($idUsuario, $datos);
                    registrarEnBitacora($usuario['correo'], 'cobertura_actualizada');

                    guardarMensaje('exito', 'Tu zona de cobertura se guardo.');
                    redirigir($urlConfig . '#cobertura');
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
              <a href="#profesional" class="enlace-menu-config">Perfil profesional</a>
              <a href="#cobertura" class="enlace-menu-config">Zona de cobertura</a>
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
              <p class="descripcion-config">JPG, PNG o WEBP de maximo 2MB. En cuentas personales debe verse tu rostro; las empresariales pueden usar su logotipo.</p>

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
                          <input type="file" id="cfgFoto" name="foto_perfil" accept="image/jpeg,image/png,image/webp" required>
                          <span class="error-campo" id="errorFotoCliente" hidden></span>
                          <?php if (isset($errores['foto']['foto_perfil'])): ?><span class="error-campo"><?php echo escaparSalida($errores['foto']['foto_perfil']); ?></span><?php endif; ?>
                      </div>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Actualizar foto</button>
              </form>
          </section>

          <?php if ($esFreelancer): ?>
          <!-- ============ Perfil profesional (freelancer) ============ -->
          <section class="tarjeta-config" id="profesional">
              <h2>Perfil profesional</h2>
              <p class="descripcion-config">Esta informacion aparece cuando los clientes comparan servicios. La descripcion pasa por un filtro automatico antes de publicarse.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="actualizar_freelancer">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="campo-formulario">
                      <label for="cfgDescripcion">Sobre ti y tu trabajo</label>
                      <textarea id="cfgDescripcion" name="descripcion" class="area-texto-config" rows="5" maxlength="600" placeholder="Cuenta que haces, con que materiales trabajas, garantias..." required><?php echo escaparSalida($valoresFreelancer['descripcion']); ?></textarea>
                      <small class="ayuda-campo"><span id="contadorDescripcion">0</span>/600 caracteres (minimo 20)</small>
                      <?php if (isset($errores['freelancer']['descripcion'])): ?><span class="error-campo"><?php echo escaparSalida($errores['freelancer']['descripcion']); ?></span><?php endif; ?>
                  </div>

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
                          <select id="cfgModalidad" name="id_modalidad" required>
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

                  <div class="campo-formulario">
                      <label for="cfgRadio">Radio de cobertura: <output id="valorRadio" for="cfgRadio"><?php echo (int) $valoresCobertura['radio_cobertura_km']; ?></output> km</label>
                      <input type="range" id="cfgRadio" name="radio_cobertura_km" class="control-radio-config" min="1" max="100" step="1" value="<?php echo (int) $valoresCobertura['radio_cobertura_km']; ?>">
                      <?php if (isset($errores['cobertura']['radio_cobertura_km'])): ?><span class="error-campo"><?php echo escaparSalida($errores['cobertura']['radio_cobertura_km']); ?></span><?php endif; ?>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Guardar cobertura</button>
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
<script src="<?php echo JS_RUTA; ?>configuracion.js?v=1"></script>

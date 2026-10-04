<?php
/*
    PROCESO: Configuracion de perfil
    Requerimientos: RSIS-01, RS-01, RS-05, RNF-01, RNF-05

    Todo se hace con el id_usuario de la sesion, nunca con uno que
    venga de la URL o del formulario. Cada seccion es un formulario
    independiente identificado por el campo oculto "operacion".
    Patron Post/Redirect/Get: si todo sale bien se guarda un mensaje
    flash y se redirige, asi un F5 no reenvia el formulario.
*/

require once DOCROOT . "user/includes/funcionesPerfil.php";

if(!hayUsuarioEnSesion())
{
  guardarMensaje('error', "Inicia sesión para configurar tu perfil.");
  redirigir(URL_BASE . '?accion=login');
}

$idUsuario = (int) $_SESSION['id_usuario'];
$usuario = obtenerUsuarioPorId($idUsuario);

if($usuario === null)
{
  session_unset();
  session_destroy();
  session_start();

  guardarMensaje('error', 'No encontramos tu cuenta. Inicia sesión de nuevo.');
  redirigir(URL_BASE . '?accion=login');
}

$esFreelancer = (int) $usuario['rol'] === ROL_FREELANCER;
$perfilFreelancer = $esFreelancer ? obtenerPerfilFreelancer($idUsuario) : null;

//errores por seccion y valores conservador por si algo falla (RU-02)
$errores = ['datos' => [], 'foto' => [], 'contrasena' => [], 'freelancer' => []];

$valoresDatos = [
    'nombre' => $usuario['nombre'],
    'telefono' => $usuario['telefono'] ??
    'tipo_cuenta' => (int) $usuario['tipo_cuenta'] === 2 ? 'empresarial' : 'personal'
];

$valoresFreelancer = $perfilFreelancer;

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $operacion = $_POST['operacion'] ?? '';

    if(!validarCsrf($_POST['token_csrf'] ?? ''))
    {
      guardarMensaje('error', 'La sesion del formulario expiro. Intenta de nuevo.');
      redirigir(URL_BASE . '?accion=configuracion');
    }

    switch($operacion)
    {
      //-----------------------------------------------------
      // Datos generales
      //-----------------------------------------------------

      case 'actualizar_datos':
      {
        $datos = [
          'nombre' => limpiarEntrada($_POST['nombre'] ?? ''),
          'telefono' => limpiarEntrada($_POST['telefono'] ?? ''),
          'tipo_cuenta' => ($_POST['tipo_cuenta'] ?? 'personal') === 'empresarial' ? 'empresarial' : 'personal'
          ];

        $valoresDatos = $datos;
        $errores['datos'] = validarDatosGenerales($datos);

        if(empty($errores['datos']))
        {
          actualizarDatosGenerales($idUsuario, $datos);
          $_SESSION['nombre_usuario'] = $datos['nombre'];

          guardarMensaje('exito', 'Tus datos se actualizaron correctamente.');
          redirigir(URL_BASE . '?accion=configuracion');
        }

        break;
      }

      //-----------------------------------------------------
      //Foto de perfil (Maximo 2MB, se valida el tipo real)
      //-----------------------------------------------------
      case 'cambiar_foto':
      {
        $resultadoFoto = guardarFotoPerfil($_FILES['foto_perfil'] ?? null);

        if(!resultadoFoto['exito'])
        {
          $errores['foto']['foto_perfil'] = $resultadoFoto['error'];
          break;
        }

        $fotoAnterior = $usuario['foto_perfil_url'];

        actualizarFotoUsuario($idUsuario, $resultadoFoto['ruta']);
        eliminarFotoPerfil($fotoAnterior);

        $_SESSION['foto_usuario'] = $resultadoFoto['ruta'];

        guardarMensaje('exito', 'Tu foto de perfil se actualizo.');
        redirigir(URL_BASE . '?accion=configuracion');
      }


      //-------------------------------------------------------
      //Cambio de contraseña (pide la actual)
      //--------------------------------------------------------
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
        redirigir(URL_BASE . '?accion=configuracion');
      }


      //-----------------------------------------------------
      // Perfil profesional (solo freelancers)
      //-----------------------------------------------------
      case 'actualizar_freelancer':
      {
          if (!$esFreelancer)
          {
              guardarMensaje('error', 'Esta seccion es solo para freelancers.');
              redirigir(URL_BASE . '?accion=configuracion');
          }

          $tarifaTexto = str_replace(',', '', limpiarEntrada($_POST['tarifa_desde'] ?? ''));

          $datos = [
              'titulo'            => limpiarEntrada($_POST['titulo'] ?? ''),
              'descripcion'       => limpiarEntrada($_POST['descripcion'] ?? ''),
              'anios_experiencia' => (int) ($_POST['anios_experiencia'] ?? 0),
              'zona_cobertura'    => limpiarEntrada($_POST['zona_cobertura'] ?? ''),
              'tarifa_desde'      => ($tarifaTexto !== '' && is_numeric($tarifaTexto)) ? (float) $tarifaTexto : null,
              'disponible'        => isset($_POST['disponible'])
          ];

          if ($tarifaTexto !== '' && !is_numeric($tarifaTexto))
          {
              $errores['freelancer']['tarifa_desde'] = 'Escribe la tarifa solo con numeros.';
          }

          $errores['freelancer'] = array_merge($errores['freelancer'], validarPerfilFreelancer($datos));
          $valoresFreelancer = $datos;

          if (empty($errores['freelancer']))
          {
              guardarPerfilFreelancer($idUsuario, $datos);

              guardarMensaje('exito', 'Tu perfil profesional se actualizo.');
              redirigir(URL_BASE . '?accion=configuracion');
          }

          break;
      }
      
    }
}

  $mensajeFlash = obtenerMensaje();
  $token = tokenCsrf();
  $urlConfig = URL_BASE . '?accion=configuracion';


?>

<section class="seccion-config">
  <div class="contenedor-config">

      <aside class="menu-config" aria-label="Secciones de configuracion">
          <a href="#datos" class="enlace-menu-config">Datos personales</a>
          <a href="#foto" class="enlace-menu-config">Foto de perfil</a>
          <?php if ($esFreelancer): ?>
              <a href="#profesional" class="enlace-menu-config">Perfil profesional</a>
          <?php endif; ?>
          <a href="#seguridad" class="enlace-menu-config">Seguridad</a>
      </aside>

      <div class="columna-config">

          <header class="encabezado-config">
              <h1>Configuracion de perfil</h1>
              <a href="<?php echo URL_BASE; ?>?accion=perfil" class="boton-enlace">Volver a mi perfil</a>
          </header>

          <?php if ($mensajeFlash !== null): ?>
              <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
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
              <p class="descripcion-config">JPG, PNG o WEBP de maximo 2MB. En cuentas personales debe verse tu rostro.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" enctype="multipart/form-data" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="cambiar_foto">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="editor-foto-config">
                      <?php if (!empty($usuario['foto_perfil_url'])): ?>
                          <img src="<?php echo URL_BASE . escaparSalida($usuario['foto_perfil_url']); ?>" alt="Tu foto de perfil actual" class="foto-perfil-grande" id="vistaPreviaFoto">
                      <?php else: ?>
                          <div class="foto-perfil-grande foto-perfil-iniciales" id="vistaPreviaFoto" aria-hidden="true">
                              <?php echo escaparSalida(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1))); ?>
                          </div>
                      <?php endif; ?>

                      <div class="campo-formulario">
                          <label for="cfgFoto">Elegir nueva foto</label>
                          <input type="file" id="cfgFoto" name="foto_perfil" accept="image/jpeg,image/png,image/webp" required>
                          <?php if (isset($errores['foto']['foto_perfil'])): ?><span class="error-campo"><?php echo escaparSalida($errores['foto']['foto_perfil']); ?></span><?php endif; ?>
                      </div>
                  </div>

                  <button type="submit" class="boton-autenticacion boton-config">Actualizar foto</button>
              </form>
          </section>

          <!-- ============ Perfil profesional (freelancer) ============ -->
          <?php if ($esFreelancer): ?>
          <section class="tarjeta-config" id="profesional">
              <h2>Perfil profesional</h2>
              <p class="descripcion-config">Esta informacion aparece cuando los clientes buscan servicios.</p>

              <form action="<?php echo $urlConfig; ?>" method="POST" class="formulario-autenticacion" novalidate>
                  <input type="hidden" name="operacion" value="actualizar_freelancer">
                  <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">

                  <div class="campo-formulario">
                      <label for="cfgTitulo">Especialidad u oficio</label>
                      <input type="text" id="cfgTitulo" name="titulo" maxlength="80" value="<?php echo escaparSalida($valoresFreelancer['titulo']); ?>" placeholder="Electricista residencial" required>
                      <?php if (isset($errores['freelancer']['titulo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['freelancer']['titulo']); ?></span><?php endif; ?>
                  </div>

                  <div class="campo-formulario">
                      <label for="cfgDescripcion">Sobre ti y tu trabajo</label>
                      <textarea id="cfgDescripcion" name="descripcion" class="area-texto-config" rows="5" maxlength="600" placeholder="Cuenta que haces, con que materiales trabajas, garantias..."><?php echo escaparSalida($valoresFreelancer['descripcion']); ?></textarea>
                      <small class="ayuda-campo"><span id="contadorDescripcion">0</span>/600 caracteres</small>
                      <?php if (isset($errores['freelancer']['descripcion'])): ?><span class="error-campo"><?php echo escaparSalida($errores['freelancer']['descripcion']); ?></span><?php endif; ?>
                  </div>

                  <div class="fila-campos">
                      <div class="campo-formulario">
                          <label for="cfgAnios">Anios de experiencia</label>
                          <input type="number" id="cfgAnios" name="anios_experiencia" min="0" max="60" value="<?php echo (int) $valoresFreelancer['anios_experiencia']; ?>">
                          <?php if (isset($errores['freelancer']['anios_experiencia'])): ?><span class="error-campo"><?php echo escaparSalida($errores['freelancer']['anios_experiencia']); ?></span><?php endif; ?>
                      </div>

                      <div class="campo-formulario">
                          <label for="cfgTarifa">Tarifa desde (MXN)</label>
                          <input type="text" id="cfgTarifa" name="tarifa_desde" inputmode="decimal" value="<?php echo $valoresFreelancer['tarifa_desde'] !== null ? escaparSalida($valoresFreelancer['tarifa_desde']) : ''; ?>" placeholder="350">
                          <small class="ayuda-campo">Opcional. Vacio se muestra como "A cotizar".</small>
                          <?php if (isset($errores['freelancer']['tarifa_desde'])): ?><span class="error-campo"><?php echo escaparSalida($errores['freelancer']['tarifa_desde']); ?></span><?php endif; ?>
                      </div>
                  </div>

                  <div class="campo-formulario">
                      <label for="cfgZona">Zona de cobertura</label>
                      <input type="text" id="cfgZona" name="zona_cobertura" maxlength="120" value="<?php echo escaparSalida($valoresFreelancer['zona_cobertura']); ?>" placeholder="Tijuana: Otay, Zona Rio, Playas">
                      <?php if (isset($errores['freelancer']['zona_cobertura'])): ?><span class="error-campo"><?php echo escaparSalida($errores['freelancer']['zona_cobertura']); ?></span><?php endif; ?>
                  </div>

                  <label class="interruptor-config">
                      <input type="checkbox" name="disponible" value="1" <?php echo !empty($valoresFreelancer['disponible']) ? 'checked' : ''; ?>>
                      <span class="pista-interruptor" aria-hidden="true"></span>
                      <span>Disponible para recibir nuevas citas</span>
                  </label>

                  <button type="submit" class="boton-autenticacion boton-config">Guardar perfil profesional</button>
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

<script>
  (function()
  {
      //vista previa de la foto antes de subirla
      const entradaFoto = document.getElementById('cfgFoto');
      const vistaPrevia = document.getElementById('vistaPreviaFoto');

      if (entradaFoto && vistaPrevia)
      {
          entradaFoto.addEventListener('change', function()
          {
              const archivo = entradaFoto.files[0];

              if (!archivo || !archivo.type.startsWith('image/'))
              {
                  return;
              }

              const lector = new FileReader();

              lector.onload = function(e)
              {
                  let imagen = vistaPrevia;

                  if (imagen.tagName !== 'IMG')
                  {
                      imagen = document.createElement('img');
                      imagen.className = 'foto-perfil-grande';
                      imagen.id = 'vistaPreviaFoto';
                      imagen.alt = 'Vista previa de la nueva foto';
                      vistaPrevia.replaceWith(imagen);
                  }

                  imagen.src = e.target.result;
              };

              lector.readAsDataURL(archivo);
          });
      }

      //contador de caracteres de la descripcion
      const area = document.getElementById('cfgDescripcion');
      const contador = document.getElementById('contadorDescripcion');

      if (area && contador)
      {
          const actualizar = function() { contador.textContent = area.value.length; };
          area.addEventListener('input', actualizar);
          actualizar();
      }
  })();
</script>
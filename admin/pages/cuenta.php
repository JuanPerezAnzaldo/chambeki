<?php
/*
    PERFIL DE UNA CUENTA (https://chambeki.com/admin/cuenta?id=N)
    Permiso para entrar: usuarios.ver. Cada accion exige ademas su permiso:
      - activar / suspender ........ usuarios.suspender
      - verificar freelancer / docs . usuarios.verificar
      - quitar foto / editar datos .. usuarios.editar

    Solo cuentas de clientes y freelancers: un id de empleado se trata como
    "no encontrado" (el personal se administra en Gestion de personal).
    Todas las acciones son POST con CSRF, piden motivo cuando corresponde,
    y quedan en moderacion_acciones y en bitacora_accesos.
*/

require_once DOCROOT . 'admin/includes/funcionesModeracion.php';

$idCuenta = (int) ($_GET['id'] ?? 0);
$cuenta = obtenerCuenta($idCuenta);

if ($cuenta === null)
{
    guardarMensaje('error', 'No se encontro esa cuenta.');
    redirigir(URL_ADMIN . 'cuentas');
}

$esFreelancer = (int) $cuenta['rol'] === ROL_FREELANCER;
$urlCuenta = URL_ADMIN . 'cuenta?id=' . $idCuenta;

//exige el permiso de la accion; si falta, avisa, lo deja en bitacora y regresa al perfil
function permitirAccion($admin, $permiso, $urlRetorno)
{
    if (!adminTienePermiso($admin, $permiso))
    {
        registrarEnBitacora($admin['correo'], 'admin_acceso_denegado:' . $permiso);
        guardarMensaje('error', 'Tu cuenta no tiene permiso para esa accion.');
        redirigir($urlRetorno);
    }
}

//-------- acciones --------
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    if (!csrfAdminValido($_POST['csrf'] ?? ''))
    {
        guardarMensaje('error', 'El formulario expiro. Intenta de nuevo.');
        redirigir($urlCuenta);
    }

    $operacion = $_POST['operacion'] ?? '';
    $motivo    = mb_substr(limpiarEntrada($_POST['motivo'] ?? ''), 0, 255);

    switch ($operacion)
    {
        //----- activar / suspender -----
        case 'estatus':
        {
            permitirAccion($admin, 'usuarios.suspender', $urlCuenta);

            $nuevo = (int) ($_POST['nuevo_estatus'] ?? 0);

            if (!in_array($nuevo, [ESTATUS_ACTIVO, ESTATUS_SUSPENDIDO], true) || $nuevo === (int) $cuenta['estatus'])
            {
                guardarMensaje('error', 'La cuenta ya tenia ese estatus.');
                break;
            }

            $errorMotivo = validarMotivoModeracion($motivo);

            if ($errorMotivo !== null)
            {
                guardarMensaje('error', $errorMotivo);
                break;
            }

            cambiarEstatusCuenta($idCuenta, $nuevo);
            registrarAccionModeracion($admin, $idCuenta, $nuevo === ESTATUS_ACTIVO ? 'activar' : 'suspender', null, $motivo);

            guardarMensaje('exito', $nuevo === ESTATUS_ACTIVO
                ? 'La cuenta fue activada.'
                : 'La cuenta fue suspendida: ya no podra iniciar sesion y su sesion actual se cierra en su siguiente clic.');
            break;
        }

        //----- verificar / retirar verificacion del freelancer -----
        case 'verificacion':
        {
            permitirAccion($admin, 'usuarios.verificar', $urlCuenta);

            $perfil = $esFreelancer ? perfilProfesionalDe($idCuenta) : null;

            if ($perfil === null)
            {
                guardarMensaje('error', 'Solo se puede verificar a un freelancer que ya tenga perfil profesional.');
                break;
            }

            $verificar = ($_POST['valor'] ?? '') === '1';

            if ($verificar === ((int) $perfil['perfil_verificado'] === 1))
            {
                guardarMensaje('error', 'El perfil ya tenia ese estatus de verificacion.');
                break;
            }

            //al retirar la verificacion si se pide motivo; al otorgarla es opcional
            if (!$verificar)
            {
                $errorMotivo = validarMotivoModeracion($motivo);

                if ($errorMotivo !== null)
                {
                    guardarMensaje('error', $errorMotivo);
                    break;
                }
            }

            establecerVerificacion($idCuenta, $verificar);
            registrarAccionModeracion($admin, $idCuenta, $verificar ? 'verificar' : 'quitar_verificacion', null, $motivo !== '' ? $motivo : null);

            guardarMensaje('exito', $verificar ? 'El freelancer quedo verificado.' : 'Se retiro la verificacion del freelancer.');
            break;
        }

        //----- revisar un documento de verificacion -----
        case 'revisar_doc':
        {
            permitirAccion($admin, 'usuarios.verificar', $urlCuenta);

            $resultadoDoc = (int) ($_POST['resultado'] ?? 0);
            $nota = mb_substr(limpiarEntrada($_POST['nota'] ?? ''), 0, 255);

            if (!in_array($resultadoDoc, [2, 3], true))
            {
                guardarMensaje('error', 'Elige si el documento se aprueba o se rechaza.');
                break;
            }

            if ($resultadoDoc === 3 && mb_strlen($nota) < 5)
            {
                guardarMensaje('error', 'Para rechazar un documento escribe la razon (minimo 5 caracteres).');
                break;
            }

            $idDocumento = (int) ($_POST['id_documento'] ?? 0);

            if (!revisarDocumento($idDocumento, $idCuenta, $resultadoDoc, $nota, $admin['id_usuario']))
            {
                guardarMensaje('error', 'No se pudo registrar la revision de ese documento.');
                break;
            }

            registrarAccionModeracion($admin, $idCuenta, $resultadoDoc === 2 ? 'doc_aprobado' : 'doc_rechazado', 'Documento #' . $idDocumento, $nota !== '' ? $nota : null);

            guardarMensaje('exito', $resultadoDoc === 2 ? 'Documento aprobado.' : 'Documento rechazado.');
            break;
        }

        //----- quitar foto de perfil inadecuada -----
        case 'quitar_foto':
        {
            permitirAccion($admin, 'usuarios.editar', $urlCuenta);

            if (empty($cuenta['foto_perfil_url']))
            {
                guardarMensaje('error', 'La cuenta no tiene foto de perfil.');
                break;
            }

            $errorMotivo = validarMotivoModeracion($motivo);

            if ($errorMotivo !== null)
            {
                guardarMensaje('error', $errorMotivo);
                break;
            }

            quitarFotoCuenta($idCuenta, $cuenta['foto_perfil_url']);
            registrarAccionModeracion($admin, $idCuenta, 'quitar_foto', null, $motivo);

            guardarMensaje('exito', 'Se retiro la foto de perfil.');
            break;
        }
    }

    redirigir($urlCuenta);
}

//-------- datos para mostrar --------
/*
    Cada seccion se consulta por separado: si una tabla o columna de la BD real
    no coincide con lo esperado, el perfil igual abre, esa seccion queda vacia y
    arriba aparece un aviso con el nombre de la seccion. El detalle tecnico se
    guarda en el log del servidor y solo lo ve el Super Administrador.
*/
$avisosCarga = [];

function cargarSeccionCuenta($nombre, $consulta, $valorSiFalla, &$avisos, $admin)
{
    try
    {
        return $consulta();
    }
    catch (Throwable $error)
    {
        error_log('Perfil de cuenta, seccion "' . $nombre . '": ' . $error->getMessage());

        $avisos[] = [
            'seccion' => $nombre,
            'detalle' => adminEsSuper($admin) ? $error->getMessage() : null
        ];

        return $valorSiFalla;
    }
}

$estadisticasVacias = ['servicios' => 0, 'citas' => 0, 'citas_completadas' => 0, 'calificacion' => null, 'evaluaciones' => 0];

$perfil       = $esFreelancer ? cargarSeccionCuenta('Perfil profesional', function () use ($idCuenta) { return perfilProfesionalDe($idCuenta); }, null, $avisosCarga, $admin) : null;
$cobertura    = $esFreelancer ? cargarSeccionCuenta('Cobertura', function () use ($idCuenta) { return coberturaDe($idCuenta); }, null, $avisosCarga, $admin) : null;
$documentos   = $esFreelancer ? cargarSeccionCuenta('Documentos de verificacion', function () use ($idCuenta) { return documentosDe($idCuenta); }, [], $avisosCarga, $admin) : [];
$suscripcion  = $esFreelancer ? cargarSeccionCuenta('Suscripcion', function () use ($idCuenta) { return suscripcionDe($idCuenta); }, null, $avisosCarga, $admin) : null;
$estadisticas = cargarSeccionCuenta('Actividad', function () use ($cuenta) { return estadisticasCuenta($cuenta); }, $estadisticasVacias, $avisosCarga, $admin);
$historial    = cargarSeccionCuenta('Historial de moderacion', function () use ($idCuenta) { return historialModeracion($idCuenta); }, [], $avisosCarga, $admin);

$activa      = (int) $cuenta['estatus'] === ESTATUS_ACTIVO;
$verificado  = $perfil !== null && (int) $perfil['perfil_verificado'] === 1;
$hayDocAprobado = false;

foreach ($documentos as $doc)
{
    if ((int) $doc['estado_revision'] === 2) { $hayDocAprobado = true; }
}

$puedeEditar    = adminTienePermiso($admin, 'usuarios.editar');
$puedeSuspender = adminTienePermiso($admin, 'usuarios.suspender');
$puedeVerificar = adminTienePermiso($admin, 'usuarios.verificar');

//ruta de la foto (las del sistema se guardan sin "/" inicial)
$srcFoto = '';

if (!empty($cuenta['foto_perfil_url']))
{
    $srcFoto = $cuenta['foto_perfil_url'][0] === '/' ? $cuenta['foto_perfil_url'] : '/' . $cuenta['foto_perfil_url'];
}

$token = csrfAdminToken();
$mensajeFlash = obtenerMensaje();
?>

<p class="migaja-admin"><a href="<?php echo URL_ADMIN; ?>cuentas">&larr; Cuentas</a></p>

<?php if ($mensajeFlash !== null): ?>
    <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
<?php endif; ?>

<?php if (!empty($avisosCarga)): ?>
    <div class="aviso-autenticacion aviso-error">
        <strong>Algunas secciones no se pudieron cargar:</strong>
        <ul>
            <?php foreach ($avisosCarga as $aviso): ?>
                <li>
                    <?php echo escaparSalida($aviso['seccion']); ?>
                    <?php if ($aviso['detalle'] !== null): ?>
                        <br><small>Detalle tecnico (solo Super Administrador): <code><?php echo escaparSalida($aviso['detalle']); ?></code></small>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        La tabla o columna de la base de datos no coincide con lo esperado. Quedo registrado en el log del servidor.
    </div>
<?php endif; ?>

<!-- ===== Encabezado de la cuenta ===== -->
<section class="admin-bloque admin-cuenta-cabecera">
    <div class="admin-avatar">
        <?php if ($srcFoto !== ''): ?>
            <img src="<?php echo escaparSalida($srcFoto); ?>" alt="Foto de perfil de <?php echo escaparSalida($cuenta['nombre']); ?>">
        <?php else: ?>
            <span><?php echo escaparSalida(mb_strtoupper(mb_substr($cuenta['nombre'], 0, 1))); ?></span>
        <?php endif; ?>
    </div>

    <div class="admin-cuenta-titulo">
        <h1 class="admin-titulo"><?php echo escaparSalida($cuenta['nombre']); ?> <small>#<?php echo $idCuenta; ?></small></h1>
        <div class="admin-cuenta-insignias">
            <span class="admin-insignia-ajuste"><?php echo escaparSalida(nombreRol($cuenta['rol'])); ?></span>
            <span class="admin-estatus <?php echo $activa ? 'admin-estatus-activo' : 'admin-estatus-suspendido'; ?>"><?php echo escaparSalida(nombreEstatus($cuenta['estatus'])); ?></span>
            <?php if ($esFreelancer): ?>
                <span class="insignia-verificacion <?php echo $verificado ? 'insignia-verificado' : 'insignia-pendiente'; ?>"><?php echo $verificado ? 'Verificado' : 'Sin verificar'; ?></span>
            <?php endif; ?>
        </div>
    </div>

<div style="display: flex; gap: 10px;">
        <?php if ($puedeEditar): ?>
            <a href="<?php echo URL_ADMIN; ?>cuenta-editar?id=<?php echo $idCuenta; ?>" class="boton-autenticacion admin-boton-accion">Editar datos basicos</a>
            <a href="<?php echo URL_ADMIN; ?>enviar-correo?id=<?php echo $idCuenta; ?>" class="boton-autenticacion admin-boton-accion" style="background-color: #4A5568;">Enviar correo</a>
        <?php endif; ?>
    </div>
</section>

<div class="admin-dos-columnas">

    <!-- ===== Datos y actividad ===== -->
    <section class="admin-bloque">
        <h2>Datos de la cuenta</h2>
        <dl class="admin-datos">
            <dt>Correo</dt><dd><?php echo escaparSalida($cuenta['correo']); ?></dd>
            <dt>Telefono</dt><dd><?php echo $cuenta['telefono'] !== null && $cuenta['telefono'] !== '' ? escaparSalida($cuenta['telefono']) : 'Sin telefono'; ?></dd>
            <dt>Tipo de cuenta</dt><dd><?php echo escaparSalida(nombreTipoCuenta($cuenta['tipo_cuenta'])); ?></dd>
            <dt>Registro</dt><dd><?php echo $cuenta['fecha_registro'] !== null ? escaparSalida(date('d/m/Y H:i', strtotime($cuenta['fecha_registro']))) : '-'; ?></dd>
            <dt>Ultimo cambio</dt><dd><?php echo $cuenta['fecha_actualizacion'] !== null ? escaparSalida(date('d/m/Y H:i', strtotime($cuenta['fecha_actualizacion']))) : 'Sin cambios'; ?></dd>
        </dl>
    </section>

    <section class="admin-bloque">
        <h2>Actividad</h2>
        <dl class="admin-datos">
            <?php if ($esFreelancer): ?>
                <dt>Servicios publicados</dt><dd><?php echo $estadisticas['servicios']; ?></dd>
                <dt>Citas recibidas</dt><dd><?php echo $estadisticas['citas']; ?> (<?php echo $estadisticas['citas_completadas']; ?> completadas)</dd>
                <dt>Calificacion</dt>
                <dd><?php echo $estadisticas['calificacion'] !== null ? escaparSalida($estadisticas['calificacion']) . ' / 5 (' . $estadisticas['evaluaciones'] . ' evaluaciones)' : 'Sin evaluaciones'; ?></dd>
                <dt>Suscripcion</dt>
                <dd>
                    <?php if ($suscripcion === null): ?>
                        Sin suscripcion
                    <?php else: ?>
                        <?php echo escaparSalida($suscripcion['nombre_plan'] ?? 'Plan'); ?> &middot;
                        <?php echo (int) $suscripcion['estado'] === 1 ? 'activa' : ((int) $suscripcion['estado'] === 2 ? 'vencida' : 'cancelada'); ?>
                        (vence <?php echo escaparSalida(date('d/m/Y', strtotime($suscripcion['fecha_vencimiento']))); ?>)
                    <?php endif; ?>
                </dd>
            <?php else: ?>
                <dt>Citas solicitadas</dt><dd><?php echo $estadisticas['citas']; ?> (<?php echo $estadisticas['citas_completadas']; ?> completadas)</dd>
            <?php endif; ?>
        </dl>
    </section>
</div>

<!-- ===== Perfil profesional (solo freelancers) ===== -->
<?php if ($esFreelancer): ?>
<section class="admin-bloque">
    <h2>Perfil profesional</h2>

    <?php if ($perfil === null): ?>
        <p>Este freelancer todavia no ha completado su perfil profesional.</p>
    <?php else: ?>
        <dl class="admin-datos">
            <?php if (!empty($perfil['nombre_negocio'])): ?>
                <dt>Nombre del negocio</dt><dd><?php echo escaparSalida($perfil['nombre_negocio']); ?></dd>
            <?php endif; ?>
            <dt>Descripcion</dt>
            <dd><?php echo $perfil['descripcion'] !== null && $perfil['descripcion'] !== '' ? nl2br(escaparSalida($perfil['descripcion'])) : 'Sin descripcion'; ?></dd>
            <dt>Como ofrece sus servicios</dt><dd><?php echo (int) $perfil['es_empresa'] === 1 ? 'Empresa' : 'Persona fisica'; ?></dd>
            <dt>Formas de pago</dt>
            <dd>
                <?php
                    $formasPago = [];
                    if (!empty($perfil['acepta_efectivo'])) { $formasPago[] = 'Efectivo'; }
                    if (!empty($perfil['acepta_tarjeta']))  { $formasPago[] = 'Tarjeta'; }
                    echo escaparSalida(!empty($formasPago) ? implode(' y ', $formasPago) : 'Sin definir');
                ?>
            </dd>
            <dt>RFC / CURP</dt><dd><?php echo !empty($perfil['identificador_fiscal']) ? '<code>' . escaparSalida(enmascararDato($perfil['identificador_fiscal'])) . '</code> (parcial)' : 'No capturado'; ?></dd>
            <dt>Cobertura</dt>
            <dd>
                <?php if ($cobertura === null): ?>
                    Sin definir
                <?php else: ?>
                    <?php echo escaparSalida(trim(($cobertura['ciudad_limite'] ?? '') . ' ' . ($cobertura['direccion_texto'] ?? ''))); ?>
                    <?php echo $cobertura['radio_cobertura_km'] !== null ? '&middot; radio ' . (int) $cobertura['radio_cobertura_km'] . ' km' : ''; ?>
                    <?php echo !empty($cobertura['modalidad']) ? '&middot; ' . escaparSalida($cobertura['modalidad']) : ''; ?>
                <?php endif; ?>
            </dd>
        </dl>
    <?php endif; ?>
</section>

<!-- ===== Documentos de verificacion ===== -->
<section class="admin-bloque">
    <h2>Documentos de verificacion</h2>

    <?php if (empty($documentos)): ?>
        <p>No ha enviado documentos.</p>
    <?php else: ?>
        <div class="admin-tabla-contenedor admin-tabla-sin-borde">
            <table class="admin-tabla">
                <thead><tr><th>Documento</th><th>Estado</th><th>Revision</th><?php echo $puedeVerificar ? '<th>Resolver</th>' : ''; ?></tr></thead>
                <tbody>
                    <?php foreach ($documentos as $doc): ?>
                        <tr>
                            <td>
                                <strong><?php echo escaparSalida(nombreTipoDocumento($doc['tipo_documento'])); ?></strong>
                                <?php
                                    $urlDoc = (string) $doc['url_archivo'];
                                    $urlSegura = $urlDoc !== '' && ($urlDoc[0] === '/' || strpos($urlDoc, 'https://') === 0);
                                ?>
                                <?php if ($urlSegura): ?>
                                    <br><a href="<?php echo escaparSalida($urlDoc); ?>" target="_blank" rel="noopener noreferrer" class="admin-enlace-accion">Ver archivo</a>
                                <?php elseif ($urlDoc === ''): ?>
                                    <br><small>Archivo ya no disponible</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $estadoDoc = (int) $doc['estado_revision']; ?>
                                <span class="insignia-verificacion <?php echo $estadoDoc === 2 ? 'insignia-verificado' : ($estadoDoc === 3 ? 'admin-estatus-suspendido' : 'insignia-pendiente'); ?>">
                                    <?php echo escaparSalida(nombreEstadoRevision($estadoDoc)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($doc['fecha_revision'] !== null): ?>
                                    <?php echo escaparSalida(date('d/m/Y H:i', strtotime($doc['fecha_revision']))); ?>
                                    <?php echo !empty($doc['revisor']) ? '<br><small>por ' . escaparSalida($doc['revisor']) . '</small>' : ''; ?>
                                    <?php echo !empty($doc['notas_aprobacion']) ? '<br><small>' . escaparSalida($doc['notas_aprobacion']) . '</small>' : ''; ?>
                                <?php else: ?>
                                    <small>Sin revisar</small>
                                <?php endif; ?>
                            </td>
                            <?php if ($puedeVerificar): ?>
                                <td>
                                    <form action="<?php echo $urlCuenta; ?>" method="POST" class="admin-form-revision">
                                        <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">
                                        <input type="hidden" name="operacion" value="revisar_doc">
                                        <input type="hidden" name="id_documento" value="<?php echo (int) $doc['id_documento']; ?>">
                                        <select name="resultado" aria-label="Resultado de la revision">
                                            <option value="2">Aprobar</option>
                                            <option value="3">Rechazar</option>
                                        </select>
                                        <input type="text" name="nota" maxlength="255" placeholder="Nota (obligatoria al rechazar)" aria-label="Nota de la revision">
                                        <button type="submit" class="admin-boton-salir">Guardar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ===== Acciones de moderacion ===== -->
<?php if ($puedeSuspender || ($puedeVerificar && $perfil !== null) || ($puedeEditar && $srcFoto !== '')): ?>
<section class="admin-bloque">
    <h2>Acciones de moderacion</h2>
    <p class="admin-nota">Cada accion queda en el historial de la cuenta con tu nombre y el motivo.</p>

    <div class="admin-acciones-moderacion">

        <?php if ($puedeSuspender): ?>
        <form action="<?php echo $urlCuenta; ?>" method="POST" class="admin-accion-caja"
              onsubmit="return confirm('<?php echo $activa ? 'Suspender' : 'Activar'; ?> esta cuenta?');">
            <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">
            <input type="hidden" name="operacion" value="estatus">
            <input type="hidden" name="nuevo_estatus" value="<?php echo $activa ? ESTATUS_SUSPENDIDO : ESTATUS_ACTIVO; ?>">
            <strong><?php echo $activa ? 'Suspender cuenta' : 'Activar cuenta'; ?></strong>
            <small><?php echo $activa ? 'No podra iniciar sesion y se le cierra la sesion que tenga abierta.' : 'Podra volver a iniciar sesion.'; ?></small>
            <label for="motivoEstatus" class="admin-etiqueta-corta">Motivo</label>
            <input type="text" id="motivoEstatus" name="motivo" maxlength="255" placeholder="Ej. Solicitud del usuario, reportes de fraude..." required>
            <button type="submit" class="boton-autenticacion admin-boton-accion <?php echo $activa ? 'admin-boton-peligro' : ''; ?>"><?php echo $activa ? 'Suspender' : 'Activar'; ?></button>
        </form>
        <?php endif; ?>

        <?php if ($puedeVerificar && $perfil !== null): ?>
        <form action="<?php echo $urlCuenta; ?>" method="POST" class="admin-accion-caja"
              onsubmit="return confirm('<?php echo $verificado ? 'Retirar la verificacion de' : 'Verificar a'; ?> este freelancer?');">
            <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">
            <input type="hidden" name="operacion" value="verificacion">
            <input type="hidden" name="valor" value="<?php echo $verificado ? '0' : '1'; ?>">
            <strong><?php echo $verificado ? 'Retirar verificacion' : 'Verificar freelancer'; ?></strong>
            <?php if (!$verificado && !$hayDocAprobado): ?>
                <small class="admin-aviso-chico">Ojo: todavia no tiene ningun documento aprobado.</small>
            <?php endif; ?>
            <label for="motivoVerif" class="admin-etiqueta-corta">Motivo <?php echo $verificado ? '' : '(opcional)'; ?></label>
            <input type="text" id="motivoVerif" name="motivo" maxlength="255" <?php echo $verificado ? 'required' : ''; ?>>
            <button type="submit" class="boton-autenticacion admin-boton-accion <?php echo $verificado ? 'admin-boton-peligro' : ''; ?>"><?php echo $verificado ? 'Retirar' : 'Verificar'; ?></button>
        </form>
        <?php endif; ?>

        <?php if ($puedeEditar && $srcFoto !== ''): ?>
        <form action="<?php echo $urlCuenta; ?>" method="POST" class="admin-accion-caja"
              onsubmit="return confirm('Quitar la foto de perfil de esta cuenta?');">
            <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">
            <input type="hidden" name="operacion" value="quitar_foto">
            <strong>Quitar foto de perfil</strong>
            <small>Para fotos inadecuadas. El usuario podra subir otra.</small>
            <label for="motivoFoto" class="admin-etiqueta-corta">Motivo</label>
            <input type="text" id="motivoFoto" name="motivo" maxlength="255" required>
            <button type="submit" class="boton-autenticacion admin-boton-accion admin-boton-peligro">Quitar foto</button>
        </form>
        <?php endif; ?>

    </div>
</section>
<?php endif; ?>

<!-- ===== Historial de moderacion ===== -->
<section class="admin-bloque">
    <h2>Historial de moderacion</h2>

    <?php if (empty($historial)): ?>
        <p>Esta cuenta no tiene acciones de moderacion registradas.</p>
    <?php else: ?>
        <div class="admin-tabla-contenedor admin-tabla-sin-borde">
            <table class="admin-tabla">
                <thead><tr><th>Fecha</th><th>Accion</th><th>Por</th><th>Motivo / detalle</th></tr></thead>
                <tbody>
                    <?php foreach ($historial as $evento): ?>
                        <tr>
                            <td><?php echo escaparSalida(date('d/m/Y H:i', strtotime($evento['fecha']))); ?></td>
                            <td><?php echo escaparSalida(etiquetaAccionModeracion($evento['accion'])); ?></td>
                            <td><?php echo escaparSalida($evento['admin_nombre'] ?? 'Empleado eliminado'); ?></td>
                            <td>
                                <?php echo escaparSalida(trim(($evento['motivo'] ?? '') . ' ' . ($evento['detalle'] !== null ? '(' . $evento['detalle'] . ')' : ''))); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

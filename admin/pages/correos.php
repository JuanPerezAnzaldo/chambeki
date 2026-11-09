<?php
/*
    COMUNICACION Y CORREOS INSTITUCIONALES (/admin/correos)
    Ruta alias: /admin/enviar-correo?id=N  (preselecciona a esa cuenta)
    Permiso: usuarios.editar

    Permite redactar un correo oficial (plantillas con la identidad de
    CHAMBEKI) a una o varias cuentas y consulta el historial de envios
    (tabla correos_log) con el empleado que lo emitio.
*/
require_once MAILER;
require_once DOCROOT . 'admin/includes/funcionesModeracion.php';
require_once DOCROOT . 'admin/includes/funcionesCorreos.php';

$plantillas = plantillasCorreo();

//valores del formulario (se conservan si hay error)
$idsMarcados = [];
$plantillaSel = 'aviso';
$asuntoVal = '';
$mensajeVal = '';

//preseleccion desde otras pantallas: ?id=N, ?destino=N o ?ids=1,2,3 ; ?plantilla=reporte
foreach (['id', 'destino'] as $clave)
{
    if (isset($_GET[$clave]))
    {
        $idsMarcados[] = (int) $_GET[$clave];
    }
}
if (!empty($_GET['ids']))
{
    $idsMarcados = array_merge($idsMarcados, array_map('intval', explode(',', (string) $_GET['ids'])));
}
if (isset($_GET['plantilla']) && isset($plantillas[strtolower((string) $_GET['plantilla'])]))
{
    $plantillaSel = strtolower((string) $_GET['plantilla']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $idsMarcados  = array_map('intval', (array) ($_POST['destinatarios'] ?? []));
    $plantillaSel = strtolower(limpiarEntrada($_POST['plantilla'] ?? ''));
    $asuntoVal    = limpiarEntrada($_POST['asunto'] ?? '');
    $mensajeVal   = limpiarEntrada($_POST['mensaje'] ?? '');

    if (!csrfAdminValido($_POST['csrf'] ?? ''))
    {
        guardarMensaje('error', 'El formulario expiró. Intenta de nuevo.');
        redirigir(URL_ADMIN . 'correos');
    }

    $destinatarios = destinatariosPorIds($idsMarcados);
    $error = validarCorreoOficial($plantillaSel, $asuntoVal, $mensajeVal, count($destinatarios));

    if ($error !== null)
    {
        guardarMensaje('error', $error);
    }
    else
    {
        $resultado = enviarCorreoOficial($admin, $destinatarios, $plantillaSel, $asuntoVal, $mensajeVal);

        if (empty($resultado['fallidos']))
        {
            guardarMensaje('exito', $resultado['enviados'] === 1
                ? 'Correo oficial enviado a ' . $destinatarios[0]['nombre'] . '.'
                : 'Se enviaron ' . $resultado['enviados'] . ' correos oficiales.');
        }
        else
        {
            $detalle = [];
            foreach ($resultado['fallidos'] as $f) { $detalle[] = $f['nombre'] . ' (' . $f['error'] . ')'; }
            guardarMensaje('error', 'Enviados: ' . $resultado['enviados'] . '. No se pudo enviar a: ' . implode('; ', $detalle));
        }

        redirigir(URL_ADMIN . 'correos');
    }
}

if (!isset($plantillas[$plantillaSel])) { $plantillaSel = 'aviso'; }

//precarga el texto de la plantilla cuando aun no se ha escrito nada
if ($asuntoVal === '' && $mensajeVal === '')
{
    $asuntoVal  = $plantillas[$plantillaSel]['asunto'];
    $mensajeVal = $plantillas[$plantillaSel]['mensaje'];
}

$destinatariosDisponibles = listarDestinatariosCorreo();

//si la preseleccion es solo clientes, el filtro inicial no debe ocultarla
$tipoInicial = '2';
foreach ($destinatariosDisponibles as $d)
{
    if (in_array((int) $d['id_usuario'], $idsMarcados, true) && (int) $d['rol'] === 3) { $tipoInicial = ''; break; }
}

//historial
$filtrosLog = [
    'q'         => mb_substr(limpiarEntrada($_GET['q'] ?? ''), 0, 100),
    'plantilla' => strtolower(limpiarEntrada($_GET['p'] ?? ''))
];
$historial = historialCorreos($filtrosLog, (int) ($_GET['pagina'] ?? 1));

function enlacePaginaCorreos($filtros, $pagina)
{
    $parametros = ['pagina' => $pagina];
    if ($filtros['q'] !== '')         { $parametros['q'] = $filtros['q']; }
    if ($filtros['plantilla'] !== '') { $parametros['p'] = $filtros['plantilla']; }

    return URL_ADMIN . 'correos?' . http_build_query($parametros) . '#historial';
}

$token = csrfAdminToken();
$mensajeFlash = obtenerMensaje();
?>

<div class="correo-encabezado">
    <div>
        <h1 class="admin-titulo">Correos</h1>
        <p class="admin-subtitulo">Escribe a freelancers o clientes con la imagen de CHAMBEKI. Todo envío queda registrado en el historial.</p>
    </div>
    <a href="#historial" class="admin-enlace-accion">Ver historial &darr;</a>
</div>

<?php if ($mensajeFlash !== null): ?>
    <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
<?php endif; ?>

<form action="<?php echo URL_ADMIN; ?>correos" method="POST" class="correo-form" id="formCorreo">
    <input type="hidden" name="csrf" value="<?php echo escaparSalida($token); ?>">

    <!-- 1. destinatarios -->
    <section class="admin-bloque correo-panel correo-destinatarios">
        <h2><span class="correo-paso">1</span> Destinatarios</h2>

        <div class="correo-filtros">
            <input type="search" id="buscarDestino" placeholder="Buscar por nombre o correo" aria-label="Buscar destinatario" maxlength="100">
            <select id="tipoDestino" aria-label="Tipo de cuenta">
                <option value="2" <?php echo $tipoInicial === '2' ? 'selected' : ''; ?>>Freelancers</option>
                <option value="3">Clientes</option>
                <option value="" <?php echo $tipoInicial === '' ? 'selected' : ''; ?>>Todos</option>
            </select>
        </div>

        <div class="correo-acciones-lista">
            <button type="button" id="marcarVisibles">Marcar visibles</button>
            <button type="button" id="limpiarMarcas">Limpiar</button>
            <span class="correo-contador" id="contadorDestinos">0 seleccionados</span>
        </div>

        <div class="correo-lista" id="listaDestinos">
            <?php foreach ($destinatariosDisponibles as $d): ?>
                <label class="correo-destino" data-rol="<?php echo (int) $d['rol']; ?>"
                       data-texto="<?php echo escaparSalida(mb_strtolower($d['nombre'] . ' ' . $d['correo'])); ?>">
                    <input type="checkbox" name="destinatarios[]" value="<?php echo (int) $d['id_usuario']; ?>"
                           <?php echo in_array((int) $d['id_usuario'], $idsMarcados, true) ? 'checked' : ''; ?>>
                    <span class="correo-avatar"><?php echo escaparSalida(mb_strtoupper(mb_substr($d['nombre'], 0, 1))); ?></span>
                    <span class="correo-destino-datos">
                        <strong><?php echo escaparSalida($d['nombre']); ?></strong>
                        <small><?php echo escaparSalida($d['correo']); ?></small>
                    </span>
                    <em class="correo-etiqueta <?php echo (int) $d['rol'] === 2 ? 'es-freelancer' : 'es-cliente'; ?>"><?php echo (int) $d['rol'] === 2 ? 'Freelancer' : 'Cliente'; ?><?php echo (int) $d['estatus'] === 2 ? ' · Suspendido' : ''; ?></em>
                </label>
            <?php endforeach; ?>
            <p class="correo-vacio" id="sinDestinos" hidden>No hay cuentas que coincidan.</p>
        </div>
        <small class="ayuda-campo">Máximo <?php echo CORREOS_MAX_DESTINATARIOS; ?> por envío. Cada persona recibe su propio correo.</small>
    </section>

    <!-- 2. redaccion -->
    <section class="admin-bloque correo-panel correo-redaccion">
        <h2><span class="correo-paso">2</span> Mensaje</h2>

        <div class="correo-plantillas" role="radiogroup" aria-label="Plantilla">
            <?php foreach ($plantillas as $clave => $datos): ?>
                <label class="correo-plantilla" style="--acento: <?php echo escaparSalida($datos['color']); ?>">
                    <input type="radio" name="plantilla" value="<?php echo escaparSalida($clave); ?>" <?php echo $clave === $plantillaSel ? 'checked' : ''; ?>>
                    <span class="correo-plantilla-titulo"><?php echo escaparSalida($datos['etiqueta']); ?></span>
                    <small><?php echo escaparSalida($datos['descripcion']); ?></small>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="campo-formulario">
            <label for="asuntoCorreo">Asunto</label>
            <input type="text" id="asuntoCorreo" name="asunto" maxlength="150" value="<?php echo escaparSalida($asuntoVal); ?>" required>
        </div>

        <div class="campo-formulario">
            <label for="mensajeCorreo">Mensaje</label>
            <textarea id="mensajeCorreo" name="mensaje" rows="9" maxlength="5000" required><?php echo escaparSalida($mensajeVal); ?></textarea>
            <small class="ayuda-campo">Usa <code>{nombre}</code> para insertar el nombre de cada destinatario y reemplaza lo que esté entre [corchetes].</small>
        </div>

        <button type="submit" class="boton-autenticacion" id="botonEnviarCorreo">Enviar correo</button>
    </section>

    <!-- 3. vista previa -->
    <aside class="admin-bloque correo-panel correo-vista">
        <h2><span class="correo-paso">3</span> Vista previa</h2>
        <div class="correo-mail">
            <div class="correo-mail-cabecera">CHAMBEKI<small>Comunicado oficial</small></div>
            <div class="correo-mail-cuerpo">
                <span class="correo-mail-etiqueta" id="prevEtiqueta"></span>
                <h3 id="prevAsunto"></h3>
                <p class="correo-mail-para">Para: <strong id="prevNombre">Nombre del destinatario</strong></p>
                <div class="correo-mail-mensaje" id="prevMensaje"></div>
                <p class="correo-mail-firma">Atentamente,<br><strong>Equipo de Soporte CHAMBEKI</strong></p>
            </div>
        </div>
    </aside>
</form>

<section class="admin-bloque" id="historial">
    <h2>Historial de envíos</h2>

    <form action="<?php echo URL_ADMIN; ?>correos#historial" method="GET" class="admin-filtros">
        <div class="campo-formulario admin-filtro-texto">
            <label for="logQ">Buscar</label>
            <input type="search" id="logQ" name="q" value="<?php echo escaparSalida($filtrosLog['q']); ?>" placeholder="Destinatario, correo, asunto o empleado" maxlength="100">
        </div>
        <div class="campo-formulario">
            <label for="logP">Plantilla</label>
            <select id="logP" name="p">
                <option value="">Todas</option>
                <?php foreach ($plantillas as $clave => $datos): ?>
                    <option value="<?php echo escaparSalida($clave); ?>" <?php echo $filtrosLog['plantilla'] === $clave ? 'selected' : ''; ?>><?php echo escaparSalida($datos['etiqueta']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filtros-botones">
            <button type="submit" class="boton-autenticacion admin-boton-accion">Filtrar</button>
        </div>
    </form>

    <p class="admin-resultados ayuda-campo"><?php echo (int) $historial['total']; ?> correo(s) registrado(s).</p>

    <div class="admin-tabla-contenedor">
        <table class="admin-tabla">
            <thead><tr><th>Fecha</th><th>Destinatario</th><th>Plantilla</th><th>Asunto</th><th>Emitido por</th></tr></thead>
            <tbody>
                <?php if (empty($historial['filas'])): ?>
                    <tr><td colspan="5">Aún no se han enviado correos oficiales.</td></tr>
                <?php endif; ?>
                <?php foreach ($historial['filas'] as $c): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($c['fecha_envio'])); ?></td>
                        <td>
                            <a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo (int) $c['id_destino']; ?>"><?php echo escaparSalida($c['destino_nombre']); ?></a><br>
                            <small><?php echo escaparSalida($c['destino_correo']); ?></small>
                        </td>
                        <td><span class="insignia-verificacion insignia-pendiente"><?php echo escaparSalida(etiquetaPlantillaCorreo($c['plantilla'])); ?></span></td>
                        <td>
                            <details class="correo-detalle">
                                <summary><?php echo escaparSalida($c['asunto']); ?></summary>
                                <div><?php echo nl2br(escaparSalida($c['mensaje'])); ?></div>
                            </details>
                        </td>
                        <td><small><?php echo escaparSalida($c['admin_nombre']); ?></small></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($historial['totalPaginas'] > 1): ?>
        <nav class="admin-paginacion" aria-label="Paginación del historial">
            <?php for ($i = 1; $i <= $historial['totalPaginas']; $i++): ?>
                <a href="<?php echo escaparSalida(enlacePaginaCorreos($filtrosLog, $i)); ?>" class="<?php echo $i === $historial['pagina'] ? 'activo' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>

<script>
(function () {
    var plantillas = <?php echo json_encode(array_map(function ($p) { return ['asunto' => $p['asunto'], 'mensaje' => $p['mensaje'], 'etiqueta' => $p['etiqueta'], 'color' => $p['color']]; }, $plantillas), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var radios = Array.prototype.slice.call(document.querySelectorAll('input[name="plantilla"]'));
    var asunto = document.getElementById('asuntoCorreo');
    var mensaje = document.getElementById('mensajeCorreo');
    var buscar = document.getElementById('buscarDestino');
    var tipo = document.getElementById('tipoDestino');
    var items = Array.prototype.slice.call(document.querySelectorAll('.correo-destino'));
    var vacio = document.getElementById('sinDestinos');
    var contador = document.getElementById('contadorDestinos');
    var formulario = document.getElementById('formCorreo');
    var vista = document.querySelector('.correo-vista');

    function plantillaMarcada() {
        var r = radios.filter(function (x) { return x.checked; })[0];
        return r ? r.value : 'aviso';
    }
    var plantillaActual = plantillaMarcada();

    function primerNombre() {
        var marcado = items.filter(function (i) { return i.querySelector('input').checked; })[0];
        return marcado ? marcado.querySelector('strong').textContent : 'Nombre del destinatario';
    }
    function vistaPrevia() {
        var p = plantillas[plantillaMarcada()];
        var nombre = primerNombre();
        vista.style.setProperty('--acento', p.color);
        document.getElementById('prevEtiqueta').textContent = p.etiqueta;
        document.getElementById('prevAsunto').textContent = asunto.value.split('{nombre}').join(nombre);
        document.getElementById('prevNombre').textContent = nombre;
        document.getElementById('prevMensaje').textContent = mensaje.value.split('{nombre}').join(nombre);
    }

    //si el texto sigue siendo el de la plantilla anterior se cambia sin preguntar
    radios.forEach(function (r) {
        r.addEventListener('change', function () {
            var anterior = plantillas[plantillaActual];
            var nueva = plantillas[plantillaMarcada()];
            var sinEditar = asunto.value === anterior.asunto && mensaje.value === anterior.mensaje;
            if (sinEditar || window.confirm('Se reemplazará el asunto y el mensaje por el texto de la nueva plantilla. ¿Continuar?')) {
                asunto.value = nueva.asunto;
                mensaje.value = nueva.mensaje;
                plantillaActual = plantillaMarcada();
            } else {
                radios.forEach(function (x) { x.checked = (x.value === plantillaActual); });
            }
            vistaPrevia();
        });
    });
    asunto.addEventListener('input', vistaPrevia);
    mensaje.addEventListener('input', vistaPrevia);

    function visible(item) {
        var okTipo = tipo.value === '' || item.getAttribute('data-rol') === tipo.value;
        var okTexto = item.getAttribute('data-texto').indexOf(buscar.value.trim().toLowerCase()) !== -1;
        return okTipo && okTexto;
    }
    function actualizar() {
        var hay = 0;
        items.forEach(function (item) {
            var v = visible(item);
            item.hidden = !v;
            if (v) { hay++; }
            item.classList.toggle('marcado', item.querySelector('input').checked);
        });
        vacio.hidden = hay > 0;
        var marcados = items.filter(function (i) { return i.querySelector('input').checked; }).length;
        contador.textContent = marcados + ' seleccionado' + (marcados === 1 ? '' : 's');
        contador.classList.toggle('activo', marcados > 0);
        vistaPrevia();
    }
    buscar.addEventListener('input', actualizar);
    tipo.addEventListener('change', actualizar);
    document.getElementById('listaDestinos').addEventListener('change', actualizar);
    document.getElementById('marcarVisibles').addEventListener('click', function () {
        items.forEach(function (i) { if (!i.hidden) { i.querySelector('input').checked = true; } });
        actualizar();
    });
    document.getElementById('limpiarMarcas').addEventListener('click', function () {
        items.forEach(function (i) { i.querySelector('input').checked = false; });
        actualizar();
    });

    formulario.addEventListener('submit', function (e) {
        var n = items.filter(function (i) { return i.querySelector('input').checked; }).length;
        if (n === 0) { e.preventDefault(); window.alert('Selecciona al menos un destinatario.'); return; }
        if (!window.confirm('Se enviará este correo a ' + n + ' destinatario(s). ¿Confirmas?')) { e.preventDefault(); return; }
        var boton = document.getElementById('botonEnviarCorreo');
        boton.disabled = true;
        boton.textContent = 'Enviando…';
    });

    actualizar();
})();
</script>

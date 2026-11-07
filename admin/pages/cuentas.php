<?php
/*
    BUSCADOR CENTRALIZADO DE CUENTAS (https://chambeki.com/admin/cuentas)
    Permiso: usuarios.ver (Super y Supervisor/Soporte).

    Busca por nombre, correo, telefono o ID entre clientes y freelancers, con
    filtros por tipo, estatus y verificacion. Los empleados no aparecen aqui.
*/

require_once DOCROOT . 'admin/includes/funcionesModeracion.php';

$filtros = [
    'q'          => limpiarEntrada($_GET['q'] ?? ''),
    'rol'        => (string) ($_GET['rol'] ?? ''),
    'estatus'    => (string) ($_GET['estatus'] ?? ''),
    'verificado' => (string) ($_GET['verificado'] ?? '')
];

$filtros['q'] = mb_substr($filtros['q'], 0, 100);

$resultado = buscarCuentas($filtros, (int) ($_GET['pagina'] ?? 1));
$hayFiltros = $filtros['q'] !== '' || $filtros['rol'] !== '' || $filtros['estatus'] !== '' || $filtros['verificado'] !== '';

//enlaces de paginacion: conservan los filtros activos
function enlacePaginaCuentas($filtros, $pagina)
{
    $parametros = array_filter($filtros, function ($valor) { return $valor !== ''; });
    $parametros['pagina'] = $pagina;

    return URL_ADMIN . 'cuentas?' . http_build_query($parametros);
}

$mensajeFlash = obtenerMensaje();
?>

<h1 class="admin-titulo">Cuentas</h1>
<p class="admin-subtitulo">Busca clientes y freelancers para inspeccionar su perfil, atender solicitudes y moderar la cuenta.</p>

<?php if ($mensajeFlash !== null): ?>
    <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
<?php endif; ?>

<form action="<?php echo URL_ADMIN; ?>cuentas" method="GET" class="admin-filtros">
    <div class="campo-formulario admin-filtro-texto">
        <label for="filtroQ">Buscar</label>
        <input type="search" id="filtroQ" name="q" value="<?php echo escaparSalida($filtros['q']); ?>" placeholder="Nombre, correo, telefono o ID" maxlength="100">
    </div>

    <div class="campo-formulario">
        <label for="filtroRol">Tipo</label>
        <select id="filtroRol" name="rol">
            <option value="">Todos</option>
            <option value="3" <?php echo $filtros['rol'] === '3' ? 'selected' : ''; ?>>Clientes</option>
            <option value="2" <?php echo $filtros['rol'] === '2' ? 'selected' : ''; ?>>Freelancers</option>
        </select>
    </div>

    <div class="campo-formulario">
        <label for="filtroEstatus">Estatus</label>
        <select id="filtroEstatus" name="estatus">
            <option value="">Todos</option>
            <option value="1" <?php echo $filtros['estatus'] === '1' ? 'selected' : ''; ?>>Activas</option>
            <option value="2" <?php echo $filtros['estatus'] === '2' ? 'selected' : ''; ?>>Suspendidas</option>
        </select>
    </div>

    <div class="campo-formulario">
        <label for="filtroVerificado">Verificacion</label>
        <select id="filtroVerificado" name="verificado">
            <option value="">Todas</option>
            <option value="si" <?php echo $filtros['verificado'] === 'si' ? 'selected' : ''; ?>>Freelancers verificados</option>
            <option value="no" <?php echo $filtros['verificado'] === 'no' ? 'selected' : ''; ?>>Freelancers sin verificar</option>
        </select>
    </div>

    <div class="admin-filtros-botones">
        <button type="submit" class="boton-autenticacion admin-boton-accion">Buscar</button>
        <?php if ($hayFiltros): ?>
            <a href="<?php echo URL_ADMIN; ?>cuentas" class="admin-enlace-accion">Limpiar</a>
        <?php endif; ?>
    </div>
</form>

<p class="admin-resultados">
    <?php echo number_format($resultado['total']); ?> cuenta(s) encontrada(s)<?php echo $hayFiltros ? ' con estos filtros' : ''; ?>.
</p>

<div class="admin-tabla-contenedor">
    <table class="admin-tabla">
        <thead>
            <tr>
                <th>ID</th>
                <th>Cuenta</th>
                <th>Tipo</th>
                <th>Estatus</th>
                <th>Verificacion</th>
                <th>Registro</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($resultado['filas'])): ?>
                <tr><td colspan="7">No se encontraron cuentas. Prueba con otro nombre, correo o telefono.</td></tr>
            <?php endif; ?>

            <?php foreach ($resultado['filas'] as $cuenta): ?>
                <?php $activa = (int) $cuenta['estatus'] === ESTATUS_ACTIVO; ?>
                <tr class="<?php echo $activa ? '' : 'admin-fila-suspendida'; ?>">
                    <td>#<?php echo (int) $cuenta['id_usuario']; ?></td>
                    <td>
                        <strong><?php echo escaparSalida($cuenta['nombre']); ?></strong><br>
                        <small><?php echo escaparSalida($cuenta['correo']); ?></small>
                    </td>
                    <td><?php echo escaparSalida(nombreRol($cuenta['rol'])); ?></td>
                    <td>
                        <span class="admin-estatus <?php echo $activa ? 'admin-estatus-activo' : 'admin-estatus-suspendido'; ?>">
                            <?php echo escaparSalida(nombreEstatus($cuenta['estatus'])); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ((int) $cuenta['rol'] !== ROL_FREELANCER): ?>
                            <small>No aplica</small>
                        <?php elseif ((int) $cuenta['verificado'] === 1): ?>
                            <span class="insignia-verificacion insignia-verificado">Verificado</span>
                        <?php else: ?>
                            <span class="insignia-verificacion insignia-pendiente">Sin verificar</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $cuenta['fecha_registro'] !== null ? escaparSalida(date('d/m/Y', strtotime($cuenta['fecha_registro']))) : '-'; ?></td>
                    <td><a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo (int) $cuenta['id_usuario']; ?>" class="admin-enlace-accion">Inspeccionar</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($resultado['paginas'] > 1): ?>
    <nav class="admin-paginacion" aria-label="Paginas de resultados">
        <?php if ($resultado['pagina'] > 1): ?>
            <a href="<?php echo escaparSalida(enlacePaginaCuentas($filtros, $resultado['pagina'] - 1)); ?>">&larr; Anterior</a>
        <?php endif; ?>

        <span>Pagina <?php echo $resultado['pagina']; ?> de <?php echo $resultado['paginas']; ?></span>

        <?php if ($resultado['pagina'] < $resultado['paginas']): ?>
            <a href="<?php echo escaparSalida(enlacePaginaCuentas($filtros, $resultado['pagina'] + 1)); ?>">Siguiente &rarr;</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

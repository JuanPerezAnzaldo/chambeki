<?php
/*
    HOME DEL ADMINISTRADOR - metricas globales (https://chambeki.com/admin/dashboard)
    Permiso: dashboard.ver (Super y Supervisor/Soporte).

    Las cifras se calculan en cada visita directamente de la BD, sin cache.
    "Usuarios registrados" cuenta clientes + freelancers (no empleados).
*/

require_once DOCROOT . 'admin/includes/funcionesModeracion.php';

$m = metricasDashboard();
$altasPorDia = registrosPorDia(30);
$recientes = ultimosRegistros(8);

//acceso anterior de este administrador (el mas reciente es el actual, por eso LIMIT 2)
$conexion = conexionBD();
$sentenciaAccesos = $conexion->prepare(
    "SELECT fecha_evento, ip_origen
     FROM bitacora_accesos
     WHERE correo = :correo AND evento = 'admin_login'
     ORDER BY id_bitacora DESC
     LIMIT 2"
);
$sentenciaAccesos->execute([':correo' => $admin['correo']]);
$accesos = $sentenciaAccesos->fetchAll();
$accesoAnterior = $accesos[1] ?? null;

//comparacion de nuevos ingresos: ultimos 7 dias contra los 7 anteriores
$diferencia7 = $m['nuevos_7'] - $m['nuevos_7_previos'];

if ($m['nuevos_7_previos'] > 0)
{
    $porcentaje7 = (int) round(($diferencia7 / $m['nuevos_7_previos']) * 100);
}
else
{
    $porcentaje7 = null;
}

$maximoDia = max(1, max($altasPorDia));
$puedeVerCuentas = adminTienePermiso($admin, 'usuarios.ver');
$mensajeFlash = obtenerMensaje();
?>

<div class="admin-encabezado-pagina">
    <div>
        <h1 class="admin-titulo">Hola, <?php echo escaparSalida($admin['nombre']); ?></h1>
        <p class="admin-subtitulo">
            <strong><?php echo escaparSalida(nombreRolAdmin($admin['id_rol_admin'])); ?></strong>.
            <?php if ($accesoAnterior !== null): ?>
                Tu acceso anterior fue el <?php echo escaparSalida(date('d/m/Y H:i', strtotime($accesoAnterior['fecha_evento']))); ?>
                desde la IP <?php echo escaparSalida($accesoAnterior['ip_origen']); ?>.
            <?php else: ?>
                Este es tu primer acceso registrado.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($puedeVerCuentas): ?>
        <form action="<?php echo URL_ADMIN; ?>cuentas" method="GET" class="admin-buscador-rapido">
            <input type="search" name="q" placeholder="Buscar cuenta por nombre, correo, telefono o ID" maxlength="100" aria-label="Buscar cuenta">
            <button type="submit" class="admin-boton-salir">Buscar</button>
        </form>
    <?php endif; ?>
</div>

<?php if ($mensajeFlash !== null): ?>
    <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
<?php endif; ?>

<!-- ===== Metricas principales ===== -->
<section class="admin-tarjetas">
    <article class="admin-tarjeta">
        <span class="admin-numero"><?php echo number_format($m['total']); ?></span>
        <span class="admin-etiqueta">Usuarios registrados</span>
        <small><?php echo number_format($m['clientes']); ?> clientes &middot; <?php echo number_format($m['freelancers']); ?> freelancers</small>
    </article>

    <article class="admin-tarjeta">
        <span class="admin-numero"><?php echo number_format($m['freelancers_activos']); ?></span>
        <span class="admin-etiqueta">Freelancers activos</span>
        <small>de <?php echo number_format($m['freelancers']); ?> registrados</small>
    </article>

    <article class="admin-tarjeta">
        <span class="admin-numero"><?php echo number_format($m['nuevos_7']); ?></span>
        <span class="admin-etiqueta">Nuevos ingresos (7 dias)</span>
        <small>
            <?php if ($porcentaje7 === null): ?>
                <?php echo $m['nuevos_7'] > 0 ? 'Sin ingresos la semana anterior' : 'Sin ingresos en las ultimas 2 semanas'; ?>
            <?php else: ?>
                <span class="<?php echo $diferencia7 >= 0 ? 'admin-tendencia-sube' : 'admin-tendencia-baja'; ?>">
                    <?php echo $diferencia7 >= 0 ? '&#9650;' : '&#9660;'; ?> <?php echo abs($porcentaje7); ?>%
                </span> vs. la semana anterior
            <?php endif; ?>
        </small>
    </article>

    <article class="admin-tarjeta">
        <span class="admin-numero"><?php echo number_format($m['nuevos_hoy']); ?></span>
        <span class="admin-etiqueta">Nuevos hoy</span>
        <small><?php echo number_format($m['nuevos_30']); ?> en los ultimos 30 dias</small>
    </article>
</section>

<!-- ===== Pendientes de atencion ===== -->
<section class="admin-tarjetas admin-tarjetas-secundarias">
    <article class="admin-tarjeta <?php echo $m['freelancers_sin_verificar'] > 0 ? 'admin-tarjeta-alerta' : ''; ?>">
        <span class="admin-numero"><?php echo number_format($m['freelancers_sin_verificar']); ?></span>
        <span class="admin-etiqueta">Freelancers por verificar</span>
        <?php if ($puedeVerCuentas): ?>
            <a href="<?php echo URL_ADMIN; ?>cuentas?rol=2&amp;estatus=1&amp;verificado=no" class="admin-enlace-accion">Ver lista &rarr;</a>
        <?php endif; ?>
    </article>

    <article class="admin-tarjeta <?php echo $m['docs_pendientes'] > 0 ? 'admin-tarjeta-alerta' : ''; ?>">
        <span class="admin-numero"><?php echo number_format($m['docs_pendientes']); ?></span>
        <span class="admin-etiqueta">Documentos por revisar</span>
        <small>Se revisan desde el perfil de cada freelancer</small>
    </article>

    <article class="admin-tarjeta">
        <span class="admin-numero"><?php echo number_format($m['suspendidas']); ?></span>
        <span class="admin-etiqueta">Cuentas suspendidas</span>
        <?php if ($puedeVerCuentas): ?>
            <a href="<?php echo URL_ADMIN; ?>cuentas?estatus=2" class="admin-enlace-accion">Ver lista &rarr;</a>
        <?php endif; ?>
    </article>

    <article class="admin-tarjeta">
        <span class="admin-numero"><?php echo number_format($m['suscripciones_activas']); ?></span>
        <span class="admin-etiqueta">Suscripciones activas</span>
    </article>

    <article class="admin-tarjeta">
        <span class="admin-numero"><?php echo number_format($m['citas_mes']); ?></span>
        <span class="admin-etiqueta">Citas este mes</span>
        <small><?php echo number_format($m['citas_mes_completadas']); ?> completadas</small>
    </article>
</section>

<!-- ===== Grafica de nuevos ingresos ===== -->
<section class="admin-bloque">
    <h2>Nuevos ingresos por dia (ultimos 30 dias)</h2>

    <div class="admin-grafica" role="img" aria-label="Grafica de barras con los registros nuevos de cada uno de los ultimos 30 dias">
        <?php foreach ($altasPorDia as $dia => $total): ?>
            <?php $alto = $total > 0 ? max(6, (int) round(($total / $maximoDia) * 100)) : 2; ?>
            <div class="admin-grafica-columna" title="<?php echo escaparSalida(date('d/m', strtotime($dia)) . ': ' . $total . ' registro(s)'); ?>">
                <span class="admin-grafica-valor"><?php echo $total > 0 ? $total : ''; ?></span>
                <div class="admin-grafica-barra <?php echo $total === 0 ? 'vacia' : ''; ?>" style="height: <?php echo $alto; ?>%"></div>
                <span class="admin-grafica-dia"><?php echo date('d', strtotime($dia)); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <small class="admin-nota">El numero bajo cada barra es el dia del mes. Cuenta clientes y freelancers.</small>
</section>

<!-- ===== Ultimos registros ===== -->
<section class="admin-bloque">
    <h2>Ultimas cuentas registradas</h2>

    <div class="admin-tabla-contenedor admin-tabla-sin-borde">
        <table class="admin-tabla">
            <thead>
                <tr><th>Cuenta</th><th>Tipo</th><th>Estatus</th><th>Registro</th></tr>
            </thead>
            <tbody>
                <?php if (empty($recientes)): ?>
                    <tr><td colspan="4">Todavia no hay cuentas registradas.</td></tr>
                <?php endif; ?>

                <?php foreach ($recientes as $cuenta): ?>
                    <tr>
                        <td>
                            <?php if ($puedeVerCuentas): ?>
                                <a href="<?php echo URL_ADMIN; ?>cuenta?id=<?php echo (int) $cuenta['id_usuario']; ?>" class="admin-enlace-accion"><strong><?php echo escaparSalida($cuenta['nombre']); ?></strong></a>
                            <?php else: ?>
                                <strong><?php echo escaparSalida($cuenta['nombre']); ?></strong>
                            <?php endif; ?>
                            <br><small><?php echo escaparSalida($cuenta['correo']); ?></small>
                        </td>
                        <td><?php echo escaparSalida(nombreRol($cuenta['rol'])); ?></td>
                        <td>
                            <span class="admin-estatus <?php echo (int) $cuenta['estatus'] === ESTATUS_ACTIVO ? 'admin-estatus-activo' : 'admin-estatus-suspendido'; ?>">
                                <?php echo escaparSalida(nombreEstatus($cuenta['estatus'])); ?>
                            </span>
                        </td>
                        <td><?php echo escaparSalida(date('d/m/Y H:i', strtotime($cuenta['fecha_registro']))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-bloque">
    <h2>Lo que puedes hacer con tu cuenta</h2>
    <ul class="admin-lista-permisos">
        <?php foreach ($admin['permisos'] as $permiso): ?>
            <li><?php echo escaparSalida(etiquetaPermiso($permiso)); ?></li>
        <?php endforeach; ?>
    </ul>
</section>

<?php
/*
    Cabecera del panel de administracion.
    Variables que recibe de admin/index.php:
      $layoutAdmin  'login' (pantalla sin menu) o 'panel' (topbar + menu)
      $tituloPagina titulo de la pestana
      $admin        datos frescos del administrador (null en el login)
      $rutaActual   para resaltar el item del menu
*/
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo escaparSalida($tituloPagina); ?> | <?php echo SITENAME; ?> Admin</title>

    <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>global.css?v=1">
    <link rel="stylesheet" href="<?php echo CSS_RUTA; ?>autenticacion.css?v=1">
    <link rel="stylesheet" href="<?php echo URL_ADMIN; ?>assets/css/admin.css?v=1">

    <script>
        //mismo modo claro/oscuro que el sitio (misma llave de localStorage)
        try { document.documentElement.setAttribute('data-theme', localStorage.getItem('chambeki-tema') || 'light'); } catch (e) {}
    </script>
</head>
<body class="admin-<?php echo $layoutAdmin === 'login' ? 'login' : 'panel'; ?>">

<?php if ($layoutAdmin === 'panel' && $admin !== null): ?>

    <header class="admin-topbar">
        <a href="<?php echo URL_ADMIN; ?>dashboard" class="admin-marca"><?php echo SITENAME; ?> <span>Admin</span></a>

        <div class="admin-usuario">
            <div class="admin-usuario-datos">
                <strong><?php echo escaparSalida($admin['nombre']); ?></strong>
                <span class="admin-insignia-rol admin-rol-<?php echo (int) $admin['id_rol_admin']; ?>">
                    <?php echo escaparSalida(nombreRolAdmin($admin['id_rol_admin'])); ?>
                </span>
            </div>

            <a href="<?php echo URL_ADMIN; ?>cambiar-contrasena" class="admin-enlace-cuenta">Mi contrasena</a>

            <form action="<?php echo URL_ADMIN; ?>logout" method="POST">
                <input type="hidden" name="csrf" value="<?php echo escaparSalida(csrfAdminToken()); ?>">
                <button type="submit" class="admin-boton-salir">Cerrar sesion</button>
            </form>
        </div>
    </header>

    <div class="admin-cuerpo">
        <nav class="admin-menu" aria-label="Menu del panel">
            <?php foreach (rutasAdmin() as $claveRuta => $datosRuta): ?>
                <?php
                    //el menu solo muestra lo que el rol puede abrir
                    if (empty($datosRuta['menu']) || !adminTienePermiso($admin, $datosRuta['permiso'] ?? null))
                    {
                        continue;
                    }
                ?>
                <a href="<?php echo URL_ADMIN . $claveRuta; ?>" class="admin-menu-item <?php echo ($claveRuta === $rutaActual || (rutasAdmin()[$rutaActual]['padre'] ?? '') === $claveRuta) ? 'activo' : ''; ?>">
                    <?php echo escaparSalida($datosRuta['menu']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <main class="admin-contenido">

<?php else: ?>

    <main class="admin-login-contenedor">

<?php endif; ?>

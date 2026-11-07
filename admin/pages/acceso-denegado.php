<?php
/*
    Pantalla 403. La muestra el middleware (exigirPermisoAdmin) cuando el
    administrador esta autenticado pero su rol no incluye el permiso.
    Recibe: $permisoDenegado y $admin.
*/
?>

<div class="admin-denegado">
    <span class="admin-denegado-codigo">403</span>
    <h1 class="admin-titulo">No tienes permiso para esta seccion</h1>
    <p class="admin-subtitulo">
        Tu rol (<strong><?php echo escaparSalida(nombreRolAdmin($admin['id_rol_admin'])); ?></strong>)
        no incluye: <em><?php echo escaparSalida(etiquetaPermiso($permisoDenegado)); ?></em>.
        El intento quedo registrado en la bitacora.
    </p>
    <a href="<?php echo URL_ADMIN; ?>dashboard" class="boton-autenticacion admin-boton-volver">Volver al resumen</a>
</div>

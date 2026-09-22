<?php
/*
    PROCESO: Perfil del usuario
    Requerimientos: RSIS-01, RS-01

    Solo el usuario que inicio sesion puede ver sus propios datos:
    todo se consulta con el id_usuario que vive en la sesion de PHP,
    nunca con un id que venga de la URL o del formulario.
*/

if (!hayUsuarioEnSesion())
{
    guardarMensaje('error', 'Inicia sesion para ver tu perfil.');
    redirigir(URL_BASE . '?accion=login');
}

$usuario = obtenerUsuarioPorId($_SESSION['id_usuario']);

//si el usuario ya no existe en la BD (borrado, etc.) se cierra la sesion
if ($usuario === null)
{
    session_unset();
    session_destroy();
    session_start();

    guardarMensaje('error', 'No encontramos tu cuenta. Inicia sesion de nuevo.');
    redirigir(URL_BASE . '?accion=login');
}

$mensajeFlash = obtenerMensaje();
?>

<section class="seccion-perfil">
    <div class="contenedor-perfil">

        <?php if ($mensajeFlash !== null): ?>
            <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
        <?php endif; ?>

        <div class="tarjeta-perfil">

            <div class="cabecera-perfil">
                <?php if (!empty($usuario['foto_perfil_url'])): ?>
                    <img src="<?php echo URL_BASE . escaparSalida($usuario['foto_perfil_url']); ?>" alt="Foto de perfil de <?php echo escaparSalida($usuario['nombre']); ?>" class="foto-perfil-grande">
                <?php else: ?>
                    <div class="foto-perfil-grande foto-perfil-iniciales" aria-hidden="true">
                        <?php echo escaparSalida(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1))); ?>
                    </div>
                <?php endif; ?>

                <div class="identidad-perfil">
                    <h1 class="nombre-perfil"><?php echo escaparSalida($usuario['nombre']); ?></h1>
                    <span class="etiqueta-rol-perfil"><?php echo escaparSalida(nombreRol($usuario['rol'])); ?></span>
                </div>
            </div>

            <dl class="lista-datos-perfil">
                <div class="dato-perfil">
                    <dt>Correo electronico</dt>
                    <dd><?php echo escaparSalida($usuario['correo']); ?></dd>
                </div>

                <div class="dato-perfil">
                    <dt>Telefono</dt>
                    <dd><?php echo ($usuario['telefono'] !== null && $usuario['telefono'] !== '') ? escaparSalida($usuario['telefono']) : 'No registrado'; ?></dd>
                </div>

                <div class="dato-perfil">
                    <dt>Tipo de cuenta</dt>
                    <dd><?php echo escaparSalida(nombreTipoCuenta($usuario['tipo_cuenta'])); ?></dd>
                </div>

                <div class="dato-perfil">
                    <dt>Estatus de la cuenta</dt>
                    <dd><?php echo escaparSalida(nombreEstatus($usuario['estatus'])); ?></dd>
                </div>

                <div class="dato-perfil">
                    <dt>Miembro desde</dt>
                    <dd><?php echo escaparSalida(date('d/m/Y', strtotime($usuario['fecha_registro']))); ?></dd>
                </div>
            </dl>

            <div class="acciones-perfil">
                <a href="/?accion=recuperar" class="boton-autenticacion boton-perfil-enlace">Cambiar mi contrasena</a>
                <a href="<?php echo URL_BASE; ?>?accion=cerrar_sesion" class="boton-enlace">Cerrar sesion</a>
            </div>

        </div>

    </div>
</section>

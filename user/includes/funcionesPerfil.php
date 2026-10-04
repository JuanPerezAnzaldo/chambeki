
Funcionesperfil · PHP
<?php
/*
    Funciones de la pantalla de configuracion de perfil.
    Se incluye desde user/pages/configuracion.php con require_once.
    Depende de functions.php (conexionBD, guardarFotoPerfil, etc.).
*/

//=============================================================
// CSRF (los formularios de configuracion cambian datos sensibles)
//=============================================================

function tokenCsrf()
{
    if (empty($_SESSION['token_csrf']))
    {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['token_csrf'];
}

function validarCsrf($tokenRecibido)
{
    return isset($_SESSION['token_csrf'])
        && is_string($tokenRecibido)
        && hash_equals($_SESSION['token_csrf'], $tokenRecibido);
}

//=============================================================
// Datos generales del usuario
//=============================================================

function validarDatosGenerales($datos)
{
    $errores = [];

    if (mb_strlen($datos['nombre']) < 3 || mb_strlen($datos['nombre']) > 100)
    {
        $errores['nombre'] = 'Escribe tu nombre completo (entre 3 y 100 caracteres).';
    }

    $telefonoLimpio = preg_replace('/[^0-9]/', '', $datos['telefono']);

    if (strlen($telefonoLimpio) !== 10)
    {
        $errores['telefono'] = 'El telefono debe tener 10 digitos.';
    }

    return $errores;
}

function actualizarDatosGenerales($idUsuario, $datos)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'UPDATE usuarios
         SET nombre = :nombre, telefono = :telefono, tipo_cuenta = :tipo, fecha_actualizacion = NOW()
         WHERE id_usuario = :id'
    );

    $sentencia->execute([
        ':nombre'   => $datos['nombre'],
        ':telefono' => preg_replace('/[^0-9]/', '', $datos['telefono']),
        ':tipo'     => $datos['tipo_cuenta'] === 'empresarial' ? 2 : 1,
        ':id'       => $idUsuario
    ]);
}

function actualizarFotoUsuario($idUsuario, $rutaNueva)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'UPDATE usuarios SET foto_perfil_url = :foto, fecha_actualizacion = NOW() WHERE id_usuario = :id'
    );

    $sentencia->execute([':foto' => $rutaNueva, ':id' => $idUsuario]);
}

//=============================================================
// Contrasena (pide la actual antes de cambiarla)
//=============================================================

function obtenerHashContrasena($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT password_hash FROM usuarios WHERE id_usuario = :id LIMIT 1');
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    return $fila === false ? null : $fila['password_hash'];
}

//=============================================================
// Perfil profesional (solo rol Freelancer)
//
// Requiere esta tabla (ejecutala una vez en tu BD):
//
//   CREATE TABLE perfiles_freelancer (
//       id_usuario        INT NOT NULL PRIMARY KEY,
//       titulo            VARCHAR(80)  NOT NULL DEFAULT '',
//       descripcion       VARCHAR(600) NOT NULL DEFAULT '',
//       anios_experiencia TINYINT UNSIGNED NOT NULL DEFAULT 0,
//       zona_cobertura    VARCHAR(120) NOT NULL DEFAULT '',
//       tarifa_desde      DECIMAL(10,2) NULL,
//       disponible        TINYINT(1) NOT NULL DEFAULT 1,
//       fecha_actualizacion DATETIME NULL,
//       FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
//   );
//=============================================================

function obtenerPerfilFreelancer($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT titulo, descripcion, anios_experiencia, zona_cobertura, tarifa_desde, disponible
         FROM perfiles_freelancer
         WHERE id_usuario = :id
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $perfil = $sentencia->fetch();

    if ($perfil === false)
    {
        return [
            'titulo'            => '',
            'descripcion'       => '',
            'anios_experiencia' => 0,
            'zona_cobertura'    => '',
            'tarifa_desde'      => null,
            'disponible'        => 1
        ];
    }

    return $perfil;
}

function validarPerfilFreelancer($datos)
{
    $errores = [];

    if (mb_strlen($datos['titulo']) < 3 || mb_strlen($datos['titulo']) > 80)
    {
        $errores['titulo'] = 'Escribe tu especialidad (entre 3 y 80 caracteres).';
    }

    if (mb_strlen($datos['descripcion']) > 600)
    {
        $errores['descripcion'] = 'La descripcion no puede pasar de 600 caracteres.';
    }

    if ($datos['anios_experiencia'] < 0 || $datos['anios_experiencia'] > 60)
    {
        $errores['anios_experiencia'] = 'Los anios de experiencia deben estar entre 0 y 60.';
    }

    if (mb_strlen($datos['zona_cobertura']) > 120)
    {
        $errores['zona_cobertura'] = 'La zona de cobertura es demasiado larga.';
    }

    if ($datos['tarifa_desde'] !== null && $datos['tarifa_desde'] < 0)
    {
        $errores['tarifa_desde'] = 'La tarifa no puede ser negativa.';
    }

    return $errores;
}

function guardarPerfilFreelancer($idUsuario, $datos)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'INSERT INTO perfiles_freelancer
            (id_usuario, titulo, descripcion, anios_experiencia, zona_cobertura, tarifa_desde, disponible, fecha_actualizacion)
         VALUES
            (:id, :titulo, :descripcion, :anios, :zona, :tarifa, :disponible, NOW())
         ON DUPLICATE KEY UPDATE
            titulo = VALUES(titulo),
            descripcion = VALUES(descripcion),
            anios_experiencia = VALUES(anios_experiencia),
            zona_cobertura = VALUES(zona_cobertura),
            tarifa_desde = VALUES(tarifa_desde),
            disponible = VALUES(disponible),
            fecha_actualizacion = NOW()'
    );

    $sentencia->execute([
        ':id'         => $idUsuario,
        ':titulo'     => $datos['titulo'],
        ':descripcion'=> $datos['descripcion'],
        ':anios'      => $datos['anios_experiencia'],
        ':zona'       => $datos['zona_cobertura'],
        ':tarifa'     => $datos['tarifa_desde'],
        ':disponible' => $datos['disponible'] ? 1 : 0
    ]);
}
?>


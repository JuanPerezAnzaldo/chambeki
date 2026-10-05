<?php
/*
    Funciones de la pantalla de configuracion de perfil.
    Se incluye desde user/pages/configuracion.php con require_once.
    Depende de functions.php (conexionBD, guardarFotoPerfil, etc.).

    Usa las tablas del modelo ER del Avance 1:
      usuarios, perfil_freelancer, cobertura_freelancer, cat_modalidad
    Todas las consultas son preparadas (RS-02).
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
// Filtro de seguridad lexica (RF-06, RLN-03)
// Lista inicial; cuando haya panel de admin conviene moverla a la BD.
//=============================================================

function normalizarTexto($texto)
{
    $texto = mb_strtolower((string) $texto, 'UTF-8');

    return strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n'
    ]);
}

function palabrasProhibidas()
{
    return [
        'drogas', 'narcotico', 'narcoticos', 'sicario', 'sicarios',
        'armas de fuego', 'venta de armas', 'documentos falsos', 'falsificacion',
        'lavado de dinero', 'prostitucion', 'trata de personas', 'secuestro'
    ];
}

//devuelve la primera palabra prohibida encontrada o null si el texto esta limpio
function buscarPalabraProhibida($texto)
{
    $normalizado = normalizarTexto($texto);

    foreach (palabrasProhibidas() as $palabra)
    {
        if (preg_match('/\b' . preg_quote($palabra, '/') . '\b/u', $normalizado))
        {
            return $palabra;
        }
    }

    return null;
}

//=============================================================
// Perfil profesional (tabla perfil_freelancer, solo rol Freelancer)
//=============================================================

function obtenerPerfilFreelancer($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT descripcion, identificador_fiscal, perfil_verificado, acepto_efectivo, acepto_tarjeta
         FROM perfil_freelancer
         WHERE id_usuario = :id
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $perfil = $sentencia->fetch();

    if ($perfil === false)
    {
        return [
            'descripcion'          => '',
            'identificador_fiscal' => '',
            'perfil_verificado'    => 0,
            'acepto_efectivo'      => 1,
            'acepto_tarjeta'       => 0
        ];
    }

    $perfil['descripcion'] = (string) $perfil['descripcion'];
    $perfil['identificador_fiscal'] = (string) $perfil['identificador_fiscal'];

    return $perfil;
}

function validarPerfilFreelancer($datos)
{
    $errores = [];
    $largo = mb_strlen($datos['descripcion']);

    if ($largo < 20 || $largo > 600)
    {
        $errores['descripcion'] = 'Describe tu servicio en 20 a 600 caracteres.';
    }
    elseif (($palabra = buscarPalabraProhibida($datos['descripcion'])) !== null)
    {
        $errores['descripcion'] = 'Tu descripcion contiene contenido no permitido ("' . $palabra . '"). Quitalo para publicar tu perfil.';
    }

    if ($datos['identificador_fiscal'] !== '' && !preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $datos['identificador_fiscal']))
    {
        $errores['identificador_fiscal'] = 'El RFC no tiene un formato valido (12 o 13 caracteres).';
    }

    if (!$datos['acepto_efectivo'] && !$datos['acepto_tarjeta'])
    {
        $errores['cobro'] = 'Elige al menos una forma de cobro.';
    }

    return $errores;
}

function guardarPerfilFreelancer($idUsuario, $datos)
{
    $conexion = conexionBD();

    $existe = $conexion->prepare('SELECT id_perfil FROM perfil_freelancer WHERE id_usuario = :id LIMIT 1');
    $existe->execute([':id' => $idUsuario]);

    $parametros = [
        ':descripcion' => $datos['descripcion'],
        ':rfc'         => $datos['identificador_fiscal'] !== '' ? $datos['identificador_fiscal'] : null,
        ':efectivo'    => $datos['acepto_efectivo'] ? 1 : 0,
        ':tarjeta'     => $datos['acepto_tarjeta'] ? 1 : 0,
        ':id'          => $idUsuario
    ];

    if ($existe->fetch() !== false)
    {
        $sentencia = $conexion->prepare(
            'UPDATE perfil_freelancer
             SET descripcion = :descripcion, identificador_fiscal = :rfc,
                 acepto_efectivo = :efectivo, acepto_tarjeta = :tarjeta
             WHERE id_usuario = :id'
        );
    }
    else
    {
        $sentencia = $conexion->prepare(
            'INSERT INTO perfil_freelancer (id_usuario, descripcion, identificador_fiscal, acepto_efectivo, acepto_tarjeta)
             VALUES (:id, :descripcion, :rfc, :efectivo, :tarjeta)'
        );
    }

    $sentencia->execute($parametros);
}

//=============================================================
// Cobertura (tabla cobertura_freelancer, RUS-02, RSIS-02)
// latitud_base / longitud_base no se tocan aqui: los llena el
// mapa interactivo (Avance 4) y se conservan al guardar.
//=============================================================

function obtenerModalidades()
{
    $conexion = conexionBD();

    return $conexion->query('SELECT id_modalidad, nombre FROM cat_modalidad ORDER BY id_modalidad')->fetchAll();
}

function obtenerCoberturaFreelancer($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_modalidad, direccion_texto, ciudad_limite, radio_cobertura_km
         FROM cobertura_freelancer
         WHERE id_usuario = :id
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $cobertura = $sentencia->fetch();

    if ($cobertura === false)
    {
        return ['id_modalidad' => 0, 'direccion_texto' => '', 'ciudad_limite' => '', 'radio_cobertura_km' => 10];
    }

    $cobertura['direccion_texto'] = (string) $cobertura['direccion_texto'];
    $cobertura['ciudad_limite'] = (string) $cobertura['ciudad_limite'];

    return $cobertura;
}

function validarCobertura($datos, $modalidades)
{
    $errores = [];
    $idsValidos = array_map('intval', array_column($modalidades, 'id_modalidad'));

    if (!in_array((int) $datos['id_modalidad'], $idsValidos, true))
    {
        $errores['id_modalidad'] = 'Elige como ofreces tu servicio.';
    }

    if (mb_strlen($datos['ciudad_limite']) < 2 || mb_strlen($datos['ciudad_limite']) > 100)
    {
        $errores['ciudad_limite'] = 'Escribe la ciudad donde trabajas (2 a 100 caracteres).';
    }

    if (mb_strlen($datos['direccion_texto']) > 255)
    {
        $errores['direccion_texto'] = 'La direccion no puede pasar de 255 caracteres.';
    }

    if ($datos['radio_cobertura_km'] < 1 || $datos['radio_cobertura_km'] > 100)
    {
        $errores['radio_cobertura_km'] = 'El radio debe estar entre 1 y 100 km.';
    }

    return $errores;
}

function guardarCobertura($idUsuario, $datos)
{
    $conexion = conexionBD();

    $existe = $conexion->prepare('SELECT id_cobertura FROM cobertura_freelancer WHERE id_usuario = :id LIMIT 1');
    $existe->execute([':id' => $idUsuario]);

    $parametros = [
        ':modalidad' => (int) $datos['id_modalidad'],
        ':direccion' => $datos['direccion_texto'],
        ':ciudad'    => $datos['ciudad_limite'],
        ':radio'     => (int) $datos['radio_cobertura_km'],
        ':id'        => $idUsuario
    ];

    if ($existe->fetch() !== false)
    {
        $sentencia = $conexion->prepare(
            'UPDATE cobertura_freelancer
             SET id_modalidad = :modalidad, direccion_texto = :direccion,
                 ciudad_limite = :ciudad, radio_cobertura_km = :radio
             WHERE id_usuario = :id'
        );
    }
    else
    {
        $sentencia = $conexion->prepare(
            'INSERT INTO cobertura_freelancer (id_usuario, id_modalidad, direccion_texto, ciudad_limite, radio_cobertura_km)
             VALUES (:id, :modalidad, :direccion, :ciudad, :radio)'
        );
    }

    $sentencia->execute($parametros);
}
?>

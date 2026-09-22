<?php
/*
    Funciones compartidas de CHAMBEKI.
    Aqui viven las validaciones de servidor (RS-05), el manejo de
    codigos temporales (RUS-01, RSIS-05), el hashing de contrasenas
    (RS-01) y las consultas preparadas contra la BD (RS-02).
*/

//=============================================================
// Catalogos (mismos valores que usa la tabla `usuarios` de la BD)
//=============================================================

define('ROL_ADMIN',      1);
define('ROL_FREELANCER', 2);
define('ROL_CLIENTE',    3);

define('ESTATUS_ACTIVO',     1);
define('ESTATUS_SUSPENDIDO', 2);

//columna `tipo` de codigos_verificacion
define('TIPO_CODIGO_REGISTRO',     1);
define('TIPO_CODIGO_RECUPERACION', 2);
define('TIPO_CODIGO_LOGIN',        3);

//=============================================================
// Utilidades generales
//=============================================================

function limpiarEntrada($texto)
{
    return trim((string) $texto);
}

function escaparSalida($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function redirigir($destino)
{
    header('Location: ' . $destino);
    exit;
}

function guardarMensaje($tipo, $texto)
{
    $_SESSION['mensaje_flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function obtenerMensaje()
{
    if (!isset($_SESSION['mensaje_flash']))
    {
        return null;
    }

    $mensaje = $_SESSION['mensaje_flash'];
    unset($_SESSION['mensaje_flash']);

    return $mensaje;
}

//deja un rastro de los eventos importantes de la cuenta (RLN-07)
function registrarEnBitacora($correo, $evento)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'INSERT INTO bitacora_accesos (correo, evento, ip_origen, fecha_evento)
         VALUES (:correo, :evento, :ip, NOW())'
    );

    $sentencia->execute([
        ':correo' => $correo,
        ':evento' => $evento,
        ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}

//=============================================================
// Validaciones del lado del servidor (RS-05)
//=============================================================

function validarDatosRegistro($datos)
{
    $errores = [];

    if (mb_strlen($datos['nombre']) < 3)
    {
        $errores['nombre'] = 'Escribe tu nombre completo (minimo 3 caracteres).';
    }

    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL))
    {
        $errores['correo'] = 'El correo no tiene un formato valido.';
    }

    //telefono de 10 digitos, se aceptan espacios o guiones al capturar
    $telefonoLimpio = preg_replace('/[^0-9]/', '', $datos['telefono']);

    if (strlen($telefonoLimpio) !== 10)
    {
        $errores['telefono'] = 'El telefono debe tener 10 digitos.';
    }

    $errorContrasena = validarContrasena($datos['contrasena'], $datos['contrasena_confirmar']);

    if ($errorContrasena !== null)
    {
        $errores['contrasena'] = $errorContrasena;
    }

    //RLN-02: la casilla de terminos es obligatoria
    if (empty($datos['terminos']))
    {
        $errores['terminos'] = 'Debes aceptar los terminos y condiciones para continuar.';
    }

    return $errores;
}

function validarContrasena($contrasena, $confirmacion)
{
    if (strlen($contrasena) < 8)
    {
        return 'La contrasena debe tener al menos 8 caracteres.';
    }

    if (!preg_match('/[A-Za-z]/', $contrasena) || !preg_match('/[0-9]/', $contrasena))
    {
        return 'La contrasena debe combinar letras y numeros.';
    }

    if ($contrasena !== $confirmacion)
    {
        return 'Las contrasenas no coinciden.';
    }

    return null;
}

//=============================================================
// Consultas de usuarios (RF-04)
//=============================================================

function correoYaRegistrado($correo)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE correo = :correo LIMIT 1');
    $sentencia->execute([':correo' => $correo]);

    return $sentencia->fetch() !== false;
}

function obtenerUsuarioPorCorreo($correo)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_usuario, nombre, correo, password_hash, rol, estatus, foto_perfil_url
         FROM usuarios
         WHERE correo = :correo
         LIMIT 1'
    );
    $sentencia->execute([':correo' => $correo]);

    $usuario = $sentencia->fetch();

    return $usuario === false ? null : $usuario;
}

//RSIS-01: se usa para recargar los datos del dueno de la sesion (perfil.php)
function obtenerUsuarioPorId($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_usuario, nombre, correo, telefono, rol, tipo_cuenta, foto_perfil_url, estatus, fecha_registro
         FROM usuarios
         WHERE id_usuario = :id
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $usuario = $sentencia->fetch();

    return $usuario === false ? null : $usuario;
}

//=============================================================
// Etiquetas legibles de los catalogos (header y perfil)
//=============================================================

function nombreRol($rol)
{
    switch ((int) $rol)
    {
        case ROL_ADMIN:      return 'Administrador';
        case ROL_FREELANCER: return 'Freelancer';
        default:             return 'Cliente';
    }
}

function nombreTipoCuenta($tipoCuenta)
{
    return (int) $tipoCuenta === 2 ? 'Empresarial' : 'Personal';
}

function nombreEstatus($estatus)
{
    return (int) $estatus === ESTATUS_ACTIVO ? 'Activa' : 'Suspendida';
}

//guarda el registro ya verificado: fecha actual + contrasena encriptada
function crearUsuario($datos)
{
    $conexion = conexionBD();

    /*
        RS-01: hashing fuerte, nunca texto plano.
        El registro cifra la contrasena desde el primer paso, por eso
        aqui se acepta el hash ya generado.
    */
    $contrasenaCifrada = isset($datos['contrasena_hash'])
        ? $datos['contrasena_hash']
        : password_hash($datos['contrasena'], PASSWORD_DEFAULT);

    //todo registro nuevo entra como Cliente; se vuelve Freelancer hasta que
    //complete el proceso de "registro de freelancer" (Avance 2, seccion II.D)
    $sentencia = $conexion->prepare(
        'INSERT INTO usuarios
            (nombre, correo, password_hash, rol, telefono, foto_perfil_url, tipo_cuenta, acepto_terminos, fecha_registro)
         VALUES
            (:nombre, :correo, :contrasena, :rol, :telefono, :foto, :tipo, 1, NOW())'
    );

    $sentencia->execute([
        ':nombre'     => $datos['nombre'],
        ':correo'     => $datos['correo'],
        ':contrasena' => $contrasenaCifrada,
        ':rol'        => ROL_CLIENTE,
        ':telefono'   => $datos['telefono'],
        ':foto'       => $datos['foto_perfil'],
        ':tipo'       => $datos['tipo_cuenta'] === 'empresarial' ? 2 : 1
    ]);

    return (int) $conexion->lastInsertId();
}

function actualizarContrasena($correo, $contrasenaNueva)
{
    $conexion = conexionBD();

    $contrasenaCifrada = password_hash($contrasenaNueva, PASSWORD_DEFAULT);

    $sentencia = $conexion->prepare(
        'UPDATE usuarios
         SET password_hash = :contrasena, fecha_actualizacion = NOW()
         WHERE correo = :correo'
    );

    $sentencia->execute([
        ':contrasena' => $contrasenaCifrada,
        ':correo'     => $correo
    ]);

    return $sentencia->rowCount() > 0;
}

//=============================================================
// Codigos temporales (RUS-01, RSIS-05)
//=============================================================

define('MINUTOS_VIGENCIA_CODIGO', 15);
define('MAXIMO_INTENTOS_CODIGO', 5);

/*
    En el codigo se llama a estas funciones con 'registro' o 'recuperacion'
    porque es mas facil de leer; aqui se traduce al entero que en verdad
    guarda la columna `tipo` (1=Registro, 2=Recuperacion).
*/
function tipoCodigoATinyint($tipo)
{
    if ($tipo === 'recuperacion')
    {
        return TIPO_CODIGO_RECUPERACION;
    }

    if ($tipo === 'login')
    {
        return TIPO_CODIGO_LOGIN;
    }

    return TIPO_CODIGO_REGISTRO;
}

function generarCodigoVerificacion($correo, $tipo)
{
    $conexion = conexionBD();
    $tipoBD = tipoCodigoATinyint($tipo);

    //cualquier codigo anterior del mismo tipo deja de servir
    $sentenciaInvalidar = $conexion->prepare(
        'UPDATE codigos_verificacion
         SET usado = 1
         WHERE correo = :correo AND tipo = :tipo AND usado = 0'
    );
    $sentenciaInvalidar->execute([':correo' => $correo, ':tipo' => $tipoBD]);

    //random_int es criptograficamente seguro, rand() no
    $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $sentencia = $conexion->prepare(
        'INSERT INTO codigos_verificacion (correo, codigo_hash, tipo, fecha_creacion, fecha_expiracion)
         VALUES (:correo, :hash, :tipo, NOW(), DATE_ADD(NOW(), INTERVAL :minutos MINUTE))'
    );

    $sentencia->bindValue(':correo',  $correo);
    $sentencia->bindValue(':hash',    password_hash($codigo, PASSWORD_DEFAULT));
    $sentencia->bindValue(':tipo',    $tipoBD, PDO::PARAM_INT);
    $sentencia->bindValue(':minutos', MINUTOS_VIGENCIA_CODIGO, PDO::PARAM_INT);
    $sentencia->execute();

    return $codigo;
}

/*
    Valida el codigo capturado por el usuario.
    Devuelve ['valido' => bool, 'mensaje' => string]
*/
function validarCodigoVerificacion($correo, $tipo, $codigoCapturado)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_codigo, codigo_hash, intentos, fecha_expiracion
         FROM codigos_verificacion
         WHERE correo = :correo AND tipo = :tipo AND usado = 0
         ORDER BY id_codigo DESC
         LIMIT 1'
    );
    $sentencia->execute([':correo' => $correo, ':tipo' => tipoCodigoATinyint($tipo)]);

    $registro = $sentencia->fetch();

    if ($registro === false)
    {
        return ['valido' => false, 'mensaje' => 'No hay un codigo activo. Solicita uno nuevo.'];
    }

    if (strtotime($registro['fecha_expiracion']) < time())
    {
        marcarCodigoComoUsado($registro['id_codigo']);

        return ['valido' => false, 'mensaje' => 'El codigo expiro. Solicita uno nuevo.'];
    }

    if ((int) $registro['intentos'] >= MAXIMO_INTENTOS_CODIGO)
    {
        marcarCodigoComoUsado($registro['id_codigo']);

        return ['valido' => false, 'mensaje' => 'Se agotaron los intentos. Solicita un codigo nuevo.'];
    }

    if (!password_verify($codigoCapturado, $registro['codigo_hash']))
    {
        $sentenciaIntento = $conexion->prepare(
            'UPDATE codigos_verificacion SET intentos = intentos + 1 WHERE id_codigo = :id'
        );
        $sentenciaIntento->execute([':id' => $registro['id_codigo']]);

        $restantes = MAXIMO_INTENTOS_CODIGO - ((int) $registro['intentos'] + 1);

        return ['valido' => false, 'mensaje' => 'El codigo es incorrecto. Te quedan ' . $restantes . ' intentos.'];
    }

    marcarCodigoComoUsado($registro['id_codigo']);

    return ['valido' => true, 'mensaje' => 'Codigo verificado.'];
}

function marcarCodigoComoUsado($idCodigo)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('UPDATE codigos_verificacion SET usado = 1 WHERE id_codigo = :id');
    $sentencia->execute([':id' => $idCodigo]);
}

//=============================================================
// Foto de perfil (RNF-01, RNF-05)
//=============================================================

define('PESO_MAXIMO_FOTO', 2 * 1024 * 1024); //2MB

function guardarFotoPerfil($archivo)
{
    if (!isset($archivo) || $archivo['error'] === UPLOAD_ERR_NO_FILE)
    {
        return ['exito' => false, 'error' => 'Sube una foto de perfil donde se vea tu rostro.'];
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK)
    {
        return ['exito' => false, 'error' => 'La imagen no se subio correctamente. Intenta de nuevo.'];
    }

    if ($archivo['size'] > PESO_MAXIMO_FOTO)
    {
        return ['exito' => false, 'error' => 'La imagen pesa mas de 2MB. Usa una mas ligera.'];
    }

    //se revisa el tipo real del archivo, no la extension que trae el nombre
    $informacion = getimagesize($archivo['tmp_name']);

    if ($informacion === false)
    {
        return ['exito' => false, 'error' => 'El archivo no es una imagen valida.'];
    }

    $extensionesPermitidas = [
        IMAGETYPE_JPEG => '.jpg',
        IMAGETYPE_PNG  => '.png',
        IMAGETYPE_WEBP => '.webp'
    ];

    if (!isset($extensionesPermitidas[$informacion[2]]))
    {
        return ['exito' => false, 'error' => 'Solo se aceptan imagenes JPG, PNG o WEBP.'];
    }

    $carpetaDestino = DOCROOT . 'user/assets/img/perfiles/';

    if (!is_dir($carpetaDestino))
    {
        mkdir($carpetaDestino, 0775, true);
    }

    $nombreArchivo = 'perfil_' . bin2hex(random_bytes(8)) . $extensionesPermitidas[$informacion[2]];

    if (!move_uploaded_file($archivo['tmp_name'], $carpetaDestino . $nombreArchivo))
    {
        return ['exito' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
    }

    return ['exito' => true, 'ruta' => 'user/assets/img/perfiles/' . $nombreArchivo];
}

function eliminarFotoPerfil($rutaRelativa)
{
    if (!empty($rutaRelativa) && file_exists(DOCROOT . $rutaRelativa))
    {
        unlink(DOCROOT . $rutaRelativa);
    }
}

//=============================================================
// Sesion
//=============================================================

function hayUsuarioEnSesion()
{
    return isset($_SESSION['id_usuario']);
}

function iniciarSesionUsuario($usuario)
{
    //evita el robo de sesion reutilizando el id anterior
    session_regenerate_id(true);

    $_SESSION['id_usuario']     = $usuario['id_usuario'];
    $_SESSION['nombre_usuario'] = $usuario['nombre'];
    $_SESSION['correo_usuario'] = $usuario['correo'];
    $_SESSION['rol_usuario']    = $usuario['rol'];
    $_SESSION['foto_usuario']   = $usuario['foto_perfil_url'] ?? null;
    $_SESSION['ultima_actividad'] = time();
}


//=============================================================
// Búsqueda de Servicios
//=============================================================

function buscarServicios($oficio, $ubicacion)
{
    $conexion = conexionBD();

    $sql = "SELECT s.titulo, s.descripcion, s.monto, u.nombre AS freelancer, c.nombre_categoria 
            FROM servicios s
            INNER JOIN usuarios u ON s.id_usuario = u.id_usuario
            INNER JOIN cat_categorias c ON s.id_categoria = c.id_categoria
            WHERE (s.titulo LIKE :busqueda1 OR s.descripcion LIKE :busqueda2 OR c.nombre_categoria LIKE :busqueda3)";

    $termino = '%' . $oficio . '%';
    $sentencia = $conexion->prepare($sql);

    $sentencia->execute([
        ':busqueda1' => $termino,
        ':busqueda2' => $termino,
        ':busqueda3' => $termino
    ]);

    return $sentencia->fetchAll(PDO::FETCH_ASSOC);
}
?>


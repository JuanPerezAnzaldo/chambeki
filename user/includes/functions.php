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

/*
    LISTADO DE SERVICIOS / TRABAJADORES CON FILTROS
    Pegar en functions.php, REEMPLAZANDO la buscarServicios actual
    (la del final del archivo). Usa tu esquema real:
      servicios (titulo, descripcion, monto, id_usuario, id_categoria)
      cat_categorias (id_categoria, nombre_categoria)
      usuarios (id_usuario, nombre, foto_perfil_url)
      cobertura_freelancer (id_usuario, id_modalidad, direccion_texto,
                            ciudad_limite, radio_cobertura_km, latitud_base, longitud_base)
      cat_modalidad (id_modalidad, nombre)
      suscripciones (Premium vigente => prioridad)
    Requiere funcionesPerfil.php (obtenerModalidades, normalizarTexto, constantes PLAN_/SUSCRIPCION_).

    La llave de `servicios` (para "mas recientes") se detecta sola con SHOW COLUMNS.
*/

const SERVICIOS_POR_PAGINA = 12;

//ids de modalidad que cuentan como "en linea / remoto" (se detectan por el nombre del catalogo)
function idsModalidadEnLinea($modalidades)
{
    $ids = [];

    foreach ($modalidades as $m)
    {
        $nombre = normalizarTexto($m['nombre']);

        if (strpos($nombre, 'linea') !== false || strpos($nombre, 'remot') !== false || strpos($nombre, 'online') !== false)
        {
            $ids[] = (int) $m['id_modalidad'];
        }
    }

    return $ids;
}

//-------------------------------------------------------------
// 1) Leer y validar filtros desde $_GET (nunca confiar en la URL)
//-------------------------------------------------------------
function leerFiltrosListado(array $g, array $modalidades): array
{
    //ubicacion.js manda: texto | lugar (ciudad elegida de la lista) | gps | online
    //"lugar" se trata como texto: se busca por el nombre de la ciudad
    $tipoUbicacion = in_array($g['tipo_ubicacion'] ?? '', ['texto', 'lugar', 'gps', 'online'], true) ? $g['tipo_ubicacion'] : 'texto';
    if ($tipoUbicacion === 'lugar')
    {
        $tipoUbicacion = 'texto';
    }

    $lat = (isset($g['latitud'])  && is_numeric($g['latitud']))  ? (float) $g['latitud']  : null;
    $lng = (isset($g['longitud']) && is_numeric($g['longitud'])) ? (float) $g['longitud'] : null;

    $hayGeo = $tipoUbicacion === 'gps' && $lat !== null && $lng !== null
        && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;

    //modalidad elegida en el filtro (debe existir en el catalogo)
    $idsValidos = array_map('intval', array_column($modalidades, 'id_modalidad'));
    $modalidad  = (int) ($g['modalidad'] ?? 0);
    if (!in_array($modalidad, $idsValidos, true))
    {
        $modalidad = 0;
    }

    //"En linea / Remoto" desde el buscador del home
    $modalidadesSql = $modalidad > 0 ? [$modalidad] : [];
    if ($tipoUbicacion === 'online' && $modalidad === 0)
    {
        $modalidadesSql = idsModalidadEnLinea($modalidades);
    }

    $precioMin = (isset($g['precio_min']) && is_numeric($g['precio_min'])) ? max(0, (float) $g['precio_min']) : null;
    $precioMax = (isset($g['precio_max']) && is_numeric($g['precio_max'])) ? max(0, (float) $g['precio_max']) : null;
    if ($precioMin !== null && $precioMax !== null && $precioMin > $precioMax)
    {
        [$precioMin, $precioMax] = [$precioMax, $precioMin];
    }

    $maxKm = (int) ($g['max_km'] ?? 0);
    if (!in_array($maxKm, [5, 10, 25, 50], true) || !$hayGeo)
    {
        $maxKm = 0;
    }

    $orden = in_array($g['orden'] ?? '', ['relevancia', 'precio_asc', 'precio_desc', 'reciente', 'cercania'], true)
        ? $g['orden'] : ($hayGeo ? 'cercania' : 'relevancia');
    if ($orden === 'cercania' && !$hayGeo)
    {
        $orden = 'relevancia';
    }

    return [
        'oficio'          => limpiarEntrada($g['oficio'] ?? ''),
        'ubicacion'       => limpiarEntrada($g['ubicacion'] ?? ''),
        'tipo_ubicacion'  => $tipoUbicacion,
        'latitud'         => $hayGeo ? $lat : null,
        'longitud'        => $hayGeo ? $lng : null,
        'hay_geo'         => $hayGeo,
        'id_categoria'    => max(0, (int) ($g['categoria'] ?? 0)),
        'modalidad'       => $modalidad,
        'modalidades_sql' => $modalidadesSql,
        'precio_min'      => $precioMin,
        'precio_max'      => $precioMax,
        'max_km'          => $maxKm,
        'orden'           => $orden,
        'pagina'          => max(1, (int) ($g['pagina'] ?? 1))
    ];
}

function obtenerCategoriasListado(): array
{
    $conexion = conexionBD();

    return $conexion->query('SELECT id_categoria, nombre_categoria FROM cat_categorias ORDER BY nombre_categoria')
                    ->fetchAll(PDO::FETCH_ASSOC);
}

//llave primaria de `servicios` (se lee de la BD para no suponer su nombre)
function columnaLlaveServicios()
{
    static $columna = null;

    if ($columna !== null)
    {
        return $columna;
    }

    try
    {
        $filas = conexionBD()->query('SHOW COLUMNS FROM servicios')->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $fila)
        {
            if (($fila['Key'] ?? '') === 'PRI' && preg_match('/^[A-Za-z0-9_]+$/', $fila['Field']))
            {
                return $columna = 's.' . $fila['Field'];
            }
        }
    }
    catch (\Throwable $excepcion)
    {
        //se usa el respaldo de abajo
    }

    return $columna = 's.id_usuario';
}

//-------------------------------------------------------------
// 2) Busqueda con filtros. Devuelve ['items' => [...], 'total' => n]
//-------------------------------------------------------------
function buscarServicios(array $f): array
{
    $conexion = conexionBD();
    $where    = ['1 = 1'];
    $params   = [];

    //distancia en km (Haversine) contra la base del freelancer.
    //Cada uso lleva marcadores propios: PDO no permite repetir el mismo nombre.
    $contadorDistancia = 0;
    $nuevaDistancia = function () use (&$params, &$contadorDistancia, $f)
    {
        $n = ++$contadorDistancia;
        $params[':lat_a' . $n] = $f['latitud'];
        $params[':lat_b' . $n] = $f['latitud'];
        $params[':lng' . $n]   = $f['longitud'];

        return '(6371 * ACOS(LEAST(1, COS(RADIANS(:lat_a' . $n . ')) * COS(RADIANS(cf.latitud_base))
                * COS(RADIANS(cf.longitud_base) - RADIANS(:lng' . $n . ')) + SIN(RADIANS(:lat_b' . $n . ')) * SIN(RADIANS(cf.latitud_base)))))';
    };

    if ($f['hay_geo'])
    {
        //con GPS solo aparecen freelancers con base definida y cuyo radio de cobertura llega al cliente
        $where[] = 'cf.latitud_base IS NOT NULL AND cf.longitud_base IS NOT NULL';
        $where[] = $nuevaDistancia() . ' <= cf.radio_cobertura_km';

        if ($f['max_km'] > 0)
        {
            $where[] = $nuevaDistancia() . ' <= :max_km';
            $params[':max_km'] = $f['max_km'];
        }
    }

    //texto libre (misma logica flexible que ya tenias)
    if ($f['oficio'] !== '')
    {
        $oficio = $f['oficio'];
        $flexible = '%' . str_replace(' ', '%', addcslashes($oficio, '%_\\')) . '%';
        $raiz = '%' . addcslashes(mb_substr($oficio, 0, -1), '%_\\') . '%';

        if (mb_strtolower($oficio) === 'electricista')
        {
            $raiz = '%electricidad%';
        }

        $where[] = '(s.titulo LIKE :q_titulo OR s.descripcion LIKE :q_desc OR c.nombre_categoria LIKE :q_raiz)';
        $params[':q_titulo'] = $flexible;
        $params[':q_desc']   = $flexible;
        $params[':q_raiz']   = $raiz;
    }

    //ubicacion escrita a mano: ciudad o direccion del freelancer
    if ($f['tipo_ubicacion'] === 'texto' && $f['ubicacion'] !== '')
    {
        $where[] = '(cf.ciudad_limite LIKE :zona1 OR cf.direccion_texto LIKE :zona2)';
        $like = '%' . addcslashes($f['ubicacion'], '%_\\') . '%';
        $params[':zona1'] = $like;
        $params[':zona2'] = $like;
    }

    if ($f['id_categoria'] > 0)
    {
        $where[] = 's.id_categoria = :cat';
        $params[':cat'] = $f['id_categoria'];
    }

    //modalidad (ids ya validados contra el catalogo => seguros de concatenar)
    if (!empty($f['modalidades_sql']))
    {
        $where[] = 'cf.id_modalidad IN (' . implode(',', array_map('intval', $f['modalidades_sql'])) . ')';
    }

    //al filtrar por precio se excluyen los "A cotizar" (monto = 0)
    if ($f['precio_min'] !== null || $f['precio_max'] !== null)
    {
        $where[] = 's.monto > 0';
    }
    if ($f['precio_min'] !== null)
    {
        $where[] = 's.monto >= :pmin';
        $params[':pmin'] = $f['precio_min'];
    }
    if ($f['precio_max'] !== null)
    {
        $where[] = 's.monto <= :pmax';
        $params[':pmax'] = $f['precio_max'];
    }

    //Premium vigente => mayor prioridad en la busqueda
    $exprPremium = '(EXISTS (SELECT 1 FROM suscripciones su
                            WHERE su.id_usuario = s.id_usuario
                              AND su.id_plan = ' . (int) PLAN_PREMIUM . '
                              AND su.estado = ' . (int) SUSCRIPCION_ACTIVA . '
                              AND su.fecha_vencimiento > NOW()))';

    //orden: lista blanca, nunca se concatena lo que manda el usuario
    $llave = columnaLlaveServicios();

    switch ($f['orden'])
    {
        case 'precio_asc':  $orden = 's.monto = 0, s.monto ASC';  break;   //"A cotizar" al final
        case 'precio_desc': $orden = 's.monto DESC';              break;
        case 'reciente':    $orden = $llave . ' DESC';            break;
        case 'cercania':    $orden = 'distancia_km ASC';          break;
        default:            $orden = 'es_premium DESC, ' . $llave . ' DESC';
    }

    $sqlBase = 'FROM servicios s
                INNER JOIN usuarios u ON u.id_usuario = s.id_usuario
                INNER JOIN cat_categorias c ON c.id_categoria = s.id_categoria
                LEFT JOIN cobertura_freelancer cf ON cf.id_usuario = s.id_usuario
                LEFT JOIN cat_modalidad m ON m.id_modalidad = cf.id_modalidad
                WHERE ' . implode(' AND ', $where);

    $total = $conexion->prepare('SELECT COUNT(*) ' . $sqlBase);
    $total->execute($params);
    $total = (int) $total->fetchColumn();

    $exprDistancia = $f['hay_geo'] ? $nuevaDistancia() : 'NULL';

    $offset = ($f['pagina'] - 1) * SERVICIOS_POR_PAGINA;

    $sql = 'SELECT s.titulo, s.descripcion, s.monto, c.nombre_categoria,
                   u.nombre AS freelancer, u.foto_perfil_url,
                   m.nombre AS modalidad, cf.ciudad_limite,
                   ' . $exprPremium . ' AS es_premium,
                   ' . $exprDistancia . ' AS distancia_km '
         . $sqlBase . ' ORDER BY ' . $orden
         . ' LIMIT ' . SERVICIOS_POR_PAGINA . ' OFFSET ' . (int) $offset;

    $sentencia = $conexion->prepare($sql);
    $sentencia->execute($params);

    return ['items' => $sentencia->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
}
?>
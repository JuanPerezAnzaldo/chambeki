<?php
/*
    Funciones del modulo "Identidad comercial" del freelancer.
    Se incluyen desde user/pages/configuracion.php con require_once.

    Cubre: nombre comercial y biografia, banner de portada, redes
    sociales, horarios semanales y la validacion de coordenadas del mapa.
    (La zona de cobertura y el perfil de cobro siguen en funcionesPerfil.php.)

    Depende de functions.php (conexionBD, limpiarEntrada) y de
    funcionesPerfil.php / funcionesSocio.php (filtro de palabras
    prohibidas, slug del perfil publico y constantes MODALIDAD_*).
    Todas las consultas son preparadas (RS-02).
*/

require_once DOCROOT . 'user/includes/funcionesPerfil.php';
require_once DOCROOT . 'user/includes/funcionesSocio.php';

define('PESO_MAXIMO_BANNER', 3 * 1024 * 1024); //3MB
define('BANNER_ANCHO_MINIMO', 600);
define('BANNER_ALTO_MINIMO', 200);
define('CARPETA_BANNERS', 'user/assets/img/banners/');

//=============================================================
// Identidad comercial: nombre comercial y descripcion
// (tabla perfil_freelancer)
//=============================================================

function obtenerIdentidadComercial($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT nombre_negocio, descripcion, slug
         FROM perfil_freelancer
         WHERE id_usuario = :id
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    if ($fila === false)
    {
        return ['nombre_negocio' => '', 'descripcion' => '', 'slug' => ''];
    }

    return [
        'nombre_negocio' => (string) $fila['nombre_negocio'],
        'descripcion'    => (string) $fila['descripcion'],
        'slug'           => (string) $fila['slug']
    ];
}

function validarIdentidadComercial($datos)
{
    $errores = [];
    $largoNombre = mb_strlen($datos['nombre_negocio']);

    if ($largoNombre < 3 || $largoNombre > 150)
    {
        $errores['nombre_negocio'] = 'Escribe el nombre de tu negocio o tu nombre profesional (3 a 150 caracteres).';
    }
    elseif (($palabra = buscarPalabraProhibida($datos['nombre_negocio'])) !== null)
    {
        $errores['nombre_negocio'] = 'El nombre contiene contenido no permitido ("' . $palabra . '"). Quitalo para publicar tu perfil.';
    }

    $largoDescripcion = mb_strlen($datos['descripcion']);

    if ($largoDescripcion < 20 || $largoDescripcion > 600)
    {
        $errores['descripcion'] = 'Describe tu negocio en 20 a 600 caracteres.';
    }
    elseif (($palabra = buscarPalabraProhibida($datos['descripcion'])) !== null)
    {
        $errores['descripcion'] = 'Tu descripcion contiene contenido no permitido ("' . $palabra . '"). Quitalo para publicar tu perfil.';
    }

    return $errores;
}

//el slug del perfil publico NO cambia al renombrar el negocio (los enlaces compartidos siguen funcionando)
function guardarIdentidadComercial($idUsuario, $datos)
{
    $conexion = conexionBD();

    $existe = $conexion->prepare('SELECT id_perfil, slug FROM perfil_freelancer WHERE id_usuario = :id LIMIT 1');
    $existe->execute([':id' => $idUsuario]);
    $perfil = $existe->fetch();

    if ($perfil !== false)
    {
        $slug = (string) $perfil['slug'];

        if ($slug === '')
        {
            $slug = generarSlugUnico($datos['nombre_negocio']);
        }

        $sentencia = $conexion->prepare(
            'UPDATE perfil_freelancer
             SET nombre_negocio = :nombre, descripcion = :descripcion, slug = :slug
             WHERE id_usuario = :id'
        );
    }
    else
    {
        $slug = generarSlugUnico($datos['nombre_negocio']);

        $sentencia = $conexion->prepare(
            'INSERT INTO perfil_freelancer (id_usuario, nombre_negocio, slug, descripcion)
             VALUES (:id, :nombre, :slug, :descripcion)'
        );
    }

    $sentencia->execute([
        ':nombre'      => $datos['nombre_negocio'],
        ':descripcion' => $datos['descripcion'],
        ':slug'        => $slug,
        ':id'          => $idUsuario
    ]);
}

//=============================================================
// Banner de portada (tabla config_premium.banner_url)
//=============================================================

function obtenerBannerFreelancer($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT banner_url FROM config_premium WHERE id_usuario = :id ORDER BY id_config DESC LIMIT 1'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $valor = $sentencia->fetchColumn();

    return ($valor !== false && $valor !== null && $valor !== '') ? (string) $valor : null;
}

/*
    Guarda la imagen del banner (tipo real, peso y dimensiones minimas).
    El navegador ya la recorta a 3:1 con Cropper.js; aqui se vuelve a
    validar todo porque nunca se confia en el navegador (RS-05).
    Devuelve ['exito' => true, 'ruta' => '...'] o ['exito' => false, 'error' => '...'].
*/
function guardarImagenBanner($archivo)
{
    if (!isset($archivo) || !is_array($archivo) || $archivo['error'] === UPLOAD_ERR_NO_FILE)
    {
        return ['exito' => false, 'error' => 'Elige una imagen para tu banner.'];
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK)
    {
        return ['exito' => false, 'error' => 'La imagen no se subio correctamente. Intenta de nuevo.'];
    }

    if ($archivo['size'] > PESO_MAXIMO_BANNER)
    {
        return ['exito' => false, 'error' => 'La imagen pesa mas de 3MB. Usa una mas ligera.'];
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

    if ($informacion[0] < BANNER_ANCHO_MINIMO || $informacion[1] < BANNER_ALTO_MINIMO)
    {
        return ['exito' => false, 'error' => 'El banner debe medir al menos ' . BANNER_ANCHO_MINIMO . ' x ' . BANNER_ALTO_MINIMO . ' pixeles.'];
    }

    $carpetaDestino = DOCROOT . CARPETA_BANNERS;

    if (!is_dir($carpetaDestino))
    {
        mkdir($carpetaDestino, 0775, true);
    }

    $nombreArchivo = 'banner_' . bin2hex(random_bytes(8)) . $extensionesPermitidas[$informacion[2]];

    if (!move_uploaded_file($archivo['tmp_name'], $carpetaDestino . $nombreArchivo))
    {
        return ['exito' => false, 'error' => 'No se pudo guardar la imagen en el servidor.'];
    }

    return ['exito' => true, 'ruta' => CARPETA_BANNERS . $nombreArchivo];
}

//ruta guardada en la BD -> URL para el <img> ('' si no hay banner)
function urlBanner($ruta)
{
    $ruta = trim((string) $ruta);

    if ($ruta === '')
    {
        return '';
    }

    if (preg_match('#^https?://#i', $ruta))
    {
        return $ruta;
    }

    return URL_BASE . ltrim($ruta, '/');
}

//$ruta = null quita el banner. Si el usuario no tenia fila en config_premium y solo quita, no hay nada que hacer.
function actualizarBannerFreelancer($idUsuario, $ruta)
{
    $conexion = conexionBD();

    $existe = $conexion->prepare('SELECT id_config FROM config_premium WHERE id_usuario = :id ORDER BY id_config DESC LIMIT 1');
    $existe->execute([':id' => $idUsuario]);
    $fila = $existe->fetch();

    if ($fila !== false)
    {
        $sentencia = $conexion->prepare('UPDATE config_premium SET banner_url = :ruta WHERE id_config = :id_config');
        $sentencia->execute([':ruta' => $ruta, ':id_config' => $fila['id_config']]);

        return;
    }

    if ($ruta !== null)
    {
        $sentencia = $conexion->prepare('INSERT INTO config_premium (id_usuario, banner_url) VALUES (:id, :ruta)');
        $sentencia->execute([':id' => $idUsuario, ':ruta' => $ruta]);
    }
}

//solo borra archivos de la carpeta de banners (nunca una ruta arbitraria)
function eliminarArchivoBanner($rutaRelativa)
{
    $rutaRelativa = (string) $rutaRelativa;

    if ($rutaRelativa === '' || strpos($rutaRelativa, CARPETA_BANNERS) !== 0 || strpos($rutaRelativa, '..') !== false)
    {
        return;
    }

    if (file_exists(DOCROOT . $rutaRelativa))
    {
        unlink(DOCROOT . $rutaRelativa);
    }
}

//=============================================================
// Redes sociales (tabla redes_sociales_freelancer)
//=============================================================

function redesSocialesDisponibles()
{
    return [
        'facebook'  => ['etiqueta' => 'Facebook',    'dominios' => ['facebook.com', 'fb.com', 'fb.me'], 'ejemplo' => 'https://facebook.com/tu-negocio'],
        'instagram' => ['etiqueta' => 'Instagram',   'dominios' => ['instagram.com'],                   'ejemplo' => 'https://instagram.com/tu_negocio'],
        'tiktok'    => ['etiqueta' => 'TikTok',      'dominios' => ['tiktok.com'],                      'ejemplo' => 'https://tiktok.com/@tu_negocio'],
        'x_twitter' => ['etiqueta' => 'X (Twitter)', 'dominios' => ['x.com', 'twitter.com'],            'ejemplo' => 'https://x.com/tu_negocio'],
        'linkedin'  => ['etiqueta' => 'LinkedIn',    'dominios' => ['linkedin.com'],                    'ejemplo' => 'https://linkedin.com/company/tu-negocio'],
        'sitio_web' => ['etiqueta' => 'Sitio web',   'dominios' => [],                                  'ejemplo' => 'https://www.tunegocio.com']
    ];
}

//devuelve [clave_red => url] solo con las redes que el freelancer ya configuro
function obtenerRedesFreelancer($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT red_social, url FROM redes_sociales_freelancer WHERE id_usuario = :id ORDER BY id_red');
    $sentencia->execute([':id' => $idUsuario]);

    $redes = [];

    foreach ($sentencia->fetchAll() as $fila)
    {
        $redes[$fila['red_social']] = $fila['url'];
    }

    return $redes;
}

function dominioCoincide($host, $dominios)
{
    foreach ($dominios as $dominio)
    {
        $sufijo = '.' . $dominio;

        if ($host === $dominio || substr($host, -strlen($sufijo)) === $sufijo)
        {
            return true;
        }
    }

    return false;
}

/*
    Revisa los enlaces escritos por el freelancer. Un campo vacio
    significa "sin esa red" (se borra). Si falta el "https://" se agrega.
    Solo se aceptan http(s); un "javascript:..." nunca se guarda.
    Devuelve ['errores' => [red => mensaje], 'redes' => [red => url_limpia]].
*/
function validarRedesSociales($entrada)
{
    $errores = [];
    $redes = [];

    foreach (redesSocialesDisponibles() as $clave => $datosRed)
    {
        $valor = (is_array($entrada) && isset($entrada[$clave]) && is_string($entrada[$clave])) ? limpiarEntrada($entrada[$clave]) : '';

        if ($valor === '')
        {
            continue;
        }

        if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $valor))
        {
            $valor = 'https://' . $valor;
        }

        $esquema = strtolower((string) parse_url($valor, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($valor, PHP_URL_HOST));

        if (strlen($valor) > 255)
        {
            $errores[$clave] = 'El enlace es demasiado largo (maximo 255 caracteres).';
        }
        elseif (!in_array($esquema, ['http', 'https'], true) || $host === '' || filter_var($valor, FILTER_VALIDATE_URL) === false)
        {
            $errores[$clave] = 'Escribe un enlace valido, por ejemplo ' . $datosRed['ejemplo'];
        }
        elseif ($clave === 'sitio_web' && strpos($host, '.') === false)
        {
            $errores[$clave] = 'Escribe un enlace valido, por ejemplo ' . $datosRed['ejemplo'];
        }
        elseif ($datosRed['dominios'] !== [] && !dominioCoincide($host, $datosRed['dominios']))
        {
            $errores[$clave] = 'Ese enlace no parece ser de ' . $datosRed['etiqueta'] . '. Ejemplo: ' . $datosRed['ejemplo'];
        }
        else
        {
            $redes[$clave] = $valor;
        }
    }

    return ['errores' => $errores, 'redes' => $redes];
}

//reemplaza todas las redes del freelancer en una transaccion (todo o nada)
function guardarRedesFreelancer($idUsuario, $redes)
{
    $conexion = conexionBD();

    try
    {
        $conexion->beginTransaction();

        $borrar = $conexion->prepare('DELETE FROM redes_sociales_freelancer WHERE id_usuario = :id');
        $borrar->execute([':id' => $idUsuario]);

        $insertar = $conexion->prepare(
            'INSERT INTO redes_sociales_freelancer (id_usuario, red_social, url) VALUES (:id, :red, :url)'
        );

        foreach ($redes as $clave => $url)
        {
            $insertar->execute([':id' => $idUsuario, ':red' => $clave, ':url' => $url]);
        }

        $conexion->commit();
    }
    catch (\Throwable $excepcion)
    {
        if ($conexion->inTransaction())
        {
            $conexion->rollBack();
        }

        throw $excepcion;
    }
}

//=============================================================
// Matriz de horarios semanales (tabla horarios_freelancer)
// dia_semana: 1 = Lunes ... 7 = Domingo
//=============================================================

function nombresDiasSemana()
{
    return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo'];
}

//horario sugerido la primera vez: lunes a viernes 09:00-18:00, sabado 09:00-14:00, domingo cerrado
function horarioPredeterminado()
{
    $horario = [];

    foreach (nombresDiasSemana() as $dia => $nombre)
    {
        $horario[$dia] = [
            'cerrado' => $dia === 7,
            'abre'    => '09:00',
            'cierra'  => $dia === 6 ? '14:00' : '18:00'
        ];
    }

    return $horario;
}

//devuelve [1..7 => ['cerrado' => bool, 'abre' => 'HH:MM', 'cierra' => 'HH:MM']]
function obtenerHorarioFreelancer($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT dia_semana, hora_apertura, hora_cierre, es_cerrado
         FROM horarios_freelancer
         WHERE id_usuario = :id'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $filas = $sentencia->fetchAll();
    $horario = horarioPredeterminado();

    foreach ($filas as $fila)
    {
        $dia = (int) $fila['dia_semana'];

        if ($dia < 1 || $dia > 7)
        {
            continue;
        }

        $horario[$dia] = [
            'cerrado' => (bool) $fila['es_cerrado'],
            'abre'    => $fila['hora_apertura'] !== null ? substr((string) $fila['hora_apertura'], 0, 5) : $horario[$dia]['abre'],
            'cierra'  => $fila['hora_cierre'] !== null ? substr((string) $fila['hora_cierre'], 0, 5) : $horario[$dia]['cierra']
        ];
    }

    return $horario;
}

//"09:30" o "09:30:00" -> "09:30"; cualquier otra cosa -> null
function normalizarHora($texto)
{
    if (!is_string($texto) || !preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(:[0-5][0-9])?$/', trim($texto), $partes))
    {
        return null;
    }

    return $partes[1] . ':' . $partes[2];
}

function minutosDeHora($hora)
{
    return ((int) substr($hora, 0, 2)) * 60 + (int) substr($hora, 3, 2);
}

/*
    Revisa los 7 dias del formulario (horario[dia][abre|cierra|cerrado]).
    Un dia no laborable no necesita horas; uno laborable necesita
    apertura y cierre, con el cierre despues de la apertura.
    Devuelve ['errores' => [dia|'general' => mensaje], 'horario' => [1..7 => [...]]].
*/
function validarHorarios($entrada)
{
    $errores = [];
    $horario = [];
    $diasLaborables = 0;

    foreach (nombresDiasSemana() as $dia => $nombre)
    {
        $fila = (is_array($entrada) && isset($entrada[$dia]) && is_array($entrada[$dia])) ? $entrada[$dia] : [];

        $cerrado = isset($fila['cerrado']);
        $abre = normalizarHora($fila['abre'] ?? '');
        $cierra = normalizarHora($fila['cierra'] ?? '');

        $horario[$dia] = [
            'cerrado' => $cerrado,
            'abre'    => $abre !== null ? $abre : '',
            'cierra'  => $cierra !== null ? $cierra : ''
        ];

        if ($cerrado)
        {
            continue;
        }

        $diasLaborables++;

        if ($abre === null || $cierra === null)
        {
            $errores[$dia] = 'Elige la hora de apertura y la de cierre.';
        }
        elseif (minutosDeHora($abre) >= minutosDeHora($cierra))
        {
            $errores[$dia] = 'El cierre debe ser despues de la apertura.';
        }
    }

    if ($diasLaborables === 0)
    {
        $errores['general'] = 'Deja al menos un dia laborable.';
    }

    return ['errores' => $errores, 'horario' => $horario];
}

//reemplaza los 7 dias en una transaccion (todo o nada)
function guardarHorariosFreelancer($idUsuario, $horario)
{
    $conexion = conexionBD();

    try
    {
        $conexion->beginTransaction();

        $borrar = $conexion->prepare('DELETE FROM horarios_freelancer WHERE id_usuario = :id');
        $borrar->execute([':id' => $idUsuario]);

        $insertar = $conexion->prepare(
            'INSERT INTO horarios_freelancer (id_usuario, dia_semana, hora_apertura, hora_cierre, es_cerrado)
             VALUES (:id, :dia, :abre, :cierra, :cerrado)'
        );

        foreach ($horario as $dia => $fila)
        {
            $insertar->execute([
                ':id'      => $idUsuario,
                ':dia'     => (int) $dia,
                ':abre'    => $fila['cerrado'] ? null : $fila['abre'] . ':00',
                ':cierra'  => $fila['cerrado'] ? null : $fila['cierra'] . ':00',
                ':cerrado' => $fila['cerrado'] ? 1 : 0
            ]);
        }

        $conexion->commit();
    }
    catch (\Throwable $excepcion)
    {
        if ($conexion->inTransaction())
        {
            $conexion->rollBack();
        }

        throw $excepcion;
    }
}

//=============================================================
// Coordenadas del mapa (se guardan en cobertura_freelancer)
//=============================================================

//null = vacio, false = no es un numero, float = coordenada
function leerCoordenada($valor)
{
    $valor = str_replace(',', '.', limpiarEntrada(is_string($valor) ? $valor : ''));

    if ($valor === '')
    {
        return null;
    }

    return is_numeric($valor) ? (float) $valor : false;
}

//texto listo para un input (vacio si no hay coordenada)
function textoCoordenada($valor)
{
    if (!is_float($valor) && !is_int($valor))
    {
        return '';
    }

    return rtrim(rtrim(sprintf('%.8F', $valor), '0'), '.');
}

/*
    Una cobertura "En linea" no necesita ubicacion; "Local fisico" y
    "A domicilio" si, porque los clientes encuentran al negocio por mapa.
*/
function validarCoordenadasCobertura($datos)
{
    $errores = [];
    $lat = $datos['latitud_base'] ?? null;
    $lon = $datos['longitud_base'] ?? null;

    if ((int) $datos['id_modalidad'] === MODALIDAD_EN_LINEA)
    {
        return $errores;
    }

    if ($lat === null || $lon === null)
    {
        $errores['ubicacion'] = 'Fija tu ubicacion en el mapa: haz clic, busca tu direccion o escribe las coordenadas.';
    }
    elseif ($lat === false || $lon === false || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180)
    {
        $errores['ubicacion'] = 'Las coordenadas no son validas (latitud de -90 a 90 y longitud de -180 a 180).';
    }

    return $errores;
}
?>

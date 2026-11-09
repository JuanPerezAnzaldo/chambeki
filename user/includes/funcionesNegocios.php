<?php
/*
    Funciones del listado de negocios (tarjetas) y de la vista publica
    del perfil. Se incluye con require_once desde servicio.php y
    profesional.php. Depende de functions.php y funcionesPerfil.php.
    Todas las consultas son preparadas (RS-02).
*/

require_once DOCROOT . 'user/includes/funcionesPerfil.php';

//zona horaria con la que se decide si un negocio esta abierto o cerrado
define('ZONA_HORARIA_NEGOCIOS', 'America/Tijuana');
define('MAXIMO_NEGOCIOS_LISTADO', 60);

//busqueda por radio (slider del mapa del buscador)
define('RADIO_BUSQUEDA_MIN_KM', 1);
define('RADIO_BUSQUEDA_MAX_KM', 50);
define('RADIO_BUSQUEDA_PREDETERMINADO_KM', 25);
define('RADIO_TIERRA_KM', 6371);

if (!defined('MODALIDAD_LOCAL'))     { define('MODALIDAD_LOCAL', 1); }
if (!defined('MODALIDAD_DOMICILIO')) { define('MODALIDAD_DOMICILIO', 2); }
if (!defined('MODALIDAD_EN_LINEA'))  { define('MODALIDAD_EN_LINEA', 3); }

//=============================================================
// Utilidades de presentacion
//=============================================================

function nombresDias()
{
    return [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
}

function nombreNegocio($negocio)
{
    $nombre = trim((string) ($negocio['nombre_negocio'] ?? ''));

    return $nombre !== '' ? $nombre : (string) $negocio['nombre'];
}

function inicialesNegocio($nombre)
{
    $partes = preg_split('/\s+/', trim((string) $nombre));
    $iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1));

    if (isset($partes[1]))
    {
        $iniciales .= mb_strtoupper(mb_substr($partes[1], 0, 1));
    }

    return $iniciales !== '' ? $iniciales : 'C';
}

//las fotos se guardan con y sin "/" inicial; se unifica para no generar "//ruta"
function urlImagen($ruta)
{
    $ruta = trim((string) $ruta);

    if ($ruta === '')
    {
        return null;
    }

    if (preg_match('#^https?://#i', $ruta))
    {
        return $ruta;
    }

    return URL_BASE . ltrim($ruta, '/');
}

function enlaceNegocio($negocio)
{
    if (!empty($negocio['slug']))
    {
        return URL_BASE . '?accion=profesional&slug=' . rawurlencode($negocio['slug']);
    }

    return URL_BASE . '?accion=profesional&id=' . (int) $negocio['id_usuario'];
}

function etiquetaModalidad($idModalidad)
{
    switch ((int) $idModalidad)
    {
        case MODALIDAD_LOCAL:     return 'Local físico';
        case MODALIDAD_DOMICILIO: return 'A domicilio';
        case MODALIDAD_EN_LINEA:  return 'En línea';
        default:                  return '';
    }
}

function textoDistancia($km)
{
    if ($km === null)
    {
        return '';
    }

    $km = (float) $km;

    return number_format($km, $km < 10 ? 1 : 0) . ' km';
}

//estrellas con relleno parcial: el ancho se calcula con la variable CSS --valor
function htmlEstrellas($promedio)
{
    $valor = max(0, min(5, round((float) $promedio, 1)));

    return '<span class="estrellas" style="--valor:' . $valor . '" role="img" aria-label="' . $valor . ' de 5 estrellas"></span>';
}

function textoPrecio($monto, $idTipoPrecio)
{
    $monto = (float) $monto;

    if ((int) $idTipoPrecio === 3)
    {
        return 'Previa cotización';
    }

    if ($monto <= 0)
    {
        return 'A cotizar';
    }

    $cifra = '$' . number_format($monto, fmod($monto, 1) == 0 ? 0 : 2);

    if ((int) $idTipoPrecio === 2)
    {
        return $cifra . ' por hora';
    }

    if ((int) $idTipoPrecio === 4)
    {
        return 'Desde ' . $cifra;
    }

    return $cifra;
}

function nombreCorto($nombre)
{
    $partes = preg_split('/\s+/', trim((string) $nombre));
    $corto = $partes[0] ?? 'Cliente';

    if (isset($partes[1]))
    {
        $corto .= ' ' . mb_strtoupper(mb_substr($partes[1], 0, 1)) . '.';
    }

    return $corto;
}

//numero mexicano de 10 digitos -> 52XXXXXXXXXX (formato de tel: y wa.me)
function numeroParaEnlace($telefono)
{
    $digitos = preg_replace('/[^0-9]/', '', (string) $telefono);

    if (strlen($digitos) === 10)
    {
        return '52' . $digitos;
    }

    if (strlen($digitos) === 12 && strpos($digitos, '52') === 0)
    {
        return $digitos;
    }

    return null;
}

function etiquetaRedSocial($red)
{
    $etiquetas = [
        'facebook'  => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok'    => 'TikTok',
        'x_twitter' => 'X (Twitter)',
        'linkedin'  => 'LinkedIn',
        'sitio_web' => 'Sitio web'
    ];

    return $etiquetas[$red] ?? $red;
}

//solo se publican enlaces http(s); un "javascript:..." nunca llega al href
function urlRedSegura($url)
{
    $url = trim((string) $url);

    if (!preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false)
    {
        return null;
    }

    return $url;
}

//=============================================================
// Horario y estado Abierto / Cerrado
//=============================================================

function minutosDesdeHora($hora)
{
    if ($hora === null || $hora === '')
    {
        return null;
    }

    $partes = explode(':', (string) $hora);

    return ((int) $partes[0]) * 60 + (int) ($partes[1] ?? 0);
}

function formatoHora($minutos)
{
    return sprintf('%02d:%02d', intdiv((int) $minutos, 60) % 24, ((int) $minutos) % 60);
}

//convierte las filas de horarios_freelancer a [1..7 => ['a' => min, 'c' => min, 'x' => bool]]; null si no hay filas
function armarHorario($filas)
{
    if (empty($filas))
    {
        return null;
    }

    $horario = array_fill(1, 7, null);

    foreach ($filas as $fila)
    {
        $dia = (int) $fila['dia_semana'];

        if ($dia < 1 || $dia > 7)
        {
            continue;
        }

        $horario[$dia] = [
            'a' => minutosDesdeHora($fila['hora_apertura']),
            'c' => minutosDesdeHora($fila['hora_cierre']),
            'x' => (bool) $fila['es_cerrado']
        ];
    }

    return $horario;
}

function horarioEnJson($horario)
{
    return $horario === null ? '' : json_encode($horario, JSON_FORCE_OBJECT);
}

function intervaloValido($dia)
{
    return $dia !== null && !$dia['x'] && $dia['a'] !== null && $dia['c'] !== null;
}

/*
    Devuelve ['abierto' => true|false|null, 'texto' => 'Abierto'|'Cerrado'|'Sin horario', 'detalle' => '...'].
    Un cierre menor o igual a la apertura se toma como horario que pasa de medianoche.
    El mismo algoritmo vive en negocios.js para que la etiqueta se actualice sin recargar.
*/
function estadoNegocio($horario, $ahora = null)
{
    if ($horario === null)
    {
        return ['abierto' => null, 'texto' => 'Sin horario', 'detalle' => ''];
    }

    $ahora = $ahora ?: new DateTime('now', new DateTimeZone(ZONA_HORARIA_NEGOCIOS));
    $dia = (int) $ahora->format('N');
    $minutos = ((int) $ahora->format('G')) * 60 + (int) $ahora->format('i');

    $hoy = $horario[$dia] ?? null;
    $ayer = $horario[$dia === 1 ? 7 : $dia - 1] ?? null;

    if (intervaloValido($hoy))
    {
        $cruzaMedianoche = $hoy['c'] <= $hoy['a'];

        if (($cruzaMedianoche && $minutos >= $hoy['a']) || (!$cruzaMedianoche && $minutos >= $hoy['a'] && $minutos < $hoy['c']))
        {
            return ['abierto' => true, 'texto' => 'Abierto', 'detalle' => 'Cierra a las ' . formatoHora($hoy['c'])];
        }
    }

    if (intervaloValido($ayer) && $ayer['c'] <= $ayer['a'] && $minutos < $ayer['c'])
    {
        return ['abierto' => true, 'texto' => 'Abierto', 'detalle' => 'Cierra a las ' . formatoHora($ayer['c'])];
    }

    $detalle = '';

    if (intervaloValido($hoy) && $minutos < $hoy['a'])
    {
        $detalle = 'Abre hoy a las ' . formatoHora($hoy['a']);
    }
    else
    {
        $nombres = nombresDias();

        for ($i = 1; $i <= 7; $i++)
        {
            $siguiente = (($dia - 1 + $i) % 7) + 1;

            if (intervaloValido($horario[$siguiente] ?? null))
            {
                $detalle = ($i === 1 ? 'Abre mañana' : 'Abre el ' . mb_strtolower($nombres[$siguiente])) . ' a las ' . formatoHora($horario[$siguiente]['a']);
                break;
            }
        }
    }

    return ['abierto' => false, 'texto' => 'Cerrado', 'detalle' => $detalle];
}

//texto de una fila de la tabla semanal: "09:00 - 18:00" o "Cerrado"
function textoHorarioDia($dia)
{
    if ($dia === null || $dia['x'] || $dia['a'] === null || $dia['c'] === null)
    {
        return 'Cerrado';
    }

    return formatoHora($dia['a']) . ' - ' . formatoHora($dia['c']);
}

function obtenerHorarioNegocio($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT dia_semana, hora_apertura, hora_cierre, es_cerrado
         FROM horarios_freelancer
         WHERE id_usuario = :id'
    );
    $sentencia->execute([':id' => $idUsuario]);

    return armarHorario($sentencia->fetchAll());
}

//=============================================================
// Busqueda: una tarjeta por negocio
//=============================================================

function leerTextoGet($origen, $clave)
{
    return isset($origen[$clave]) && is_string($origen[$clave]) ? limpiarEntrada($origen[$clave]) : '';
}

//filtro de ubicacion que manda el buscador del home (latitud, longitud, tipo_ubicacion)
function leerFiltroUbicacion($origen)
{
    $tipo = leerTextoGet($origen, 'tipo_ubicacion');

    if (!in_array($tipo, ['online', 'gps', 'lugar', 'texto'], true))
    {
        $tipo = 'texto';
    }

    $lat = null;
    $lon = null;
    $latCrudo = leerTextoGet($origen, 'latitud');
    $lonCrudo = leerTextoGet($origen, 'longitud');

    if (in_array($tipo, ['gps', 'lugar'], true) && is_numeric($latCrudo) && is_numeric($lonCrudo) && abs((float) $latCrudo) <= 90 && abs((float) $lonCrudo) <= 180)
    {
        $lat = (float) $latCrudo;
        $lon = (float) $lonCrudo;
    }

    if ($lat === null && in_array($tipo, ['gps', 'lugar'], true))
    {
        $tipo = 'texto';
    }

    //con coordenadas siempre hay radio: el elegido en el slider o el predeterminado
    $radio = null;

    if ($lat !== null)
    {
        $radioCrudo = leerTextoGet($origen, 'radio_km');
        $radio = is_numeric($radioCrudo) ? (int) round((float) $radioCrudo) : RADIO_BUSQUEDA_PREDETERMINADO_KM;
        $radio = max(RADIO_BUSQUEDA_MIN_KM, min(RADIO_BUSQUEDA_MAX_KM, $radio));
    }

    return ['tipo' => $tipo, 'lat' => $lat, 'lon' => $lon, 'radio' => $radio, 'texto' => leerTextoGet($origen, 'ubicacion')];
}

//mismos "trucos" de buscarServicios: espacios como comodin y raiz sin la ultima letra
function patronesDeBusqueda($oficio)
{
    $oficio = trim($oficio);
    $escapar = function ($texto) { return addcslashes($texto, '%_\\'); };

    $flexible = '%' . str_replace(' ', '%', $escapar($oficio)) . '%';
    $raiz = mb_strlen($oficio) >= 3 ? '%' . $escapar(mb_substr($oficio, 0, -1)) . '%' : $flexible;

    if (mb_strtolower($oficio) === 'electricista')
    {
        $raiz = '%electricidad%';
    }

    return ['flexible' => $flexible, 'raiz' => $raiz];
}

/*
    Distancia en km entre cobertura_freelancer (cf.latitud_base/longitud_base) y un punto.
    Formula de Haversine: a = sin^2(dLat/2) + cos(lat1) * cos(lat2) * sin^2(dLon/2); d = 2R * asin(sqrt(a)).
    LEAST(1, ...) evita que un error de redondeo saque el valor de ASIN del dominio.
    Cada uso lleva un sufijo distinto porque con sentencias preparadas nativas
    un mismo marcador no puede repetirse en la consulta.
*/
function sqlHaversine($sufijo, $lat, $lon, array &$parametros)
{
    $marcadorLat1 = ':hlat1_' . $sufijo;
    $marcadorLat2 = ':hlat2_' . $sufijo;
    $marcadorLon  = ':hlon_' . $sufijo;

    $parametros[$marcadorLat1] = $lat;
    $parametros[$marcadorLat2] = $lat;
    $parametros[$marcadorLon]  = $lon;

    return '(2 * ' . RADIO_TIERRA_KM . ' * ASIN(SQRT(LEAST(1, '
        . 'POW(SIN(RADIANS(cf.latitud_base - ' . $marcadorLat1 . ') / 2), 2) '
        . '+ COS(RADIANS(' . $marcadorLat2 . ')) * COS(RADIANS(cf.latitud_base)) '
        . '* POW(SIN(RADIANS(cf.longitud_base - ' . $marcadorLon . ') / 2), 2)))))';
}

//cuadro que contiene al circulo de busqueda: permite usar el indice antes de calcular Haversine
function cajaDelimitadora($lat, $lon, $radioKm)
{
    $kmPorGrado = 111.045;
    $deltaLat = $radioKm / $kmPorGrado;
    $deltaLon = $radioKm / ($kmPorGrado * max(0.01, cos(deg2rad($lat))));

    return [
        'min_lat' => max(-90, $lat - $deltaLat),
        'max_lat' => min(90, $lat + $deltaLat),
        'min_lon' => max(-180, $lon - $deltaLon),
        'max_lon' => min(180, $lon + $deltaLon)
    ];
}

/*
    Negocios (freelancers activos) que coinciden con la busqueda.
    Aparece quien tenga un servicio activo o una categoria elegida
    en el wizard "Ofrece tu servicio". Orden: Premium primero, luego
    el mas cercano, luego la mejor valoracion.
*/
function buscarNegocios($oficio, $ubicacion)
{
    $conexion = conexionBD();
    $parametros = [];

    if ($ubicacion['lat'] !== null)
    {
        $distancia = 'CASE WHEN cf.latitud_base IS NULL OR cf.longitud_base IS NULL THEN NULL ELSE '
            . sqlHaversine('d', $ubicacion['lat'], $ubicacion['lon'], $parametros) . ' END';
    }
    else
    {
        $distancia = 'NULL';
    }

    $sql = "SELECT u.id_usuario, u.nombre, u.foto_perfil_url,
                   p.nombre_negocio, p.slug, p.es_empresa, p.perfil_verificado,
                   cf.id_modalidad, cf.ciudad_limite,
                   ($distancia) AS distancia_km,
                   (SELECT AVG(e.calificacion) FROM evaluaciones e
                        INNER JOIN citas ci ON ci.id_cita = e.id_cita
                        INNER JOIN servicios sv ON sv.id_servicio = ci.id_servicio
                        WHERE sv.id_usuario = u.id_usuario) AS promedio,
                   (SELECT COUNT(*) FROM evaluaciones e2
                        INNER JOIN citas ci2 ON ci2.id_cita = e2.id_cita
                        INNER JOIN servicios sv2 ON sv2.id_servicio = ci2.id_servicio
                        WHERE sv2.id_usuario = u.id_usuario) AS total_opiniones,
                   (SELECT COUNT(*) FROM servicios sx WHERE sx.id_usuario = u.id_usuario AND sx.estatus = 1) AS total_servicios,
                   (SELECT MIN(sm.monto) FROM servicios sm WHERE sm.id_usuario = u.id_usuario AND sm.estatus = 1 AND sm.monto > 0) AS precio_desde,
                   EXISTS(SELECT 1 FROM suscripciones su
                        WHERE su.id_usuario = u.id_usuario AND su.id_plan = " . (int) PLAN_PREMIUM . "
                          AND su.estado = " . (int) SUSCRIPCION_ACTIVA . " AND su.fecha_vencimiento > NOW()) AS es_premium
            FROM usuarios u
            LEFT JOIN perfil_freelancer p ON p.id_usuario = u.id_usuario
            LEFT JOIN cobertura_freelancer cf ON cf.id_usuario = u.id_usuario
            WHERE u.rol = " . (int) ROL_FREELANCER . " AND u.estatus = " . (int) ESTATUS_ACTIVO . "
              AND (EXISTS(SELECT 1 FROM servicios sa WHERE sa.id_usuario = u.id_usuario AND sa.estatus = 1)
                   OR EXISTS(SELECT 1 FROM freelancer_categorias fa WHERE fa.id_usuario = u.id_usuario))";

    $oficio = trim($oficio);

    if ($oficio !== '')
    {
        $patrones = patronesDeBusqueda($oficio);

        $sql .= " AND (EXISTS(SELECT 1 FROM servicios sb
                              INNER JOIN cat_categorias cb ON cb.id_categoria = sb.id_categoria
                              WHERE sb.id_usuario = u.id_usuario AND sb.estatus = 1
                                AND (sb.titulo LIKE :f1 OR sb.descripcion LIKE :f2 OR cb.nombre_categoria LIKE :r1))
                    OR EXISTS(SELECT 1 FROM freelancer_categorias fb
                              INNER JOIN cat_categorias cc ON cc.id_categoria = fb.id_categoria
                              WHERE fb.id_usuario = u.id_usuario AND cc.nombre_categoria LIKE :r2)
                    OR p.nombre_negocio LIKE :f3
                    OR u.nombre LIKE :f4)";

        $parametros[':f1'] = $patrones['flexible'];
        $parametros[':f2'] = $patrones['flexible'];
        $parametros[':f3'] = $patrones['flexible'];
        $parametros[':f4'] = $patrones['flexible'];
        $parametros[':r1'] = $patrones['raiz'];
        $parametros[':r2'] = $patrones['raiz'];
    }

    if ($ubicacion['tipo'] === 'online')
    {
        $sql .= ' AND cf.id_modalidad = ' . (int) MODALIDAD_EN_LINEA;
    }
    elseif ($ubicacion['lat'] === null && $ubicacion['texto'] !== '')
    {
        //quien trabaja en linea atiende en cualquier ciudad
        $sql .= ' AND (cf.id_modalidad = ' . (int) MODALIDAD_EN_LINEA . ' OR cf.ciudad_limite LIKE :ciudad)';
        $parametros[':ciudad'] = '%' . addcslashes($ubicacion['texto'], '%_\\') . '%';
    }

    //solo negocios dentro del radio: caja (usa el indice) y despues Haversine exacta
    if ($ubicacion['lat'] !== null)
    {
        $caja = cajaDelimitadora($ubicacion['lat'], $ubicacion['lon'], $ubicacion['radio']);

        $sql .= ' AND cf.latitud_base BETWEEN :min_lat AND :max_lat'
              . ' AND cf.longitud_base BETWEEN :min_lon AND :max_lon'
              . ' AND ' . sqlHaversine('w', $ubicacion['lat'], $ubicacion['lon'], $parametros) . ' <= :radio_km';

        $parametros[':min_lat']  = $caja['min_lat'];
        $parametros[':max_lat']  = $caja['max_lat'];
        $parametros[':min_lon']  = $caja['min_lon'];
        $parametros[':max_lon']  = $caja['max_lon'];
        $parametros[':radio_km'] = $ubicacion['radio'];
    }

    $sql .= ' ORDER BY es_premium DESC, (distancia_km IS NULL), distancia_km ASC, promedio DESC, u.nombre ASC
              LIMIT ' . (int) MAXIMO_NEGOCIOS_LISTADO;

    $sentencia = $conexion->prepare($sql);
    $sentencia->execute($parametros);

    //un usuario con dos filas de cobertura o de perfil saldria duplicado
    $negocios = [];

    foreach ($sentencia->fetchAll() as $fila)
    {
        if (!isset($negocios[$fila['id_usuario']]))
        {
            $fila['horario'] = null;
            $fila['categorias'] = [];
            $negocios[$fila['id_usuario']] = $fila;
        }
    }

    if (empty($negocios))
    {
        return [];
    }

    $ids = array_keys($negocios);
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $horarios = $conexion->prepare(
        "SELECT id_usuario, dia_semana, hora_apertura, hora_cierre, es_cerrado
         FROM horarios_freelancer
         WHERE id_usuario IN ($marcadores)"
    );
    $horarios->execute($ids);

    $filasPorUsuario = [];

    foreach ($horarios->fetchAll() as $fila)
    {
        $filasPorUsuario[$fila['id_usuario']][] = $fila;
    }

    foreach ($filasPorUsuario as $idUsuario => $filas)
    {
        $negocios[$idUsuario]['horario'] = armarHorario($filas);
    }

    $categorias = $conexion->prepare(
        "SELECT t.id_usuario, c.nombre_categoria
         FROM (SELECT id_usuario, id_categoria FROM freelancer_categorias
               UNION
               SELECT id_usuario, id_categoria FROM servicios WHERE estatus = 1) t
         INNER JOIN cat_categorias c ON c.id_categoria = t.id_categoria
         WHERE t.id_usuario IN ($marcadores)
         ORDER BY c.nombre_categoria"
    );
    $categorias->execute($ids);

    foreach ($categorias->fetchAll() as $fila)
    {
        $negocios[$fila['id_usuario']]['categorias'][] = $fila['nombre_categoria'];
    }

    return array_values($negocios);
}

//=============================================================
// Vista publica del perfil
//=============================================================

//se busca por slug; si el negocio aun no tiene, por id
function obtenerNegocioPublico($slug, $idUsuario)
{
    $conexion = conexionBD();

    $sql = 'SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.foto_perfil_url, u.fecha_registro,
                   p.nombre_negocio, p.slug, p.es_empresa, p.perfil_verificado, p.descripcion, p.whatsapp_contacto,
                   cf.id_modalidad, cf.direccion_texto, cf.ciudad_limite, cf.latitud_base, cf.longitud_base, cf.radio_cobertura_km
            FROM usuarios u
            LEFT JOIN perfil_freelancer p ON p.id_usuario = u.id_usuario
            LEFT JOIN cobertura_freelancer cf ON cf.id_usuario = u.id_usuario
            WHERE u.rol = ' . (int) ROL_FREELANCER . ' AND u.estatus = ' . (int) ESTATUS_ACTIVO;

    if ($slug !== '')
    {
        $sentencia = $conexion->prepare($sql . ' AND p.slug = :slug LIMIT 1');
        $sentencia->execute([':slug' => $slug]);
    }
    else
    {
        $sentencia = $conexion->prepare($sql . ' AND u.id_usuario = :id LIMIT 1');
        $sentencia->execute([':id' => $idUsuario]);
    }

    $negocio = $sentencia->fetch();

    return $negocio === false ? null : $negocio;
}

function obtenerServiciosNegocio($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT s.id_servicio, s.titulo, s.descripcion, s.monto, s.id_tipo_precio, s.duracion_minutos, c.nombre_categoria
         FROM servicios s
         INNER JOIN cat_categorias c ON c.id_categoria = s.id_categoria
         WHERE s.id_usuario = :id AND s.estatus = 1
         ORDER BY s.orden, s.id_servicio'
    );
    $sentencia->execute([':id' => $idUsuario]);

    return $sentencia->fetchAll();
}

function obtenerCategoriasNegocio($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT c.nombre_categoria
         FROM (SELECT id_categoria FROM freelancer_categorias WHERE id_usuario = :id1
               UNION
               SELECT id_categoria FROM servicios WHERE id_usuario = :id2 AND estatus = 1) t
         INNER JOIN cat_categorias c ON c.id_categoria = t.id_categoria
         ORDER BY c.nombre_categoria'
    );
    $sentencia->execute([':id1' => $idUsuario, ':id2' => $idUsuario]);

    return array_column($sentencia->fetchAll(), 'nombre_categoria');
}

function obtenerRedesNegocio($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT red_social, url FROM redes_sociales_freelancer WHERE id_usuario = :id ORDER BY id_red');
    $sentencia->execute([':id' => $idUsuario]);

    return $sentencia->fetchAll();
}

//promedio, total y cuantas opiniones hay de cada calificacion
function obtenerResumenOpiniones($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT e.calificacion, COUNT(*) AS total
         FROM evaluaciones e
         INNER JOIN citas ci ON ci.id_cita = e.id_cita
         INNER JOIN servicios s ON s.id_servicio = ci.id_servicio
         WHERE s.id_usuario = :id
         GROUP BY e.calificacion'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $distribucion = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    $total = 0;
    $suma = 0;

    foreach ($sentencia->fetchAll() as $fila)
    {
        $calificacion = (int) $fila['calificacion'];

        if ($calificacion >= 1 && $calificacion <= 5)
        {
            $distribucion[$calificacion] = (int) $fila['total'];
            $total += (int) $fila['total'];
            $suma += $calificacion * (int) $fila['total'];
        }
    }

    return ['promedio' => $total > 0 ? $suma / $total : null, 'total' => $total, 'distribucion' => $distribucion];
}

function obtenerOpiniones($idUsuario, $limite = 20)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT e.calificacion, e.comentario, e.fecha, cli.nombre AS cliente, s.titulo AS servicio
         FROM evaluaciones e
         INNER JOIN citas ci ON ci.id_cita = e.id_cita
         INNER JOIN servicios s ON s.id_servicio = ci.id_servicio
         INNER JOIN usuarios cli ON cli.id_usuario = ci.id_cliente
         WHERE s.id_usuario = :id
         ORDER BY e.fecha DESC
         LIMIT ' . (int) $limite
    );
    $sentencia->execute([':id' => $idUsuario]);

    return $sentencia->fetchAll();
}

/*
    Mapa sin librerias: iframe de OpenStreetMap.
    - Local fisico: marcador exacto.
    - A domicilio: la base es el domicilio del freelancer, asi que NO se marca;
      se muestra la zona aproximada (centro redondeado a ~2 km) con el radio de cobertura.
    - En linea: sin mapa.
*/
function datosMapaNegocio($negocio)
{
    $modalidad = (int) ($negocio['id_modalidad'] ?? 0);
    $lat = $negocio['latitud_base'] !== null ? (float) $negocio['latitud_base'] : null;
    $lon = $negocio['longitud_base'] !== null ? (float) $negocio['longitud_base'] : null;

    if ($modalidad === MODALIDAD_EN_LINEA)
    {
        return ['tipo' => 'online'];
    }

    if ($lat === null || $lon === null)
    {
        $consulta = trim(((string) $negocio['direccion_texto']) . ' ' . ((string) $negocio['ciudad_limite']));

        return [
            'tipo'    => 'sin_coordenadas',
            'enlace'  => $consulta !== '' ? 'https://www.openstreetmap.org/search?query=' . rawurlencode($consulta) : null
        ];
    }

    if ($modalidad === MODALIDAD_DOMICILIO)
    {
        $radioKm = max(1, min(100, (int) $negocio['radio_cobertura_km']));
        $centroLat = round($lat / 0.02) * 0.02;
        $centroLon = round($lon / 0.02) * 0.02;
        $deltaLat = $radioKm / 111;
        $deltaLon = $radioKm / (111 * max(0.2, cos(deg2rad($centroLat))));

        $caja = [$centroLon - $deltaLon, $centroLat - $deltaLat, $centroLon + $deltaLon, $centroLat + $deltaLat];

        return [
            'tipo'  => 'zona',
            'src'   => 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode(implode(',', array_map(function ($n) { return round($n, 5); }, $caja))) . '&layer=mapnik',
            'radio' => $radioKm
        ];
    }

    $delta = 0.006;
    $caja = [$lon - $delta, $lat - $delta, $lon + $delta, $lat + $delta];

    return [
        'tipo'   => 'local',
        'src'    => 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode(implode(',', array_map(function ($n) { return round($n, 5); }, $caja))) . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lon),
        'enlace' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($lat . ',' . $lon)
    ];
}
?>
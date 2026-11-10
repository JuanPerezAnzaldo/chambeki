<?php
/*
    Funciones del panel "Mi Negocio" (user/pages/miNegocio.php).
    Reutiliza las consultas de funcionesPerfil.php y funcionesIdentidad.php
    para no duplicar lectura de datos.
*/

require_once DOCROOT . 'user/includes/funcionesIdentidad.php';

/*
    El catalogo de servicios (alta, edicion, orden) todavia no existe.
    Mientras sea false: la tarjeta del catalogo sale como "Proximamente"
    y los servicios NO cuentan para el porcentaje de completitud (si
    contaran, nadie podria llegar al 100%).
    Cuando exista la pantalla ?accion=catalogo, cambiar a true.
*/
define('CATALOGO_DISPONIBLE', false);

//url de la pagina publica del negocio (la usa el boton "Vista previa")
function urlPaginaPublica($idUsuario, $slug)
{
    if ($slug !== '')
    {
        return URL_BASE . '?accion=profesional&slug=' . rawurlencode($slug);
    }

    return URL_BASE . '?accion=profesional&id=' . (int) $idUsuario;
}

//dias guardados en horarios_freelancer: ['filas' => total, 'laborables' => dias abiertos]
function obtenerResumenHorarios($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT COUNT(*) AS filas, COALESCE(SUM(es_cerrado = 0), 0) AS laborables
         FROM horarios_freelancer
         WHERE id_usuario = :id'
    );
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    return ['filas' => (int) $fila['filas'], 'laborables' => (int) $fila['laborables']];
}

function contarServiciosPublicados($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT COUNT(*) FROM servicios WHERE id_usuario = :id AND estatus = 1');
    $sentencia->execute([':id' => $idUsuario]);

    return (int) $sentencia->fetchColumn();
}

/*
    Porcentaje de completitud del perfil. Cada punto tiene un peso; el
    porcentaje es lo completado entre el total de puntos que aplican.
    Devuelve ['porcentaje' => 0-100, 'completos' => n, 'total' => n,
              'items' => [['titulo', 'detalle', 'completo', 'url'], ...]].
*/
function calcularCompletitudPerfil($datos)
{
    $urlConfig = URL_BASE . '?accion=configuracion';

    $enLinea = (int) $datos['cobertura']['id_modalidad'] === MODALIDAD_EN_LINEA;
    $tieneModalidad = (int) $datos['cobertura']['id_modalidad'] > 0;
    $tieneCoordenadas = $datos['cobertura']['latitud_base'] !== null && $datos['cobertura']['longitud_base'] !== null;

    $puntos = [
        ['peso' => 10, 'titulo' => 'Foto de perfil',          'detalle' => 'Una foto o logotipo genera confianza.',           'completo' => !empty($datos['usuario']['foto_perfil_url']),                       'url' => $urlConfig . '#foto'],
        ['peso' => 10, 'titulo' => 'Nombre comercial',        'detalle' => 'Cómo te encuentran y te reconocen los clientes.',  'completo' => mb_strlen($datos['identidad']['nombre_negocio']) >= 3,              'url' => $urlConfig . '#identidad'],
        ['peso' => 15, 'titulo' => 'Descripción del negocio', 'detalle' => 'Cuenta qué haces y por qué elegirte (mín. 20 caracteres).', 'completo' => mb_strlen($datos['identidad']['descripcion']) >= 20,           'url' => $urlConfig . '#identidad'],
        ['peso' => 10, 'titulo' => 'Banner de portada',       'detalle' => 'La imagen grande de tu página pública.',           'completo' => $datos['banner'] !== null,                                          'url' => $urlConfig . '#banner'],
        ['peso' => 10, 'titulo' => 'Redes sociales',          'detalle' => 'Al menos un enlace a tu red o sitio web.',         'completo' => count($datos['redes']) > 0,                                         'url' => $urlConfig . '#redes'],
        ['peso' => 15, 'titulo' => 'Ubicación y cobertura',   'detalle' => 'Tu punto en el mapa y hasta dónde llegas.',        'completo' => $enLinea || ($tieneModalidad && $tieneCoordenadas),                 'url' => $urlConfig . '#cobertura'],
        ['peso' => 15, 'titulo' => 'Horarios de atención',    'detalle' => 'Qué días y a qué horas atiendes.',                 'completo' => $datos['horarios']['filas'] > 0 && $datos['horarios']['laborables'] > 0, 'url' => $urlConfig . '#horarios']
    ];

    if (CATALOGO_DISPONIBLE)
    {
        $puntos[] = ['peso' => 15, 'titulo' => 'Servicios publicados', 'detalle' => 'Publica al menos un servicio en tu catálogo.', 'completo' => $datos['servicios'] > 0, 'url' => URL_BASE . '?accion=catalogo'];
    }

    $pesoTotal = 0;
    $pesoLogrado = 0;
    $completos = 0;

    foreach ($puntos as $punto)
    {
        $pesoTotal += $punto['peso'];

        if ($punto['completo'])
        {
            $pesoLogrado += $punto['peso'];
            $completos++;
        }
    }

    return [
        'porcentaje' => $pesoTotal > 0 ? (int) round($pesoLogrado * 100 / $pesoTotal) : 0,
        'completos'  => $completos,
        'total'      => count($puntos),
        'items'      => $puntos
    ];
}

//texto corto de los dias que atiende, ej. "Lun a Vie" o "5 días a la semana"
function resumenDiasAtencion($laborables)
{
    if ($laborables <= 0)
    {
        return 'Sin días laborables';
    }

    return $laborables === 1 ? '1 día a la semana' : $laborables . ' días a la semana';
}
?>

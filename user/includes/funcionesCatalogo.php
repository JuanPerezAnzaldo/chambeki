<?php
/*
    Funciones del catalogo de servicios del freelancer
    (user/pages/catalogo.php): alta, edicion, baja, visibilidad, orden
    y galeria de imagenes promocionales.
 
    Tablas: servicios, galeria_freelancer, cat_categorias, cat_tipos_precio.
    Toda operacion recibe el id del usuario de la SESION y comprueba que
    el servicio le pertenezca (RSIS-01). Consultas preparadas (RS-02).
*/
 
require_once DOCROOT . 'user/includes/funcionesPerfil.php';
require_once DOCROOT . 'user/includes/funcionesSocio.php';
 
define('MAX_SERVICIOS_POR_FREELANCER', 30);
define('MAX_IMAGENES_POR_SERVICIO', 6);
define('PESO_MAXIMO_IMAGEN_SERVICIO', 2 * 1024 * 1024); //2MB cada una
define('IMAGEN_SERVICIO_ANCHO_MINIMO', 300);
define('IMAGEN_SERVICIO_ALTO_MINIMO', 200);
define('CARPETA_SERVICIOS', 'user/assets/img/servicios/');
 
//ids de cat_tipos_precio
define('PRECIO_FIJO', 1);
define('PRECIO_POR_HORA', 2);
define('PRECIO_A_COTIZAR', 3);
define('PRECIO_DESDE', 4);
 
//=============================================================
// Consultas
//=============================================================
 
//servicios del freelancer en el orden en que los ve el cliente, con su primera imagen y numero de imagenes
function obtenerCatalogoFreelancer($idUsuario)
{
    $conexion = conexionBD();
 
    $sentencia = $conexion->prepare(
        'SELECT s.id_servicio, s.titulo, s.descripcion, s.monto, s.id_tipo_precio, s.duracion_minutos,
                s.estatus, s.orden, c.nombre_categoria,
                (SELECT g.url_imagen FROM galeria_freelancer g WHERE g.id_servicio = s.id_servicio ORDER BY g.id_imagen LIMIT 1) AS imagen_principal,
                (SELECT COUNT(*) FROM galeria_freelancer g2 WHERE g2.id_servicio = s.id_servicio) AS total_imagenes
         FROM servicios s
         INNER JOIN cat_categorias c ON c.id_categoria = s.id_categoria
         WHERE s.id_usuario = :id
         ORDER BY s.orden, s.id_servicio'
    );
    $sentencia->execute([':id' => $idUsuario]);
 
    return $sentencia->fetchAll();
}
 
//un servicio, solo si es del usuario (si no, null)
function obtenerServicioDelFreelancer($idServicio, $idUsuario)
{
    $conexion = conexionBD();
 
    $sentencia = $conexion->prepare(
        'SELECT id_servicio, id_categoria, id_tipo_precio, titulo, descripcion, monto, duracion_minutos, estatus, orden
         FROM servicios
         WHERE id_servicio = :id AND id_usuario = :usuario
         LIMIT 1'
    );
    $sentencia->execute([':id' => $idServicio, ':usuario' => $idUsuario]);
 
    $servicio = $sentencia->fetch();
 
    return $servicio === false ? null : $servicio;
}
 
function obtenerImagenesServicio($idServicio, $idUsuario)
{
    $conexion = conexionBD();
 
    $sentencia = $conexion->prepare(
        'SELECT id_imagen, url_imagen
         FROM galeria_freelancer
         WHERE id_servicio = :servicio AND id_usuario = :usuario
         ORDER BY id_imagen'
    );
    $sentencia->execute([':servicio' => $idServicio, ':usuario' => $idUsuario]);
 
    return $sentencia->fetchAll();
}
 
function contarServiciosFreelancer($idUsuario)
{
    $conexion = conexionBD();
 
    $sentencia = $conexion->prepare('SELECT COUNT(*) FROM servicios WHERE id_usuario = :id');
    $sentencia->execute([':id' => $idUsuario]);
 
    return (int) $sentencia->fetchColumn();
}
 
function contarCitasServicio($idServicio)
{
    $conexion = conexionBD();
 
    $sentencia = $conexion->prepare('SELECT COUNT(*) FROM citas WHERE id_servicio = :id');
    $sentencia->execute([':id' => $idServicio]);
 
    return (int) $sentencia->fetchColumn();
}
 
/*
    Modalidades de cobro que se ofrecen: Precio fijo, Desde y A cotizar.
    Si el servicio que se edita ya tenia otra (p. ej. "Por hora"), se
    conserva en la lista para no cambiarla sin querer.
*/
function modalidadesCobroDisponibles($idActual = 0)
{
    $permitidas = [PRECIO_FIJO, PRECIO_DESDE, PRECIO_A_COTIZAR];
 
    if ((int) $idActual > 0 && !in_array((int) $idActual, $permitidas, true))
    {
        $permitidas[] = (int) $idActual;
    }
 
    $conexion = conexionBD();
    $filas = $conexion->query('SELECT id_tipo_precio, nombre FROM cat_tipos_precio ORDER BY id_tipo_precio')->fetchAll();
 
    $lista = [];
 
    foreach ($filas as $fila)
    {
        if (in_array((int) $fila['id_tipo_precio'], $permitidas, true))
        {
            $lista[(int) $fila['id_tipo_precio']] = $fila['nombre'];
        }
    }
 
    return $lista;
}
 
//texto del precio igual al del perfil publico: "$500", "Desde $500", "Previa cotización"
function textoPrecioServicio($monto, $idTipoPrecio)
{
    $monto = (float) $monto;
    $tipo = (int) $idTipoPrecio;
 
    if ($tipo === PRECIO_A_COTIZAR)
    {
        return 'Previa cotización';
    }
 
    if ($monto <= 0)
    {
        return 'A cotizar';
    }
 
    $cifra = '$' . number_format($monto, fmod($monto, 1) == 0 ? 0 : 2);
 
    if ($tipo === PRECIO_POR_HORA)
    {
        return $cifra . ' por hora';
    }
 
    return $tipo === PRECIO_DESDE ? 'Desde ' . $cifra : $cifra;
}
 
//=============================================================
// Duracion: se captura en minutos u horas y se guarda en minutos
//=============================================================
 
//90 -> ['cantidad' => 1.5, 'unidad' => 'horas']; 45 -> 45 minutos
function duracionParaFormulario($minutos)
{
    $minutos = (int) $minutos;
 
    if ($minutos > 0 && $minutos % 60 === 0)
    {
        return ['cantidad' => (string) ($minutos / 60), 'unidad' => 'horas'];
    }
 
    return ['cantidad' => $minutos > 0 ? (string) $minutos : '', 'unidad' => 'minutos'];
}
 
//devuelve minutos enteros o null si no es valida
function minutosDesdeDuracion($cantidad, $unidad)
{
    $cantidad = str_replace(',', '.', trim((string) $cantidad));
 
    if (!is_numeric($cantidad))
    {
        return null;
    }
 
    $minutos = (float) $cantidad * ($unidad === 'horas' ? 60 : 1);
 
    return (int) round($minutos);
}
 
//90 -> "1 h 30 min", 45 -> "45 min", 120 -> "2 h"
function textoDuracionServicio($minutos)
{
    $minutos = (int) $minutos;
 
    if ($minutos <= 0)
    {
        return '';
    }
 
    if ($minutos < 60)
    {
        return $minutos . ' min';
    }
 
    $horas = intdiv($minutos, 60);
    $resto = $minutos % 60;
 
    return $resto === 0 ? $horas . ' h' : $horas . ' h ' . $resto . ' min';
}
 
//=============================================================
// Validacion
//=============================================================
 
/*
    $datos: titulo, descripcion, id_categoria, id_tipo_precio, monto,
    duracion_cantidad, duracion_unidad. $categoriasValidas: ids que existen.
    Devuelve [campo => mensaje] (vacio si todo esta bien).
*/
function validarServicio($datos, $categoriasValidas, $modalidadesValidas)
{
    $errores = [];
 
    $largoTitulo = mb_strlen($datos['titulo']);
 
    if ($largoTitulo < 5 || $largoTitulo > 150)
    {
        $errores['titulo'] = 'Escribe el nombre del servicio (5 a 150 caracteres).';
    }
    elseif (($palabra = buscarPalabraProhibida($datos['titulo'])) !== null)
    {
        $errores['titulo'] = 'El nombre contiene contenido no permitido ("' . $palabra . '"). Quitalo para publicar el servicio.';
    }
 
    $largoDescripcion = mb_strlen($datos['descripcion']);
 
    if ($largoDescripcion < 20 || $largoDescripcion > 1000)
    {
        $errores['descripcion'] = 'Describe el servicio en 20 a 1000 caracteres: que incluye, materiales, garantia...';
    }
    elseif (($palabra = buscarPalabraProhibida($datos['descripcion'])) !== null)
    {
        $errores['descripcion'] = 'La descripcion contiene contenido no permitido ("' . $palabra . '"). Quitalo para publicar el servicio.';
    }
 
    if (!in_array((int) $datos['id_categoria'], $categoriasValidas, true))
    {
        $errores['id_categoria'] = 'Elige una categoria de la lista.';
    }
 
    $tipo = (int) $datos['id_tipo_precio'];
 
    if (!in_array($tipo, $modalidadesValidas, true))
    {
        $errores['id_tipo_precio'] = 'Elige como cobras este servicio.';
    }
    elseif ($tipo !== PRECIO_A_COTIZAR)
    {
        $monto = str_replace(',', '.', trim((string) $datos['monto']));
 
        if (!is_numeric($monto) || (float) $monto <= 0 || (float) $monto > 999999.99)
        {
            $errores['monto'] = 'Escribe un monto mayor a 0 (maximo 999,999.99).';
        }
    }
 
    $minutos = minutosDesdeDuracion($datos['duracion_cantidad'], $datos['duracion_unidad']);
 
    if ($minutos === null || $minutos < 5 || $minutos > 10080)
    {
        $errores['duracion'] = 'Indica la duracion estimada: de 5 minutos a 7 dias (168 horas).';
    }
 
    return $errores;
}
 
//monto listo para guardar: "A cotizar" no lleva monto (se guarda 0)
function montoParaGuardar($idTipoPrecio, $monto)
{
    if ((int) $idTipoPrecio === PRECIO_A_COTIZAR)
    {
        return 0.00;
    }
 
    return round((float) str_replace(',', '.', trim((string) $monto)), 2);
}
 
//=============================================================
// Imagenes
//=============================================================
 
//$_FILES['imagenes'] viene "por columnas"; se convierte a una lista de archivos y se descartan los campos vacios
function normalizarArchivosSubidos($archivos)
{
    $lista = [];
 
    if (!is_array($archivos) || !isset($archivos['name']) || !is_array($archivos['name']))
    {
        return $lista;
    }
 
    foreach ($archivos['name'] as $i => $nombre)
    {
        if (($archivos['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
        {
            continue;
        }
 
        $lista[] = [
            'name'     => $nombre,
            'tmp_name' => $archivos['tmp_name'][$i],
            'error'    => $archivos['error'][$i],
            'size'     => $archivos['size'][$i]
        ];
    }
 
    return $lista;
}
 
/*
    Comprueba UNA imagen (tipo real, peso, dimensiones) sin moverla aun.
    Devuelve ['exito' => true, 'extension' => '.jpg'] o ['exito' => false, 'error' => '...'].
*/
function validarImagenServicio($archivo)
{
    if ($archivo['error'] !== UPLOAD_ERR_OK)
    {
        return ['exito' => false, 'error' => 'Una imagen no se subio correctamente. Intenta de nuevo.'];
    }
 
    if ($archivo['size'] > PESO_MAXIMO_IMAGEN_SERVICIO)
    {
        return ['exito' => false, 'error' => 'Cada imagen debe pesar maximo 2MB.'];
    }
 
    $informacion = getimagesize($archivo['tmp_name']);
 
    if ($informacion === false)
    {
        return ['exito' => false, 'error' => 'Uno de los archivos no es una imagen valida.'];
    }
 
    $extensiones = [IMAGETYPE_JPEG => '.jpg', IMAGETYPE_PNG => '.png', IMAGETYPE_WEBP => '.webp'];
 
    if (!isset($extensiones[$informacion[2]]))
    {
        return ['exito' => false, 'error' => 'Solo se aceptan imagenes JPG, PNG o WEBP.'];
    }
 
    if ($informacion[0] < IMAGEN_SERVICIO_ANCHO_MINIMO || $informacion[1] < IMAGEN_SERVICIO_ALTO_MINIMO)
    {
        return ['exito' => false, 'error' => 'Las imagenes deben medir al menos ' . IMAGEN_SERVICIO_ANCHO_MINIMO . ' x ' . IMAGEN_SERVICIO_ALTO_MINIMO . ' pixeles.'];
    }
 
    return ['exito' => true, 'extension' => $extensiones[$informacion[2]]];
}
 
//mueve una imagen ya validada a la carpeta publica y devuelve su ruta relativa (o null si falla)
function moverImagenServicio($archivo, $extension)
{
    $carpeta = DOCROOT . CARPETA_SERVICIOS;
 
    if (!is_dir($carpeta))
    {
        mkdir($carpeta, 0775, true);
    }
 
    $nombre = 'serv_' . bin2hex(random_bytes(8)) . $extension;
 
    if (!move_uploaded_file($archivo['tmp_name'], $carpeta . $nombre))
    {
        return null;
    }
 
    return CARPETA_SERVICIOS . $nombre;
}
 
//solo borra archivos de la carpeta de servicios (nunca una ruta arbitraria)
function eliminarArchivoServicio($rutaRelativa)
{
    $rutaRelativa = (string) $rutaRelativa;
 
    if ($rutaRelativa === '' || strpos($rutaRelativa, CARPETA_SERVICIOS) !== 0 || strpos($rutaRelativa, '..') !== false)
    {
        return;
    }
 
    if (file_exists(DOCROOT . $rutaRelativa))
    {
        unlink(DOCROOT . $rutaRelativa);
    }
}
 
//ruta de la BD -> URL del <img> ('' si no hay)
function urlImagenServicio($ruta)
{
    $ruta = trim((string) $ruta);
 
    if ($ruta === '')
    {
        return '';
    }
 
    return preg_match('#^https?://#i', $ruta) ? $ruta : URL_BASE . ltrim($ruta, '/');
}
 
//=============================================================
// Escritura
//=============================================================
 
/*
    Crea o actualiza un servicio junto con su galeria, todo en una
    transaccion. $idServicio = 0 crea uno nuevo (al final del orden).
    $rutasNuevas: imagenes ya movidas al disco. $quitar: ids de imagen a borrar.
    Devuelve el id del servicio; las imagenes quitadas se borran del disco
    solo despues de confirmar la transaccion.
*/
function guardarServicioConGaleria($idUsuario, $idServicio, $datos, $rutasNuevas, $quitar)
{
    $conexion = conexionBD();
    $archivosABorrar = [];
 
    try
    {
        $conexion->beginTransaction();
 
        $valores = [
            ':categoria'   => (int) $datos['id_categoria'],
            ':tipo'        => (int) $datos['id_tipo_precio'],
            ':titulo'      => $datos['titulo'],
            ':descripcion' => $datos['descripcion'],
            ':monto'       => montoParaGuardar($datos['id_tipo_precio'], $datos['monto']),
            ':duracion'    => minutosDesdeDuracion($datos['duracion_cantidad'], $datos['duracion_unidad']),
            ':estatus'     => !empty($datos['estatus']) ? 1 : 0
        ];
 
        if ($idServicio > 0)
        {
            $sentencia = $conexion->prepare(
                'UPDATE servicios
                 SET id_categoria = :categoria, id_tipo_precio = :tipo, titulo = :titulo, descripcion = :descripcion,
                     monto = :monto, duracion_minutos = :duracion, estatus = :estatus
                 WHERE id_servicio = :id AND id_usuario = :usuario'
            );
            $sentencia->execute($valores + [':id' => $idServicio, ':usuario' => $idUsuario]);
        }
        else
        {
            $siguiente = $conexion->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM servicios WHERE id_usuario = :usuario');
            $siguiente->execute([':usuario' => $idUsuario]);
 
            $sentencia = $conexion->prepare(
                'INSERT INTO servicios (id_usuario, id_categoria, id_tipo_precio, titulo, descripcion, monto, duracion_minutos, estatus, orden)
                 VALUES (:usuario, :categoria, :tipo, :titulo, :descripcion, :monto, :duracion, :estatus, :orden)'
            );
            $sentencia->execute($valores + [':usuario' => $idUsuario, ':orden' => (int) $siguiente->fetchColumn()]);
 
            $idServicio = (int) $conexion->lastInsertId();
        }
 
        //imagenes que el freelancer quito (solo si son de este servicio y de este usuario)
        if (!empty($quitar))
        {
            $buscar = $conexion->prepare('SELECT url_imagen FROM galeria_freelancer WHERE id_imagen = :imagen AND id_servicio = :servicio AND id_usuario = :usuario');
            $borrar = $conexion->prepare('DELETE FROM galeria_freelancer WHERE id_imagen = :imagen AND id_servicio = :servicio AND id_usuario = :usuario');
 
            foreach ($quitar as $idImagen)
            {
                $parametros = [':imagen' => (int) $idImagen, ':servicio' => $idServicio, ':usuario' => $idUsuario];
 
                $buscar->execute($parametros);
                $ruta = $buscar->fetchColumn();
 
                if ($ruta !== false)
                {
                    $borrar->execute($parametros);
                    $archivosABorrar[] = $ruta;
                }
            }
        }
 
        if (!empty($rutasNuevas))
        {
            $insertar = $conexion->prepare('INSERT INTO galeria_freelancer (id_usuario, id_servicio, url_imagen) VALUES (:usuario, :servicio, :ruta)');
 
            foreach ($rutasNuevas as $ruta)
            {
                $insertar->execute([':usuario' => $idUsuario, ':servicio' => $idServicio, ':ruta' => $ruta]);
            }
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
 
    foreach ($archivosABorrar as $ruta)
    {
        eliminarArchivoServicio($ruta);
    }
 
    return $idServicio;
}
 
function cambiarEstatusServicio($idServicio, $idUsuario, $activo)
{
    $conexion = conexionBD();
 
    $sentencia = $conexion->prepare('UPDATE servicios SET estatus = :estatus WHERE id_servicio = :id AND id_usuario = :usuario');
    $sentencia->execute([':estatus' => $activo ? 1 : 0, ':id' => $idServicio, ':usuario' => $idUsuario]);
 
    return $sentencia->rowCount() > 0;
}
 
/*
    Sube o baja un servicio una posicion. Se vuelve a numerar toda la
    lista (1..n) para que nunca queden numeros repetidos ni huecos.
*/
function moverServicio($idServicio, $idUsuario, $direccion)
{
    $conexion = conexionBD();
 
    try
    {
        $conexion->beginTransaction();
 
        $lista = $conexion->prepare('SELECT id_servicio FROM servicios WHERE id_usuario = :usuario ORDER BY orden, id_servicio FOR UPDATE');
        $lista->execute([':usuario' => $idUsuario]);
        $ids = array_map('intval', $lista->fetchAll(PDO::FETCH_COLUMN));
 
        $posicion = array_search((int) $idServicio, $ids, true);
        $destino = $direccion === 'arriba' ? $posicion - 1 : $posicion + 1;
 
        if ($posicion === false || $destino < 0 || $destino >= count($ids))
        {
            $conexion->rollBack();
 
            return false;
        }
 
        $intercambio = $ids[$posicion];
        $ids[$posicion] = $ids[$destino];
        $ids[$destino] = $intercambio;
 
        $actualizar = $conexion->prepare('UPDATE servicios SET orden = :orden WHERE id_servicio = :id AND id_usuario = :usuario');
 
        foreach ($ids as $indice => $id)
        {
            $actualizar->execute([':orden' => $indice + 1, ':id' => $id, ':usuario' => $idUsuario]);
        }
 
        $conexion->commit();
 
        return true;
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
 
/*
    Elimina un servicio y sus imagenes. Si ya tiene citas NO se borra
    (la BD borraria tambien las citas y sus opiniones en cascada): en ese
    caso se debe ocultar. Devuelve 'eliminado', 'con_citas' o 'no_existe'.
*/
function eliminarServicioCompleto($idServicio, $idUsuario)
{
    if (obtenerServicioDelFreelancer($idServicio, $idUsuario) === null)
    {
        return 'no_existe';
    }
 
    if (contarCitasServicio($idServicio) > 0)
    {
        return 'con_citas';
    }
 
    $conexion = conexionBD();
 
    try
    {
        $conexion->beginTransaction();
 
        $rutas = $conexion->prepare('SELECT url_imagen FROM galeria_freelancer WHERE id_servicio = :servicio AND id_usuario = :usuario');
        $rutas->execute([':servicio' => $idServicio, ':usuario' => $idUsuario]);
        $archivos = $rutas->fetchAll(PDO::FETCH_COLUMN);
 
        $conexion->prepare('DELETE FROM galeria_freelancer WHERE id_servicio = :servicio AND id_usuario = :usuario')
                 ->execute([':servicio' => $idServicio, ':usuario' => $idUsuario]);
 
        $conexion->prepare('DELETE FROM servicios WHERE id_servicio = :servicio AND id_usuario = :usuario')
                 ->execute([':servicio' => $idServicio, ':usuario' => $idUsuario]);
 
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
 
    foreach ($archivos as $ruta)
    {
        eliminarArchivoServicio($ruta);
    }
 
    return 'eliminado';
}
?>
<?php
/*
    Funciones del wizard "Ofrece tu servicio" (Cliente -> Freelancer).
    Se incluye desde user/pages/registroSocio.php.
    Depende de functions.php y de funcionesPerfil.php (CSRF, filtro de
    palabras prohibidas, catalogo de modalidades).
    Todas las consultas son preparadas (RS-02).
*/

require_once DOCROOT . 'user/includes/funcionesPerfil.php';

define('MAXIMO_CATEGORIAS_SOCIO', 5);
define('RADIO_COBERTURA_INICIAL_KM', 10);

//ids de cat_modalidad
define('MODALIDAD_LOCAL',     1);
define('MODALIDAD_DOMICILIO', 2);
define('MODALIDAD_EN_LINEA',  3);

//=============================================================
// Catalogo
//=============================================================

function obtenerCategorias()
{
    $conexion = conexionBD();

    return $conexion->query('SELECT id_categoria, nombre_categoria FROM cat_categorias ORDER BY nombre_categoria')->fetchAll();
}

//=============================================================
// Slug del perfil publico
//=============================================================

function generarSlugBase($texto)
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', normalizarTexto($texto));
    $slug = trim(substr(trim($slug, '-'), 0, 60), '-');

    return $slug !== '' ? $slug : 'profesional';
}

function slugDisponible($slug)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT 1 FROM perfil_freelancer WHERE slug = :slug LIMIT 1');
    $sentencia->execute([':slug' => $slug]);

    return $sentencia->fetchColumn() === false;
}

//plomeria-lopez, plomeria-lopez-2, plomeria-lopez-3...
function generarSlugUnico($nombreNegocio)
{
    $base = generarSlugBase($nombreNegocio);

    if (slugDisponible($base))
    {
        return $base;
    }

    for ($i = 2; $i <= 20; $i++)
    {
        if (slugDisponible($base . '-' . $i))
        {
            return $base . '-' . $i;
        }
    }

    return $base . '-' . bin2hex(random_bytes(3));
}

//=============================================================
// Validacion de servidor (RS-05): se repite todo lo del navegador
//=============================================================

function modalidadRequiereCiudad($idModalidad)
{
    return in_array((int) $idModalidad, [MODALIDAD_LOCAL, MODALIDAD_DOMICILIO], true);
}

function modalidadRequiereDireccion($idModalidad)
{
    return (int) $idModalidad === MODALIDAD_LOCAL;
}

function validarDatosSocio($datos, $modalidades, $categorias)
{
    $errores = [];

    //paso 1: negocio
    $largoNombre = mb_strlen($datos['nombre_negocio']);

    if ($largoNombre < 3 || $largoNombre > 150)
    {
        $errores['nombre_negocio'] = 'Escribe el nombre de tu negocio o tu nombre profesional (3 a 150 caracteres).';
    }
    elseif (($palabra = buscarPalabraProhibida($datos['nombre_negocio'])) !== null)
    {
        $errores['nombre_negocio'] = 'El nombre contiene contenido no permitido ("' . $palabra . '").';
    }

    $largoDescripcion = mb_strlen($datos['descripcion']);

    if ($largoDescripcion < 20 || $largoDescripcion > 600)
    {
        $errores['descripcion'] = 'Describe tu servicio en 20 a 600 caracteres.';
    }
    elseif (($palabra = buscarPalabraProhibida($datos['descripcion'])) !== null)
    {
        $errores['descripcion'] = 'Tu descripcion contiene contenido no permitido ("' . $palabra . '"). Quitalo para continuar.';
    }

    //paso 2: categorias
    $idsCategoriasValidos = array_map('intval', array_column($categorias, 'id_categoria'));

    if (count($datos['categorias']) < 1)
    {
        $errores['categorias'] = 'Elige al menos una categoria.';
    }
    elseif (count($datos['categorias']) > MAXIMO_CATEGORIAS_SOCIO)
    {
        $errores['categorias'] = 'Puedes elegir hasta ' . MAXIMO_CATEGORIAS_SOCIO . ' categorias.';
    }
    elseif (array_diff($datos['categorias'], $idsCategoriasValidos) !== [])
    {
        $errores['categorias'] = 'Una de las categorias elegidas no es valida.';
    }

    //paso 3: modalidad
    $idsModalidadesValidos = array_map('intval', array_column($modalidades, 'id_modalidad'));

    if (!in_array((int) $datos['id_modalidad'], $idsModalidadesValidos, true))
    {
        $errores['id_modalidad'] = 'Elige como ofreces tu servicio.';
    }
    else
    {
        if (modalidadRequiereCiudad($datos['id_modalidad']) && (mb_strlen($datos['ciudad_limite']) < 2 || mb_strlen($datos['ciudad_limite']) > 100))
        {
            $errores['ciudad_limite'] = 'Escribe la ciudad donde trabajas (2 a 100 caracteres).';
        }

        if (modalidadRequiereDireccion($datos['id_modalidad']) && (mb_strlen($datos['direccion_texto']) < 5 || mb_strlen($datos['direccion_texto']) > 255))
        {
            $errores['direccion_texto'] = 'Escribe la direccion de tu local (5 a 255 caracteres).';
        }
    }

    //paso 4: contacto
    if (strlen(preg_replace('/[^0-9]/', '', $datos['telefono'])) !== 10)
    {
        $errores['telefono'] = 'El telefono debe tener 10 digitos.';
    }

    return $errores;
}

//numero de paso (1 a 4) donde esta cada error, para reabrir el wizard ahi
function pasoDelError($campo)
{
    $pasos = [
        'nombre_negocio'  => 1,
        'descripcion'     => 1,
        'categorias'      => 2,
        'id_modalidad'    => 3,
        'ciudad_limite'   => 3,
        'direccion_texto' => 3,
        'telefono'        => 4
    ];

    return $pasos[$campo] ?? 1;
}

//=============================================================
// Alta del freelancer (una sola transaccion: todo o nada)
//=============================================================

//devuelve ['exito' => true, 'slug' => '...'] o ['exito' => false, 'error' => '...']
function registrarSocio($idUsuario, $datos)
{
    $conexion = conexionBD();
    $intentos = 0;

    while (true)
    {
        $slug = $intentos === 0
            ? generarSlugUnico($datos['nombre_negocio'])
            : generarSlugBase($datos['nombre_negocio']) . '-' . bin2hex(random_bytes(3));

        try
        {
            $conexion->beginTransaction();

            //1) bloquear al usuario y confirmar que sigue siendo Cliente
            $bloqueo = $conexion->prepare('SELECT rol FROM usuarios WHERE id_usuario = :id FOR UPDATE');
            $bloqueo->execute([':id' => $idUsuario]);
            $fila = $bloqueo->fetch();

            if ($fila === false || (int) $fila['rol'] !== ROL_CLIENTE)
            {
                $conexion->rollBack();
                return ['exito' => false, 'error' => 'Esta cuenta ya no puede cambiar a freelancer.'];
            }

            //2) cambio de rol
            $conexion->prepare('UPDATE usuarios SET rol = :rol, fecha_actualizacion = NOW() WHERE id_usuario = :id')
                ->execute([':rol' => ROL_FREELANCER, ':id' => $idUsuario]);

            //3) perfil profesional (si ya habia uno de antes se conserva su slug)
            $existe = $conexion->prepare('SELECT id_perfil, slug FROM perfil_freelancer WHERE id_usuario = :id LIMIT 1');
            $existe->execute([':id' => $idUsuario]);
            $perfil = $existe->fetch();

            $parametrosPerfil = [
                ':nombre'      => $datos['nombre_negocio'],
                ':empresa'     => $datos['es_empresa'] ? 1 : 0,
                ':descripcion' => $datos['descripcion'],
                ':whatsapp'    => preg_replace('/[^0-9]/', '', $datos['telefono']),
                ':id'          => $idUsuario
            ];

            if ($perfil !== false)
            {
                if (!empty($perfil['slug']))
                {
                    $slug = $perfil['slug'];
                }

                $parametrosPerfil[':slug'] = $slug;

                $conexion->prepare(
                    'UPDATE perfil_freelancer
                     SET nombre_negocio = :nombre, slug = :slug, es_empresa = :empresa,
                         descripcion = :descripcion, whatsapp_contacto = :whatsapp
                     WHERE id_usuario = :id'
                )->execute($parametrosPerfil);
            }
            else
            {
                $parametrosPerfil[':slug'] = $slug;

                $conexion->prepare(
                    'INSERT INTO perfil_freelancer (id_usuario, nombre_negocio, slug, es_empresa, descripcion, whatsapp_contacto)
                     VALUES (:id, :nombre, :slug, :empresa, :descripcion, :whatsapp)'
                )->execute($parametrosPerfil);
            }

            //4) categorias de servicio
            $conexion->prepare('DELETE FROM freelancer_categorias WHERE id_usuario = :id')->execute([':id' => $idUsuario]);

            $insertarCategoria = $conexion->prepare('INSERT INTO freelancer_categorias (id_usuario, id_categoria) VALUES (:id, :categoria)');

            foreach ($datos['categorias'] as $idCategoria)
            {
                $insertarCategoria->execute([':id' => $idUsuario, ':categoria' => (int) $idCategoria]);
            }

            //5) modalidad y zona de trabajo (latitud/longitud los llena el mapa despues)
            $requiereCiudad    = modalidadRequiereCiudad($datos['id_modalidad']);
            $requiereDireccion = modalidadRequiereDireccion($datos['id_modalidad']);

            $parametrosCobertura = [
                ':modalidad' => (int) $datos['id_modalidad'],
                ':direccion' => $requiereDireccion ? $datos['direccion_texto'] : null,
                ':ciudad'    => $requiereCiudad ? $datos['ciudad_limite'] : null,
                ':radio'     => RADIO_COBERTURA_INICIAL_KM,
                ':id'        => $idUsuario
            ];

            $existeCobertura = $conexion->prepare('SELECT id_cobertura FROM cobertura_freelancer WHERE id_usuario = :id LIMIT 1');
            $existeCobertura->execute([':id' => $idUsuario]);

            if ($existeCobertura->fetch() !== false)
            {
                $conexion->prepare(
                    'UPDATE cobertura_freelancer
                     SET id_modalidad = :modalidad, direccion_texto = :direccion,
                         ciudad_limite = :ciudad, radio_cobertura_km = :radio
                     WHERE id_usuario = :id'
                )->execute($parametrosCobertura);
            }
            else
            {
                $conexion->prepare(
                    'INSERT INTO cobertura_freelancer (id_usuario, id_modalidad, direccion_texto, ciudad_limite, radio_cobertura_km)
                     VALUES (:id, :modalidad, :direccion, :ciudad, :radio)'
                )->execute($parametrosCobertura);
            }

            $conexion->commit();

            return ['exito' => true, 'slug' => $slug];
        }
        catch (\PDOException $excepcion)
        {
            if ($conexion->inTransaction())
            {
                $conexion->rollBack();
            }

            //otro usuario tomo el mismo slug en este instante: se reintenta con sufijo aleatorio
            $slugDuplicado = $excepcion->getCode() === '23000' && stripos($excepcion->getMessage(), 'slug') !== false;

            if ($slugDuplicado && $intentos < 3)
            {
                $intentos++;
                continue;
            }

            error_log('registrarSocio: ' . $excepcion->getMessage());

            return ['exito' => false, 'error' => 'No pudimos guardar tu perfil. Intenta de nuevo en un momento.'];
        }
    }
}
?>
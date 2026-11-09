<?php
/*
    FUNCIONES DEL MODULO DE CORREOS INSTITUCIONALES (/admin/correos)

    El envio real lo hace shared/includes/mailer.php (tipo 'oficial', con la
    identidad visual de CHAMBEKI). Aqui viven las plantillas de redaccion, la
    busqueda de destinatarios y el historial (tabla correos_log).
*/

const CORREOS_MAX_DESTINATARIOS = 25;   //tope por envio (cada correo es una conexion SMTP)
const CORREOS_POR_PAGINA        = 20;

//plantillas de redaccion. {nombre} se reemplaza por el nombre de cada destinatario
function plantillasCorreo()
{
    return [
        'aclaracion' => [
            'etiqueta' => 'Solicitud de aclaración de documentos',
            'asunto'   => 'Aclaración sobre tus documentos de verificación',
            'mensaje'  => "Revisamos los documentos que cargaste para verificar tu perfil y necesitamos una aclaración.\n\nDetalle: [describe qué documento y qué falta o no es legible]\n\nPor favor vuelve a cargarlo desde tu perfil o responde a este correo en un plazo de 5 días hábiles.\n\nGracias por tu apoyo."
        ],
        'suspension' => [
            'etiqueta' => 'Aviso de suspensión preventiva',
            'asunto'   => 'Aviso: suspensión preventiva de tu cuenta',
            'mensaje'  => "Tu cuenta ha sido suspendida de forma preventiva mientras revisamos una incidencia relacionada con tu actividad en la plataforma.\n\nMotivo: [describe el motivo]\n\nDurante la revisión no podrás recibir nuevas solicitudes. Si consideras que se trata de un error, responde a este correo con tus comentarios y evidencia."
        ],
        'aviso' => [
            'etiqueta' => 'Aviso general del sistema',
            'asunto'   => 'Aviso importante de CHAMBEKI',
            'mensaje'  => "Queremos informarte lo siguiente:\n\n[escribe aquí el aviso]\n\nGracias por ser parte de CHAMBEKI."
        ],
        'reporte' => [
            'etiqueta' => 'Resolución de reporte',
            'asunto'   => 'Resolución de tu reporte en CHAMBEKI',
            'mensaje'  => "Hemos concluido la revisión del reporte relacionado con tu cuenta.\n\nResolución: [resume la resolución y las acciones tomadas]\n\nSi tienes preguntas sobre esta decisión, responde a este correo."
        ]
    ];
}

function etiquetaPlantillaCorreo($clave)
{
    $plantillas = plantillasCorreo();
    return $plantillas[strtolower((string) $clave)]['etiqueta'] ?? (string) $clave;
}

//clientes y freelancers a los que el personal puede escribir (nunca empleados)
function listarDestinatariosCorreo()
{
    $conexion = conexionBD();

    return $conexion->query(
        'SELECT id_usuario, nombre, correo, rol, estatus
         FROM usuarios
         WHERE rol IN (2, 3)
         ORDER BY rol ASC, nombre ASC
         LIMIT 1000'
    )->fetchAll();
}

//valida los ids recibidos contra la BD y devuelve solo cuentas reales y enviables
function destinatariosPorIds($ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), function ($id) { return $id > 0; })));

    if (empty($ids))
    {
        return [];
    }

    $conexion = conexionBD();
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $sentencia = $conexion->prepare(
        "SELECT id_usuario, nombre, correo, rol FROM usuarios WHERE rol IN (2, 3) AND id_usuario IN ($marcadores)"
    );
    $sentencia->execute($ids);

    return $sentencia->fetchAll();
}

function validarCorreoOficial($plantilla, $asunto, $mensaje, $cantidadDestinatarios)
{
    if ($cantidadDestinatarios < 1)
    {
        return 'Selecciona al menos un destinatario.';
    }

    if ($cantidadDestinatarios > CORREOS_MAX_DESTINATARIOS)
    {
        return 'Puedes enviar a un máximo de ' . CORREOS_MAX_DESTINATARIOS . ' destinatarios a la vez.';
    }

    if (!isset(plantillasCorreo()[$plantilla]))
    {
        return 'La plantilla seleccionada no es válida.';
    }

    if ($asunto === '' || mb_strlen($asunto) > 150)
    {
        return 'El asunto es obligatorio y no puede pasar de 150 caracteres.';
    }

    if ($mensaje === '' || mb_strlen($mensaje) > 5000)
    {
        return 'El mensaje es obligatorio y no puede pasar de 5000 caracteres.';
    }

    return null;
}

/*
    Envia un correo por destinatario (cada uno recibe su nombre) y registra en
    correos_log solo los que el servidor SMTP acepto.
    Devuelve ['enviados' => int, 'fallidos' => [['nombre'=>..., 'error'=>...], ...]]
*/
function enviarCorreoOficial($admin, $destinatarios, $plantilla, $asunto, $mensaje)
{
    $conexion = conexionBD();
    $registro = $conexion->prepare(
        'INSERT INTO correos_log (id_admin, id_usuario_destino, plantilla, asunto, mensaje, fecha_envio)
         VALUES (:admin, :destino, :plantilla, :asunto, :mensaje, NOW())'
    );

    $enviados = 0;
    $fallidos = [];

    @set_time_limit(120);

    foreach ($destinatarios as $destino)
    {
        $asuntoFinal  = str_replace('{nombre}', $destino['nombre'], $asunto);
        $mensajeFinal = str_replace('{nombre}', $destino['nombre'], $mensaje);

        $resultado = enviarCorreo('oficial', $destino['correo'], $destino['nombre'], [
            'plantilla' => $plantilla,
            'asunto'    => $asuntoFinal,
            'mensaje'   => $mensajeFinal
        ]);

        if (!empty($resultado['exito']))
        {
            $registro->execute([
                ':admin'     => $admin['id_usuario'],
                ':destino'   => $destino['id_usuario'],
                ':plantilla' => $plantilla,
                ':asunto'    => mb_substr($asuntoFinal, 0, 150),
                ':mensaje'   => $mensajeFinal
            ]);

            registrarEnBitacora($admin['correo'], 'admin_correo_enviado:' . $destino['id_usuario']);
            $enviados++;
        }
        else
        {
            $fallidos[] = ['nombre' => $destino['nombre'], 'error' => $resultado['error'] ?? 'Error desconocido'];
        }
    }

    return ['enviados' => $enviados, 'fallidos' => $fallidos];
}

//historial paginado. filtros: q (destinatario, asunto o emisor), plantilla
function historialCorreos($filtros, $pagina)
{
    $conexion = conexionBD();

    $donde = ' WHERE 1=1';
    $parametros = [];

    if ($filtros['plantilla'] !== '' && isset(plantillasCorreo()[$filtros['plantilla']]))
    {
        $donde .= ' AND c.plantilla = :plantilla';
        $parametros[':plantilla'] = $filtros['plantilla'];
    }

    if ($filtros['q'] !== '')
    {
        $donde .= ' AND (dest.nombre LIKE :q1 OR dest.correo LIKE :q2 OR c.asunto LIKE :q3 OR adm.nombre LIKE :q4)';
        $texto = '%' . $filtros['q'] . '%';
        $parametros[':q1'] = $parametros[':q2'] = $parametros[':q3'] = $parametros[':q4'] = $texto;
    }

    $desde = ' FROM correos_log c
               JOIN usuarios dest ON c.id_usuario_destino = dest.id_usuario
               JOIN usuarios adm  ON c.id_admin = adm.id_usuario';

    $sentenciaTotal = $conexion->prepare('SELECT COUNT(*)' . $desde . $donde);
    $sentenciaTotal->execute($parametros);
    $total = (int) $sentenciaTotal->fetchColumn();

    $totalPaginas = max(1, (int) ceil($total / CORREOS_POR_PAGINA));
    $pagina = min(max(1, (int) $pagina), $totalPaginas);
    $inicio = ($pagina - 1) * CORREOS_POR_PAGINA;

    $sentencia = $conexion->prepare(
        'SELECT c.id_log, c.plantilla, c.asunto, c.mensaje, c.fecha_envio,
                dest.id_usuario AS id_destino, dest.nombre AS destino_nombre, dest.correo AS destino_correo,
                adm.nombre AS admin_nombre'
        . $desde . $donde .
        ' ORDER BY c.fecha_envio DESC, c.id_log DESC LIMIT ' . CORREOS_POR_PAGINA . ' OFFSET ' . $inicio
    );
    $sentencia->execute($parametros);

    return [
        'filas'        => $sentencia->fetchAll(),
        'total'        => $total,
        'pagina'       => $pagina,
        'totalPaginas' => $totalPaginas
    ];
}

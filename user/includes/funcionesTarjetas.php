<?php
/*
    Funciones de tarjetas de crédito / débito del perfil (Stripe).
    Se incluye desde user/pages/perfil.php y user/pages/apiTarjetaIntent.php
    con require_once. Depende de functions.php (conexionBD, registrarEnBitacora).

    COMO SE PROTEGEN LOS DATOS:
      1. El número completo, fecha y CVC los captura Stripe Elements en un iframe
         de Stripe. NUNCA pasan por este servidor ni se guardan en la BD.
      2. Stripe devuelve un token (pm_...). Ese token se guarda CIFRADO con
         AES-256-GCM en tarjetas_cliente.token_pasarela. La llave de cifrado vive
         en las variables de entorno / secrets de Replit o en secretos.local.php.
      3. En claro solo se guardan marca, últimos 4 dígitos y vencimiento.
*/

define('MAXIMO_TARJETAS_USUARIO', 5);
define('ANIOS_VIGENCIA_TARJETA', 20);

//=============================================================
// Configuración y secretos
//=============================================================

function secretoConfig($nombre)
{
    static $locales = null;

    if ($locales === null)
    {
        $archivo = DOCROOT . 'shared/includes/secretos.local.php';
        $locales = file_exists($archivo) ? (array) include $archivo : [];
    }

    $valor = getenv($nombre);

    if ($valor !== false && $valor !== '')
    {
        return (string) $valor;
    }

    return isset($locales[$nombre]) ? (string) $locales[$nombre] : '';
}

function clavePublicaStripe()
{
    return secretoConfig('STRIPE_PUBLISHABLE_KEY');
}

function pagosConTarjetaDisponibles()
{
    return clavePublicaStripe() !== ''
        && secretoConfig('STRIPE_SECRET_KEY') !== ''
        && llaveCifradoTarjetas() !== null
        && file_exists(DOCROOT . 'vendor/autoload.php');
}

function clienteStripe()
{
    static $cliente = null;

    if ($cliente !== null)
    {
        return $cliente;
    }

    require_once DOCROOT . 'vendor/autoload.php';

    $cliente = new \Stripe\StripeClient(secretoConfig('STRIPE_SECRET_KEY'));

    return $cliente;
}

//=============================================================
// Cifrado de datos de pago (AES-256-GCM)
//=============================================================

function llaveCifradoTarjetas()
{
    $secreto = secretoConfig('CHAMBEKI_CLAVE_CIFRADO');

    if (strlen($secreto) < 32)
    {
        return null;
    }

    return hash_hkdf('sha256', $secreto, 32, 'chambeki-tarjetas-v1');
}

function contextoCifradoTarjeta($idUsuario)
{
    return 'tarjeta:' . (int) $idUsuario;
}

function cifrarDatoPago($textoPlano, $contexto)
{
    $llave = llaveCifradoTarjetas();

    if ($llave === null)
    {
        throw new RuntimeException('Falta la llave de cifrado de tarjetas.');
    }

    $iv = random_bytes(12);
    $etiqueta = '';

    $cifrado = openssl_encrypt($textoPlano, 'aes-256-gcm', $llave, OPENSSL_RAW_DATA, $iv, $etiqueta, $contexto, 16);

    if ($cifrado === false)
    {
        throw new RuntimeException('No se pudo cifrar el dato de pago.');
    }

    return 'v1:' . base64_encode($iv . $etiqueta . $cifrado);
}

function descifrarDatoPago($paquete, $contexto)
{
    $llave = llaveCifradoTarjetas();

    if ($llave === null || strpos((string) $paquete, 'v1:') !== 0)
    {
        return null;
    }

    $binario = base64_decode(substr($paquete, 3), true);

    if ($binario === false || strlen($binario) <= 28)
    {
        return null;
    }

    $iv = substr($binario, 0, 12);
    $etiqueta = substr($binario, 12, 16);
    $cifrado = substr($binario, 28);

    $textoPlano = openssl_decrypt($cifrado, 'aes-256-gcm', $llave, OPENSSL_RAW_DATA, $iv, $etiqueta, $contexto);

    return $textoPlano === false ? null : $textoPlano;
}

//=============================================================
// Consultas BD (tabla tarjetas_cliente)
//=============================================================

function obtenerTarjetas($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_tarjeta, ultimos_4_digitos, marca, exp_mes, exp_anio, es_predeterminado, fecha_registro
         FROM tarjetas_cliente
         WHERE id_usuario = :id
         ORDER BY es_predeterminado DESC, id_tarjeta DESC'
    );
    $sentencia->execute([':id' => $idUsuario]);

    return $sentencia->fetchAll();
}

function obtenerTarjetaDeUsuario($idTarjeta, $idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare(
        'SELECT id_tarjeta, token_pasarela, ultimos_4_digitos, marca, exp_mes, exp_anio, es_predeterminado
         FROM tarjetas_cliente
         WHERE id_tarjeta = :tarjeta AND id_usuario = :usuario
         LIMIT 1'
    );
    $sentencia->execute([':tarjeta' => $idTarjeta, ':usuario' => $idUsuario]);

    $tarjeta = $sentencia->fetch();

    return $tarjeta === false ? null : $tarjeta;
}

function contarTarjetas($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT COUNT(*) AS total FROM tarjetas_cliente WHERE id_usuario = :id');
    $sentencia->execute([':id' => $idUsuario]);

    return (int) $sentencia->fetch()['total'];
}

function obtenerIdClienteStripe($idUsuario)
{
    $conexion = conexionBD();

    $sentencia = $conexion->prepare('SELECT stripe_customer_id FROM usuarios WHERE id_usuario = :id LIMIT 1');
    $sentencia->execute([':id' => $idUsuario]);

    $fila = $sentencia->fetch();

    return ($fila === false || empty($fila['stripe_customer_id'])) ? null : $fila['stripe_customer_id'];
}

//=============================================================
// Formato y Presentación
//=============================================================

function nombreMarcaTarjeta($marca)
{
    $nombres = [
        'visa'       => 'Visa',
        'mastercard' => 'Mastercard',
        'amex'       => 'American Express',
        'discover'   => 'Discover',
        'diners'     => 'Diners Club',
        'jcb'        => 'JCB',
        'unionpay'   => 'UnionPay'
    ];

    return $nombres[strtolower((string) $marca)] ?? 'Tarjeta';
}

function claseMarcaTarjeta($marca)
{
    $conocidas = ['visa', 'mastercard', 'amex', 'discover', 'diners', 'jcb', 'unionpay'];
    $marca = strtolower((string) $marca);

    return in_array($marca, $conocidas, true) ? $marca : 'generica';
}

function tarjetaVencida($mes, $anio)
{
    return ((int) $anio * 12 + (int) $mes) < ((int) date('Y') * 12 + (int) date('n'));
}

function formatoVencimiento($mes, $anio)
{
    return str_pad((string) (int) $mes, 2, '0', STR_PAD_LEFT) . '/' . substr((string) (int) $anio, -2);
}

function validarVencimientoTarjeta($mes, $anio)
{
    $mes = (int) $mes;
    $anio = (int) $anio;
    $anioActual = (int) date('Y');

    if ($mes < 1 || $mes > 12)
    {
        return 'Elige un mes de vencimiento valido.';
    }

    if ($anio < $anioActual || $anio > $anioActual + ANIOS_VIGENCIA_TARJETA)
    {
        return 'Elige un anio de vencimiento valido.';
    }

    if (tarjetaVencida($mes, $anio))
    {
        return 'Esa fecha ya paso. Escribe el vencimiento vigente de tu tarjeta.';
    }

    return null;
}

//=============================================================
// Stripe: Clientes, SetupIntents y Alta
//=============================================================

function obtenerOCrearClienteStripe($usuario)
{
    $idUsuario = (int) $usuario['id_usuario'];
    $idCliente = obtenerIdClienteStripe($idUsuario);

    if ($idCliente !== null)
    {
        try
        {
            // Verificamos que el cliente exista en la cuenta actual de Stripe
            clienteStripe()->customers->retrieve($idCliente);
            return $idCliente;
        }
        catch (\Throwable $excepcion)
        {
            // Si el cliente no existe en esta cuenta/entorno, creamos uno nuevo
            $idCliente = null;
        }
    }

    $cliente = clienteStripe()->customers->create([
        'email'    => $usuario['correo'],
        'name'     => $usuario['nombre'],
        'metadata' => ['id_usuario' => (string) $idUsuario]
    ]);

    $conexion = conexionBD();
    $sentencia = $conexion->prepare('UPDATE usuarios SET stripe_customer_id = :cliente WHERE id_usuario = :id');
    $sentencia->execute([':cliente' => $cliente->id, ':id' => $idUsuario]);

    return $cliente->id;
}

function crearSetupIntentTarjeta($usuario)
{
    $intento = clienteStripe()->setupIntents->create([
        'customer'                  => obtenerOCrearClienteStripe($usuario),
        'automatic_payment_methods' => ['enabled' => true],
        'usage'                     => 'off_session',
        'metadata'                  => ['id_usuario' => (string) (int) $usuario['id_usuario']]
    ]);

    return $intento->client_secret;
}

function desvincularMetodoPagoStripe($idMetodoPago)
{
    try
    {
        clienteStripe()->paymentMethods->detach($idMetodoPago);
    }
    catch (\Throwable $excepcion)
    {
        error_log('No se pudo desvincular el metodo de pago en Stripe: ' . $excepcion->getMessage());
    }
}

function guardarTarjetaDesdeSetupIntent($usuario, $idSetupIntent)
{
    $idUsuario = (int) $usuario['id_usuario'];

    if (!preg_match('/^seti_[A-Za-z0-9]+$/', (string) $idSetupIntent))
    {
        return ['exito' => false, 'mensaje' => 'No pudimos confirmar tu tarjeta. Intenta de nuevo.'];
    }

    if (contarTarjetas($idUsuario) >= MAXIMO_TARJETAS_USUARIO)
    {
        return ['exito' => false, 'mensaje' => 'Ya tienes ' . MAXIMO_TARJETAS_USUARIO . ' tarjetas guardadas. Elimina una para agregar otra.'];
    }

    $idCliente = obtenerIdClienteStripe($idUsuario);

    if ($idCliente === null)
    {
        return ['exito' => false, 'mensaje' => 'No pudimos confirmar tu tarjeta. Intenta de nuevo.'];
    }

    $intento = clienteStripe()->setupIntents->retrieve($idSetupIntent, ['expand' => ['payment_method']]);
    $metodo = $intento->payment_method;

    $esDelUsuario = $intento->customer === $idCliente
        && ($intento->metadata['id_usuario'] ?? null) === (string) $idUsuario;

    if ($intento->status !== 'succeeded' || !$esDelUsuario || !is_object($metodo) || $metodo->type !== 'card')
    {
        return ['exito' => false, 'mensaje' => 'Tu banco no confirmo la tarjeta. Revisa los datos o prueba con otra.'];
    }

    $marca = (string) $metodo->card->brand;
    $ultimos4 = (string) $metodo->card->last4;
    $mes = (int) $metodo->card->exp_month;
    $anio = (int) $metodo->card->exp_year;

    foreach (obtenerTarjetas($idUsuario) as $existente)
    {
        if ($existente['ultimos_4_digitos'] === $ultimos4 && strtolower($existente['marca']) === strtolower($marca)
            && (int) $existente['exp_mes'] === $mes && (int) $existente['exp_anio'] === $anio)
        {
            $propia = obtenerTarjetaDeUsuario($existente['id_tarjeta'], $idUsuario);

            if (descifrarDatoPago($propia['token_pasarela'], contextoCifradoTarjeta($idUsuario)) !== $metodo->id)
            {
                desvincularMetodoPagoStripe($metodo->id);
            }

            return ['exito' => false, 'mensaje' => 'Esa tarjeta ya esta guardada en tu cuenta.'];
        }
    }

    $conexion = conexionBD();

    try
    {
        $esPrimera = contarTarjetas($idUsuario) === 0;

        $sentencia = $conexion->prepare(
            'INSERT INTO tarjetas_cliente
                (id_usuario, token_pasarela, ultimos_4_digitos, marca, exp_mes, exp_anio, es_predeterminado, fecha_registro)
             VALUES
                (:usuario, :token, :ultimos, :marca, :mes, :anio, :predeterminada, NOW())'
        );

        $sentencia->execute([
            ':usuario'        => $idUsuario,
            ':token'          => cifrarDatoPago($metodo->id, contextoCifradoTarjeta($idUsuario)),
            ':ultimos'        => $ultimos4,
            ':marca'          => $marca,
            ':mes'            => $mes,
            ':anio'           => $anio,
            ':predeterminada' => $esPrimera ? 1 : 0
        ]);
    }
    catch (\Throwable $excepcion)
    {
        desvincularMetodoPagoStripe($metodo->id);
        throw $excepcion;
    }

    if ($esPrimera)
    {
        marcarPredeterminadaEnStripe($idCliente, $metodo->id);
    }

    registrarEnBitacora($usuario['correo'], 'tarjeta_agregada');

    return ['exito' => true, 'mensaje' => 'Tu tarjeta ' . nombreMarcaTarjeta($marca) . ' terminada en ' . $ultimos4 . ' se guardo correctamente.'];
}

//=============================================================
// Actualizar, Predeterminar y Eliminar
//=============================================================

function actualizarVencimientoTarjeta($usuario, $idTarjeta, $mes, $anio)
{
    $idUsuario = (int) $usuario['id_usuario'];
    $error = validarVencimientoTarjeta($mes, $anio);

    if ($error !== null)
    {
        return ['exito' => false, 'mensaje' => $error];
    }

    $tarjeta = obtenerTarjetaDeUsuario($idTarjeta, $idUsuario);

    if ($tarjeta === null)
    {
        return ['exito' => false, 'mensaje' => 'No encontramos esa tarjeta.'];
    }

    $token = descifrarDatoPago($tarjeta['token_pasarela'], contextoCifradoTarjeta($idUsuario));

    if ($token === null)
    {
        error_log('No se pudo descifrar el token de la tarjeta ' . (int) $idTarjeta);

        return ['exito' => false, 'mensaje' => 'No pudimos actualizar esta tarjeta. Elimínala y vuelve a agregarla.'];
    }

    clienteStripe()->paymentMethods->update($token, ['card' => ['exp_month' => (int) $mes, 'exp_year' => (int) $anio]]);

    $conexion = conexionBD();
    $sentencia = $conexion->prepare(
        'UPDATE tarjetas_cliente SET exp_mes = :mes, exp_anio = :anio WHERE id_tarjeta = :tarjeta AND id_usuario = :usuario'
    );
    $sentencia->execute([':mes' => (int) $mes, ':anio' => (int) $anio, ':tarjeta' => $idTarjeta, ':usuario' => $idUsuario]);

    registrarEnBitacora($usuario['correo'], 'tarjeta_actualizada');

    return ['exito' => true, 'mensaje' => 'Actualizamos el vencimiento de tu tarjeta terminada en ' . $tarjeta['ultimos_4_digitos'] . '.'];
}

function marcarPredeterminadaEnStripe($idCliente, $idMetodoPago)
{
    try
    {
        clienteStripe()->customers->update($idCliente, ['invoice_settings' => ['default_payment_method' => $idMetodoPago]]);
    }
    catch (\Throwable $excepcion)
    {
        error_log('No se pudo marcar el metodo predeterminado en Stripe: ' . $excepcion->getMessage());
    }
}

function establecerTarjetaPredeterminada($usuario, $idTarjeta)
{
    $idUsuario = (int) $usuario['id_usuario'];
    $tarjeta = obtenerTarjetaDeUsuario($idTarjeta, $idUsuario);

    if ($tarjeta === null)
    {
        return ['exito' => false, 'mensaje' => 'No encontramos esa tarjeta.'];
    }

    $conexion = conexionBD();
    $conexion->beginTransaction();

    try
    {
        $conexion->prepare('UPDATE tarjetas_cliente SET es_predeterminado = 0 WHERE id_usuario = :usuario')
                 ->execute([':usuario' => $idUsuario]);

        $conexion->prepare('UPDATE tarjetas_cliente SET es_predeterminado = 1 WHERE id_tarjeta = :tarjeta AND id_usuario = :usuario')
                 ->execute([':tarjeta' => $idTarjeta, ':usuario' => $idUsuario]);

        $conexion->commit();
    }
    catch (\Throwable $excepcion)
    {
        $conexion->rollBack();
        throw $excepcion;
    }

    $token = descifrarDatoPago($tarjeta['token_pasarela'], contextoCifradoTarjeta($idUsuario));
    $idCliente = obtenerIdClienteStripe($idUsuario);

    if ($token !== null && $idCliente !== null)
    {
        marcarPredeterminadaEnStripe($idCliente, $token);
    }

    registrarEnBitacora($usuario['correo'], 'tarjeta_predeterminada');

    return ['exito' => true, 'mensaje' => 'Ahora tu tarjeta terminada en ' . $tarjeta['ultimos_4_digitos'] . ' es la predeterminada.'];
}

function eliminarTarjetaUsuario($usuario, $idTarjeta)
{
    $idUsuario = (int) $usuario['id_usuario'];
    $tarjeta = obtenerTarjetaDeUsuario($idTarjeta, $idUsuario);

    if ($tarjeta === null)
    {
        return ['exito' => false, 'mensaje' => 'No encontramos esa tarjeta.'];
    }

    $conexion = conexionBD();
    $conexion->beginTransaction();

    try
    {
        $conexion->prepare('DELETE FROM tarjetas_cliente WHERE id_tarjeta = :tarjeta AND id_usuario = :usuario')
                 ->execute([':tarjeta' => $idTarjeta, ':usuario' => $idUsuario]);

        if ((int) $tarjeta['es_predeterminado'] === 1)
        {
            $conexion->prepare(
                'UPDATE tarjetas_cliente SET es_predeterminado = 1
                 WHERE id_tarjeta = (
                     SELECT id_tarjeta FROM (
                         SELECT id_tarjeta FROM tarjetas_cliente WHERE id_usuario = :usuario ORDER BY (exp_anio * 12 + exp_mes) DESC, id_tarjeta DESC LIMIT 1
                     ) AS ultima
                 )'
            )->execute([':usuario' => $idUsuario]);
        }

        $conexion->commit();
    }
    catch (PDOException $excepcion)
    {
        $conexion->rollBack();

        if ($excepcion->getCode() === '23000')
        {
            return ['exito' => false, 'mensaje' => 'Esta tarjeta esta ligada a una cita y no se puede eliminar todavia. Agrega otra y ponla como predeterminada.'];
        }

        throw $excepcion;
    }

    $token = descifrarDatoPago($tarjeta['token_pasarela'], contextoCifradoTarjeta($idUsuario));

    if ($token !== null)
    {
        desvincularMetodoPagoStripe($token);
    }

    registrarEnBitacora($usuario['correo'], 'tarjeta_eliminada');

    return ['exito' => true, 'mensaje' => 'Eliminamos tu tarjeta terminada en ' . $tarjeta['ultimos_4_digitos'] . '.'];
}
?>
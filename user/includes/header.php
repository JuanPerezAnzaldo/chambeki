                                                                                                <?php
                                                                                                /*
                                                                                                    SISTEMA DE CITAS (cliente <-> freelancer)

                                                                                                    - El cliente elige un servicio, ve los huecos libres segun el horario
                                                                                                      semanal del freelancer (horarios_freelancer) y las citas ya tomadas,
                                                                                                      y envia una solicitud (estado Pendiente).
                                                                                                    - El freelancer la acepta (Confirmada) o la rechaza (Rechazada).
                                                                                                    - Cualquiera de las dos partes puede cancelar una cita futura.

                                                                                                    Las citas Pendientes y Confirmadas bloquean su hueco, asi no se
                                                                                                    empalman. Todas las consultas son preparadas (RS-02).
                                                                                                    Depende de functions.php (conexionBD, registrarEnBitacora, etc.).
                                                                                                */

                                                                                                //tokenCsrf(), validarCsrf() y buscarPalabraProhibida() viven aqui
                                                                                                require_once DOCROOT . 'user/includes/funcionesPerfil.php';

                                                                                                const CITA_PENDIENTE  = 1;
                                                                                                const CITA_CONFIRMADA = 2;
                                                                                                const CITA_COMPLETADA = 3;
                                                                                                const CITA_CANCELADA  = 4;
                                                                                                const CITA_RECHAZADA  = 5;

                                                                                                const CITA_INTERVALO_MIN      = 30;   //los huecos arrancan cada 30 min
                                                                                                const CITA_DURACION_DEFECTO   = 60;   //si el servicio no tiene duracion
                                                                                                const CITA_ANTICIPACION_HORAS = 2;    //no se agenda con menos de 2 h de aviso
                                                                                                const CITA_DIAS_MAXIMOS       = 14;   //hasta cuantos dias adelante se agenda
                                                                                                const CITA_MAX_PENDIENTES     = 5;    //solicitudes abiertas por cliente
                                                                                                const CITA_NOTA_MAX           = 300;

                                                                                                //=============================================================
                                                                                                // Fechas
                                                                                                //=============================================================

                                                                                                function zonaCitas()
                                                                                                {
                                                                                                    return new DateTimeZone(defined('ZONA_HORARIA_NEGOCIOS') ? ZONA_HORARIA_NEGOCIOS : 'America/Tijuana');
                                                                                                }

                                                                                                function ahoraCitas()
                                                                                                {
                                                                                                    return new DateTimeImmutable('now', zonaCitas());
                                                                                                }

                                                                                                function fechaDesdeBD($texto)
                                                                                                {
                                                                                                    return new DateTimeImmutable($texto, zonaCitas());
                                                                                                }

                                                                                                function minutosDeHora($hora)
                                                                                                {
                                                                                                    $partes = explode(':', $hora);

                                                                                                    return ((int) $partes[0]) * 60 + (int) $partes[1];
                                                                                                }

                                                                                                function horaDesdeMinutos($minutos)
                                                                                                {
                                                                                                    return $minutos >= 1440 ? '24:00' : sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
                                                                                                }

                                                                                                function nombreDiaSemana($numero)
                                                                                                {
                                                                                                    $dias = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

                                                                                                    return $dias[(int) $numero];
                                                                                                }

                                                                                                //"Lunes 24 de noviembre, 10:00 a 11:00"
                                                                                                function textoFechaCita($inicio, $fin)
                                                                                                {
                                                                                                    $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

                                                                                                    return nombreDiaSemana($inicio->format('N')) . ' ' . $inicio->format('j') . ' de ' . $meses[(int) $inicio->format('n')]
                                                                                                        . ', ' . $inicio->format('H:i') . ' a ' . $fin->format('H:i');
                                                                                                }

                                                                                                //=============================================================
                                                                                                // Servicio y disponibilidad
                                                                                                //=============================================================

                                                                                                //servicio publicado de un freelancer activo; null si no se puede agendar
                                                                                                function obtenerServicioAgendable($idServicio)
                                                                                                {
                                                                                                    $sentencia = conexionBD()->prepare(
                                                                                                        'SELECT s.id_servicio, s.id_usuario AS id_freelancer, s.titulo, s.monto, s.duracion_minutos, s.id_tipo_precio,
                                                                                                                tp.nombre AS tipo_precio, u.nombre AS nombre_usuario, pf.nombre_negocio, pf.slug
                                                                                                         FROM servicios s
                                                                                                         JOIN usuarios u ON u.id_usuario = s.id_usuario AND u.rol = :rol AND u.estatus = :activo
                                                                                                         LEFT JOIN perfil_freelancer pf ON pf.id_usuario = s.id_usuario
                                                                                                         LEFT JOIN cat_tipos_precio tp ON tp.id_tipo_precio = s.id_tipo_precio
                                                                                                         WHERE s.id_servicio = :id AND s.estatus = 1
                                                                                                         LIMIT 1'
                                                                                                    );
                                                                                                    $sentencia->execute([':rol' => ROL_FREELANCER, ':activo' => ESTATUS_ACTIVO, ':id' => (int) $idServicio]);

                                                                                                    $fila = $sentencia->fetch();

                                                                                                    return $fila === false ? null : $fila;
                                                                                                }

                                                                                                function duracionCita($servicio)
                                                                                                {
                                                                                                    $minutos = (int) ($servicio['duracion_minutos'] ?? 0);

                                                                                                    return $minutos >= 15 ? min($minutos, 480) : CITA_DURACION_DEFECTO;
                                                                                                }

                                                                                                function nombreProfesionalCita($servicio)
                                                                                                {
                                                                                                    return !empty($servicio['nombre_negocio']) ? $servicio['nombre_negocio'] : $servicio['nombre_usuario'];
                                                                                                }

                                                                                                //[dia(1-7) => ['a' => minutos de apertura, 'c' => minutos de cierre]] solo de los dias abiertos
                                                                                                function obtenerHorarioSemanal($idFreelancer)
                                                                                                {
                                                                                                    $sentencia = conexionBD()->prepare(
                                                                                                        'SELECT dia_semana, hora_apertura, hora_cierre
                                                                                                         FROM horarios_freelancer
                                                                                                         WHERE id_usuario = :id AND es_cerrado = 0 AND hora_apertura IS NOT NULL AND hora_cierre IS NOT NULL'
                                                                                                    );
                                                                                                    $sentencia->execute([':id' => (int) $idFreelancer]);

                                                                                                    $horario = [];

                                                                                                    foreach ($sentencia->fetchAll() as $fila)
                                                                                                    {
                                                                                                        $apertura = minutosDeHora($fila['hora_apertura']);
                                                                                                        $cierre = minutosDeHora($fila['hora_cierre']);

                                                                                                        //si cierra pasada la medianoche, las citas llegan solo hasta las 24:00
                                                                                                        if ($cierre <= $apertura)
                                                                                                        {
                                                                                                            $cierre = 1440;
                                                                                                        }

                                                                                                        $horario[(int) $fila['dia_semana']] = ['a' => $apertura, 'c' => $cierre];
                                                                                                    }

                                                                                                    return $horario;
                                                                                                }

                                                                                                //citas Pendientes/Confirmadas del freelancer que tocan el rango [desde, hasta)
                                                                                                function obtenerCitasOcupadas($idFreelancer, $desde, $hasta, $conexion = null)
                                                                                                {
                                                                                                    $conexion = $conexion ?: conexionBD();

                                                                                                    $sentencia = $conexion->prepare(
                                                                                                        'SELECT c.fecha_hora_inicio AS ini,
                                                                                                                COALESCE(c.fecha_hora_fin, DATE_ADD(c.fecha_hora_inicio, INTERVAL 60 MINUTE)) AS fin
                                                                                                         FROM citas c
                                                                                                         JOIN servicios s ON s.id_servicio = c.id_servicio
                                                                                                         WHERE s.id_usuario = :f
                                                                                                           AND c.estado_cita IN (1, 2)
                                                                                                           AND c.fecha_hora_inicio < :hasta
                                                                                                           AND COALESCE(c.fecha_hora_fin, DATE_ADD(c.fecha_hora_inicio, INTERVAL 60 MINUTE)) > :desde'
                                                                                                    );
                                                                                                    $sentencia->execute([
                                                                                                        ':f'     => (int) $idFreelancer,
                                                                                                        ':hasta' => $hasta->format('Y-m-d H:i:s'),
                                                                                                        ':desde' => $desde->format('Y-m-d H:i:s')
                                                                                                    ]);

                                                                                                    $ocupadas = [];

                                                                                                    foreach ($sentencia->fetchAll() as $fila)
                                                                                                    {
                                                                                                        $ocupadas[] = ['ini' => fechaDesdeBD($fila['ini']), 'fin' => fechaDesdeBD($fila['fin'])];
                                                                                                    }

                                                                                                    return $ocupadas;
                                                                                                }

                                                                                                //horas de inicio libres ("09:00", "09:30"...) de un dia
                                                                                                function huecosDelDia($dia, $horario, $duracion, $ocupadas, $ahora)
                                                                                                {
                                                                                                    $numeroDia = (int) $dia->format('N');

                                                                                                    if (!isset($horario[$numeroDia]))
                                                                                                    {
                                                                                                        return [];
                                                                                                    }

                                                                                                    $limite = $ahora->modify('+' . CITA_ANTICIPACION_HORAS . ' hours');
                                                                                                    $huecos = [];

                                                                                                    for ($minuto = $horario[$numeroDia]['a']; $minuto + $duracion <= $horario[$numeroDia]['c']; $minuto += CITA_INTERVALO_MIN)
                                                                                                    {
                                                                                                        $inicio = $dia->setTime(intdiv($minuto, 60), $minuto % 60);

                                                                                                        if ($inicio < $limite)
                                                                                                        {
                                                                                                            continue;
                                                                                                        }

                                                                                                        $fin = $inicio->modify('+' . $duracion . ' minutes');
                                                                                                        $libre = true;

                                                                                                        foreach ($ocupadas as $ocupada)
                                                                                                        {
                                                                                                            if ($ocupada['ini'] < $fin && $ocupada['fin'] > $inicio)
                                                                                                            {
                                                                                                                $libre = false;
                                                                                                                break;
                                                                                                            }
                                                                                                        }

                                                                                                        if ($libre)
                                                                                                        {
                                                                                                            $huecos[] = $inicio->format('H:i');
                                                                                                        }
                                                                                                    }

                                                                                                    return $huecos;
                                                                                                }

                                                                                                //['Y-m-d' => ['fecha' => DateTimeImmutable, 'huecos' => [...]]] para los proximos dias
                                                                                                function calcularDisponibilidad($servicio, $horario)
                                                                                                {
                                                                                                    $dias = [];

                                                                                                    if (empty($horario))
                                                                                                    {
                                                                                                        return $dias;
                                                                                                    }

                                                                                                    $ahora = ahoraCitas();
                                                                                                    $primero = $ahora->setTime(0, 0);
                                                                                                    $ocupadas = obtenerCitasOcupadas($servicio['id_freelancer'], $primero, $primero->modify('+' . (CITA_DIAS_MAXIMOS + 1) . ' days'));
                                                                                                    $duracion = duracionCita($servicio);

                                                                                                    for ($i = 0; $i <= CITA_DIAS_MAXIMOS; $i++)
                                                                                                    {
                                                                                                        $dia = $primero->modify('+' . $i . ' days');

                                                                                                        $dias[$dia->format('Y-m-d')] = [
                                                                                                            'fecha'  => $dia,
                                                                                                            'huecos' => huecosDelDia($dia, $horario, $duracion, $ocupadas, $ahora)
                                                                                                        ];
                                                                                                    }

                                                                                                    return $dias;
                                                                                                }

                                                                                                //=============================================================
                                                                                                // Solicitar cita (cliente)
                                                                                                //=============================================================

                                                                                                //devuelve ['exito' => bool, 'mensaje' => string]
                                                                                                function crearCita($idCliente, $idServicio, $fecha, $hora, $nota)
                                                                                                {
                                                                                                    $idCliente = (int) $idCliente;
                                                                                                    $nota = trim($nota);

                                                                                                    $servicio = obtenerServicioAgendable($idServicio);

                                                                                                    if ($servicio === null)
                                                                                                    {
                                                                                                        return ['exito' => false, 'mensaje' => 'Ese servicio ya no está disponible.'];
                                                                                                    }

                                                                                                    if ((int) $servicio['id_freelancer'] === $idCliente)
                                                                                                    {
                                                                                                        return ['exito' => false, 'mensaje' => 'No puedes agendar un servicio tuyo.'];
                                                                                                    }

                                                                                                    if (mb_strlen($nota) > CITA_NOTA_MAX)
                                                                                                    {
                                                                                                        return ['exito' => false, 'mensaje' => 'El mensaje no puede pasar de ' . CITA_NOTA_MAX . ' caracteres.'];
                                                                                                    }

                                                                                                    if ($nota !== '' && function_exists('buscarPalabraProhibida') && buscarPalabraProhibida($nota) !== null)
                                                                                                    {
                                                                                                        return ['exito' => false, 'mensaje' => 'Tu mensaje contiene contenido no permitido. Quítalo para continuar.'];
                                                                                                    }

                                                                                                    $inicio = DateTimeImmutable::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora, zonaCitas());

                                                                                                    if ($inicio === false || $inicio->format('Y-m-d H:i') !== $fecha . ' ' . $hora)
                                                                                                    {
                                                                                                        return ['exito' => false, 'mensaje' => 'Elige un día y una hora válidos.'];
                                                                                                    }

                                                                                                    $hoy = ahoraCitas()->setTime(0, 0);
                                                                                                    $dia = $inicio->setTime(0, 0);

                                                                                                    if ($dia < $hoy || $dia > $hoy->modify('+' . CITA_DIAS_MAXIMOS . ' days'))
                                                                                                    {
                                                                                                        return ['exito' => false, 'mensaje' => 'Solo puedes agendar en los próximos ' . CITA_DIAS_MAXIMOS . ' días.'];
                                                                                                    }

                                                                                                    $horario = obtenerHorarioSemanal($servicio['id_freelancer']);
                                                                                                    $duracion = duracionCita($servicio);
                                                                                                    $conexion = conexionBD();
                                                                                                    $error = null;
                                                                                                    $idCita = 0;

                                                                                                    try
                                                                                                    {
                                                                                                        $conexion->beginTransaction();

                                                                                                        //se bloquea al freelancer para que dos clientes no tomen el mismo hueco a la vez
                                                                                                        $bloqueo = $conexion->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = :id FOR UPDATE');
                                                                                                        $bloqueo->execute([':id' => (int) $servicio['id_freelancer']]);
                                                                                                        $bloqueo->fetch();

                                                                                                        $abiertas = $conexion->prepare('SELECT COUNT(*) FROM citas WHERE id_cliente = :c AND estado_cita = 1 AND fecha_hora_inicio > :ahora');
                                                                                                        $abiertas->execute([':c' => $idCliente, ':ahora' => ahoraCitas()->format('Y-m-d H:i:s')]);

                                                                                                        if ((int) $abiertas->fetchColumn() >= CITA_MAX_PENDIENTES)
                                                                                                        {
                                                                                                            $error = 'Ya tienes ' . CITA_MAX_PENDIENTES . ' solicitudes esperando respuesta. Espera a que respondan o cancela alguna.';
                                                                                                        }
                                                                                                        else
                                                                                                        {
                                                                                                            $ocupadas = obtenerCitasOcupadas($servicio['id_freelancer'], $dia, $dia->modify('+1 day'), $conexion);
                                                                                                            $huecos = huecosDelDia($dia, $horario, $duracion, $ocupadas, ahoraCitas());

                                                                                                            if (!in_array($inicio->format('H:i'), $huecos, true))
                                                                                                            {
                                                                                                                $error = 'Ese horario ya no está disponible. Elige otro.';
                                                                                                            }
                                                                                                        }

                                                                                                        if ($error !== null)
                                                                                                        {
                                                                                                            $conexion->rollBack();

                                                                                                            return ['exito' => false, 'mensaje' => $error];
                                                                                                        }

                                                                                                        $conexion->prepare(
                                                                                                            'INSERT INTO citas (id_servicio, id_cliente, id_tarjeta, fecha_hora_inicio, fecha_hora_fin, estado_cita, nota_cliente, fecha_creacion)
                                                                                                             VALUES (:servicio, :cliente, NULL, :inicio, :fin, 1, :nota, NOW())'
                                                                                                        )->execute([
                                                                                                            ':servicio' => (int) $servicio['id_servicio'],
                                                                                                            ':cliente'  => $idCliente,
                                                                                                            ':inicio'   => $inicio->format('Y-m-d H:i:s'),
                                                                                                            ':fin'      => $inicio->modify('+' . $duracion . ' minutes')->format('Y-m-d H:i:s'),
                                                                                                            ':nota'     => $nota !== '' ? $nota : null
                                                                                                        ]);

                                                                                                        $idCita = (int) $conexion->lastInsertId();
                                                                                                        $conexion->commit();
                                                                                                    }
                                                                                                    catch (\PDOException $excepcion)
                                                                                                    {
                                                                                                        if ($conexion->inTransaction())
                                                                                                        {
                                                                                                            $conexion->rollBack();
                                                                                                        }

                                                                                                        error_log('crearCita: ' . $excepcion->getMessage());

                                                                                                        return ['exito' => false, 'mensaje' => 'No pudimos enviar tu solicitud. Intenta de nuevo en un momento.'];
                                                                                                    }

                                                                                                    registrarEnBitacora($_SESSION['correo_usuario'] ?? '', 'cita_solicitada:' . $idCita);

                                                                                                    return ['exito' => true, 'mensaje' => 'Enviamos tu solicitud a ' . nombreProfesionalCita($servicio) . '. Te mostraremos aquí su respuesta.'];
                                                                                                }

                                                                                                //=============================================================
                                                                                                // Aceptar / rechazar (freelancer) y cancelar (cualquiera de las partes)
                                                                                                //=============================================================

                                                                                                function responderCita($idFreelancer, $idCita, $aceptar, $motivo)
                                                                                                {
                                                                                                    $motivo = mb_substr(trim($motivo), 0, 300);
                                                                                                    $conexion = conexionBD();

                                                                                                    try
                                                                                                    {
                                                                                                        $conexion->beginTransaction();

                                                                                                        $seleccion = $conexion->prepare(
                                                                                                            'SELECT c.estado_cita, c.fecha_hora_inicio,
                                                                                                                    COALESCE(c.fecha_hora_fin, DATE_ADD(c.fecha_hora_inicio, INTERVAL 60 MINUTE)) AS fin
                                                                                                             FROM citas c
                                                                                                             JOIN servicios s ON s.id_servicio = c.id_servicio
                                                                                                             WHERE c.id_cita = :cita AND s.id_usuario = :f
                                                                                                             FOR UPDATE'
                                                                                                        );
                                                                                                        $seleccion->execute([':cita' => (int) $idCita, ':f' => (int) $idFreelancer]);
                                                                                                        $cita = $seleccion->fetch();

                                                                                                        $error = null;

                                                                                                        if ($cita === false)
                                                                                                        {
                                                                                                            $error = 'No encontramos esa solicitud.';
                                                                                                        }
                                                                                                        elseif ((int) $cita['estado_cita'] !== CITA_PENDIENTE)
                                                                                                        {
                                                                                                            $error = 'Esa solicitud ya fue respondida.';
                                                                                                        }
                                                                                                        elseif (fechaDesdeBD($cita['fecha_hora_inicio']) <= ahoraCitas())
                                                                                                        {
                                                                                                            $error = 'La hora de esa solicitud ya pasó.';
                                                                                                        }
                                                                                                        elseif ($aceptar)
                                                                                                        {
                                                                                                            $choque = $conexion->prepare(
                                                                                                                'SELECT 1 FROM citas c
                                                                                                                 JOIN servicios s ON s.id_servicio = c.id_servicio
                                                                                                                 WHERE s.id_usuario = :f AND c.id_cita <> :cita AND c.estado_cita = 2
                                                                                                                   AND c.fecha_hora_inicio < :fin
                                                                                                                   AND COALESCE(c.fecha_hora_fin, DATE_ADD(c.fecha_hora_inicio, INTERVAL 60 MINUTE)) > :ini
                                                                                                                 LIMIT 1'
                                                                                                            );
                                                                                                            $choque->execute([':f' => (int) $idFreelancer, ':cita' => (int) $idCita, ':fin' => $cita['fin'], ':ini' => $cita['fecha_hora_inicio']]);

                                                                                                            if ($choque->fetchColumn() !== false)
                                                                                                            {
                                                                                                                $error = 'Ya tienes otra cita confirmada que se empalma con esta.';
                                                                                                            }
                                                                                                        }

                                                                                                        if ($error !== null)
                                                                                                        {
                                                                                                            $conexion->rollBack();

                                                                                                            return ['exito' => false, 'mensaje' => $error];
                                                                                                        }

                                                                                                        $conexion->prepare('UPDATE citas SET estado_cita = :estado, motivo_respuesta = :motivo, fecha_respuesta = NOW() WHERE id_cita = :cita')
                                                                                                            ->execute([
                                                                                                                ':estado' => $aceptar ? CITA_CONFIRMADA : CITA_RECHAZADA,
                                                                                                                ':motivo' => $motivo !== '' ? $motivo : null,
                                                                                                                ':cita'   => (int) $idCita
                                                                                                            ]);

                                                                                                        $conexion->commit();
                                                                                                    }
                                                                                                    catch (\PDOException $excepcion)
                                                                                                    {
                                                                                                        if ($conexion->inTransaction())
                                                                                                        {
                                                                                                            $conexion->rollBack();
                                                                                                        }

                                                                                                        error_log('responderCita: ' . $excepcion->getMessage());

                                                                                                        return ['exito' => false, 'mensaje' => 'No pudimos guardar tu respuesta. Intenta de nuevo.'];
                                                                                                    }

                                                                                                    registrarEnBitacora($_SESSION['correo_usuario'] ?? '', ($aceptar ? 'cita_aceptada:' : 'cita_rechazada:') . (int) $idCita);

                                                                                                    return ['exito' => true, 'mensaje' => $aceptar ? 'Cita confirmada. El cliente ya puede verla.' : 'Solicitud rechazada.'];
                                                                                                }

                                                                                                function cancelarCita($idUsuario, $idCita, $motivo)
                                                                                                {
                                                                                                    $idUsuario = (int) $idUsuario;
                                                                                                    $motivo = mb_substr(trim($motivo), 0, 300);
                                                                                                    $conexion = conexionBD();

                                                                                                    try
                                                                                                    {
                                                                                                        $conexion->beginTransaction();

                                                                                                        $seleccion = $conexion->prepare(
                                                                                                            'SELECT c.id_cliente, c.estado_cita, c.fecha_hora_inicio, s.id_usuario AS id_freelancer
                                                                                                             FROM citas c
                                                                                                             JOIN servicios s ON s.id_servicio = c.id_servicio
                                                                                                             WHERE c.id_cita = :cita
                                                                                                             FOR UPDATE'
                                                                                                        );
                                                                                                        $seleccion->execute([':cita' => (int) $idCita]);
                                                                                                        $cita = $seleccion->fetch();

                                                                                                        $error = null;
                                                                                                        $esCliente = $cita !== false && (int) $cita['id_cliente'] === $idUsuario;
                                                                                                        $esFreelancer = $cita !== false && (int) $cita['id_freelancer'] === $idUsuario;

                                                                                                        if (!$esCliente && !$esFreelancer)
                                                                                                        {
                                                                                                            $error = 'No encontramos esa cita.';
                                                                                                        }
                                                                                                        elseif (!in_array((int) $cita['estado_cita'], [CITA_PENDIENTE, CITA_CONFIRMADA], true))
                                                                                                        {
                                                                                                            $error = 'Esa cita ya no se puede cancelar.';
                                                                                                        }
                                                                                                        elseif (fechaDesdeBD($cita['fecha_hora_inicio']) <= ahoraCitas())
                                                                                                        {
                                                                                                            $error = 'Esa cita ya pasó.';
                                                                                                        }

                                                                                                        if ($error !== null)
                                                                                                        {
                                                                                                            $conexion->rollBack();

                                                                                                            return ['exito' => false, 'mensaje' => $error];
                                                                                                        }

                                                                                                        if ($motivo === '')
                                                                                                        {
                                                                                                            $motivo = $esCliente ? 'Cancelada por el cliente.' : 'Cancelada por el profesional.';
                                                                                                        }

                                                                                                        $conexion->prepare('UPDATE citas SET estado_cita = 4, motivo_respuesta = :motivo, fecha_respuesta = NOW() WHERE id_cita = :cita')
                                                                                                            ->execute([':motivo' => $motivo, ':cita' => (int) $idCita]);

                                                                                                        $conexion->commit();
                                                                                                    }
                                                                                                    catch (\PDOException $excepcion)
                                                                                                    {
                                                                                                        if ($conexion->inTransaction())
                                                                                                        {
                                                                                                            $conexion->rollBack();
                                                                                                        }

                                                                                                        error_log('cancelarCita: ' . $excepcion->getMessage());

                                                                                                        return ['exito' => false, 'mensaje' => 'No pudimos cancelar la cita. Intenta de nuevo.'];
                                                                                                    }

                                                                                                    registrarEnBitacora($_SESSION['correo_usuario'] ?? '', 'cita_cancelada:' . (int) $idCita);

                                                                                                    return ['exito' => true, 'mensaje' => 'Cita cancelada.'];
                                                                                                }

                                                                                                //=============================================================
                                                                                                // Listados
                                                                                                //=============================================================

                                                                                                //citas que hizo el usuario como cliente (mas recientes primero)
                                                                                                function listarCitasCliente($idUsuario)
                                                                                                {
                                                                                                    $sentencia = conexionBD()->prepare(
                                                                                                        'SELECT c.id_cita, c.fecha_hora_inicio, c.fecha_hora_fin, c.estado_cita, c.nota_cliente, c.motivo_respuesta,
                                                                                                                s.id_servicio, s.titulo, s.monto, s.id_tipo_precio,
                                                                                                                COALESCE(NULLIF(pf.nombre_negocio, \'\'), u.nombre) AS contraparte,
                                                                                                                pf.whatsapp_contacto AS contacto
                                                                                                         FROM citas c
                                                                                                         JOIN servicios s ON s.id_servicio = c.id_servicio
                                                                                                         JOIN usuarios u ON u.id_usuario = s.id_usuario
                                                                                                         LEFT JOIN perfil_freelancer pf ON pf.id_usuario = s.id_usuario
                                                                                                         WHERE c.id_cliente = :id
                                                                                                         ORDER BY c.fecha_hora_inicio DESC
                                                                                                         LIMIT 100'
                                                                                                    );
                                                                                                    $sentencia->execute([':id' => (int) $idUsuario]);

                                                                                                    return $sentencia->fetchAll();
                                                                                                }

                                                                                                //citas que le pidieron al freelancer (mas recientes primero)
                                                                                                function listarCitasFreelancer($idUsuario)
                                                                                                {
                                                                                                    $sentencia = conexionBD()->prepare(
                                                                                                        'SELECT c.id_cita, c.fecha_hora_inicio, c.fecha_hora_fin, c.estado_cita, c.nota_cliente, c.motivo_respuesta,
                                                                                                                s.id_servicio, s.titulo, s.monto, s.id_tipo_precio,
                                                                                                                uc.nombre AS contraparte, uc.telefono AS contacto
                                                                                                         FROM citas c
                                                                                                         JOIN servicios s ON s.id_servicio = c.id_servicio
                                                                                                         JOIN usuarios uc ON uc.id_usuario = c.id_cliente
                                                                                                         WHERE s.id_usuario = :id
                                                                                                         ORDER BY c.fecha_hora_inicio DESC
                                                                                                         LIMIT 200'
                                                                                                    );
                                                                                                    $sentencia->execute([':id' => (int) $idUsuario]);

                                                                                                    return $sentencia->fetchAll();
                                                                                                }

                                                                                                //=============================================================
                                                                                                // Presentacion
                                                                                                //=============================================================

                                                                                                function citaFinal($cita)
                                                                                                {
                                                                                                    return !empty($cita['fecha_hora_fin'])
                                                                                                        ? fechaDesdeBD($cita['fecha_hora_fin'])
                                                                                                        : fechaDesdeBD($cita['fecha_hora_inicio'])->modify('+' . CITA_DURACION_DEFECTO . ' minutes');
                                                                                                }

                                                                                                //una cita sigue "viva" si esta Pendiente o Confirmada y todavia no empieza
                                                                                                function citaVigente($cita, $ahora)
                                                                                                {
                                                                                                    return in_array((int) $cita['estado_cita'], [CITA_PENDIENTE, CITA_CONFIRMADA], true)
                                                                                                        && fechaDesdeBD($cita['fecha_hora_inicio']) > $ahora;
                                                                                                }

                                                                                                //[texto, clase css]
                                                                                                function estadoVisibleCita($cita, $ahora)
                                                                                                {
                                                                                                    $estado = (int) $cita['estado_cita'];

                                                                                                    if ($estado === CITA_PENDIENTE && fechaDesdeBD($cita['fecha_hora_inicio']) <= $ahora)
                                                                                                    {
                                                                                                        return ['Sin respuesta', 'neutra'];
                                                                                                    }

                                                                                                    $mapa = [
                                                                                                        CITA_PENDIENTE  => ['Pendiente', 'pendiente'],
                                                                                                        CITA_CONFIRMADA => ['Confirmada', 'confirmada'],
                                                                                                        CITA_COMPLETADA => ['Completada', 'confirmada'],
                                                                                                        CITA_CANCELADA  => ['Cancelada', 'cancelada'],
                                                                                                        CITA_RECHAZADA  => ['Rechazada', 'cancelada']
                                                                                                    ];

                                                                                                    return $mapa[$estado] ?? ['Desconocido', 'neutra'];
                                                                                                }

                                                                                                //formulario con boton que despliega un campo de motivo antes de confirmar
                                                                                                function formularioCitaConMotivo($operacion, $idCita, $tokenCsrf, $textoBoton, $textoConfirmar)
                                                                                                {
                                                                                                    ?>
                                                                                                    <details class="cita-desplegable">
                                                                                                        <summary class="boton-cita boton-cita-secundario"><?php echo escaparSalida($textoBoton); ?></summary>
                                                                                                        <form action="<?php echo URL_BASE; ?>?accion=citas" method="POST" class="cita-form-motivo">
                                                                                                            <input type="hidden" name="operacion" value="<?php echo escaparSalida($operacion); ?>">
                                                                                                            <input type="hidden" name="id_cita" value="<?php echo (int) $idCita; ?>">
                                                                                                            <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($tokenCsrf); ?>">
                                                                                                            <label for="motivo<?php echo $operacion . (int) $idCita; ?>">Motivo (opcional)</label>
                                                                                                            <textarea id="motivo<?php echo $operacion . (int) $idCita; ?>" name="motivo" rows="2" maxlength="300"></textarea>
                                                                                                            <button type="submit" class="boton-cita boton-cita-peligro"><?php echo escaparSalida($textoConfirmar); ?></button>
                                                                                                        </form>
                                                                                                    </details>
                                                                                                    <?php
                                                                                                }

                                                                                                //$vista: 'freelancer' (solicitudes recibidas) o 'cliente' (mis solicitudes)
                                                                                                function pintarTarjetaCita($cita, $vista, $tokenCsrf)
                                                                                                {
                                                                                                    $ahora = ahoraCitas();
                                                                                                    $inicio = fechaDesdeBD($cita['fecha_hora_inicio']);
                                                                                                    $estado = estadoVisibleCita($cita, $ahora);
                                                                                                    $vigente = citaVigente($cita, $ahora);
                                                                                                    $confirmada = (int) $cita['estado_cita'] === CITA_CONFIRMADA;
                                                                                                    $etiquetaPersona = $vista === 'freelancer' ? 'Cliente' : 'Profesional';
                                                                                                    ?>
                                                                                                    <article class="cita-tarjeta">
                                                                                                        <div class="cita-cabecera">
                                                                                                            <div>
                                                                                                                <h3 class="cita-titulo"><?php echo escaparSalida($cita['titulo']); ?></h3>
                                                                                                                <p class="cita-persona"><?php echo $etiquetaPersona; ?>: <strong><?php echo escaparSalida($cita['contraparte']); ?></strong></p>
                                                                                                            </div>
                                                                                                            <span class="cita-estado cita-estado-<?php echo $estado[1]; ?>"><?php echo $estado[0]; ?></span>
                                                                                                        </div>

                                                                                                        <p class="cita-fecha"><?php echo escaparSalida(textoFechaCita($inicio, citaFinal($cita))); ?></p>

                                                                                                        <?php if (!empty($cita['nota_cliente'])): ?>
                                                                                                            <p class="cita-nota"><?php echo $vista === 'freelancer' ? 'Mensaje del cliente' : 'Tu mensaje'; ?>: <?php echo escaparSalida($cita['nota_cliente']); ?></p>
                                                                                                        <?php endif; ?>

                                                                                                        <?php if (!empty($cita['motivo_respuesta'])): ?>
                                                                                                            <p class="cita-nota">Motivo: <?php echo escaparSalida($cita['motivo_respuesta']); ?></p>
                                                                                                        <?php endif; ?>

                                                                                                        <?php if ($confirmada && !empty($cita['contacto'])): ?>
                                                                                                            <p class="cita-contacto">Contacto: <strong><?php echo escaparSalida($cita['contacto']); ?></strong></p>
                                                                                                        <?php endif; ?>

                                                                                                        <?php if ($vigente): ?>
                                                                                                            <div class="cita-acciones">
                                                                                                                <?php if ($vista === 'freelancer' && (int) $cita['estado_cita'] === CITA_PENDIENTE): ?>
                                                                                                                    <form action="<?php echo URL_BASE; ?>?accion=citas" method="POST">
                                                                                                                        <input type="hidden" name="operacion" value="aceptar_cita">
                                                                                                                        <input type="hidden" name="id_cita" value="<?php echo (int) $cita['id_cita']; ?>">
                                                                                                                        <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($tokenCsrf); ?>">
                                                                                                                        <button type="submit" class="boton-cita boton-cita-principal">Aceptar</button>
                                                                                                                    </form>
                                                                                                                    <?php formularioCitaConMotivo('rechazar_cita', $cita['id_cita'], $tokenCsrf, 'Rechazar', 'Rechazar solicitud'); ?>
                                                                                                                <?php else: ?>
                                                                                                                    <?php formularioCitaConMotivo('cancelar_cita', $cita['id_cita'], $tokenCsrf, 'Cancelar cita', 'Confirmar cancelación'); ?>
                                                                                                                <?php endif; ?>
                                                                                                            </div>
                                                                                                        <?php endif; ?>
                                                                                                    </article>
                                                                                                    <?php
                                                                                                }
<?php
/*
    MODULO: Catalogo de servicios del freelancer
    Alta, consulta, edicion y baja de servicios, con modalidad de cobro,
    monto, duracion estimada, galeria de imagenes promocionales, y un
    interruptor para mostrar/ocultar y reordenar en el perfil publico.

    Vistas (GET):  ?accion=catalogo                       lista
                   ?accion=catalogo&vista=nuevo            formulario nuevo
                   ?accion=catalogo&vista=editar&id=N      formulario de edicion
    Patron Post/Redirect/Get: si todo sale bien se guarda un mensaje
    flash y se redirige; si hay errores se vuelve a pintar el formulario
    con lo que el usuario escribio (RU-02).
    Todo se consulta con el id_usuario de la SESION (RSIS-01).
*/

require_once DOCROOT . 'user/includes/funcionesCatalogo.php';

if (!hayUsuarioEnSesion())
{
    guardarMensaje('error', 'Inicia sesión para administrar tu catálogo.');
    redirigir(URL_BASE . '?accion=login');
}

$idUsuario = (int) $_SESSION['id_usuario'];
$usuario = obtenerUsuarioPorId($idUsuario);

if ($usuario === null)
{
    redirigir(URL_BASE . '?accion=cerrar_sesion');
}

//el rol se revisa en la BD (la sesion puede estar desactualizada)
if ((int) $usuario['rol'] !== ROL_FREELANCER)
{
    guardarMensaje('error', 'El catálogo es solo para freelancers.');
    redirigir(URL_BASE . '?accion=perfil');
}

$urlCatalogo = URL_BASE . '?accion=catalogo';
$urlPanel = URL_BASE . '?accion=mi_negocio';

$categorias = obtenerCategorias();
$categoriasValidas = array_map('intval', array_column($categorias, 'id_categoria'));

$errores = [];
$vista = 'lista';
$servicioEnEdicion = 0;
$valores = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $operacion = $_POST['operacion'] ?? '';
    $idServicio = (int) ($_POST['id_servicio'] ?? 0);

    if (!validarCsrf($_POST['token_csrf'] ?? ''))
    {
        guardarMensaje('error', 'La sesión del formulario expiró. Intenta de nuevo.');
        redirigir($urlCatalogo);
    }

    try
    {
        switch ($operacion)
        {
            //-----------------------------------------------------
            // Mostrar u ocultar un servicio en el perfil publico
            //-----------------------------------------------------
            case 'cambiar_estatus':
            {
                $activar = ($_POST['estatus'] ?? '') === '1';

                if (cambiarEstatusServicio($idServicio, $idUsuario, $activar))
                {
                    registrarEnBitacora($usuario['correo'], $activar ? 'servicio_activado' : 'servicio_oculto');
                    guardarMensaje('exito', $activar ? 'El servicio ahora es visible en tu perfil.' : 'El servicio quedó oculto: los clientes ya no lo ven.');
                }
                else
                {
                    guardarMensaje('error', 'No encontramos ese servicio.');
                }

                redirigir($urlCatalogo);
            }

            //-----------------------------------------------------
            // Subir / bajar de posicion
            //-----------------------------------------------------
            case 'mover_servicio':
            {
                $direccion = ($_POST['direccion'] ?? '') === 'arriba' ? 'arriba' : 'abajo';

                if (moverServicio($idServicio, $idUsuario, $direccion))
                {
                    registrarEnBitacora($usuario['correo'], 'servicio_reordenado');
                }

                redirigir($urlCatalogo);
            }

            //-----------------------------------------------------
            // Eliminar (pide confirmar; no borra servicios con citas)
            //-----------------------------------------------------
            case 'eliminar_servicio':
            {
                if (($_POST['confirmar'] ?? '') !== '1')
                {
                    guardarMensaje('error', 'Marca la casilla de confirmación para eliminar el servicio.');
                    redirigir($urlCatalogo . '&vista=editar&id=' . $idServicio);
                }

                $resultado = eliminarServicioCompleto($idServicio, $idUsuario);

                if ($resultado === 'eliminado')
                {
                    registrarEnBitacora($usuario['correo'], 'servicio_eliminado');
                    guardarMensaje('exito', 'El servicio se eliminó.');
                    redirigir($urlCatalogo);
                }

                if ($resultado === 'con_citas')
                {
                    guardarMensaje('error', 'Este servicio ya tiene citas registradas y no se puede eliminar. Ocúltalo para que los clientes dejen de verlo.');
                    redirigir($urlCatalogo . '&vista=editar&id=' . $idServicio);
                }

                guardarMensaje('error', 'No encontramos ese servicio.');
                redirigir($urlCatalogo);
            }

            //-----------------------------------------------------
            // Crear o actualizar un servicio (con su galeria)
            //-----------------------------------------------------
            case 'guardar_servicio':
            {
                $actual = null;

                if ($idServicio > 0)
                {
                    $actual = obtenerServicioDelFreelancer($idServicio, $idUsuario);

                    if ($actual === null)
                    {
                        guardarMensaje('error', 'No encontramos ese servicio.');
                        redirigir($urlCatalogo);
                    }
                }
                elseif (contarServiciosFreelancer($idUsuario) >= MAX_SERVICIOS_POR_FREELANCER)
                {
                    guardarMensaje('error', 'Llegaste al máximo de ' . MAX_SERVICIOS_POR_FREELANCER . ' servicios. Elimina o reutiliza alguno.');
                    redirigir($urlCatalogo);
                }

                $datos = [
                    'titulo'            => limpiarEntrada($_POST['titulo'] ?? ''),
                    'descripcion'       => limpiarEntrada($_POST['descripcion'] ?? ''),
                    'id_categoria'      => (int) ($_POST['id_categoria'] ?? 0),
                    'id_tipo_precio'    => (int) ($_POST['id_tipo_precio'] ?? 0),
                    'monto'             => limpiarEntrada($_POST['monto'] ?? ''),
                    'duracion_cantidad' => limpiarEntrada($_POST['duracion_cantidad'] ?? ''),
                    'duracion_unidad'   => ($_POST['duracion_unidad'] ?? '') === 'horas' ? 'horas' : 'minutos',
                    'estatus'           => isset($_POST['estatus'])
                ];

                $modalidadesValidas = array_keys(modalidadesCobroDisponibles($actual !== null ? (int) $actual['id_tipo_precio'] : 0));
                $errores = validarServicio($datos, $categoriasValidas, $modalidadesValidas);

                //galeria: cuantas quedan, cuantas se suben y que cada una sea valida
                $imagenesActuales = $actual !== null ? obtenerImagenesServicio($idServicio, $idUsuario) : [];
                $idsActuales = array_map('intval', array_column($imagenesActuales, 'id_imagen'));

                $quitar = isset($_POST['quitar_imagenes']) && is_array($_POST['quitar_imagenes']) ? array_map('intval', $_POST['quitar_imagenes']) : [];
                $quitar = array_values(array_intersect($quitar, $idsActuales));

                $archivosNuevos = normalizarArchivosSubidos($_FILES['imagenes'] ?? null);
                $lugares = MAX_IMAGENES_POR_SERVICIO - (count($idsActuales) - count($quitar));

                if (count($archivosNuevos) > $lugares)
                {
                    $errores['imagenes'] = 'Cada servicio admite máximo ' . MAX_IMAGENES_POR_SERVICIO . ' imágenes' . ($lugares > 0 ? ' (te quedan ' . $lugares . ' lugares).' : ' y ya llegaste al límite. Quita alguna para subir otra.');
                }
                else
                {
                    $extensiones = [];

                    foreach ($archivosNuevos as $indice => $archivo)
                    {
                        $revision = validarImagenServicio($archivo);

                        if (!$revision['exito'])
                        {
                            $errores['imagenes'] = $revision['error'];
                            break;
                        }

                        $extensiones[$indice] = $revision['extension'];
                    }
                }

                if (empty($errores))
                {
                    $rutasNuevas = [];

                    foreach ($archivosNuevos as $indice => $archivo)
                    {
                        $ruta = moverImagenServicio($archivo, $extensiones[$indice]);

                        if ($ruta === null)
                        {
                            foreach ($rutasNuevas as $rutaMovida)
                            {
                                eliminarArchivoServicio($rutaMovida);
                            }

                            $errores['imagenes'] = 'No se pudo guardar una imagen en el servidor. Intenta de nuevo.';
                            break;
                        }

                        $rutasNuevas[] = $ruta;
                    }
                }

                if (empty($errores))
                {
                    try
                    {
                        $idGuardado = guardarServicioConGaleria($idUsuario, $idServicio, $datos, $rutasNuevas, $quitar);
                    }
                    catch (\Throwable $excepcion)
                    {
                        //si la BD falla, las imagenes recien subidas no deben quedar huerfanas
                        foreach ($rutasNuevas as $rutaMovida)
                        {
                            eliminarArchivoServicio($rutaMovida);
                        }

                        throw $excepcion;
                    }

                    registrarEnBitacora($usuario['correo'], $idServicio > 0 ? 'servicio_actualizado' : 'servicio_creado');
                    guardarMensaje('exito', $idServicio > 0 ? 'Los cambios del servicio se guardaron.' : '¡Servicio creado! Ya forma parte de tu catálogo.');
                    redirigir($urlCatalogo);
                }

                //con errores: se vuelve a pintar el formulario con lo escrito
                $vista = $idServicio > 0 ? 'editar' : 'nuevo';
                $servicioEnEdicion = $idServicio;
                $valores = $datos;

                break;
            }
        }
    }
    catch (\Throwable $excepcion)
    {
        //nunca se le muestra el detalle del error al usuario final
        error_log('Error en el catalogo de servicios: ' . $excepcion->getMessage());

        guardarMensaje('error', 'No pudimos completar la operación. Intenta de nuevo en unos minutos.');
        redirigir($urlCatalogo);
    }
}
else
{
    $vistaSolicitada = isset($_GET['vista']) && is_string($_GET['vista']) ? $_GET['vista'] : 'lista';

    if ($vistaSolicitada === 'nuevo')
    {
        if (contarServiciosFreelancer($idUsuario) >= MAX_SERVICIOS_POR_FREELANCER)
        {
            guardarMensaje('error', 'Llegaste al máximo de ' . MAX_SERVICIOS_POR_FREELANCER . ' servicios.');
            redirigir($urlCatalogo);
        }

        $vista = 'nuevo';
    }
    elseif ($vistaSolicitada === 'editar')
    {
        $servicioEnEdicion = (int) ($_GET['id'] ?? 0);
        $actualGet = $servicioEnEdicion > 0 ? obtenerServicioDelFreelancer($servicioEnEdicion, $idUsuario) : null;

        if ($actualGet === null)
        {
            guardarMensaje('error', 'No encontramos ese servicio.');
            redirigir($urlCatalogo);
        }

        $duracion = duracionParaFormulario($actualGet['duracion_minutos']);

        $valores = [
            'titulo'            => $actualGet['titulo'],
            'descripcion'       => $actualGet['descripcion'],
            'id_categoria'      => (int) $actualGet['id_categoria'],
            'id_tipo_precio'    => (int) $actualGet['id_tipo_precio'],
            'monto'             => (float) $actualGet['monto'] > 0 ? rtrim(rtrim(number_format((float) $actualGet['monto'], 2, '.', ''), '0'), '.') : '',
            'duracion_cantidad' => $duracion['cantidad'],
            'duracion_unidad'   => $duracion['unidad'],
            'estatus'           => (int) $actualGet['estatus'] === 1
        ];

        $vista = 'editar';
    }
}

$token = tokenCsrf();
$mensajeFlash = obtenerMensaje();

if ($vista === 'lista')
{
    $catalogo = obtenerCatalogoFreelancer($idUsuario);
    $totalVisibles = 0;

    foreach ($catalogo as $fila)
    {
        $totalVisibles += (int) $fila['estatus'] === 1 ? 1 : 0;
    }
}
else
{
    $esEdicion = $vista === 'editar';

    if ($valores === null)
    {
        $valores = [
            'titulo' => '', 'descripcion' => '', 'id_categoria' => 0, 'id_tipo_precio' => PRECIO_FIJO,
            'monto' => '', 'duracion_cantidad' => '', 'duracion_unidad' => 'minutos', 'estatus' => true
        ];
    }

    $modalidadesCobro = modalidadesCobroDisponibles((int) $valores['id_tipo_precio']);
    $imagenesServicio = $esEdicion ? obtenerImagenesServicio($servicioEnEdicion, $idUsuario) : [];
    $citasServicio = $esEdicion ? contarCitasServicio($servicioEnEdicion) : 0;
}
?>

<section class="seccion-catalogo">
    <div class="contenedor-catalogo">

        <?php if ($mensajeFlash !== null): ?>
            <p class="aviso-autenticacion aviso-<?php echo escaparSalida($mensajeFlash['tipo']); ?>" role="status"><?php echo escaparSalida($mensajeFlash['texto']); ?></p>
        <?php endif; ?>

        <?php if ($vista === 'lista'): ?>

            <!-- ============ LISTA ============ -->
            <header class="cabecera-catalogo">
                <div>
                    <a href="<?php echo $urlPanel; ?>" class="enlace-volver-catalogo">&larr; Volver a Mi Negocio</a>
                    <h1 class="titulo-catalogo">Catálogo de servicios</h1>
                    <p class="subtitulo-catalogo">
                        <?php if (empty($catalogo)): ?>
                            Publica lo que ofreces para que los clientes te encuentren.
                        <?php else: ?>
                            <?php echo count($catalogo); ?> de <?php echo MAX_SERVICIOS_POR_FREELANCER; ?> servicios · <?php echo (int) $totalVisibles; ?> visibles en tu perfil. Los clientes los ven en este orden.
                        <?php endif; ?>
                    </p>
                </div>

                <a href="<?php echo $urlCatalogo; ?>&amp;vista=nuevo" class="boton-catalogo boton-catalogo-principal">+ Nuevo servicio</a>
            </header>

            <?php if (empty($catalogo)): ?>
                <div class="tarjeta-catalogo estado-vacio-catalogo">
                    <p><strong>Todavía no tienes servicios.</strong></p>
                    <p>Crea el primero: ponle nombre, precio, duración y fotos de tu trabajo.</p>
                    <a href="<?php echo $urlCatalogo; ?>&amp;vista=nuevo" class="boton-catalogo boton-catalogo-principal">Crear mi primer servicio</a>
                </div>
            <?php else: ?>
                <ul class="lista-catalogo">
                    <?php foreach ($catalogo as $posicion => $servicio): ?>
                        <?php
                            $visible = (int) $servicio['estatus'] === 1;
                            $imagenPrincipal = urlImagenServicio($servicio['imagen_principal']);
                            $totalImagenes = (int) $servicio['total_imagenes'];
                            $precio = textoPrecioServicio($servicio['monto'], $servicio['id_tipo_precio']);
                            $duracionTexto = textoDuracionServicio($servicio['duracion_minutos']);
                        ?>
                        <li class="item-catalogo<?php echo $visible ? '' : ' oculto'; ?>">
                            <div class="miniatura-catalogo">
                                <?php if ($imagenPrincipal !== ''): ?>
                                    <img src="<?php echo escaparSalida($imagenPrincipal); ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span aria-hidden="true">Sin foto</span>
                                <?php endif; ?>
                            </div>

                            <div class="datos-catalogo">
                                <div class="chips-catalogo">
                                    <span class="chip-catalogo"><?php echo escaparSalida($servicio['nombre_categoria']); ?></span>
                                    <span class="chip-catalogo <?php echo $visible ? 'chip-visible' : 'chip-oculto'; ?>"><?php echo $visible ? 'Visible' : 'Oculto'; ?></span>
                                </div>
                                <h2><?php echo escaparSalida($servicio['titulo']); ?></h2>
                                <p class="meta-catalogo">
                                    <?php echo escaparSalida($precio); ?>
                                    <?php if ($duracionTexto !== ''): ?> · <?php echo escaparSalida($duracionTexto); ?><?php endif; ?>
                                    · <?php echo $totalImagenes === 1 ? '1 imagen' : $totalImagenes . ' imágenes'; ?>
                                </p>
                            </div>

                            <div class="acciones-catalogo">
                                <div class="orden-catalogo" role="group" aria-label="Cambiar posición">
                                    <form action="<?php echo $urlCatalogo; ?>" method="POST">
                                        <input type="hidden" name="operacion" value="mover_servicio">
                                        <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
                                        <input type="hidden" name="id_servicio" value="<?php echo (int) $servicio['id_servicio']; ?>">
                                        <input type="hidden" name="direccion" value="arriba">
                                        <button type="submit" class="boton-orden" aria-label="Subir «<?php echo escaparSalida($servicio['titulo']); ?>»" <?php echo $posicion === 0 ? 'disabled' : ''; ?>>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>
                                        </button>
                                    </form>
                                    <form action="<?php echo $urlCatalogo; ?>" method="POST">
                                        <input type="hidden" name="operacion" value="mover_servicio">
                                        <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
                                        <input type="hidden" name="id_servicio" value="<?php echo (int) $servicio['id_servicio']; ?>">
                                        <input type="hidden" name="direccion" value="abajo">
                                        <button type="submit" class="boton-orden" aria-label="Bajar «<?php echo escaparSalida($servicio['titulo']); ?>»" <?php echo $posicion === count($catalogo) - 1 ? 'disabled' : ''; ?>>
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                                        </button>
                                    </form>
                                </div>

                                <form action="<?php echo $urlCatalogo; ?>" method="POST">
                                    <input type="hidden" name="operacion" value="cambiar_estatus">
                                    <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
                                    <input type="hidden" name="id_servicio" value="<?php echo (int) $servicio['id_servicio']; ?>">
                                    <input type="hidden" name="estatus" value="<?php echo $visible ? '0' : '1'; ?>">
                                    <button type="submit" class="conmutador-visibilidad<?php echo $visible ? ' activo' : ''; ?>" role="switch" aria-checked="<?php echo $visible ? 'true' : 'false'; ?>" title="<?php echo $visible ? 'Ocultar de mi perfil' : 'Mostrar en mi perfil'; ?>">
                                        <span class="pista-conmutador" aria-hidden="true"></span>
                                        <span><?php echo $visible ? 'Visible' : 'Oculto'; ?></span>
                                    </button>
                                </form>

                                <a href="<?php echo $urlCatalogo; ?>&amp;vista=editar&amp;id=<?php echo (int) $servicio['id_servicio']; ?>" class="boton-catalogo boton-catalogo-secundario">Editar</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

        <?php else: ?>

            <!-- ============ FORMULARIO (nuevo / editar) ============ -->
            <header class="cabecera-catalogo">
                <div>
                    <a href="<?php echo $urlCatalogo; ?>" class="enlace-volver-catalogo">&larr; Volver al catálogo</a>
                    <h1 class="titulo-catalogo"><?php echo $esEdicion ? 'Editar servicio' : 'Nuevo servicio'; ?></h1>
                </div>
            </header>

            <form action="<?php echo $urlCatalogo; ?>" method="POST" enctype="multipart/form-data" class="tarjeta-catalogo formulario-autenticacion" id="formServicio" novalidate>
                <input type="hidden" name="operacion" value="guardar_servicio">
                <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
                <input type="hidden" name="id_servicio" value="<?php echo (int) $servicioEnEdicion; ?>">

                <div class="campo-formulario">
                    <label for="catTitulo">Nombre del servicio</label>
                    <input type="text" id="catTitulo" name="titulo" maxlength="150" value="<?php echo escaparSalida($valores['titulo']); ?>" placeholder="Ej. Instalación de calentador de agua" required>
                    <?php if (isset($errores['titulo'])): ?><span class="error-campo"><?php echo escaparSalida($errores['titulo']); ?></span><?php endif; ?>
                </div>

                <div class="campo-formulario">
                    <label for="catCategoria">Categoría</label>
                    <select id="catCategoria" name="id_categoria" required>
                        <option value="">Elige una categoría</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo (int) $categoria['id_categoria']; ?>" <?php echo (int) $valores['id_categoria'] === (int) $categoria['id_categoria'] ? 'selected' : ''; ?>><?php echo escaparSalida($categoria['nombre_categoria']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errores['id_categoria'])): ?><span class="error-campo"><?php echo escaparSalida($errores['id_categoria']); ?></span><?php endif; ?>
                </div>

                <div class="campo-formulario">
                    <label for="catDescripcion">Descripción detallada</label>
                    <textarea id="catDescripcion" name="descripcion" rows="5" maxlength="1000" placeholder="Qué incluye, materiales, tiempos, garantía..." required><?php echo escaparSalida($valores['descripcion']); ?></textarea>
                    <small class="ayuda-campo"><span id="contadorServicio">0</span>/1000 caracteres (mínimo 20)</small>
                    <?php if (isset($errores['descripcion'])): ?><span class="error-campo"><?php echo escaparSalida($errores['descripcion']); ?></span><?php endif; ?>
                </div>

                <div class="fila-campos">
                    <div class="campo-formulario">
                        <label for="catTipoPrecio">Modalidad de cobro</label>
                        <select id="catTipoPrecio" name="id_tipo_precio" data-a-cotizar="<?php echo (int) PRECIO_A_COTIZAR; ?>" required>
                            <?php foreach ($modalidadesCobro as $idTipo => $nombreTipo): ?>
                                <option value="<?php echo (int) $idTipo; ?>" <?php echo (int) $valores['id_tipo_precio'] === (int) $idTipo ? 'selected' : ''; ?>><?php echo escaparSalida($nombreTipo); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errores['id_tipo_precio'])): ?><span class="error-campo"><?php echo escaparSalida($errores['id_tipo_precio']); ?></span><?php endif; ?>
                    </div>

                    <div class="campo-formulario" id="grupoMontoServicio">
                        <label for="catMonto">Monto (MXN)</label>
                        <input type="number" id="catMonto" name="monto" min="0" max="999999.99" step="0.01" inputmode="decimal" value="<?php echo escaparSalida($valores['monto']); ?>" placeholder="500">
                        <?php if (isset($errores['monto'])): ?><span class="error-campo"><?php echo escaparSalida($errores['monto']); ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="campo-formulario">
                    <label for="catDuracion">Duración estimada</label>
                    <div class="fila-duracion">
                        <input type="number" id="catDuracion" name="duracion_cantidad" min="0" step="any" inputmode="decimal" value="<?php echo escaparSalida($valores['duracion_cantidad']); ?>" placeholder="90" required>
                        <select name="duracion_unidad" aria-label="Unidad de la duración">
                            <option value="minutos" <?php echo $valores['duracion_unidad'] === 'minutos' ? 'selected' : ''; ?>>minutos</option>
                            <option value="horas" <?php echo $valores['duracion_unidad'] === 'horas' ? 'selected' : ''; ?>>horas</option>
                        </select>
                    </div>
                    <?php if (isset($errores['duracion'])): ?><span class="error-campo"><?php echo escaparSalida($errores['duracion']); ?></span><?php endif; ?>
                </div>

                <!-- Galeria de imagenes promocionales -->
                <div class="campo-formulario" id="bloqueGaleria" data-maximo="<?php echo (int) MAX_IMAGENES_POR_SERVICIO; ?>" data-actuales="<?php echo count($imagenesServicio ?? []); ?>">
                    <label for="catImagenes">Galería de imágenes promocionales</label>

                    <?php if (!empty($imagenesServicio)): ?>
                        <ul class="galeria-actual-catalogo">
                            <?php foreach ($imagenesServicio as $imagen): ?>
                                <li>
                                    <img src="<?php echo escaparSalida(urlImagenServicio($imagen['url_imagen'])); ?>" alt="Imagen del servicio" loading="lazy">
                                    <label class="quitar-imagen">
                                        <input type="checkbox" name="quitar_imagenes[]" value="<?php echo (int) $imagen['id_imagen']; ?>" data-quitar-imagen>
                                        Quitar
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <input type="file" id="catImagenes" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple>
                    <small class="ayuda-campo">Hasta <?php echo (int) MAX_IMAGENES_POR_SERVICIO; ?> imágenes por servicio (JPG, PNG o WEBP, mínimo <?php echo (int) IMAGEN_SERVICIO_ANCHO_MINIMO; ?> × <?php echo (int) IMAGEN_SERVICIO_ALTO_MINIMO; ?> px). Las muy grandes se reducen solas antes de subirse.</small>
                    <span class="error-campo" id="errorImagenesCliente" hidden></span>
                    <?php if (isset($errores['imagenes'])): ?><span class="error-campo"><?php echo escaparSalida($errores['imagenes']); ?></span><?php endif; ?>
                    <ul class="galeria-nueva-catalogo" id="vistaPreviaImagenes" hidden></ul>
                </div>

                <label class="casilla-visible-catalogo">
                    <input type="checkbox" name="estatus" value="1" <?php echo !empty($valores['estatus']) ? 'checked' : ''; ?>>
                    <span>Mostrar este servicio en mi perfil público</span>
                </label>

                <div class="acciones-formulario-catalogo">
                    <button type="submit" class="boton-autenticacion boton-catalogo-guardar"><?php echo $esEdicion ? 'Guardar cambios' : 'Crear servicio'; ?></button>
                    <a href="<?php echo $urlCatalogo; ?>" class="boton-enlace">Cancelar</a>
                </div>
            </form>

            <?php if ($esEdicion): ?>
                <section class="tarjeta-catalogo zona-peligro-catalogo" aria-labelledby="tituloEliminar">
                    <h2 id="tituloEliminar">Eliminar servicio</h2>

                    <?php if ($citasServicio > 0): ?>
                        <p>Este servicio tiene <?php echo (int) $citasServicio; ?> <?php echo $citasServicio === 1 ? 'cita registrada' : 'citas registradas'; ?>, por eso no se puede eliminar (se perderían también sus citas y opiniones). Si ya no lo ofreces, desactiva «Mostrar este servicio en mi perfil público» y guarda.</p>
                    <?php else: ?>
                        <p>Se borrará el servicio y todas sus imágenes. Esta acción no se puede deshacer.</p>

                        <form action="<?php echo $urlCatalogo; ?>" method="POST" class="formulario-eliminar-servicio">
                            <input type="hidden" name="operacion" value="eliminar_servicio">
                            <input type="hidden" name="token_csrf" value="<?php echo escaparSalida($token); ?>">
                            <input type="hidden" name="id_servicio" value="<?php echo (int) $servicioEnEdicion; ?>">

                            <label class="casilla-visible-catalogo">
                                <input type="checkbox" name="confirmar" value="1" required>
                                <span>Entiendo que se eliminará de forma definitiva</span>
                            </label>

                            <button type="submit" class="boton-catalogo boton-catalogo-peligro">Eliminar servicio</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</section>

<script src="<?php echo JS_RUTA; ?>catalogo.js?v=1"></script>

<?php
// Recibir parámetros del método GET para la búsqueda
$oficio_buscado = isset($_GET['oficio']) ? limpiarEntrada($_GET['oficio']) : '';
$ubicacion_buscada = isset($_GET['ubicacion']) ? limpiarEntrada($_GET['ubicacion']) : '';

// Conexión y consulta a la Base de Datos para buscar coincidencias
$conexion = conexionBD();

// CORRECCIÓN: Se separaron los parámetros en :busqueda1, :busqueda2 y :busqueda3
$sql = "SELECT s.titulo, s.descripcion, s.monto, u.nombre AS freelancer, c.nombre_categoria 
        FROM servicios s
        INNER JOIN usuarios u ON s.id_usuario = u.id_usuario
        INNER JOIN cat_categorias c ON s.id_categoria = c.id_categoria
        WHERE (s.titulo LIKE :busqueda1 OR s.descripcion LIKE :busqueda2 OR c.nombre_categoria LIKE :busqueda3)";

$termino = '%' . $oficio_buscado . '%';
$sentencia = $conexion->prepare($sql);

// CORRECCIÓN: Se asigna el término a cada parámetro individualmente
$sentencia->execute([
    ':busqueda1' => $termino,
    ':busqueda2' => $termino,
    ':busqueda3' => $termino
]);

$resultados = $sentencia->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Buscador en la parte superior del listado -->
<div style="background: var(--color-fondo-hero); padding: 2rem 1rem;">
    <div class="contenedor-buscador" style="margin-bottom: 0;">
        <form action="/" method="GET" class="caja-busqueda">
            <input type="hidden" name="accion" value="servicios">

            <div class="campo-busqueda campo-oficio">
                <input type="text" name="oficio" class="input-busqueda" value="<?php echo escaparSalida($oficio_buscado); ?>" placeholder="Oficio, especialidad o técnico...">
            </div>

            <div class="divisor-vertical-campos"></div>

            <div class="campo-busqueda campo-zona">
                <input type="text" name="ubicacion" class="input-busqueda" value="<?php echo escaparSalida($ubicacion_buscada); ?>" placeholder="Ubicación">
            </div>

            <button type="submit" class="boton-buscar">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            </button>
        </form>
    </div>
</div>

<!-- Contenedor Principal de Resultados -->
<div class="envoltorio-contenido">
    <div class="cabecera-seccion">
        <h2><?php echo $oficio_buscado ? 'Resultados para: "' . escaparSalida($oficio_buscado) . '"' : 'Todos los servicios disponibles'; ?></h2>
    </div>

    <!-- Rejilla de tarjetas de resultados -->
    <div class="rejilla-especialistas" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));">

        <?php if (count($resultados) > 0): ?>
            <?php foreach ($resultados as $servicio): ?>
                <div class="tarjeta-especialista" style="flex-direction: column; align-items: flex-start; gap: 0.8rem; padding: 1.2rem;">

                    <!-- Categoría y Precio -->
                    <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                        <span style="font-size: 0.75rem; background: var(--color-borde); padding: 3px 8px; border-radius: var(--radio-pequeno); color: var(--color-texto-principal); font-weight: 600;">
                            <?php echo escaparSalida($servicio['nombre_categoria']); ?>
                        </span>
                        <span style="font-size: 1.1rem; font-weight: 800; color: var(--color-boton-principal);">
                            <?php echo $servicio['monto'] > 0 ? '$' . number_format($servicio['monto'], 2) : 'A cotizar'; ?>
                        </span>
                    </div>

                    <!-- Título y Descripción -->
                    <h3 style="font-size: 1.15rem; color: var(--color-texto-principal); margin: 0;"><?php echo escaparSalida($servicio['titulo']); ?></h3>
                    <p style="font-size: 0.88rem; color: var(--color-texto-cuerpo); line-height: 1.4; margin: 0; flex-grow: 1;">
                        <?php echo escaparSalida($servicio['descripcion']); ?>
                    </p>

                    <!-- Nombre del Profesional -->
                    <div style="font-size: 0.85rem; color: var(--color-marcador-posicion); display: flex; align-items: center; gap: 5px; margin-top: 5px;">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        <?php echo escaparSalida($servicio['freelancer']); ?>
                    </div>

                    <!-- Botón de Agendar Deshabilitado -->
                    <button class="boton-nav-sesion" style="width: 100%; margin-top: 10px; background: #e5e7eb; color: #9ca3af !important; border: 1px solid #d1d5db; cursor: not-allowed;" disabled title="Disponible en futuras actualizaciones">
                        Agendar Cita
                    </button>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Mensaje de no hay resultados -->
            <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; background: var(--color-fondo-tarjeta); border-radius: var(--radio-mediano); border: 1px dashed var(--color-borde);">
                <h3 style="color: var(--color-texto-principal); margin-bottom: 0.5rem;">No encontramos profesionales</h3>
                <p style="color: var(--color-marcador-posicion);">Intenta utilizar términos más generales como "Limpieza" o "Plomero".</p>
            </div>
        <?php endif; ?>

    </div>
</div>
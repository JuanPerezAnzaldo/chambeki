<?php
/*
    DIAGNOSTICO TEMPORAL - borrar despues de usarlo.
    Subelo junto a index.php y abre: https://chambeki.com/diagnostico.php
    Copia y pega aqui todo lo que muestre.
*/
ob_start();
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

echo "PHP: " . PHP_VERSION . "  (" . PHP_SAPI . ")\n";
echo "DOCUMENT_ROOT: " . $_SERVER['DOCUMENT_ROOT'] . "\n\n";

function revisarArchivo($ruta)
{
    if (!is_file($ruta))
    {
        echo "[NO EXISTE]       $ruta\n";
        return;
    }

    $permisos = substr(sprintf('%o', fileperms($ruta)), -4);
    $codigo   = file_get_contents($ruta);

    try
    {
        token_get_all($codigo, defined('TOKEN_PARSE') ? TOKEN_PARSE : 0);
        echo "[sintaxis ok]     $ruta  (permisos $permisos)\n";
    }
    catch (\Throwable $e)
    {
        echo "[ERROR SINTAXIS]  $ruta  linea " . $e->getLine() . ": " . $e->getMessage() . "\n";
    }

    $defaults = preg_match_all('/\bdefault\s*:/', $codigo);

    if (basename($ruta) === 'index.php' && $defaults > 1)
    {
        echo "  >>> AVISO: index.php tiene $defaults 'default:'. Debe haber solo 1 en el switch.\n";
    }
}

echo "== 1) Revision de index.php ==\n";
revisarArchivo($_SERVER['DOCUMENT_ROOT'] . '/index.php');

echo "\n== 2) Cargando configuration.php ==\n";
$rutaConfig = $_SERVER['DOCUMENT_ROOT'] . '/shared/includes/configuration.php';
revisarArchivo($rutaConfig);

try
{
    include $rutaConfig;
    echo "configuration.php cargo bien.\n";
}
catch (\Throwable $e)
{
    echo "FALLO al cargar configuration.php: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
}

echo "\n== 3) Archivos que cargo configuration.php ==\n";
foreach (get_included_files() as $archivo)
{
    if (strpos($archivo, '/vendor/') === false && $archivo !== __FILE__)
    {
        revisarArchivo($archivo);
    }
}

echo "\n== 4) Funciones del listado ==\n";
foreach (['buscarServicios', 'leerFiltrosListado', 'obtenerCategoriasListado', 'columnaLlaveServicios', 'conexionBD'] as $funcion)
{
    echo str_pad($funcion, 28) . (function_exists($funcion) ? 'existe' : 'NO EXISTE') . "\n";
}
echo "SERVICIOS_POR_PAGINA: " . (defined('SERVICIOS_POR_PAGINA') ? SERVICIOS_POR_PAGINA : 'NO DEFINIDA') . "\n";

echo "\n== 5) Prueba de la pagina del listado ==\n";
if (defined('PAGINAS'))
{
    revisarArchivo(PAGINAS . 'servicio.php');

    $accion = 'servicios';
    $_GET['accion'] = 'servicios';

    try
    {
        ob_start();
        include PAGINAS . 'servicio.php';
        $html = ob_get_clean();

        echo "servicio.php se ejecuto sin error fatal (" . strlen($html) . " caracteres de HTML).\n";

        if (strpos($html, 'ERROR REAL') !== false)
        {
            preg_match('/ERROR REAL:[^<]*/', $html, $coincidencia);
            echo ">>> " . $coincidencia[0] . "\n";
        }
    }
    catch (\Throwable $e)
    {
        while (ob_get_level() > 1) { ob_end_clean(); }
        echo "FALLO servicio.php: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
    }
}
else
{
    echo "PAGINAS no esta definida (configuration.php no termino de cargar).\n";
}

echo "\n== Fin del diagnostico ==\n";
ob_end_flush();
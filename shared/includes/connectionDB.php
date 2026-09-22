<?php
/*
    Conexion a la base de datos (RF-04)
    Se usa PDO porque permite sentencias preparadas en todas las
    consultas y con eso se neutraliza la inyeccion SQL (RS-02).
*/

/*
    ENTORNO:
      'produccion' -> el sitio corre en el mismo hosting que MySQL, se usa localhost
      'local'      -> pruebas desde tu maquina; hay que habilitar "Remote MySQL"
                      en hPanel y dar de alta tu IP, o usar % para cualquier IP
*/
define('ENTORNO_BD', 'produccion');

define('BD_SERVIDOR', ENTORNO_BD === 'produccion' ? 'localhost' : 'auth-db761.hstgr.io');
define('BD_NOMBRE',   'u168577920_titmraa');
define('BD_USUARIO',  'u168577920_titmraa');
//nunca se sube la clave real al repositorio: se toma de una variable de entorno
//y solo se usa el segundo valor como respaldo mientras pruebas en tu maquina
define('BD_CLAVE',    getenv('CHAMBEKI_BD_CLAVE') ?: 'Chambek1');
define('BD_CHARSET',  'utf8mb4');

function conexionBD()
{
    //se guarda la conexion para no abrir una nueva en cada consulta
    static $conexion = null;

    if ($conexion !== null)
    {
        return $conexion;
    }

    $cadenaConexion = 'mysql:host=' . BD_SERVIDOR . ';dbname=' . BD_NOMBRE . ';charset=' . BD_CHARSET;

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        //false obliga a que las sentencias preparadas las arme el motor, no PHP
        PDO::ATTR_EMULATE_PREPARES   => false
    ];

    try
    {
        $conexion = new PDO($cadenaConexion, BD_USUARIO, BD_CLAVE, $opciones);
    }
    catch (PDOException $excepcion)
    {
        //nunca se le muestra el detalle del error al usuario final
        error_log('Error de conexion a BD: ' . $excepcion->getMessage());
        die('No fue posible conectar con el servidor. Intenta mas tarde.');
    }

    return $conexion;
}
?>
<?php
/*
    Conexion a la base de datos (RF-04)
    Se usa PDO porque permite sentencias preparadas en todas las
    consultas y con eso se neutraliza la inyeccion SQL (RS-02).
*/

/*
    ENTORNO:
      'produccion' -> el sitio corre en el mismo hosting que MySQL, se usa localhost
      'local'      -> pruebas desde tu maquina; hay que habilitar "Remote MySQL"
                      en hPanel y dar de alta tu IP, o usar % para cualquier IP
*/
define('ENTORNO_BD', 'produccion');

define('BD_SERVIDOR', ENTORNO_BD === 'produccion' ? 'localhost' : 'auth-db761.hstgr.io');
define('BD_NOMBRE',   'u168577920_titmraa');
define('BD_USUARIO',  'u168577920_titmraa');
//nunca se sube la clave real al repositorio: se toma de una variable de entorno
//y solo se usa el segundo valor como respaldo mientras pruebas en tu maquina
define('BD_CLAVE',    getenv('CHAMBEKI_BD_CLAVE') ?: 'Chambek1');
define('BD_CHARSET',  'utf8mb4');

function conexionBD()
{
    //se guarda la conexion para no abrir una nueva en cada consulta
    static $conexion = null;

    if ($conexion !== null)
    {
        return $conexion;
    }

    $cadenaConexion = 'mysql:host=' . BD_SERVIDOR . ';dbname=' . BD_NOMBRE . ';charset=' . BD_CHARSET;

    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        //false obliga a que las sentencias preparadas las arme el motor, no PHP
        PDO::ATTR_EMULATE_PREPARES   => false
    ];

    try
    {
        $conexion = new PDO($cadenaConexion, BD_USUARIO, BD_CLAVE, $opciones);
    }
    catch (PDOException $excepcion)
    {
        //nunca se le muestra el detalle del error al usuario final
        error_log('Error de conexion a BD: ' . $excepcion->getMessage());
        die('No fue posible conectar con el servidor. Intenta mas tarde.');
    }

    return $conexion;
}
?>

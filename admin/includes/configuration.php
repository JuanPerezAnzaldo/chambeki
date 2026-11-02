<?php
    if (session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }
    
    // Rutas base
    define('URL_BASE', 'http://' . $_SERVER['HTTP_HOST'] . '/');
    define('ADMIN_DOCROOT', $_SERVER['DOCUMENT_ROOT'] . '/admin/');

    define('DB_HOST','localhost');
    define('DB_NAME','u168577920_titmraa');
    define('DB_USAR','root');
    define('DB_PASS','');

    try {
        $conexionAdmin = new PDO (
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    }catch (PDOException $e){
        die("Error en la conexion con la BD" . $e->getMessage());
    }
?>
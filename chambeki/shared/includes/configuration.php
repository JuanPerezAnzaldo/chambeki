<?php
    define('SITNAME', 'chambeki');
    
    define('DOCROOT', $_SERVER['DOCUMENT_ROOT'] . '/chambeki/');
    define('ROOTURL', '/');
    
    define('HEADER', DOCROOT . 'user/includes/header.php');
    define('FOOTER', DOCROOT . 'user/includes/footer.php');
    define('FUNCTIONS', DOCROOT . 'user/includes/functions.php');
    define('PAGE', DOCROOT . 'user/pages/');
    
    
    if (file_exists(FUNCTIONS)) 
    {
        include(FUNCTIONS);
    }
    
    if (session_status() === PHP_SESSION_NONE) 
    {
        session_start();
    }
?>
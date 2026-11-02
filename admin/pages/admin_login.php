<?php
session_start();

session_unset();
session_destroy();
session_start();

$host = 'localhost';
$db = 'panel_admin';
$user = 'root';
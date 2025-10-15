<?php
// Configuración de la conexión a la base de datos

// Reemplaza estos valores con tus propias credenciales
define('DB_HOST', 'localhost');
define('DB_USER', 'u271451192_diagramas'); // <-- CAMBIA ESTO
define('DB_PASS', 'Diagramas2025'); // <-- CAMBIA ESTO
define('DB_NAME', 'u271451192_diagramas');

// Crear la conexión usando MySQLi
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Verificar la conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer el juego de caracteres a UTF-8 para soportar caracteres especiales
$conn->set_charset("utf8mb4");

// Establecer la zona horaria para la conexión actual
$conn->query("SET time_zone = '-06:00'");

?>
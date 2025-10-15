<?php
// Establecer la zona horaria por defecto para toda la aplicación
date_default_timezone_set('America/Costa_Rica');

/*
 * Conexión a la Base de Datos
 * Reemplaza los valores con los de tu configuración local.
 */

// Datos de conexión
$servername = "localhost"; // O la IP de tu servidor de base de datos
$username = "u271451192_diagramas";        // Tu usuario de MySQL
$password = "Diagramas2025";            // Tu contraseña de MySQL
$dbname = "u271451192_diagramas";      // El nombre de tu base de datos

// Crear la conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Establecer el charset a utf8mb4 para soportar caracteres especiales
$conn->set_charset("utf8mb4");

?>

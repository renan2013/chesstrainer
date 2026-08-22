<?php
// Configuración unificada de la conexión a la base de datos
$root_db = __DIR__ . '/../db_connect.php';
if (file_exists($root_db)) {
    require_once $root_db;
}
?>
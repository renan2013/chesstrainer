<?php
// Incluir el archivo de conexión a la base de datos
require_once '../db_connect.php';

// Verificar si el formulario fue enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validar y limpiar los datos de entrada
    $theme = trim($_POST['theme']);
    $fen = trim($_POST['fen']);
    $solution = trim($_POST['solution']);

    // Una validación simple para asegurarse de que los campos no están vacíos
    if (empty($theme) || empty($fen) || empty($solution)) {
        die("Error: Todos los campos son obligatorios.");
    }

    // Preparar la consulta SQL para evitar inyecciones SQL
    $stmt = $conn->prepare("INSERT INTO puzzles (theme, fen, solution) VALUES (?, ?, ?)");
    
    // Verificar si la preparación de la consulta fue exitosa
    if ($stmt === false) {
        die("Error al preparar la consulta: " . $conn->error);
    }

    // Vincular los parámetros a la consulta
    // sss indica que los tres parámetros son strings (cadenas de texto)
    $stmt->bind_param("sss", $theme, $fen, $solution);

    // Ejecutar la consulta
    if ($stmt->execute()) {
        // Si fue exitoso, mostrar un mensaje y un enlace para volver
        echo "<h1>Problema guardado con éxito!</h1>";
        echo "<p>El problema ha sido añadido a la base de datos.</p>";
        echo "<a href='add_puzzle.php'>Añadir otro problema</a>";
    } else {
        // Si hubo un error, mostrar el mensaje de error
        echo "Error al guardar el problema: " . $stmt->error;
    }

    // Cerrar el statement y la conexión
    $stmt->close();
    $conn->close();

} else {
    // Si alguien intenta acceder a este script directamente sin enviar el formulario
    echo "Acceso no permitido.";
}

?>

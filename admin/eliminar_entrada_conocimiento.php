<?php
require_once 'includes/header.php';

// 1. Comprobar si el usuario es administrador
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
    $_SESSION['error'] = "No tienes permiso para realizar esta acción.";
    header("Location: base_conocimiento.php");
    exit;
}

// 2. Validar el ID de la entrada
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID de entrada no válido.";
    header("Location: base_conocimiento.php");
    exit;
}
$id_entrada = $_GET['id'];

// 3. Preparar y ejecutar la sentencia DELETE
$sql = "DELETE FROM base_conocimiento WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id_entrada);
    if ($stmt->execute()) {
        // Usar una variable de sesión para el mensaje de éxito
        $_SESSION['mensaje'] = "La entrada ha sido eliminada exitosamente.";
    } else {
        $_SESSION['error'] = "Error al eliminar la entrada: " . $stmt->error;
    }
    $stmt->close();
} else {
    $_SESSION['error'] = "Error al preparar la consulta: " . $conn->error;
}

$conn->close();

// 4. Redirigir de vuelta a la página principal del módulo
header("Location: base_conocimiento.php");
exit;
?>

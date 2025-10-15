<?php
require_once '../includes/db.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['rol'] !== 'administrador') {
    header("location: ../login.php");
    exit;
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_publicacion = $_GET['id'];

    // Eliminar la publicación
    $sql = "DELETE FROM publicacion WHERE id_publicacion = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_publicacion);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Publicación eliminada exitosamente.";
        } else {
            $_SESSION['error'] = "Error al eliminar la publicación: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $_SESSION['error'] = "ID de publicación no especificado.";
}

header("location: gestionar_publicaciones.php");
exit;
?>
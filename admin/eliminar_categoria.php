<?php
require_once '../includes/db.php';
session_start();

// Verificar si el usuario ha iniciado sesión y es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['rol'] !== 'administrador') {
    header("location: ../login.php");
    exit;
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_categoria = $_GET['id'];

    // Eliminar la categoría
    $sql = "DELETE FROM categorias WHERE id_categorias = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_categoria);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Categoría eliminada exitosamente.";
        } else {
            $_SESSION['error'] = "Error al eliminar la categoría: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $_SESSION['error'] = "ID de categoría no especificado.";
}

header("location: gestionar_categorias.php");
exit;
?>
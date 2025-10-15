<?php
require_once '../includes/db.php';
session_start();

// Verificar si el usuario ha iniciado sesión y tiene el rol adecuado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("location: ../login.php");
    exit;
}

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id_problema = $_GET['id'];

    // Eliminar el problema
    $sql = "DELETE FROM problemas WHERE id_problemas = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id_problema);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Problema eliminado exitosamente.";
        } else {
            $_SESSION['error'] = "Error al eliminar el problema: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $_SESSION['error'] = "ID de problema no especificado.";
}

header("location: anadir_problema.php");
exit;
?>
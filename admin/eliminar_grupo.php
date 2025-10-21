<?php
session_start();
require '../db_connect.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['id_usuarios']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: ../login.php");
    exit;
}

if (isset($_GET['id'])) {
    $id_grupo = intval($_GET['id']);

    // Preparar la declaración para eliminar el grupo
    $stmt = $conn->prepare("DELETE FROM grupos WHERE id = ?");
    $stmt->bind_param("i", $id_grupo);

    if ($stmt->execute()) {
        // Éxito, redirigir a la lista de grupos
        header("Location: grupos.php");
        exit;
    } else {
        // Error
        echo "Error al eliminar el grupo: " . $conn->error;
    }

    $stmt->close();
} else {
    // No se proporcionó ID, redirigir
    header("Location: grupos.php");
    exit;
}

$conn->close();
?>

<?php
session_start();

// Verificar si el usuario ha iniciado sesión y es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'administrador') {
    header("location: ../login.php");
    exit;
}

require_once '../db_connect.php';

$error = '';
$mensaje = '';

if (isset($_GET['id']) && !empty(trim($_GET['id']))) {
    $id_usuario_a_eliminar = trim($_GET['id']);

    // Preparar la consulta para eliminar al usuario
    $sql_delete = "DELETE FROM usuarios WHERE id_usuarios = ?";
    
    if ($stmt_delete = $conn->prepare($sql_delete)) {
        $stmt_delete->bind_param("i", $id_usuario_a_eliminar);
        
        if ($stmt_delete->execute()) {
            $mensaje = "Usuario eliminado correctamente.";
        } else {
            $error = "Error al eliminar el usuario: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    } else {
        $error = "Error al preparar la consulta de eliminación.";
    }
} else {
    $error = "ID de usuario no especificado para eliminar.";
}

$conn->close();

// Redirigir de vuelta a la página de gestión de usuarios con un mensaje
if (!empty($mensaje)) {
    header("location: gestionar_usuarios.php?mensaje=" . urlencode($mensaje));
} elseif (!empty($error)) {
    header("location: gestionar_usuarios.php?error=" . urlencode($error));
} else {
    header("location: gestionar_usuarios.php");
}
exit;
?>
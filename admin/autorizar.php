<?php
session_start();
require_once '../db_connect.php';

// Verificar si el usuario ha iniciado sesión y es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'administrador') {
    $_SESSION['error'] = "No tienes permiso para realizar esta acción.";
    header("location: ../login.php");
    exit;
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $id_usuario = $_GET['id'];
    $action = $_GET['action'];

    if ($action !== 'authorize' && $action !== 'unauthorize') {
        $_SESSION['error'] = "Acción no válida.";
        header("location: gestionar_usuarios.php");
        exit;
    }

    // Obtener el nombre del usuario para el mensaje
    $nombre_usuario = '';
    $sql_get_user = "SELECT nombre_usuario FROM usuarios WHERE id_usuarios = ?";
    if ($stmt_get_user = $conn->prepare($sql_get_user)) {
        $stmt_get_user->bind_param("i", $id_usuario);
        $stmt_get_user->execute();
        $stmt_get_user->bind_result($nombre_usuario);
        $stmt_get_user->fetch();
        $stmt_get_user->close();
    }

    if (empty($nombre_usuario)) {
        $_SESSION['error'] = "No se encontró un usuario con el ID proporcionado.";
        header("location: gestionar_usuarios.php");
        exit;
    }

    // Proceder con la actualización
    $autorizado_val = ($action === 'authorize') ? 1 : 0;
    $accion_texto = ($action === 'authorize') ? "autorizado" : "desautorizado";

    $sql_update = "UPDATE usuarios SET autorizado = ? WHERE id_usuarios = ?";
    if ($stmt_update = $conn->prepare($sql_update)) {
        $stmt_update->bind_param("ii", $autorizado_val, $id_usuario);
        if ($stmt_update->execute()) {
            $_SESSION['mensaje'] = "¡Usuario '" . htmlspecialchars($nombre_usuario) . "' {$accion_texto} correctamente!";
        } else {
            $_SESSION['error'] = "Error al actualizar al usuario '" . htmlspecialchars($nombre_usuario) . "'.";
        }
        $stmt_update->close();
    } else {
        $_SESSION['error'] = "Error al preparar la consulta de actualización.";
    }
} else {
    $_SESSION['error'] = "Faltan parámetros para realizar la acción.";
}

$conn->close();

// Redirigir siempre de vuelta a la página de gestión
header("location: gestionar_usuarios.php");
exit;
?>
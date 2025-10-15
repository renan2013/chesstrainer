<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $response['message'] = 'Usuario no autenticado.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $problem_id = filter_input(INPUT_POST, 'problem_id', FILTER_VALIDATE_INT);
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $solved = filter_input(INPUT_POST, 'solved', FILTER_VALIDATE_INT); // Expecting 1 for solved

    if ($problem_id === false || $user_id === false || $solved === false) {
        $response['message'] = 'Datos de entrada inválidos.';
        echo json_encode($response);
        exit;
    }

    // Check if an entry already exists for this user and problem
    $sql_check = "SELECT COUNT(*) FROM progreso_usuarios WHERE id_usuarios = ? AND id_problemas = ?";
    if ($stmt_check = $conn->prepare($sql_check)) {
        $stmt_check->bind_param("ii", $user_id, $problem_id);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            // Update existing entry
            $sql_update = "UPDATE progreso_usuarios SET resuelto_correctamente = ?, fecha_intento = NOW() WHERE id_usuarios = ? AND id_problemas = ?";
            if ($stmt_update = $conn->prepare($sql_update)) {
                $stmt_update->bind_param("iii", $solved, $user_id, $problem_id);
                if ($stmt_update->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Progreso actualizado correctamente.';
                } else {
                    $response['message'] = 'Error al actualizar el progreso: ' . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $response['message'] = 'Error al preparar la consulta de actualización: ' . $conn->error;
            }
        } else {
            // Insert new entry
            $sql_insert = "INSERT INTO progreso_usuarios (id_usuarios, id_problemas, resuelto_correctamente, fecha_intento) VALUES (?, ?, ?, NOW())";
            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("iii", $user_id, $problem_id, $solved);
                if ($stmt_insert->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Progreso registrado correctamente.';
                } else {
                    $response['message'] = 'Error al registrar el progreso: ' . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                $response['message'] = 'Error al preparar la consulta de inserción: ' . $conn->error;
            }
        }
    } else {
        $response['message'] = 'Error al preparar la consulta de verificación: ' . $conn->error;
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

$conn->close();
echo json_encode($response);
?>
<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $response['message'] = 'Usuario no autenticado.';
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION["id_usuarios"];
    $problemId = filter_input(INPUT_POST, 'problem_id', FILTER_VALIDATE_INT);
    $attempts = filter_input(INPUT_POST, 'attempts', FILTER_VALIDATE_INT);
    $solvedStatus = filter_input(INPUT_POST, 'solved_status', FILTER_VALIDATE_INT); // 0 = in progress, 1 = solved, 2 = failed

    if ($problemId === false || $attempts === false) {
        $response['message'] = 'Datos de entrada inválidos.';
        echo json_encode($response);
        exit;
    }

    if ($solvedStatus === false || $solvedStatus === null) {
        $solvedStatus = 0;
    }

    // Check if record exists
    $sql_check = "SELECT id_progreso_usuarios, intentos, resuelto_correctamente FROM progreso_usuarios WHERE id_usuarios = ? AND id_problemas = ?";
    if ($stmt_check = $conn->prepare($sql_check)) {
        $stmt_check->bind_param("ii", $userId, $problemId);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $newAttempts = max($row['intentos'], $attempts);
            $newStatus = ($row['resuelto_correctamente'] == 1) ? 1 : max($row['resuelto_correctamente'], $solvedStatus);
            
            $sql_update = "UPDATE progreso_usuarios SET intentos = ?, resuelto_correctamente = ?, fecha_intento = NOW() WHERE id_usuarios = ? AND id_problemas = ?";
            if ($stmt_update = $conn->prepare($sql_update)) {
                $stmt_update->bind_param("iiii", $newAttempts, $newStatus, $userId, $problemId);
                $stmt_update->execute();
                $stmt_update->close();
                $response['success'] = true;
                $response['message'] = 'Intento registrado.';
            }
        } else {
            $sql_insert = "INSERT INTO progreso_usuarios (id_usuarios, id_problemas, resuelto_correctamente, intentos, fecha_intento) VALUES (?, ?, ?, ?, NOW())";
            if ($stmt_insert = $conn->prepare($sql_insert)) {
                $stmt_insert->bind_param("iiii", $userId, $problemId, $solvedStatus, $attempts);
                $stmt_insert->execute();
                $stmt_insert->close();
                $response['success'] = true;
                $response['message'] = 'Intento registrado.';
            }
        }
        $stmt_check->close();
    } else {
        $response['message'] = 'Error de BD al preparar consulta.';
    }
}

echo json_encode($response);

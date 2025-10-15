<?php
session_start();
require_once 'db_connect.php'; // Usamos el nuevo conector

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'new_rating' => null];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $response['message'] = 'Usuario no autenticado.';
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION["id_usuarios"];
    $problemId = filter_input(INPUT_POST, 'problem_id', FILTER_VALIDATE_INT);
    $outcome = filter_input(INPUT_POST, 'outcome', FILTER_VALIDATE_INT); // 1 for solved, 2 for failed
    $difficulty = filter_input(INPUT_POST, 'difficulty', FILTER_SANITIZE_STRING);

    if ($problemId === false || $outcome === false || !$difficulty) {
        $response['message'] = 'Datos de entrada inválidos.';
        echo json_encode($response);
        exit;
    }

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // 1. Verificar si ya existe un registro para este usuario y problema en el historial
        $sql_check = "SELECT id_historial FROM historial_puntos WHERE id_usuarios = ? AND id_problemas = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $userId, $problemId);
        $stmt_check->execute();
        $stmt_check->store_result();

        
        $stmt_check->close();

        // 2. Calcular el cambio de puntos
        $baseChange = 0;
        switch ($difficulty) {
            case 'Fácil': $baseChange = 1; break;
            case 'Intermedio': $baseChange = 2; break;
            case 'Difícil': $baseChange = 3; break;
            case 'Experto': $baseChange = 4; break;
            default: throw new Exception('Dificultad de problema inválida.');
        }

        $puntos_cambio = 0;
        if ($outcome == 1) { // Resuelto
            $puntos_cambio = $baseChange;
        } elseif ($outcome == 2) { // Fallado
            $puntos_cambio = -$baseChange;
        } else {
            throw new Exception('Resultado de problema inválido.');
        }

        // 3. Insertar en historial_puntos
        $sql_insert_history = "
            INSERT INTO historial_puntos (id_usuarios, id_problemas, puntos_cambio)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
            puntos_cambio = VALUES(puntos_cambio)";
        $stmt_insert = $conn->prepare($sql_insert_history);
        $stmt_insert->bind_param("iii", $userId, $problemId, $puntos_cambio);
        if (!$stmt_insert->execute()) {
            throw new Exception('Error al guardar el historial de puntos.');
        }
        $stmt_insert->close();

        // 4. Insertar o actualizar en progreso_usuarios
        $resuelto_correctamente = ($outcome == 1) ? 1 : (($outcome == 2) ? 2 : 0);
        $sql_update_progress = "
            INSERT INTO progreso_usuarios (id_usuarios, id_problemas, resuelto_correctamente, fecha_intento)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            resuelto_correctamente = VALUES(resuelto_correctamente), fecha_intento = NOW()";
        $stmt_progress = $conn->prepare($sql_update_progress);
        $stmt_progress->bind_param("iii", $userId, $problemId, $resuelto_correctamente);
        if (!$stmt_progress->execute()) {
            throw new Exception('Error al actualizar el progreso del usuario.');
        }
        $stmt_progress->close();

        // 5. Actualizar el rating del usuario
        $sql_update_rating = "UPDATE usuarios SET rating = rating + ? WHERE id_usuarios = ?";
        $stmt_update = $conn->prepare($sql_update_rating);
        $stmt_update->bind_param("ii", $puntos_cambio, $userId);
        if (!$stmt_update->execute()) {
            throw new Exception('Error al actualizar el rating del usuario.');
        }
        $stmt_update->close();

        // 5. Obtener el nuevo rating para devolverlo
        $sql_get_rating = "SELECT rating FROM usuarios WHERE id_usuarios = ?";
        $stmt_get = $conn->prepare($sql_get_rating);
        $stmt_get->bind_param("i", $userId);
        $stmt_get->execute();
        $newRating = 0;
        $stmt_get->bind_result($newRating);
        $stmt_get->fetch();
        $stmt_get->close();

        // Si todo fue bien, confirmar la transacción
        $conn->commit();

        $_SESSION['rating'] = $newRating;
        $response['success'] = true;
        $response['new_rating'] = $newRating;
        $response['message'] = 'Puntuación actualizada correctamente.';

    } catch (Exception $e) {
        // Si algo falló, revertir la transacción
        $conn->rollback();
        $response['message'] = $e->getMessage();
        error_log("Error en update_rating.php: " . $e->getMessage());
    }

} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

$conn->close();
echo json_encode($response);
?>

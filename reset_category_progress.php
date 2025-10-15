<?php
session_start();
// CORRECCIÓN: La ruta a db_connect.php es en la raíz.
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
    $categoryId = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);

    if ($categoryId === false) {
        $response['message'] = 'ID de categoría inválido.';
        echo json_encode($response);
        exit;
    }

    $conn->begin_transaction();

    try {
        $sql_problems = "SELECT id_problemas FROM problemas WHERE id_categorias = ?";
        $stmt_problems = $conn->prepare($sql_problems);
        $stmt_problems->bind_param("i", $categoryId);
        $stmt_problems->execute();
        $result = $stmt_problems->get_result();
        $problem_ids = [];
        while ($row = $result->fetch_assoc()) {
            $problem_ids[] = $row['id_problemas'];
        }
        $stmt_problems->close();

        if (empty($problem_ids)) {
            $conn->commit();
            $response['success'] = true;
            $response['message'] = 'No hay problemas en esta categoría para resetear.';
            echo json_encode($response);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($problem_ids), '?'));
        
        $params_for_bind = $problem_ids;
        array_unshift($params_for_bind, $userId);
        $types = "i" . str_repeat('i', count($problem_ids));
        $refs = [];
        foreach ($params_for_bind as $key => $value) {
            $refs[$key] = &$params_for_bind[$key];
        }

        // 1. Calcular el total de puntos a revertir
        $sql_sum = "SELECT SUM(puntos_cambio) AS total_revertir FROM historial_puntos WHERE id_usuarios = ? AND id_problemas IN ($placeholders)";
        $stmt_sum = $conn->prepare($sql_sum);
        call_user_func_array([$stmt_sum, 'bind_param'], array_merge([$types], $refs));
        $stmt_sum->execute();
        $total_revertir = 0;
        $stmt_sum->bind_result($total_revertir);
        $stmt_sum->fetch();
        $stmt_sum->close();

        // 2. Revertir el rating del usuario si es necesario
        if ($total_revertir !== null && $total_revertir != 0) {
            $sql_update_rating = "UPDATE usuarios SET rating = rating - ? WHERE id_usuarios = ?";
            $stmt_update = $conn->prepare($sql_update_rating);
            $stmt_update->bind_param("ii", $total_revertir, $userId);
            if (!$stmt_update->execute()) throw new Exception('Error al revertir el rating del usuario.');
            $stmt_update->close();
        }

        // 3. Borrar el historial de puntos para la categoría
        $sql_delete_history = "DELETE FROM historial_puntos WHERE id_usuarios = ? AND id_problemas IN ($placeholders)";
        $stmt_delete_hist = $conn->prepare($sql_delete_history);
        call_user_func_array([$stmt_delete_hist, 'bind_param'], array_merge([$types], $refs));
        if (!$stmt_delete_hist->execute()) throw new Exception('Error al borrar el historial de puntos.');
        $stmt_delete_hist->close();

        // 4. Borrar el progreso del usuario para la categoría
        $sql_delete_progress = "DELETE FROM progreso_usuarios WHERE id_usuarios = ? AND id_problemas IN ($placeholders)";
        $stmt_delete_prog = $conn->prepare($sql_delete_progress);
        call_user_func_array([$stmt_delete_prog, 'bind_param'], array_merge([$types], $refs));
        if (!$stmt_delete_prog->execute()) throw new Exception('Error al borrar el progreso del usuario.');
        $stmt_delete_prog->close();

        $sql_delete_cat_results = "DELETE FROM resultados_categorias WHERE id_usuarios = ? AND id_categorias = ?";
        $stmt_delete_cat = $conn->prepare($sql_delete_cat_results);
        $stmt_delete_cat->bind_param("ii", $userId, $categoryId);
        if (!$stmt_delete_cat->execute()) throw new Exception('Error al borrar los resultados de la categoría.');
        $stmt_delete_cat->close();

        // 5. Obtener el nuevo rating y actualizar la sesión
        $sql_get_new_rating = "SELECT rating FROM usuarios WHERE id_usuarios = ?";
        $stmt_get_rating = $conn->prepare($sql_get_new_rating);
        $stmt_get_rating->bind_param("i", $userId);
        $stmt_get_rating->execute();
        $new_rating = 0;
        $stmt_get_rating->bind_result($new_rating);
        $stmt_get_rating->fetch();
        $stmt_get_rating->close();

        $_SESSION['rating'] = $new_rating;
        $response['new_rating'] = $new_rating;

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Categoría reseteada correctamente.';

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Error en la transacción: ' . $e->getMessage();
        error_log("Error en reset_category_progress.php: " . $e->getMessage());
    }

} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

$conn->close();
echo json_encode($response);
?>

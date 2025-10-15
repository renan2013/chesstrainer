<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["id_usuarios"] ?? null;
    $category_id = $_POST['category_id'] ?? null;

    if ($user_id && $category_id !== null) {
        // Start transaction
        $conn->begin_transaction();

        try {
            // 1. Delete from progreso_usuarios
            // Join with problemas to ensure we only delete for the specific category
            $sql_delete_progress = "DELETE pu FROM progreso_usuarios pu JOIN problemas p ON pu.id_problemas = p.id_problemas WHERE pu.id_usuarios = ? AND p.id_categorias = ?";
            $stmt_progress = $conn->prepare($sql_delete_progress);
            $stmt_progress->bind_param("ii", $user_id, $category_id);
            $stmt_progress->execute();
            $stmt_progress->close();

            // 2. Delete from resultados_categorias
            $sql_delete_results = "DELETE FROM resultados_categorias WHERE id_usuarios = ? AND id_categorias = ?";
            $stmt_results = $conn->prepare($sql_delete_results);
            $stmt_results->bind_param("ii", $user_id, $category_id);
            $stmt_results->execute();
            $stmt_results->close();

            $conn->commit();
            $response['success'] = true;
            $response['message'] = "Progreso de la categoría reiniciado exitosamente.";

        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            $response['message'] = "Error al reiniciar el progreso: " . $e->getMessage();
        }

    } else {
        $response['message'] = "Datos incompletos para reiniciar el progreso.";
    }
} else {
    $response['message'] = "Método de solicitud no válido.";
}

$conn->close();
echo json_encode($response);
?>
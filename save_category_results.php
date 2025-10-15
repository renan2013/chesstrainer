<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'badge_awarded' => false
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["id_usuarios"] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $publicacion_id = $_POST['publicacion_id'] ?? null; // <-- Recibimos el ID de la publicación
    $solved_problems = $_POST['solved_problems'] ?? null;
    $total_problems = $_POST['total_problems'] ?? null;
    $percentage = $_POST['percentage'] ?? null;

    if ($user_id && $category_id !== null && $publicacion_id !== null && $solved_problems !== null && $total_problems !== null && $percentage !== null) {
        
        // Primero, guardar o actualizar los resultados de la categoría
        $sql_check = "SELECT id_resultado_categoria FROM resultados_categorias WHERE id_usuarios = ? AND id_categorias = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $user_id, $category_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $sql_save = "UPDATE resultados_categorias SET problemas_resueltos = ?, total_problemas = ?, porcentaje_aciertos = ?, fecha_ultima_actualizacion = NOW() WHERE id_usuarios = ? AND id_categorias = ?";
            $stmt_save = $conn->prepare($sql_save);
            $stmt_save->bind_param("iidii", $solved_problems, $total_problems, $percentage, $user_id, $category_id);
        } else {
            $sql_save = "INSERT INTO resultados_categorias (id_usuarios, id_categorias, problemas_resueltos, total_problemas, porcentaje_aciertos) VALUES (?, ?, ?, ?, ?)";
            $stmt_save = $conn->prepare($sql_save);
            $stmt_save->bind_param("iiiid", $user_id, $category_id, $solved_problems, $total_problems, $percentage);
        }
        $stmt_check->close();

        if ($stmt_save->execute()) {
            $response['success'] = true;
            $response['message'] = "Resultados guardados exitosamente.";

            // --- LÓGICA PARA OTORGAR INSIGNIA ---
            if ($percentage >= 80) {
                $sql_award_badge = "INSERT IGNORE INTO insignias_usuarios (id_usuarios, id_publicacion, id_categorias) VALUES (?, ?, ?)";
                $stmt_badge = $conn->prepare($sql_award_badge);
                $stmt_badge->bind_param("iii", $user_id, $publicacion_id, $category_id);
                $stmt_badge->execute();

                if ($stmt_badge->affected_rows > 0) {
                    // Si affected_rows > 0, significa que se insertó una nueva insignia (no era un duplicado)
                    $sql_get_badge_info = "SELECT nombre_categoria, imagen_insignia FROM categorias WHERE id_categorias = ? LIMIT 1";
                    $stmt_info = $conn->prepare($sql_get_badge_info);
                    $stmt_info->bind_param("i", $category_id);
                    $stmt_info->execute();
                    $result_info = $stmt_info->get_result();
                    if ($badge_info = $result_info->fetch_assoc()) {
                        if (!empty($badge_info['imagen_insignia'])) {
                            $response['badge_awarded'] = true;
                            $response['badge_name'] = $badge_info['nombre_categoria'];
                            $response['badge_image'] = 'admin/' . $badge_info['imagen_insignia'];
                        }
                    }
                    $stmt_info->close();
                }
                $stmt_badge->close();
            }
            // --- FIN DE LA LÓGICA DE INSIGNIA ---

        } else {
            $response['message'] = "Error al guardar resultados: " . $stmt_save->error;
        }
        $stmt_save->close();

    } else {
        $response['message'] = "Datos incompletos para guardar resultados.";
    }
} else {
    $response['message'] = "Método de solicitud no válido.";
}

$conn->close();
echo json_encode($response);
?>
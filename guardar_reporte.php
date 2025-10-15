<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Ocurrió un error inesperado.'];

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION["id_usuarios"])) {
    $response['message'] = 'Error: Debes iniciar sesión para reportar un error.';
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["id_usuarios"];
    $problem_id = $_POST['problem_id'] ?? null;
    $description = $_POST['description'] ?? null;

    // Basic validation
    if (empty($problem_id) || !is_numeric($problem_id)) {
        $response['message'] = 'Error: ID de problema no válido.';
        echo json_encode($response);
        exit;
    }

    if (empty($description) || trim($description) === '') {
        $response['message'] = 'Error: La descripción no puede estar vacía.';
        echo json_encode($response);
        exit;
    }

    // Insert into the database
    $sql = "INSERT INTO reporte_errores (id_problemas, id_usuarios, descripcion_error) VALUES (?, ?, ?)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("iis", $problem_id, $user_id, $description);
        
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = '¡Gracias! Tu reporte ha sido enviado exitosamente.';
        } else {
            $response['message'] = 'Error del servidor: No se pudo guardar el reporte. Inténtalo más tarde.';
            // For debugging: $response['error'] = $stmt->error;
        }
        $stmt->close();
    } else {
        $response['message'] = 'Error del servidor: No se pudo preparar la consulta.';
        // For debugging: $response['error'] = $conn->error;
    }

} else {
    $response['message'] = 'Error: Método de solicitud no válido.';
}

$conn->close();
echo json_encode($response);
?>

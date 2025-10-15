<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

// Log received POST data
error_log("update_report_status.php: Received POST data: " . print_r($_POST, true));

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['rol'] !== 'administrador') {
    $response['message'] = 'Acceso denegado.';
    error_log("update_report_status.php: Access denied for user. Session: " . print_r($_SESSION, true));
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_reporte']) && isset($_POST['resuelto'])) {
    $id_reporte = $_POST['id_reporte'];
    $resuelto = $_POST['resuelto'] ? 1 : 0; // Convert boolean to 1 or 0

    $sql = "UPDATE reporte_errores SET resuelto = ? WHERE id_reporte = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ii", $resuelto, $id_reporte);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Estado del reporte actualizado correctamente.';
            error_log("update_report_status.php: Report ID " . $id_reporte . " updated to resuelto=" . $resuelto . " successfully.");
        } else {
            $response['message'] = 'Error al actualizar el estado del reporte: ' . $stmt->error;
            error_log("update_report_status.php: Error updating report ID " . $id_reporte . ": " . $stmt->error);
        }
        $stmt->close();
    } else {
        $response['message'] = 'Error al preparar la consulta: ' . $conn->error;
        error_log("update_report_status.php: Error preparing query: " . $conn->error);
    }
} else {
    $response['message'] = 'Solicitud inválida.';
    error_log("update_report_status.php: Invalid request. Method: " . $_SERVER['REQUEST_METHOD'] . ", POST: " . print_r($_POST, true));
}

$conn->close();
echo json_encode($response);
?>
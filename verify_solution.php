<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $response['message'] = "Error: Usuario no autenticado.";
    echo json_encode($response);
    exit;
}

require_once 'includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["solucion_usuario"]) && isset($_POST["problema_id"])) {
    $solucion_usuario = trim($_POST["solucion_usuario"]);
    $problema_id = $_POST["problema_id"];
    $id_usuario = $_SESSION["id_usuarios"];

    $conn->begin_transaction();

    try {
        // 1. Consultar el estado actual del problema para el usuario
        $intentos_previos = 0;
        $ya_resuelto = false;
        $sql_check = "SELECT intentos, resuelto_correctamente FROM progreso_usuarios WHERE id_usuarios = ? AND id_problemas = ?";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bind_param("ii", $id_usuario, $problema_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $progreso = $result_check->fetch_assoc();
            $intentos_previos = $progreso['intentos'];
            $ya_resuelto = (bool)$progreso['resuelto_correctamente'];
        }
        $stmt_check->close();

        // 2. Validar si el usuario puede realizar un intento
        if ($ya_resuelto) {
            throw new Exception("Ya has resuelto este problema correctamente.");
        }
        if ($intentos_previos >= 2) {
            throw new Exception("Has superado el número máximo de 2 intentos para este problema.");
        }

        // 3. Obtener la solución correcta del problema
        $sql_solucion = "SELECT solucion FROM problemas WHERE id_problemas = ?";
        $stmt_solucion = $conn->prepare($sql_solucion);
        $stmt_solucion->bind_param("i", $problema_id);
        $stmt_solucion->execute();
        $stmt_solucion->bind_result($solucion_correcta);
        $stmt_solucion->fetch();
        $stmt_solucion->close();

        // 4. Comparar soluciones y determinar el resultado
        $resuelto_correctamente = 0;
        if (strtolower($solucion_usuario) === strtolower($solucion_correcta)) {
            $response['success'] = true;
            $response['message'] = "<div class=\"result-message success\">¡Correcto! Excelente.</div>";
            $resuelto_correctamente = 1;
        } else {
            $response['message'] = "<div class=\"result-message error\">Incorrecto. La solución correcta era: " . htmlspecialchars($solucion_correcta) . "</div>";
        }

        // 5. Registrar el intento en la base de datos (INSERT o UPDATE)
        // Esto incrementa el contador y actualiza el estado de resolución
        $sql_upsert_progreso = "
            INSERT INTO progreso_usuarios (id_usuarios, id_problemas, intentos, resuelto_correctamente, fecha_intento)
            VALUES (?, ?, 1, ?, NOW())
            ON DUPLICATE KEY UPDATE
            intentos = intentos + 1,
            resuelto_correctamente = IF(resuelto_correctamente = 1, 1, VALUES(resuelto_correctamente)),
            fecha_intento = NOW()";
        
        $stmt_upsert = $conn->prepare($sql_upsert_progreso);
        $stmt_upsert->bind_param("iii", $id_usuario, $problema_id, $resuelto_correctamente);
        if (!$stmt_upsert->execute()) {
            throw new Exception("Error al registrar el intento.");
        }
        $stmt_upsert->close();

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollback();
        $response['success'] = false;
        $response['message'] = "<div class=\"result-message error\">" . $e->getMessage() . "</div>";
    }

} else {
    $response['message'] = "Error: Datos de solución incompletos.";
}

$conn->close();
echo json_encode($response);
?>

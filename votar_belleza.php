<?php
session_start();
require_once 'db_connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'voted' => false, 'total_votos' => 0, 'message' => ''];

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    $response['message'] = 'Usuario no autenticado.';
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userId = $_SESSION["id_usuarios"];
    $problemId = filter_input(INPUT_POST, 'problem_id', FILTER_VALIDATE_INT);

    if ($problemId === false || !$problemId) {
        $response['message'] = 'ID de problema inválido.';
        echo json_encode($response);
        exit;
    }

    // Auto-crear tabla si no existe
    $sql_create_table = "
        CREATE TABLE IF NOT EXISTS `votos_belleza` (
          `id_voto` int(11) NOT NULL AUTO_INCREMENT,
          `id_usuarios` int(11) NOT NULL,
          `id_problemas` int(11) NOT NULL,
          `fecha_voto` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id_voto`),
          UNIQUE KEY `voto_unico_usuario_problema` (`id_usuarios`,`id_problemas`),
          KEY `id_usuarios` (`id_usuarios`),
          KEY `id_problemas` (`id_problemas`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($sql_create_table);

    // Verificar si el usuario ya votó
    $sql_check = "SELECT id_voto FROM votos_belleza WHERE id_usuarios = ? AND id_problemas = ?";
    if ($stmt_check = $conn->prepare($sql_check)) {
        $stmt_check->bind_param("ii", $userId, $problemId);
        $stmt_check->execute();
        $result = $stmt_check->get_result();

        if ($result->num_rows > 0) {
            // Ya votó -> Eliminar voto (Toggle OFF)
            $sql_delete = "DELETE FROM votos_belleza WHERE id_usuarios = ? AND id_problemas = ?";
            if ($stmt_del = $conn->prepare($sql_delete)) {
                $stmt_del->bind_param("ii", $userId, $problemId);
                $stmt_del->execute();
                $stmt_del->close();
            }
            $voted = false;
        } else {
            // No ha votado -> Agregar voto (Toggle ON)
            $sql_insert = "INSERT INTO votos_belleza (id_usuarios, id_problemas) VALUES (?, ?)";
            if ($stmt_ins = $conn->prepare($sql_insert)) {
                $stmt_ins->bind_param("ii", $userId, $problemId);
                $stmt_ins->execute();
                $stmt_ins->close();
            }
            $voted = true;
        }
        $stmt_check->close();

        // Obtener el total actualizado de votos de belleza para este problema
        $sql_count = "SELECT COUNT(*) as total FROM votos_belleza WHERE id_problemas = ?";
        if ($stmt_count = $conn->prepare($sql_count)) {
            $stmt_count->bind_param("i", $problemId);
            $stmt_count->execute();
            $res_count = $stmt_count->get_result();
            if ($row_c = $res_count->fetch_assoc()) {
                $totalVotos = (int)$row_c['total'];
            } else {
                $totalVotos = 0;
            }
            $stmt_count->close();
        }

        $response['success'] = true;
        $response['voted'] = $voted;
        $response['total_votos'] = $totalVotos;
        $response['message'] = $voted ? '¡Gracias por votar la belleza de este ejercicio!' : 'Voto removido.';
    } else {
        $response['message'] = 'Error al preparar la consulta.';
    }
}

echo json_encode($response);

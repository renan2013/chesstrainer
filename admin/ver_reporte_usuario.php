<?php
$page_title = "Reporte de Usuario";
require_once 'includes/header.php';

// Asegurarse de que el usuario tenga el rol adecuado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("location: ../login.php");
    exit;
}

$id_usuario_reporte = $_GET['id'] ?? null;

if (!$id_usuario_reporte) {
    $_SESSION['error'] = "ID de usuario no especificado para el reporte.";
    header("location: ver_totales_usuario.php");
    exit;
}

// Obtener el nombre del usuario para el reporte
$nombre_usuario_reporte = "";
$sql_nombre = "SELECT nombre_usuario FROM usuarios WHERE id_usuarios = ?";
if ($stmt_nombre = $conn->prepare($sql_nombre)) {
    $stmt_nombre->bind_param("i", $id_usuario_reporte);
    $stmt_nombre->execute();
    $result_nombre = $stmt_nombre->get_result();
    if ($result_nombre->num_rows > 0) {
        $nombre_usuario_reporte = $result_nombre->fetch_assoc()['nombre_usuario'];
    }
    $stmt_nombre->close();
}

// Obtener los resultados por categoría para el usuario
$resultados_categorias = [];
$sql_categorias = "
    SELECT
        c.nombre_categoria,
        rc.problemas_resueltos,
        rc.total_problemas,
        rc.porcentaje_aciertos,
        rc.fecha_ultima_actualizacion
    FROM
        resultados_categorias rc
    JOIN
        categorias c ON rc.id_categorias = c.id_categorias
    WHERE
        rc.id_usuarios = ?
    ORDER BY
        c.nombre_categoria;
";

if ($stmt_categorias = $conn->prepare($sql_categorias)) {
    $stmt_categorias->bind_param("i", $id_usuario_reporte);
    $stmt_categorias->execute();
    $result_categorias = $stmt_categorias->get_result();
    while ($row = $result_categorias->fetch_assoc()) {
        $resultados_categorias[] = $row;
    }
    $stmt_categorias->close();
}

// Calcular el porcentaje global de aciertos
$total_resueltos_global = 0;
$total_problemas_global = 0;
foreach ($resultados_categorias as $res) {
    $total_resueltos_global += $res['problemas_resueltos'];
    $total_problemas_global += $res['total_problemas'];
}
$porcentaje_global = ($total_problemas_global > 0) ? ($total_resueltos_global / $total_problemas_global) * 100 : 0;

?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; // Incluimos el menú lateral ?>

    <main class="main-content">
        <div class="container mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Reporte de Resultados para: <?php echo htmlspecialchars($nombre_usuario_reporte); ?></h3>
                <div>
                    <a href="ver_grafica_usuario.php?id=<?php echo urlencode($id_usuario_reporte); ?>" class="btn btn-info me-2">Ver Gráfica de Rendimiento</a>
                    <a href="ver_totales_usuario.php" class="btn btn-secondary">&larr; Volver a Totales por Usuario</a>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Resumen Global</div>
                <div class="card-body">
                    <p><strong>Total de Problemas Resueltos:</strong> <?php echo htmlspecialchars($total_resueltos_global); ?></p>
                    <p><strong>Total de Problemas Intentados:</strong> <?php echo htmlspecialchars($total_problemas_global); ?></p>
                    <p><strong>Porcentaje Global de Aciertos:</strong> <?php echo htmlspecialchars(number_format($porcentaje_global, 2)); ?>%</p>
                </div>
            </div>

            <h4>Resultados por Categoría</h4>
            <?php if (empty($resultados_categorias)): ?>
                <div class="alert alert-info">
                    Este usuario aún no tiene resultados registrados por categoría.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Categoría</th>
                                <th>Resueltos</th>
                                <th>Total</th>
                                <th>Porcentaje (%)</th>
                                <th>Último Intento</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_categorias as $fila): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fila['nombre_categoria']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['problemas_resueltos']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['total_problemas']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($fila['porcentaje_aciertos'], 2)); ?></td>
                                    <td>
    Debug:<br>
    DB: <?php echo $fila['fecha_ultima_actualizacion']; ?><br>
    strtotime: <?php echo strtotime($fila['fecha_ultima_actualizacion']); ?><br>
    strtotime -5h: <?php echo strtotime($fila['fecha_ultima_actualizacion'] . ' -5 hours'); ?><br>
    Final: <?php echo htmlspecialchars(date("d/m/Y H:i e", strtotime($fila['fecha_ultima_actualizacion'] . ' -5 hours'))); ?>
</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php
require_once 'includes/footer.php';
?>

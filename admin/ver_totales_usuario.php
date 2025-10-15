<?php
$page_title = "Totales por Usuario";
require_once 'includes/header.php';

// Asegurarse de que el usuario tenga el rol adecuado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("location: ../login.php");
    exit;
}

// Consultar los totales por usuario
$sql = "
    SELECT
        u.id_usuarios,
        u.nombre_usuario,
        SUM(rc.problemas_resueltos) AS total_resueltos,
        SUM(rc.total_problemas) AS total_general,
        (SUM(rc.problemas_resueltos) / SUM(rc.total_problemas)) * 100 AS porcentaje_total
    FROM
        resultados_categorias rc
    JOIN
        usuarios u ON rc.id_usuarios = u.id_usuarios
    GROUP BY
        u.nombre_usuario
    ORDER BY
        total_resueltos DESC;
";

$totales_usuario = [];
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $totales_usuario[] = $row;
    }
    $result->free();
}
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; // Incluimos el menú lateral ?>

    <main class="main-content">
        <div class="container mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Totales de Problemas Resueltos por Usuario</h3>
                <a href="ver_resultados.php" class="btn btn-secondary">&larr; Volver a Resultados por Categoría</a>
            </div>

            <?php if (empty($totales_usuario)): ?>
                <div class="alert alert-info">
                    Aún no hay totales de usuarios para mostrar.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Usuario</th>
                                <th>Problemas Resueltos</th>
                                <th>Total de Problemas</th>
                                <th>Porcentaje de Aciertos (%)</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($totales_usuario as $fila): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fila['nombre_usuario']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['total_resueltos']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['total_general']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($fila['porcentaje_total'], 2)); ?></td>
                                    <td>
                                        <a href="ver_reporte_usuario.php?id=<?php echo urlencode($fila['id_usuarios']); ?>" class="btn btn-sm btn-primary">Reporte</a>
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

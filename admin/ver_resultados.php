<?php
$page_title = "Resultados de Usuarios";
require_once 'includes/header.php';

// Asegurarse de que el usuario tenga el rol adecuado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("location: ../login.php");
    exit;
}

// Consultar los resultados de todos los usuarios
$sql = "
    SELECT
        u.nombre_usuario,
        c.nombre_categoria,
        rc.problemas_resueltos,
        rc.total_problemas,
        rc.porcentaje_aciertos,
        rc.fecha_ultima_actualizacion
    FROM
        resultados_categorias rc
    JOIN
        usuarios u ON rc.id_usuarios = u.id_usuarios
    JOIN
        categorias c ON rc.id_categorias = c.id_categorias
    ORDER BY
        u.nombre_usuario, c.nombre_categoria;
";

$resultados = [];
if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $resultados[] = $row;
    }
    $result->free();
}
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Resultados por Categoría</h1>
            <a href="ver_totales_usuario.php" class="btn btn-info">Ver Totales por Usuario</a>
        </div>

        <?php if (empty($resultados)): ?>
            <div class="alert alert-info">
                Aún no hay resultados de categorías para mostrar.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Usuario</th>
                            <th>Categoría</th>
                            <th>Resueltos</th>
                            <th>Total</th>
                            <th>Porcentaje (%)</th>
                            <th>Fecha del Intento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $fila): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fila['nombre_usuario']); ?></td>
                                <td><?php echo htmlspecialchars($fila['nombre_categoria']); ?></td>
                                <td><?php echo htmlspecialchars($fila['problemas_resueltos']); ?></td>
                                <td><?php echo htmlspecialchars($fila['total_problemas']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($fila['porcentaje_aciertos'], 2)); ?></td>
                                <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($fila['fecha_ultima_actualizacion']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php
require_once 'includes/footer.php';
?>
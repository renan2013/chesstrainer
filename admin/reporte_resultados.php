<?php
$page_title = "Reporte de Resultados";
require_once 'includes/header.php';

// Verificar si el usuario tiene el rol adecuado para ver reportes
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("location: index.php"); // Redirigir si no tiene permiso
    exit;
}

// Obtener todos los resultados de categorías de los usuarios
$resultados = [];
$sql_resultados = "
    SELECT
        rc.id_resultado_categoria,
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
        u.nombre_usuario ASC, c.nombre_categoria ASC
";

$result_resultados = $conn->query($sql_resultados);

if ($result_resultados && $result_resultados->num_rows > 0) {
    while($row = $result_resultados->fetch_assoc()) {
        $resultados[] = $row;
    }
}

?>

<h2 class="mb-4">Reporte de Resultados por Categoría</h2>

<?php if (empty($resultados)): ?>
    <div class="alert alert-info" role="alert">
        No hay resultados de categorías registrados aún.
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-light">
                <tr>
                    <th>Usuario</th>
                    <th>Categoría</th>
                    <th>Resueltos</th>
                    <th>Total</th>
                    <th>% Aciertos</th>
                    <th>Última Actualización</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultados as $res): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($res['nombre_usuario']); ?></td>
                        <td><?php echo htmlspecialchars($res['nombre_categoria']); ?></td>
                        <td><?php echo htmlspecialchars($res['problemas_resueltos']); ?></td>
                        <td><?php echo htmlspecialchars($res['total_problemas']); ?></td>
                        <td><?php echo htmlspecialchars(number_format($res['porcentaje_aciertos'], 2)); ?>%</td>
                        <td><?php echo htmlspecialchars($res['fecha_ultima_actualizacion']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
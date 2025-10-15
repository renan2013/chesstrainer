<?php
$page_title = "Gráfica de Rendimiento";
require_once 'includes/header.php';

// Asegurarse de que el usuario tenga el rol adecuado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("location: ../login.php");
    exit;
}

$id_usuario_reporte = $_GET['id'] ?? null;

if (!$id_usuario_reporte) {
    $_SESSION['error'] = "ID de usuario no especificado para la gráfica.";
    header("location: ver_totales_usuario.php");
    exit;
}

// Obtener el nombre del usuario
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

// Obtener los porcentajes de aciertos por categoría para el usuario
$labels = [];
$data = [];
$sql_grafica = "
    SELECT
        c.nombre_categoria,
        rc.porcentaje_aciertos
    FROM
        resultados_categorias rc
    JOIN
        categorias c ON rc.id_categorias = c.id_categorias
    WHERE
        rc.id_usuarios = ?
    ORDER BY
        c.nombre_categoria;
";

if ($stmt_grafica = $conn->prepare($sql_grafica)) {
    $stmt_grafica->bind_param("i", $id_usuario_reporte);
    $stmt_grafica->execute();
    $result_grafica = $stmt_grafica->get_result();
    while ($row = $result_grafica->fetch_assoc()) {
        $labels[] = $row['nombre_categoria'];
        $data[] = $row['porcentaje_aciertos'];
    }
    $stmt_grafica->close();
}

?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; // Incluimos el menú lateral ?>

    <main class="main-content">
        <div class="container mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>Gráfica de Rendimiento para: <?php echo htmlspecialchars($nombre_usuario_reporte); ?></h3>
                <a href="ver_reporte_usuario.php?id=<?php echo urlencode($id_usuario_reporte); ?>" class="btn btn-secondary">&larr; Volver al Reporte</a>
            </div>

            <?php if (empty($labels)): ?>
                <div class="alert alert-info">
                    Este usuario aún no tiene datos de rendimiento por categoría para mostrar en la gráfica.
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header">Porcentaje de Aciertos por Categoría</div>
                    <div class="card-body">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const labels = <?php echo json_encode($labels); ?>;
            const data = <?php echo json_encode($data); ?>;

            const ctx = document.getElementById('performanceChart').getContext('2d');
            const performanceChart = new Chart(ctx, {
                type: 'bar', // Puedes cambiar a 'line', 'radar', etc.
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Porcentaje de Aciertos',
                        data: data,
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Porcentaje de Aciertos'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Categoría'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y + '%';
                                }
                            }
                        }
                    }
                }
            });
        </script>
    </main>
</div>

<?php
require_once 'includes/footer.php';
?>

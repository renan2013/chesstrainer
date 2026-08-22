<?php
$page_title = "Ranking General";
require_once 'includes/header.php';

// Solo administradores e instructores pueden ver esta página
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("location: index.php");
    exit;
}

// Obtener usuarios para el ranking
$ranked_users = [];
$sql = "SELECT nombre_usuario, rating FROM usuarios WHERE rating > 0 ORDER BY rating DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $ranked_users[] = $row;
    }
}

// Preparar datos para el gráfico Chart.js (Top 10)
$top_10_users = array_slice($ranked_users, 0, 10);
$chart_labels = [];
$chart_data = [];
foreach ($top_10_users as $user) {
    $chart_labels[] = $user['nombre_usuario'];
    $chart_data[] = $user['rating'];
}

$conn->close();
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Ranking General de Jugadores</h1>
            <?php if (!empty($ranked_users)): ?>
                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#top10ChartModal">
                    <i class="fas fa-chart-bar me-2"></i>Ver Gráfica Top 10
                </button>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-trophy me-2"></i>Tabla de Posiciones</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Posición</th>
                                <th>Nombre de Usuario</th>
                                <th>Puntaje (Rating)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ranked_users)): ?>
                                <?php foreach ($ranked_users as $index => $user): ?>
                                    <tr>
                                        <td><strong><?php echo $index + 1; ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['nombre_usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($user['rating']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No hay usuarios con puntaje para mostrar.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Modal para el Gráfico Top 10 -->
<div class="modal fade" id="top10ChartModal" tabindex="-1" aria-labelledby="top10ChartModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="top10ChartModalLabel"><i class="fas fa-trophy me-2"></i>Top 10 Jugadores por Rating</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <canvas id="top10RankingChart"></canvas>
      </div>
    </div>
  </div>
</div>


<!-- Incluir Chart.js desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const labels = <?php echo json_encode($chart_labels); ?>;
    const data = <?php echo json_encode($chart_data); ?>;

    if (labels.length > 0) {
        const ctx = document.getElementById('top10RankingChart').getContext('2d');
        const rankingChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Puntaje (Rating)',
                    data: data,
                    backgroundColor: 'rgba(23, 162, 184, 0.6)',
                    borderColor: 'rgba(23, 162, 184, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>

<?php 
$page_title = "Panel de Administración";
require_once 'includes/header.php'; 

if (!in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
    header("location: ../login.php");
    exit;
}

// --- Cargar Estadísticas ---
$total_users = $conn->query("SELECT COUNT(*) as count FROM usuarios")->fetch_assoc()['count'];
$total_publications = $conn->query("SELECT COUNT(*) as count FROM publicacion")->fetch_assoc()['count'];
$total_categories = $conn->query("SELECT COUNT(*) as count FROM categorias")->fetch_assoc()['count'];
$total_problems = $conn->query("SELECT COUNT(*) as count FROM problemas")->fetch_assoc()['count'];

// --- Cargar Datos para el Gráfico Top 10 ---
$top_users = [];
$sql_top_users = "SELECT nombre_usuario, rating FROM usuarios ORDER BY rating DESC LIMIT 10";
$result_top_users = $conn->query($sql_top_users);
if ($result_top_users) {
    while ($row = $result_top_users->fetch_assoc()) {
        $top_users[] = $row;
    }
}

$chart_labels = [];
$chart_data = [];
foreach ($top_users as $user) {
    $chart_labels[] = $user['nombre_usuario'];
    $chart_data[] = $user['rating'];
}

$conn->close();
?>
<!-- Incluir CSS de chessboard.js -->
<link rel="stylesheet" href="../css/chessboard-1.0.0.min.css">

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Panel de Control</h1>
            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#top10Modal">
                <i class="fas fa-chart-bar me-2"></i>Ver Gráfica Top 10
            </button>
        </div>
        
        <p>Bienvenido(a), <strong><?php echo htmlspecialchars($_SESSION["nombre_usuario"]); ?></strong> (Rol: <em><?php echo htmlspecialchars($_SESSION["rol"]); ?></em>).</p>

        <!-- Buscador General -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="busqueda_general.php" method="GET">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control form-control-lg" placeholder="Buscar usuarios, publicaciones, problemas..." required>
                        <button class="btn btn-success" type="submit"><i class="fas fa-search"></i> Buscar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Panel Principal de Acción -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="card-title">Comienza a crear</h4>
                        <p class="card-text">Añade nuevos problemas y ejercicios a la plataforma. Haz clic en el botón para ir al editor de diagramas.</p>
                        <a href="anadir_problema.php" class="btn btn-primary btn-lg">Crear Diagrama</a>
                    </div>
                    <div class="col-md-4 text-center">
                        <img src="../img/chessboard.svg" alt="Chessboard" style="width: 156px; height: 156px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Tarjetas de Estadísticas -->
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Usuarios</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Publicaciones</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_publications; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-newspaper fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Categorías</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_categories; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-book-open fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Total Problemas</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_problems; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-puzzle-piece fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Modal para el Gráfico Top 10 -->
<div class="modal fade" id="top10Modal" tabindex="-1" aria-labelledby="top10ModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="top10ModalLabel"><i class="fas fa-trophy me-2"></i>Top 10 Jugadores por Rating</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <canvas id="top10Chart"></canvas>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../js/chessboard-1.0.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gráfico Top 10
    const labels = <?php echo json_encode($chart_labels); ?>;
    const data = <?php echo json_encode($chart_data); ?>;
    const ctx = document.getElementById('top10Chart').getContext('2d');
    const top10Chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Rating',
                data: data,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: { y: { beginAtZero: true } },
            responsive: true,
            plugins: { legend: { display: false } }
        }
    });



});
</script>

<?php require_once '../includes/footer.php'; ?>
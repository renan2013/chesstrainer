<?php 
$page_title = "Panel de Administración";
require_once 'includes/header.php'; 

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
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

<style>
/* Estilos modernos para el Dashboard de Administración */
.dashboard-wrapper {
    width: 100%;
    max-width: 1700px;
    margin: 0 auto;
}

.dashboard-header {
    background: #ffffff;
    border-radius: 16px;
    padding: 24px 28px;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
    border: 1px solid #e9ecef;
    margin-bottom: 24px;
}

/* Tarjetas de Estadísticas KPI */
.kpi-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 22px;
    border: 1px solid #edf2f7;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    transition: all 0.25s ease;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
}

.kpi-icon-box {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.kpi-val {
    font-size: 2.1rem;
    font-weight: 700;
    line-height: 1.1;
    color: #1e293b;
    margin-top: 8px;
}

.kpi-label {
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
}

/* Hero de Creación */
.hero-create-card {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 60%, #334155 100%);
    color: #ffffff;
    border-radius: 20px;
    padding: 32px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
}

.hero-create-card::after {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(59, 130, 246, 0.18) 0%, rgba(0, 0, 0, 0) 70%);
    border-radius: 50%;
    pointer-events: none;
}

.hero-board-img {
    width: 165px;
    height: 165px;
    filter: drop-shadow(0 10px 18px rgba(0,0,0,0.35));
    transition: transform 0.3s ease;
}

.hero-board-img:hover {
    transform: scale(1.05) rotate(2deg);
}

/* Buscador Moderno */
.modern-search-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 20px 24px;
    border: 1px solid #edf2f7;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
}

.search-input-group {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 4px 8px;
    transition: all 0.2s ease;
}

.search-input-group:focus-within {
    background: #ffffff;
    border-color: #3b82f6;
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12);
}

.search-input-group input {
    background: transparent;
    border: none;
    box-shadow: none !important;
}

/* Tarjetas Laterales */
.side-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 22px;
    border: 1px solid #edf2f7;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.03);
    margin-bottom: 24px;
}

.side-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Accesos Rápidos */
.quick-action-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 12px;
    background: #f8fafc;
    color: #334155;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.quick-action-item:hover {
    background: #ffffff;
    color: #1d4ed8;
    border-color: #bfdbfe;
    transform: translateX(4px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.04);
}

.quick-action-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

/* Ranking List */
.ranking-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 12px;
    border-radius: 10px;
    transition: background 0.2s ease;
}

.ranking-item:hover {
    background: #f8fafc;
}

.ranking-pos {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
}
</style>

<div class="dashboard-wrapper container-fluid px-3 px-sm-4 px-xl-5 py-4">

    <!-- CABECERA PRINCIPAL DEL DASHBOARD -->
    <header class="dashboard-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h1 class="h3 fw-bold text-dark mb-0">Panel de Administración</h1>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 fw-semibold text-capitalize">
                    <i class="fas fa-shield-alt me-1"></i><?php echo htmlspecialchars($_SESSION["rol"]); ?>
                </span>
            </div>
            <p class="text-muted mb-0">
                Bienvenido(a), <strong class="text-dark"><?php echo htmlspecialchars($_SESSION["nombre_usuario"]); ?></strong>. Gestión integral y seguimiento de la plataforma de ajedrez.
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-info fw-semibold px-3 py-2 shadow-sm rounded-3 d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#top10Modal">
                <i class="fas fa-chart-bar text-info"></i>
                <span>Gráfica Top 10</span>
            </button>
            <a href="../index.php" target="_blank" class="btn btn-outline-secondary fw-semibold px-3 py-2 shadow-sm rounded-3 d-inline-flex align-items-center gap-2" title="Previsualizar como usuario">
                <i class="fas fa-external-link-alt"></i>
                <span>Ver como usuario</span>
            </a>
        </div>
    </header>

    <!-- TARJETAS DE ESTADÍSTICAS KPI (ANCHO COMPLETO) -->
    <section class="row g-3 g-xl-4 mb-4">
        <!-- Total Usuarios -->
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="kpi-label text-primary">Total Usuarios</span>
                        <div class="kpi-val"><?php echo number_format($total_users); ?></div>
                    </div>
                    <div class="kpi-icon-box bg-primary-subtle text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <small class="text-muted">Alumnos y docentes</small>
                    <a href="gestionar_usuarios.php" class="text-decoration-none small fw-bold text-primary">Gestionar <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Total Publicaciones -->
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="kpi-label text-success">Total Publicaciones</span>
                        <div class="kpi-val"><?php echo number_format($total_publications); ?></div>
                    </div>
                    <div class="kpi-icon-box bg-success-subtle text-success">
                        <i class="fas fa-newspaper"></i>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <small class="text-muted">Cursos y módulos</small>
                    <a href="gestionar_publicaciones.php" class="text-decoration-none small fw-bold text-success">Gestionar <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Total Categorías -->
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="kpi-label text-info" style="color: #0284c7 !important;">Total Categorías</span>
                        <div class="kpi-val"><?php echo number_format($total_categories); ?></div>
                    </div>
                    <div class="kpi-icon-box bg-info-subtle text-info">
                        <i class="fas fa-book-open"></i>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <small class="text-muted">Temas organizados</small>
                    <a href="gestionar_categorias.php" class="text-decoration-none small fw-bold text-info">Gestionar <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
            </div>
        </div>

        <!-- Total Problemas -->
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <span class="kpi-label text-warning" style="color: #d97706 !important;">Total Problemas</span>
                        <div class="kpi-val"><?php echo number_format($total_problems); ?></div>
                    </div>
                    <div class="kpi-icon-box bg-warning-subtle text-warning">
                        <i class="fas fa-puzzle-piece"></i>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                    <small class="text-muted">Diagramas tácticos</small>
                    <a href="anadir_problema.php" class="text-decoration-none small fw-bold text-warning">Nuevo diagrama <i class="fas fa-plus ms-1"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- ÁREA PRINCIPAL EN DOS COLUMNAS -->
    <div class="row g-4">
        <!-- COLUMNA PRINCIPAL (HERO DE CREACIÓN Y BUSCADOR) -->
        <div class="col-xl-8 col-lg-7">
            <!-- HERO CARD: COMIENZA A CREAR -->
            <div class="hero-create-card mb-4">
                <div class="row align-items-center gy-4">
                    <div class="col-md-8 col-12">
                        <div class="d-inline-flex align-items-center gap-1 bg-white bg-opacity-10 border border-white border-opacity-20 px-3 py-1 rounded-pill text-warning small fw-semibold mb-3">
                            <i class="fas fa-chess-knight"></i> Taller de Ejercicios
                        </div>
                        <h2 class="h3 fw-bold mb-2">Comienza a crear nuevos diagramas</h2>
                        <p class="text-white-50 mb-4" style="line-height: 1.6;">
                            Añade nuevos ejercicios interactivos, configura jugadas de solución, alternativas y niveles de dificultad para los estudiantes.
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="anadir_problema.php" class="btn btn-primary btn-lg fw-semibold px-4 py-2 rounded-3 shadow d-inline-flex align-items-center gap-2">
                                <i class="fas fa-plus-circle"></i>
                                <span>Crear Diagrama</span>
                            </a>
                            <a href="gestionar_categorias.php" class="btn btn-outline-light btn-lg fw-semibold px-3 py-2 rounded-3">
                                <span>Ver Categorías</span>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4 col-12 text-center">
                        <img src="../img/chessboard.svg" alt="Tablero de Ajedrez" class="hero-board-img img-fluid">
                    </div>
                </div>
            </div>

            <!-- BUSCADOR GENERAL DE LA PLATAFORMA -->
            <div class="modern-search-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h5 class="fw-bold text-dark mb-0"><i class="fas fa-search me-2 text-primary"></i>Búsqueda Global</h5>
                    <span class="text-muted small">Usuarios, problemas y publicaciones</span>
                </div>
                <form action="busqueda_general.php" method="GET" class="mt-3">
                    <div class="search-input-group d-flex align-items-center">
                        <i class="fas fa-search text-muted fs-5 ms-2 me-2"></i>
                        <input type="text" name="q" class="form-control form-control-lg fs-6 py-2" placeholder="Escribe el nombre de un alumno, tema, o ID de problema..." required>
                        <button class="btn btn-primary px-4 py-2 rounded-3 fw-semibold ms-2" type="submit">
                            Buscar
                        </button>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-3 pt-2 align-items-center">
                    <span class="text-muted small me-1">Atajos rápidos:</span>
                    <a href="busqueda_general.php?q=mate" class="badge bg-light text-secondary border text-decoration-none px-2 py-1">Jaque Mate</a>
                    <a href="busqueda_general.php?q=tactica" class="badge bg-light text-secondary border text-decoration-none px-2 py-1">Táctica</a>
                    <a href="busqueda_general.php?q=finales" class="badge bg-light text-secondary border text-decoration-none px-2 py-1">Finales</a>
                    <a href="busqueda_general.php?q=apertura" class="badge bg-light text-secondary border text-decoration-none px-2 py-1">Apertura</a>
                </div>
            </div>
        </div>

        <!-- COLUMNA LATERAL (ACCESOS DIRECTOS Y RANKING) -->
        <div class="col-xl-4 col-lg-5">
            <!-- ACCESOS RÁPIDOS -->
            <div class="side-card">
                <div class="side-card-title">
                    <i class="fas fa-bolt text-warning"></i>
                    <span>Acciones Frecuentes</span>
                </div>
                <div class="d-flex flex-column gap-2">
                    <a href="anadir_problema.php" class="quick-action-item">
                        <div class="quick-action-icon bg-primary-subtle text-primary">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div>Crear Nuevo Ejercicio</div>
                            <small class="text-muted fw-normal">Editor FEN y soluciones</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="gestionar_categorias.php" class="quick-action-item">
                        <div class="quick-action-icon bg-info-subtle text-info">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div>Gestionar Categorías</div>
                            <small class="text-muted fw-normal">Organizar ejercicios y temas</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="grupos.php" class="quick-action-item">
                        <div class="quick-action-icon bg-success-subtle text-success">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div>Grupos de Alumnos</div>
                            <small class="text-muted fw-normal">Cursos, niveles y matrículas</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </a>

                    <a href="gestionar_reportes.php" class="quick-action-item">
                        <div class="quick-action-icon bg-danger-subtle text-danger">
                            <i class="fas fa-flag"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div>Reportes de Errores</div>
                            <small class="text-muted fw-normal">Revisar avisos de alumnos</small>
                        </div>
                        <i class="fas fa-chevron-right text-muted small"></i>
                    </a>
                </div>
            </div>

            <!-- TOP 5 JUGADORES / RANKING RÁPIDO -->
            <div class="side-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="side-card-title mb-0">
                        <i class="fas fa-trophy text-warning"></i>
                        <span>Top Jugadores</span>
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-primary text-decoration-none fw-semibold p-0" data-bs-toggle="modal" data-bs-target="#top10Modal">
                        Ver gráfica <i class="fas fa-external-link-alt ms-1"></i>
                    </button>
                </div>

                <div class="d-flex flex-column gap-1">
                    <?php 
                    $pos = 1;
                    $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
                    $top5 = array_slice($top_users, 0, 5);
                    if (!empty($top5)):
                        foreach ($top5 as $tu): 
                            $badge_bg = ($pos == 1) ? 'bg-warning-subtle text-warning' : (($pos == 2) ? 'bg-secondary-subtle text-secondary' : (($pos == 3) ? 'bg-amber-subtle text-dark' : 'bg-light text-muted'));
                    ?>
                        <div class="ranking-item">
                            <div class="d-flex align-items-center gap-2">
                                <span class="ranking-pos <?php echo $badge_bg; ?>">
                                    <?php echo $medals[$pos] ?? $pos; ?>
                                </span>
                                <span class="fw-semibold text-dark text-truncate" style="max-width: 140px;">
                                    <?php echo htmlspecialchars($tu['nombre_usuario']); ?>
                                </span>
                            </div>
                            <span class="badge bg-primary-subtle text-primary fw-bold rounded-pill px-3 py-1">
                                <?php echo htmlspecialchars($tu['rating']); ?> ELO
                            </span>
                        </div>
                    <?php 
                            $pos++;
                        endforeach;
                    else: ?>
                        <p class="text-muted small mb-0">No hay usuarios con rating registrado aún.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
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
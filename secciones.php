<?php
$page_title = "Patrones de Mate - Ajepuris";
require_once 'includes/header.php';



if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Fetch user's rating
$user_rating = 0;
$sql_user_rating = "SELECT rating FROM usuarios WHERE id_usuarios = ?";
if ($stmt_user_rating = $conn->prepare($sql_user_rating)) {
    $stmt_user_rating->bind_param("i", $_SESSION["id_usuarios"]);
    $stmt_user_rating->execute();
    $stmt_user_rating->bind_result($user_rating);
    $stmt_user_rating->fetch();
    $stmt_user_rating->close();
}

if (!isset($_GET['id_publicacion']) || empty($_GET['id_publicacion'])) {
    header("location: index.php");
    exit;
}

$id_publicacion = $_GET['id_publicacion'];

// Fetch publication details
$nombre_publicacion = '';
$descripcion_publicacion = '';
$tipo_publicacion = '';
$imagen_publicacion = '';

$sql_publicacion = "SELECT titulo, descripcion, tipo, imagen_publicacion FROM publicacion WHERE id_publicacion = ?";
if ($stmt_publicacion = $conn->prepare($sql_publicacion)) {
    $stmt_publicacion->bind_param("i", $id_publicacion);
    $stmt_publicacion->execute();
    $stmt_publicacion->bind_result($nombre_publicacion, $descripcion_publicacion, $tipo_publicacion, $imagen_publicacion);
    if (!$stmt_publicacion->fetch()) {
        header("location: index.php");
        exit;
    }
    $stmt_publicacion->close();
}


// Fetch categories for the selected publication
$all_categories = [];
$sql_base = "
    SELECT
        c.id_categorias,
        c.nombre_categoria,
        c.imagen_categoria,
        c.estado,
        COUNT(p.id_problemas) AS total_diagramas,
        IFNULL(ROUND((SUM(CASE WHEN pu.resuelto_correctamente = 1 THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(p.id_problemas), 0)), 0) AS porcentaje_aciertos
    FROM
        categorias c
    LEFT JOIN
        problemas p ON c.id_categorias = p.id_categorias
    LEFT JOIN
        progreso_usuarios pu ON p.id_problemas = pu.id_problemas AND pu.id_usuarios = ?
    WHERE
        c.id_publicacion = ?";

$sql_conditions = "";
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'creador_contenido'])) {
    $sql_conditions = " AND c.estado = 1";
}

$sql_all_categories = $sql_base . $sql_conditions . "
    GROUP BY
        c.id_categorias, c.nombre_categoria, c.imagen_categoria, c.estado
    ORDER BY
        c.nombre_categoria ASC";

$stmt = $conn->prepare($sql_all_categories);
$stmt->bind_param("ii", $_SESSION["id_usuarios"], $id_publicacion);
$stmt->execute();
$result_all_categories = $stmt->get_result();
if ($result_all_categories) {
    while ($row = $result_all_categories->fetch_assoc()) {
        $all_categories[] = $row;
    }
}

// Estadísticas del curso
$total_capitulos = count($all_categories);
$total_diagramas_curso = 0;
$suma_porcentajes = 0;
foreach ($all_categories as $c) {
    $total_diagramas_curso += (int)$c['total_diagramas'];
    $suma_porcentajes += (float)$c['porcentaje_aciertos'];
}
$progreso_general = ($total_capitulos > 0) ? round($suma_porcentajes / $total_capitulos) : 0;
?>

<?php include 'includes/lichess_menu.php'; ?>

<div class="secciones-page container-fluid px-3 px-sm-4 px-xl-5 py-4" style="max-width: 1700px; min-height: calc(100vh - 240px);">

    <!-- HERO BANNER DEL CURSO -->
    <div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #334155 100%); color: #ffffff; border-radius: 20px; overflow: hidden;">
        <div class="card-body p-4 p-md-5">
            <div class="row align-items-center gy-4">
                <div class="col-lg-8">
                    <nav aria-label="breadcrumb" class="mb-2">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="index.php" class="text-white-50 text-decoration-none small">
                                    <i class="fas fa-home me-1"></i> Inicio
                                </a>
                            </li>
                            <li class="breadcrumb-item active text-white small" aria-current="page">
                                <?php echo htmlspecialchars($nombre_publicacion); ?>
                            </li>
                        </ol>
                    </nav>

                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <h1 class="h2 fw-bold text-white mb-0"><?php echo htmlspecialchars($nombre_publicacion); ?></h1>
                        <?php if (!empty($tipo_publicacion)): ?>
                            <span class="badge <?php echo ($tipo_publicacion == 'Problema') ? 'badge-test' : 'badge-study'; ?> rounded-pill px-3 py-1">
                                <i class="fas fa-tag me-1"></i><?php echo ($tipo_publicacion == 'Problema') ? 'Test de Táctica' : 'Módulo de Estudio'; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="text-white-50 mb-3" style="max-width: 750px; line-height: 1.6;">
                        <?php echo !empty($descripcion_publicacion) ? htmlspecialchars($descripcion_publicacion) : 'Capítulos interactivos y colección de ejercicios para entrenar tus habilidades tácticas.'; ?>
                    </p>

                    <!-- Métricas del curso -->
                    <div class="d-flex flex-wrap gap-4 text-white-50 small pt-2 border-top border-white border-opacity-10">
                        <div>
                            <i class="fas fa-layer-group text-warning me-1"></i>
                            <strong class="text-white"><?php echo $total_capitulos; ?></strong> <?php echo ($total_capitulos == 1) ? 'Capítulo' : 'Capítulos'; ?>
                        </div>
                        <div>
                            <i class="fas fa-chess-board text-info me-1"></i>
                            <strong class="text-white"><?php echo $total_diagramas_curso; ?></strong> Ejercicios en total
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-chart-line text-success"></i>
                            <span>Progreso: <strong class="text-white"><?php echo $progreso_general; ?>%</strong></span>
                            <div class="progress" style="width: 80px; height: 6px; background-color: rgba(255,255,255,0.2); border-radius: 4px;">
                                <div class="progress-bar bg-success" style="width: <?php echo min(100, max(0, $progreso_general)); ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 text-lg-end">
                    <a href="index.php" class="btn btn-outline-light rounded-pill px-4 py-2 fw-semibold shadow-sm d-inline-flex align-items-center gap-2">
                        <i class="fas fa-arrow-left"></i>
                        <span>Volver a Cursos</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DE CAPÍTULOS / CATEGORÍAS -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="h4 fw-bold text-dark mb-1">
                <i class="fas fa-book-open text-primary me-2"></i>Capítulos Disponibles
            </h3>
            <p class="text-muted small mb-0">Selecciona el capítulo para empezar a resolver los diagramas de ajedrez.</p>
        </div>
        <span class="badge bg-light text-secondary border px-3 py-2 fw-semibold">
            <?php echo $total_capitulos; ?> <?php echo ($total_capitulos == 1) ? 'Sección' : 'Secciones'; ?>
        </span>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4">
        <?php if (!empty($all_categories)): ?>
            <?php foreach ($all_categories as $category): ?>
                <div class="col">
                    <div class="course-card">
                        <a href="categoria.php?category_id=<?php echo $category['id_categorias']; ?>" class="text-decoration-none h-100 d-flex flex-column">
                            <div class="card-img-wrapper">
                                <span class="type-badge-custom badge-study">
                                    <i class="fas fa-chess-knight me-1"></i><?php echo $category['total_diagramas']; ?> ejercicios
                                </span>
                                <?php if ($category['estado'] == 0 && isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['administrador', 'creador_contenido'])): ?>
                                    <span class="badge bg-secondary position-absolute top-0 start-0 m-2">Inactiva</span>
                                <?php endif; ?>
                                <img src="<?php echo htmlspecialchars(get_image_url($category['imagen_categoria'] ?? '')); ?>" alt="<?php echo htmlspecialchars($category['nombre_categoria']); ?>" onerror="handleImgError(this)">
                            </div>
                            <div class="course-card-body">
                                <h5 class="course-card-title"><?php echo htmlspecialchars($category['nombre_categoria']); ?></h5>
                                <p class="course-card-desc">
                                    Contiene <?php echo $category['total_diagramas']; ?> ejercicios tácticos con soluciones guiadas.
                                </p>
                                <div class="mt-auto pt-2 border-top">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small text-muted fw-semibold">Completado</span>
                                        <span class="small fw-bold <?php echo ($category['porcentaje_aciertos'] > 0) ? 'text-success' : 'text-muted'; ?>">
                                            <?php echo number_format($category['porcentaje_aciertos'], 0); ?>%
                                        </span>
                                    </div>
                                    <div class="progress mb-3" style="height: 6px; background-color: #e2e8f0; border-radius: 4px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(100, max(0, $category['porcentaje_aciertos'])); ?>%;"></div>
                                    </div>
                                    <div class="course-card-footer">
                                        <span class="small fw-bold text-primary">Entrenar capítulo</span>
                                        <i class="fa-solid fa-arrow-right arrow-icon text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm p-5 text-center bg-white rounded-4">
                    <i class="fa-solid fa-folder-open text-muted fs-1 mb-3"></i>
                    <h5 class="fw-bold text-dark">No hay capítulos disponibles en este momento</h5>
                    <p class="text-muted small mb-3">Este curso aún no tiene categorías de ejercicios asignadas activas.</p>
                    <div>
                        <a href="index.php" class="btn btn-primary rounded-pill px-4">Volver al Inicio</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>
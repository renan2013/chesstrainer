<?php
$page_title = "Inicio - Chess Trainer";
require_once 'includes/header.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$id_usuario_actual = $_SESSION["id_usuarios"];
$rol_usuario_actual = $_SESSION['rol'];

// --- Lógica para Estudiantes ---
if ($rol_usuario_actual == 'usuario') {
    // 1. Obtener los grupos del estudiante
    $mis_grupos = [];
    $stmt_grupos = $conn->prepare("SELECT g.id, g.nombre, u.nombre_completo AS instructor_nombre FROM grupos g JOIN grupo_miembros gm ON g.id = gm.id_grupo JOIN usuarios u ON g.id_instructor = u.id_usuarios WHERE gm.id_usuario = ? ORDER BY g.nombre");
    $stmt_grupos->bind_param("i", $id_usuario_actual);
    $stmt_grupos->execute();
    $result_grupos = $stmt_grupos->get_result();
    $grupos_data = $result_grupos->fetch_all(MYSQLI_ASSOC);
    $stmt_grupos->close();

    foreach ($grupos_data as $grupo) {
        $id_grupo = $grupo['id'];
        $mis_grupos[$id_grupo] = [
            'nombre' => $grupo['nombre'],
            'instructor' => $grupo['instructor_nombre'],
            'materiales' => []
        ];

        // 2. Obtener los IDs de los materiales del grupo
        $stmt_materiales = $conn->prepare("SELECT id_material, tipo_material FROM grupo_material WHERE id_grupo = ?");
        $stmt_materiales->bind_param("i", $id_grupo);
        $stmt_materiales->execute();
        $result_materiales = $stmt_materiales->get_result();
        $materiales_data = $result_materiales->fetch_all(MYSQLI_ASSOC);
        $stmt_materiales->close();

        // 3. Obtener detalles de cada material
        foreach ($materiales_data as $material) {
            if ($material['tipo_material'] == 'publicacion') {
                $stmt_pub = $conn->prepare("SELECT id_publicacion, titulo, descripcion, tipo, imagen_publicacion FROM publicacion WHERE id_publicacion = ? AND estado = 'activo'");
                $stmt_pub->bind_param("i", $material['id_material']);
                $stmt_pub->execute();
                $res_pub = $stmt_pub->get_result();
                if ($pub = $res_pub->fetch_assoc()) {
                    $mis_grupos[$id_grupo]['materiales'][] = $pub;
                }
                $stmt_pub->close();
            } elseif ($material['tipo_material'] == 'categoria') {
                $stmt_cat = $conn->prepare("SELECT c.id_categorias, c.nombre_categoria, c.imagen_categoria, p.tipo FROM categorias c JOIN publicacion p ON c.id_publicacion = p.id_publicacion WHERE c.id_categorias = ? AND c.estado = 'activo'");
                $stmt_cat->bind_param("i", $material['id_material']);
                $stmt_cat->execute();
                $res_cat = $stmt_cat->get_result();
                if ($cat = $res_cat->fetch_assoc()) {
                    $mis_grupos[$id_grupo]['materiales'][] = [
                        'id_categorias' => $cat['id_categorias'],
                        'titulo' => $cat['nombre_categoria'],
                        'descripcion' => 'Categoría de ' . $cat['tipo'],
                        'tipo' => $cat['tipo'],
                        'imagen_publicacion' => $cat['imagen_categoria']
                    ];
                }
                $stmt_cat->close();
            }
        }
    }

    // 4. Obtener todas las publicaciones públicas
    $public_publications = [];
    $sql_public = "SELECT id_publicacion, titulo, descripcion, tipo, imagen_publicacion FROM publicacion WHERE estado = 'activo' AND disponible_para_todos = 1 ORDER BY orden ASC, titulo ASC";
    $result_public = $conn->query($sql_public);
    if ($result_public) {
        $public_publications = $result_public->fetch_all(MYSQLI_ASSOC);
    }

} else {
    // --- Lógica para Admins/Instructores ---
    $publications = [];
    $sql_publications = "SELECT id_publicacion, titulo, descripcion, tipo, imagen_publicacion, estado FROM publicacion ORDER BY orden ASC, titulo ASC";
    $result_publications = $conn->query($sql_publications);
    if ($result_publications) {
        $publications = $result_publications->fetch_all(MYSQLI_ASSOC);
    }
}

$conn->close();
?>

<?php include 'includes/lichess_menu.php'; ?>
<div class="container dashboard-container">

    <!-- HERO BANNER -->
    <div class="index-hero d-flex align-items-center justify-content-between">
        <div>
            <h1 class="index-hero-title">
                <i class="fa-solid fa-chess-knight text-success me-2"></i>¡Bienvenido(a), <?php echo htmlspecialchars($_SESSION["nombre_usuario"]); ?>!
            </h1>
            <p class="index-hero-subtitle">Continúa con tu entrenamiento de ajedrez, resuelve tácticas y eleva tu nivel.</p>
        </div>
    </div>

    <?php if ($rol_usuario_actual == 'usuario'): ?>
        <!-- VISTA PARA ESTUDIANTES -->
        <div id="grupos-section" class="mb-5">
            <h3 class="section-heading-custom">
                <i class="fa-solid fa-users-line text-success"></i> Mis Grupos de Estudio
            </h3>
            <div class="section-divider-custom"></div>

            <?php if (!empty($mis_grupos)): ?>
                <?php foreach ($mis_grupos as $grupo): ?>
                    <div class="mb-4">
                        <div class="group-header-card d-flex align-items-center justify-content-between">
                            <div>
                                <h4 class="mb-1 text-dark font-weight-bold"><?php echo htmlspecialchars($grupo['nombre']); ?></h4>
                                <span class="text-muted small"><i class="fa-solid fa-user-tie me-1"></i>Instructor: <strong><?php echo htmlspecialchars($grupo['instructor']); ?></strong></span>
                            </div>
                        </div>

                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                            <?php if (!empty($grupo['materiales'])): ?>
                                <?php foreach ($grupo['materiales'] as $mat): ?>
                                    <?php 
                                    $es_publicacion = isset($mat['id_publicacion']);
                                    $link = $es_publicacion ? "secciones.php?id_publicacion=" . $mat['id_publicacion'] : "categoria.php?id=" . $mat['id_categorias'];
                                    $badge_class = ($mat['tipo'] == 'Problema') ? 'badge-test' : 'badge-study';
                                    $badge_label = ($mat['tipo'] == 'Problema') ? 'Test' : 'Estudio';
                                    ?>
                                    <div class="col">
                                        <div class="course-card">
                                            <a href="<?php echo $link; ?>" class="text-decoration-none h-100 d-flex flex-column">
                                                <div class="card-img-wrapper">
                                                    <span class="type-badge-custom <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                                                    <img src="<?php echo htmlspecialchars(get_image_url($mat['imagen_publicacion'])); ?>" alt="<?php echo htmlspecialchars($mat['titulo']); ?>">
                                                </div>
                                                <div class="course-card-body">
                                                    <h5 class="course-card-title"><?php echo htmlspecialchars($mat['titulo']); ?></h5>
                                                    <p class="course-card-desc"><?php echo htmlspecialchars($mat['descripcion']); ?></p>
                                                    <div class="course-card-footer">
                                                        <span>Ingresar al curso</span>
                                                        <i class="fa-solid fa-arrow-right arrow-icon"></i>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="empty-state-box">
                                        <i class="fa-solid fa-folder-open fs-2 mb-2"></i>
                                        <p class="mb-0">No hay material asignado a este grupo actualmente.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state-box mb-4">
                    <i class="fa-solid fa-user-group fs-2 mb-2 text-muted"></i>
                    <p class="mb-0">No estás inscrito en ningún grupo aún. Explora el contenido público disponible a continuación.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- CONTENIDO PÚBLICO -->
        <div id="public-section" class="mt-4">
            <h3 class="section-heading-custom">
                <i class="fa-solid fa-globe text-primary"></i> Contenido Público Disponible
            </h3>
            <div class="section-divider-custom"></div>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php if (!empty($public_publications)): ?>
                    <?php foreach ($public_publications as $pub): ?>
                        <?php
                        $badge_class = ($pub['tipo'] == 'Problema') ? 'badge-test' : 'badge-study';
                        $badge_label = ($pub['tipo'] == 'Problema') ? 'Test' : 'Estudio';
                        ?>
                        <div class="col">
                            <div class="course-card">
                                <a href="secciones.php?id_publicacion=<?php echo $pub['id_publicacion']; ?>" class="text-decoration-none h-100 d-flex flex-column">
                                    <div class="card-img-wrapper">
                                        <span class="type-badge-custom <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                                        <img src="<?php echo htmlspecialchars(get_image_url($pub['imagen_publicacion'])); ?>" alt="<?php echo htmlspecialchars($pub['titulo']); ?>">
                                    </div>
                                    <div class="course-card-body">
                                        <h5 class="course-card-title"><?php echo htmlspecialchars($pub['titulo']); ?></h5>
                                        <p class="course-card-desc"><?php echo htmlspecialchars($pub['descripcion']); ?></p>
                                        <div class="course-card-footer">
                                            <span>Acceder</span>
                                            <i class="fa-solid fa-arrow-right arrow-icon"></i>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state-box">
                            <i class="fa-solid fa-box-open fs-2 mb-2"></i>
                            <p class="mb-0">No hay contenido público disponible en este momento.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- VISTA PARA ADMINS / INSTRUCTORES -->
        <div id="admin-view-section">
            <h3 class="section-heading-custom">
                <i class="fa-solid fa-layer-group text-primary"></i> Publicaciones de Entrenamiento
            </h3>
            <div class="section-divider-custom"></div>

            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-4">
                <?php if (!empty($publications)): ?>
                    <?php foreach ($publications as $pub): ?>
                        <?php
                        $badge_class = ($pub['tipo'] == 'Problema') ? 'badge-test' : 'badge-study';
                        $badge_label = ($pub['tipo'] == 'Problema') ? 'Test' : 'Estudio';
                        ?>
                        <div class="col">
                            <div class="course-card">
                                <a href="secciones.php?id_publicacion=<?php echo $pub['id_publicacion']; ?>" class="text-decoration-none h-100 d-flex flex-column">
                                    <div class="card-img-wrapper">
                                        <span class="type-badge-custom <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                                        <img src="<?php echo htmlspecialchars(get_image_url($pub['imagen_publicacion'])); ?>" alt="<?php echo htmlspecialchars($pub['titulo']); ?>">
                                    </div>
                                    <div class="course-card-body">
                                        <h5 class="course-card-title"><?php echo htmlspecialchars($pub['titulo']); ?></h5>
                                        <p class="course-card-desc"><?php echo htmlspecialchars($pub['descripcion']); ?></p>
                                        <div class="course-card-footer">
                                            <span>Ver secciones</span>
                                            <i class="fa-solid fa-arrow-right arrow-icon"></i>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="empty-state-box">
                            <i class="fa-solid fa-layer-group fs-2 mb-2"></i>
                            <p class="mb-0">No hay publicaciones disponibles.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>
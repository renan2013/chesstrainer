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
// Corrected table and column names based on categoria.php
$sql_publicacion = "SELECT titulo FROM publicacion WHERE id_publicacion = ?";
if ($stmt_publicacion = $conn->prepare($sql_publicacion)) {
    $stmt_publicacion->bind_param("i", $id_publicacion);
    $stmt_publicacion->execute();
    $stmt_publicacion->bind_result($nombre_publicacion);
    if (!$stmt_publicacion->fetch()) {
        // Publication not found, redirect
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

?>



<?php include 'includes/lichess_menu.php'; ?>

<div class="container">
    <br/>

<div class="row row-cols-1 row-cols-md-3 g-4">
    <?php if (!empty($all_categories)): ?>
        <?php foreach ($all_categories as $category): ?>
            <div class="col">
                <div class="card h-100 text-center <?php echo ($category['estado'] == 0) ? 'bg-light' : ''; ?>">
                    <a href="categoria.php?category_id=<?php echo $category['id_categorias']; ?>" class="text-decoration-none text-dark">
                        <?php if ($category['estado'] == 0 && isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['administrador', 'creador_contenido'])): ?>
                            <span class="badge bg-secondary position-absolute top-0 start-0 m-2">Inactiva</span>
                        <?php endif; ?>
                        <img src="<?php echo htmlspecialchars(get_image_url($category['imagen_categoria'] ?? '')); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($category['nombre_categoria']); ?>" style="height: 130px; object-fit: cover; <?php echo ($category['estado'] == 0) ? 'opacity: 0.6;' : ''; ?>" onerror="handleImgError(this)">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($category['nombre_categoria']); ?> (<?php echo $category['total_diagramas']; ?>)</h5>
                            <p class="card-text"><?php echo isset($category['porcentaje_aciertos']) ? htmlspecialchars(number_format($category['porcentaje_aciertos'], 0)) . '% resuelto' : '0% resuelto'; ?></p>
                        </div>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <p class="text-center text-muted">No hay categorías disponibles.</p>
        </div>
    <?php endif; ?>
</div>

</div>

<?php require_once 'includes/footer.php'; ?>
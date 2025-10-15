<?php
$page_title = "Resultados de Búsqueda";
require_once 'includes/header.php';

if (!in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
    header("location: index.php");
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Arrays para guardar los resultados
$user_results = [];
$publication_results = [];
$category_results = [];
$problem_results = [];

if (!empty($query)) {
    $like_query = "%{$query}%";
    $is_numeric_query = is_numeric($query);

    // Buscar en Usuarios
    $sql = "SELECT id_usuarios, nombre_usuario, nombre_completo, email FROM usuarios WHERE nombre_usuario LIKE ? OR nombre_completo LIKE ? OR email LIKE ?";
    $params = ["sss", $like_query, $like_query, $like_query];
    if ($is_numeric_query) {
        $sql .= " OR id_usuarios = ?";
        $params[0] .= "i";
        $params[] = $query;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $user_results[] = $row;
    }
    $stmt->close();

    // Buscar en Publicaciones
    $sql = "SELECT id_publicacion, titulo, descripcion FROM publicacion WHERE titulo LIKE ? OR descripcion LIKE ?";
    $params = ["ss", $like_query, $like_query];
    if ($is_numeric_query) {
        $sql .= " OR id_publicacion = ?";
        $params[0] .= "i";
        $params[] = $query;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $publication_results[] = $row;
    }
    $stmt->close();

    // Buscar en Categorías
    $sql = "SELECT id_categorias, nombre_categoria FROM categorias WHERE nombre_categoria LIKE ?";
    $params = ["s", $like_query];
    if ($is_numeric_query) {
        $sql .= " OR id_categorias = ?";
        $params[0] .= "i";
        $params[] = $query;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $category_results[] = $row;
    }
    $stmt->close();

    // Buscar en Problemas
    $sql = "SELECT id_problemas, fen, tipo_problema FROM problemas WHERE fen LIKE ? OR tipo_problema LIKE ? OR solucion LIKE ?";
    $params = ["sss", $like_query, $like_query, $like_query];
    if ($is_numeric_query) {
        $sql .= " OR id_problemas = ?";
        $params[0] .= "i";
        $params[] = $query;
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $problem_results[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">
        <h1 class="mb-4">Resultados de la Búsqueda para: <em class="text-primary"><?php echo htmlspecialchars($query); ?></em></h1>

        <?php
        $total_results = count($user_results) + count($publication_results) + count($category_results) + count($problem_results);
        if (empty($query) || $total_results === 0) {
            echo "<div class='alert alert-warning'>No se encontraron resultados. Intenta con otro término de búsqueda.</div>";
        } else {
            // Resultados de Usuarios
            if (!empty($user_results)) {
                echo '<div class="card mb-4"><div class="card-header"><h5><i class="fas fa-users me-2"></i>Usuarios Encontrados ('.count($user_results).')</h5></div><div class="list-group list-group-flush">';
                foreach ($user_results as $item) {
                    echo '<a href="editar_usuario.php?id='.$item['id_usuarios'].'" class="list-group-item list-group-item-action"><strong>'.htmlspecialchars($item['nombre_usuario']).'</strong> ('.htmlspecialchars($item['nombre_completo']).')<br><small class="text-muted">ID: '.$item['id_usuarios'].' | '.htmlspecialchars($item['email']).'</small></a>';
                }
                echo '</div></div>';
            }

            // Resultados de Publicaciones
            if (!empty($publication_results)) {
                echo '<div class="card mb-4"><div class="card-header"><h5><i class="fas fa-newspaper me-2"></i>Publicaciones Encontradas ('.count($publication_results).')</h5></div><div class="list-group list-group-flush">';
                foreach ($publication_results as $item) {
                    echo '<a href="editar_publicacion.php?id='.$item['id_publicacion'].'" class="list-group-item list-group-item-action"><strong>'.htmlspecialchars($item['titulo']).'</strong> (ID: '.$item['id_publicacion'].')<br><small class="text-muted">'.htmlspecialchars(substr($item['descripcion'], 0, 100)).'...</small></a>';
                }
                echo '</div></div>';
            }

            // Resultados de Categorías
            if (!empty($category_results)) {
                echo '<div class="card mb-4"><div class="card-header"><h5><i class="fas fa-book-open me-2"></i>Categorías Encontradas ('.count($category_results).')</h5></div><div class="list-group list-group-flush">';
                foreach ($category_results as $item) {
                    echo '<a href="editar_categoria.php?id='.$item['id_categorias'].'" class="list-group-item list-group-item-action"><strong>'.htmlspecialchars($item['nombre_categoria']).'</strong> (ID: '.$item['id_categorias'].')</a>';
                }
                echo '</div></div>';
            }

            // Resultados de Problemas
            if (!empty($problem_results)) {
                echo '<div class="card mb-4"><div class="card-header"><h5><i class="fas fa-puzzle-piece me-2"></i>Problemas Encontrados ('.count($problem_results).')</h5></div><div class="list-group list-group-flush">';
                foreach ($problem_results as $item) {
                    echo '<a href="editar_problema.php?id='.$item['id_problemas'].'" class="list-group-item list-group-item-action"><strong>Problema #'.$item['id_problemas'].'</strong> ('.htmlspecialchars($item['tipo_problema']).')<br><small class="text-muted">FEN: '.htmlspecialchars($item['fen']).'</small></a>';
                }
                echo '</div></div>';
            }
        }
        ?>

    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
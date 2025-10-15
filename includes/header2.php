<?php
// PHP logic extracted from categoria2.php
$page_title = "Entrenador de Tácticas de Ajedrez";
require_once 'includes/db.php'; // Assuming db.php is needed for $conn
session_start(); // Assuming session is not started in db.php or header.php

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$is_admin = isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido']);

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

if (!isset($_GET['category_id']) || empty($_GET['category_id'])) {
    header("location: index.php");
    exit;
}

$id_publicacion = null; // Initialize to null
$current_category_id = (int)$_GET['category_id'];

// Fetch category details to get publication_id and name
$category_name = '';
$category_estado = 0;
$sql_category_details = "SELECT nombre_categoria, id_publicacion, estado FROM categorias WHERE id_categorias = ?";
if ($stmt_cat_details = $conn->prepare($sql_category_details)) {
    $stmt_cat_details->bind_param("i", $current_category_id);
    $stmt_cat_details->execute();
    $stmt_cat_details->bind_result($category_name, $id_publicacion, $category_estado);
    if (!$stmt_cat_details->fetch()) {
        header("location: index.php");
        exit;
    }
    $stmt_cat_details->close();
}

// Security check: If category is inactive, only allow admins
if ($category_estado == 0 && (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'creador_contenido']))) {
    $_SESSION['error'] = "Esta categoría no está disponible actualmente.";
    header("location: secciones.php?id_publicacion=" . $id_publicacion);
    exit;
}

// Fetch publication details (for breadcrumb)
$nombre_publicacion = '';
if ($id_publicacion) {
    $sql_publicacion = "SELECT titulo FROM publicacion WHERE id_publicacion = ?";
    if ($stmt_publicacion = $conn->prepare($sql_publicacion)) {
        $stmt_publicacion->bind_param("i", $id_publicacion);
        $stmt_publicacion->execute();
        $stmt_publicacion->bind_result($nombre_publicacion);
        $stmt_publicacion->fetch();
        $stmt_publicacion->close();
    }
}

$id_usuario_actual = $_SESSION["id_usuarios"];
$all_problems = []; // Array to hold all problems with their details and solved status
$current_problem_index = 0; // Index of the problem to display initially

// --- Fetch all problems along with user's progress for the current category ---
$sql_problems = "
    SELECT
        p.id_problemas,
        p.fen,
        p.solucion,
        p.juega,
        p.id_categorias,
        p.dificultad,
        p.modo,
        p.desarrollo,
        c.nombre_categoria,
        c.id_publicacion, -- Added for breadcrumb link
        pub.titulo AS nombre_publicacion,
        p.variante_nombre, -- Added for study mode list
        COALESCE(pu.resuelto_correctamente, 0) AS solved_by_user,
        COALESCE(pu.intentos, 0) AS attempts_by_user -- NEW: Fetch attempts
    FROM
        problemas p
    JOIN
        categorias c ON p.id_categorias = c.id_categorias
    JOIN
        publicacion pub ON c.id_publicacion = pub.id_publicacion
    LEFT JOIN
        progreso_usuarios pu ON p.id_problemas = pu.id_problemas AND pu.id_usuarios = ?
    WHERE
        p.id_categorias = ?
    GROUP BY
        p.id_problemas
    ORDER BY
        p.orden ASC, FIELD(p.dificultad, 'Fácil', 'Intermedio', 'Difícil', 'Experto'), p.id_problemas ASC";

if ($stmt_problems = $conn->prepare($sql_problems)) {
    $stmt_problems->bind_param("ii", $id_usuario_actual, $current_category_id);
    $stmt_problems->execute();
    $result_problems = $stmt_problems->get_result();
    while ($row = $result_problems->fetch_assoc()) {
        $all_problems[] = $row;
    }
    $stmt_problems->close();
}

// Determine if there are any study problems to adjust the layout
$has_study_problems = false;
foreach ($all_problems as $problem) {
    if ($problem['modo'] === 'estudio') {
        $has_study_problems = true;
        break;
    }
}

// Determine the initial problem to display (first unsolved, or first if all solved)
if (!empty($all_problems)) {
    foreach ($all_problems as $index => $problem) {
        if ($problem['solved_by_user'] == 0 && $problem['attempts_by_user'] < 2) { // Check attempts too
            $current_problem_index = $index;
            break;
        }
    }
}

// Fetch top 3 users for this category
$top_users_category = [];
if ($current_category_id) {
    $sql_top_users = "
        SELECT
            u.nombre_usuario,
            rc.problemas_resueltos
        FROM
            resultados_categorias rc
        JOIN
            usuarios u ON rc.id_usuarios = u.id_usuarios
        WHERE
            rc.id_categorias = ?
        ORDER BY
            rc.problemas_resueltos DESC, rc.fecha_creacion ASC
        LIMIT 3;
    ";
    if ($stmt_top_users = $conn->prepare($sql_top_users)) {
        $stmt_top_users->bind_param("i", $current_category_id);
        $stmt_top_users->execute();
        $result_top_users = $stmt_top_users->get_result();
        while ($row = $result_top_users->fetch_assoc()) {
            $top_users_category[] = $row;
        }
        $stmt_top_users->close();
    }
}

$total_problems_in_current_category = count($all_problems);

// HTML for the new header
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="css/chessboard-1.0.0.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="img/chess_trainer_logo.svg" alt="Logo Chess Trainer" style="height: 40px;">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Publicaciones</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="secciones.php?id_publicacion=<?php echo $id_publicacion; ?>"><?php echo htmlspecialchars($nombre_publicacion); ?></a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link active" aria-current="page"><?php echo htmlspecialchars($category_name); ?></span>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item d-flex align-items-center">
                        <span class="navbar-text me-3">
                            Bienvenido(a), <strong><?php echo htmlspecialchars($_SESSION["nombre_usuario"]); ?></strong>! (Rating: <?php echo $user_rating; ?>)
                        </span>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-outline-secondary" href="logout.php">Cerrar Sesión</a>
                    </li>
                    <li class="nav-item ms-3">
                        <?php if (!empty($all_problems)): ?>
                            <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reportErrorModal">
                                <i class="fas fa-flag me-2"></i>Reportar Error
                            </button>
                        <?php endif; ?>
                    </li>
                </ul>
            </div>
        </div>
    </header>
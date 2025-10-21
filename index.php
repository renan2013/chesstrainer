<?php
$page_title = "Inicio - Chess Trainer";
require_once 'includes/header.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$id_usuario_actual = $_SESSION["id_usuarios"];
$rol_usuario_actual = $_SESSION['rol'];

// Fetch user's rating
$user_rating = 0;
$sql_user_rating = "SELECT rating FROM usuarios WHERE id_usuarios = ?";
if ($stmt_user_rating = $conn->prepare($sql_user_rating)) {
    $stmt_user_rating->bind_param("i", $id_usuario_actual);
    $stmt_user_rating->execute();
    $stmt_user_rating->bind_result($user_rating);
    $stmt_user_rating->fetch();
    $stmt_user_rating->close();
}

$mis_grupos = [];
if ($rol_usuario_actual == 'usuario') {
    // Lógica para estudiantes: obtener sus grupos y materiales
    $stmt_grupos = $conn->prepare("SELECT g.id, g.nombre, u.nombre_completo AS instructor_nombre FROM grupos g JOIN grupo_miembros gm ON g.id = gm.id_grupo JOIN usuarios u ON g.id_instructor = u.id_usuarios WHERE gm.id_usuario = ? ORDER BY g.nombre");
    $stmt_grupos->bind_param("i", $id_usuario_actual);
    $stmt_grupos->execute();
    $result_grupos = $stmt_grupos->get_result();

    while ($grupo = $result_grupos->fetch_assoc()) {
        $id_grupo = $grupo['id'];
        $mis_grupos[$id_grupo] = [
            'nombre' => $grupo['nombre'],
            'instructor' => $grupo['instructor_nombre'],
            'materiales' => []
        ];

        $stmt_materiales = $conn->prepare("SELECT id_material, tipo_material FROM grupo_material WHERE id_grupo = ?");
        $stmt_materiales->bind_param("i", $id_grupo);
        $stmt_materiales->execute();
        $result_materiales = $stmt_materiales->get_result();

        while ($material = $result_materiales->fetch_assoc()) {
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
                // Si es una categoría, la tratamos como una "publicación" especial para la tarjeta
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
        $stmt_materiales->close();
    }
    $stmt_grupos->close();
} else {
    // Lógica para Admins/Instructores: obtener todas las publicaciones
    $publications = [];
    $sql_publications = "SELECT id_publicacion, titulo, descripcion, tipo, imagen_publicacion, estado FROM publicacion ORDER BY orden ASC, titulo ASC";
    $result_publications = $conn->query($sql_publications);
    if ($result_publications) {
        while ($row = $result_publications->fetch_assoc()) {
            $publications[] = $row;
        }
    }
}

$conn->close();
?>

<?php include 'includes/lichess_menu.php'; ?>
<div class="container">
    <br/><br/>

    <?php if ($rol_usuario_actual == 'usuario'): ?>
        <!-- VISTA PARA ESTUDIANTES -->
        <?php if (!empty($mis_grupos)): ?>
            <?php foreach ($mis_grupos as $grupo): ?>
                <div class="mb-5">
                    <h3><?php echo htmlspecialchars($grupo['nombre']); ?></h3>
                    <p class="text-muted">Instructor: <?php echo htmlspecialchars($grupo['instructor']); ?></p>
                    <hr>
                    <div class="row row-cols-1 row-cols-md-4 g-4">
                        <?php if (!empty($grupo['materiales'])): ?>
                            <?php foreach ($grupo['materiales' ] as $mat): ?>
                                <div class="col">
                                    <div class="card h-100">
                                        <?php 
                                        // Determinar el enlace y el tipo de badge
                                        $es_publicacion = isset($mat['id_publicacion']);
                                        $link = $es_publicacion ? "secciones.php?id_publicacion=" . $mat['id_publicacion'] : "categoria.php?id=" . $mat['id_categorias'];
                                        $badge_text = ($mat['tipo'] == 'Problema') ? 'Test' : 'Estudio';
                                        $badge_bg = ($mat['tipo'] == 'Problema') ? 'bg-primary' : 'bg-success';
                                        ?>
                                        <span class="badge rounded-pill <?php echo $badge_bg; ?>" style="position: absolute; top: -14px; right: 16px; z-index: 10; font-size: 0.9rem; border: 2px solid white;"><?php echo $badge_text; ?></span>
                                        <a href="<?php echo $link; ?>" class="text-decoration-none text-dark">
                                            <img src="admin/<?php echo htmlspecialchars($mat['imagen_publicacion']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($mat['titulo']); ?>" style="height: 200px; object-fit: cover;">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($mat['titulo']); ?></h5>
                                                <p class="card-text"><?php echo htmlspecialchars($mat['descripcion']); ?></p>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12"><p class="text-muted">No hay material de estudio asignado a este grupo.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info text-center">No estás inscrito en ningún grupo. Contacta a tu instructor para que te añada a un grupo de estudio.</div>
        <?php endif; ?>

    <?php else: ?>
        <!-- VISTA PARA ADMINS E INSTRUCTORES (VISTA ORIGINAL) -->
        <div class="row row-cols-1 row-cols-md-4 g-4">
            <?php if (!empty($publications)): ?>
                <?php foreach ($publications as $pub): ?>
                    <div class="col">
                        <div class="card h-100" style="position: relative;">
                            <?php
                            $badge_text = ($pub['tipo'] == 'Problema') ? 'Test' : 'Estudio';
                            $badge_bg = ($pub['tipo'] == 'Problema') ? 'bg-primary' : 'bg-success';
                            ?>
                            <span class="badge rounded-pill <?php echo $badge_bg; ?>" style="position: absolute; top: -14px; right: 16px; z-index: 10; font-size: 0.9rem; border: 2px solid white;"><?php echo $badge_text; ?></span>
                            <a href="secciones.php?id_publicacion=<?php echo $pub['id_publicacion']; ?>" class="text-decoration-none text-dark">
                                <img src="admin/<?php echo htmlspecialchars($pub['imagen_publicacion']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($pub['titulo']); ?>" style="height: 200px; object-fit: cover;">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($pub['titulo']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($pub['descripcion']); ?></p>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12"><p class="text-center text-muted">No hay publicaciones disponibles.</p></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
<?php require_once 'includes/footer.php'; ?>

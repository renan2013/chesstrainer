<?php
$page_title = "Publicaciones - Ajepuris";
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

// Fetch publications based on user role
$publications = [];
// MODIFICADO: Añadido el campo 'tipo' a la consulta
$sql_publications = "SELECT id_publicacion, titulo, descripcion, tipo, imagen_publicacion, estado FROM publicacion";

// If the user is not an admin, only show active publications
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'creador_contenido'])) {
    $sql_publications .= " WHERE estado = 'activo'";
}

$sql_publications .= " ORDER BY orden ASC, titulo ASC";
$result_publications = $conn->query($sql_publications);
if ($result_publications) {
    while ($row = $result_publications->fetch_assoc()) {
        $publications[] = $row;
    }
}

// Fetch top 3 users for ranking modal
$top_users = [];
$sql_top_users = "
    SELECT
        u.nombre_usuario,
        u.rating
    FROM
        usuarios u
    ORDER BY
        u.rating DESC
    LIMIT 3;
";

$result_top_users = $conn->query($sql_top_users);
if ($result_top_users) {
    while ($row = $result_top_users->fetch_assoc()) {
        $top_users[] = $row;
    }
}

?>

<?php include 'includes/lichess_menu.php'; ?>
<div class="container">
    <br/><br/>

<div class="row row-cols-1 row-cols-md-4 g-4">
    
    <?php if (!empty($publications)): ?>
        <?php foreach ($publications as $pub): ?>
            <div class="col">
                <div class="card h-100" style="position: relative;">
                    <?php
                    $badge_text = '';
                    $badge_bg = '';
                    if ($pub['tipo'] == 'Problema') {
                        $badge_text = 'Test';
                        $badge_bg = 'bg-primary';
                    } elseif ($pub['tipo'] == 'Estudio') {
                        $badge_text = 'Estudio';
                        $badge_bg = 'bg-success';
                    }
                    ?>
                    <?php if ($badge_text): ?>
                        <span class="badge rounded-pill <?php echo $badge_bg; ?>" style="position: absolute; top: -14px; right: 16px; z-index: 10; font-size: 0.9rem; border: 2px solid white;">
                            <?php echo $badge_text; ?>
                        </span>
                    <?php endif; ?>

                    <a href="secciones.php?id_publicacion=<?php echo $pub['id_publicacion']; ?>" class="text-decoration-none text-dark">
                        <?php if (!empty($pub['imagen_publicacion'])): ?>
                            <img src="admin/<?php echo htmlspecialchars($pub['imagen_publicacion']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($pub['titulo']); ?>" style="height: 200px; object-fit: cover;">
                        <?php else: ?>
                            <div style="height: 200px; background-color: #f8f9fa; display: flex; align-items: center; justify-content: center;">
                                <small class="text-muted">Sin imagen</small>
                            </div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($pub['titulo']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($pub['descripcion']); ?></p>
                            <hr>
                            <h6>Capítulos:</h6>
                            <?php
                            $id_publicacion_actual = $pub['id_publicacion'];
                            $sql_capitulos = "SELECT nombre_categoria FROM categorias WHERE id_publicacion = ? ORDER BY nombre_categoria ASC";
                            if ($stmt_capitulos = $conn->prepare($sql_capitulos)) {
                                $stmt_capitulos->bind_param("i", $id_publicacion_actual);
                                $stmt_capitulos->execute();
                                $result_capitulos = $stmt_capitulos->get_result();
                                $capitulos = $result_capitulos->fetch_all(MYSQLI_ASSOC);
                                $total_capitulos = count($capitulos);

                                if ($total_capitulos > 0) {
                                    echo '<ol class="list-group list-group-numbered">';
                                    $limit = 5;
                                    for ($i = 0; $i < min($limit, $total_capitulos); $i++) {
                                        echo '<li class="list-group-item py-1">' . htmlspecialchars($capitulos[$i]['nombre_categoria']) . '</li>';
                                    }
                                    if ($total_capitulos > $limit) {
                                        echo '<li class="list-group-item py-1 text-muted">... y ' . ($total_capitulos - $limit) . ' más</li>';
                                    }
                                    echo '</ol>';
                                } else {
                                    echo '<p class="card-text"><small class="text-muted">No hay capítulos registrados.</small></p>';
                                }
                                $stmt_capitulos->close();
                            }
                            ?>
                        </div>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="col-12">
            <p class="text-center text-muted">No hay publicaciones disponibles.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Ranking Modal -->
<div class="modal fade" id="rankingModal" tabindex="-1" aria-labelledby="rankingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <div class="d-flex flex-column align-items-center w-100">
            <img src="https://ajedrezpuriscal.com/chess_trainer/img/logo_blanco.svg" alt="Logo Chess Trainer" style="width: 120px; margin-bottom: 10px;">
            <h5 class="modal-title" id="rankingModalLabel"><i class="fas fa-trophy me-2"></i> ¡Top 3 Jugadores!</h5>
            <p class="mb-0"><small>Fecha: <?php echo date('d/m/Y'); ?></small></p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <?php if (!empty($top_users)): ?>
            <p class="lead">¡Felicidades a nuestros jugadores más destacados!</p>
            <ul class="list-group list-group-flush mt-3">
                <?php foreach ($top_users as $index => $user): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            <?php if ($index === 0): ?><i class="fas fa-medal text-warning me-2 fa-lg"></i><?php endif; ?>
                            <?php if ($index === 1): ?><i class="fas fa-medal text-secondary me-2 fa-lg"></i><?php endif; ?>
                            <?php if ($index === 2): ?><i class="fas fa-medal text-bronze me-2 fa-lg"></i><?php endif; ?>
                            <strong><?php echo htmlspecialchars($user['nombre_usuario']); ?></strong>
                        </span>
                        <span class="badge bg-info rounded-pill"><?php echo htmlspecialchars($user['rating']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="text-muted">Aún no hay suficientes datos para mostrar el ranking.</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer justify-content-center">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

</div>
<?php require_once 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    <?php if (isset($_SESSION['show_ranking_modal']) && $_SESSION['show_ranking_modal'] === true): ?>
        var rankingModal = new bootstrap.Modal(document.getElementById('rankingModal'));
        rankingModal.show();
        <?php unset($_SESSION['show_ranking_modal']); ?>
    <?php endif; ?>
});
</script>
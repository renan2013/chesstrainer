<?php
$page_title = "Editar Publicación";
require_once 'includes/header.php';

if ($_SESSION['rol'] !== 'administrador') {
    header("location: index.php");
    exit;
}

$mensaje = '';
$error = '';
$publicacion = null;

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("location: gestionar_publicaciones.php");
    exit;
}

$id_publicacion = $_GET['id'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_publicacion"])) {
    $titulo = trim($_POST["titulo"]);
    $descripcion = trim($_POST["descripcion"]);
    $tipo = trim($_POST["tipo"]);
    $estado = trim($_POST["estado"]);
    $orden = filter_input(INPUT_POST, 'orden', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $imagen_actual = trim($_POST["imagen_actual"]);
    $imagen_publicacion = $imagen_actual;

    if (isset($_FILES["imagen_publicacion"]) && $_FILES["imagen_publicacion"]["error"] == 0) {
        $target_dir = "uploads/publicaciones/";
        if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
        $target_file = $target_dir . uniqid() . '_' . basename($_FILES["imagen_publicacion"]["name"]);
        if (move_uploaded_file($_FILES["imagen_publicacion"]["tmp_name"], $target_file)) {
            $imagen_publicacion = $target_file;
            if (!empty($imagen_actual) && file_exists($imagen_actual)) {
                unlink($imagen_actual);
            }
        } else {
            $error = "Error al subir la nueva imagen.";
        }
    }

    if (empty($titulo)) {
        $error = "El título no puede estar vacío.";
    } elseif (empty($error)) {
        $sql = "UPDATE publicacion SET titulo = ?, descripcion = ?, tipo = ?, imagen_publicacion = ?, estado = ?, orden = ? WHERE id_publicacion = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssii", $titulo, $descripcion, $tipo, $imagen_publicacion, $estado, $orden, $id_publicacion);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Publicación actualizada exitosamente.";
                header("Location: gestionar_publicaciones.php");
                exit;
            } else {
                $error = "Error al actualizar la publicación: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

$sql_select = "SELECT id_publicacion, titulo, descripcion, tipo, imagen_publicacion, estado, orden FROM publicacion WHERE id_publicacion = ?";
if ($stmt = $conn->prepare($sql_select)) {
    $stmt->bind_param("i", $id_publicacion);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $publicacion = $result->fetch_assoc();
    } else {
        header("location: gestionar_publicaciones.php");
        exit;
    }
    $stmt->close();
}
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">

        <h1 class="mb-4">Editar Publicación</h1>

        <?php if(!empty($mensaje)): ?>
            <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($publicacion): ?>
        <form action="editar_publicacion.php?id=<?php echo $id_publicacion; ?>" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="titulo" class="form-label">Título</label>
                <input type="text" name="titulo" id="titulo" class="form-control" value="<?php echo htmlspecialchars($publicacion['titulo']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea name="descripcion" id="descripcion" class="form-control"><?php echo htmlspecialchars($publicacion['descripcion']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="tipo" class="form-label">Tipo de Publicación</label>
                <select name="tipo" id="tipo" class="form-select">
                    <option value="Problema" <?php echo ($publicacion['tipo'] == 'Problema') ? 'selected' : ''; ?>>Problema (El usuario resuelve y puntúa)</option>
                    <option value="Estudio" <?php echo ($publicacion['tipo'] == 'Estudio') ? 'selected' : ''; ?>>Estudio (El usuario solo analiza)</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="imagen_publicacion" class="form-label">Cambiar Imagen</label>
                <input type="file" name="imagen_publicacion" id="imagen_publicacion" class="form-control">
                <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($publicacion['imagen_publicacion']); ?>">
                <?php if (!empty($publicacion['imagen_publicacion'])): ?>
                    <div class="mt-2">
                        <p class="mb-1"><small>Imagen actual:</small></p>
                        <img src="<?php echo htmlspecialchars($publicacion['imagen_publicacion']); ?>" alt="Imagen actual" style="max-width: 200px; border-radius: 5px;">
                    </div>
                <?php endif; ?>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select name="estado" id="estado" class="form-select">
                        <option value="activo" <?php echo ($publicacion['estado'] == 'activo') ? 'selected' : ''; ?>>Activo (Publicado)</option>
                        <option value="inactivo" <?php echo ($publicacion['estado'] == 'inactivo') ? 'selected' : ''; ?>>Inactivo (Oculto)</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="orden" class="form-label">Orden</label>
                    <input type="number" name="orden" id="orden" class="form-control" value="<?php echo htmlspecialchars($publicacion['orden']); ?>" required>
                </div>
            </div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                <button type="submit" name="edit_publicacion" class="btn btn-primary">Guardar Cambios</button>
                <a href="gestionar_publicaciones.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>
        <?php else: ?>
            <div class="alert alert-warning">La publicación no fue encontrada.</div>
        <?php endif; ?>

    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
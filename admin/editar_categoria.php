<?php
$page_title = "Editar Categoría";
require_once 'includes/header.php';

// Verificar si el usuario tiene permisos
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['rol'] !== 'administrador') {
    header("location: ../login.php");
    exit;
}

$error = '';
$mensaje = '';
$categoria = null;
$id_categoria = $_GET['id'] ?? null;

if (!$id_categoria) {
    $_SESSION['error'] = "ID de categoría no especificado.";
    header("location: gestionar_categorias.php");
    exit;
}

// Obtener datos de la categoría
$sql_categoria = "SELECT * FROM categorias WHERE id_categorias = ?";
if ($stmt = $conn->prepare($sql_categoria)) {
    $stmt->bind_param("i", $id_categoria);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $categoria = $result->fetch_assoc();
    } else {
        $_SESSION['error'] = "No se encontró la categoría.";
        header("location: gestionar_categorias.php");
        exit;
    }
    $stmt->close();
}

// Procesar el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_categoria = trim($_POST["nombre_categoria"]);
    $id_publicacion = $_POST["id_publicacion"];
    $estado = $_POST["estado"];
    $imagen_categoria_path = $categoria['imagen_categoria'];

    if (isset($_FILES["imagen_categoria"]) && $_FILES["imagen_categoria"]["error"] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/categorias/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $file_name = basename($_FILES["imagen_categoria"]["name"]);
        $target_file = $target_dir . uniqid() . "_" . $file_name;
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
        $check = getimagesize($_FILES["imagen_categoria"]["tmp_name"]);
        if($check !== false) {
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
                $error = "Solo se permiten archivos JPG, JPEG, PNG y GIF.";
            } else {
                if (move_uploaded_file($_FILES["imagen_categoria"]["tmp_name"], $target_file)) {
                    if (!empty($categoria['imagen_categoria']) && file_exists($categoria['imagen_categoria'])) {
                        unlink($categoria['imagen_categoria']);
                    }
                    $imagen_categoria_path = $target_file;
                } else {
                    $error = "Error al mover el archivo subido.";
                }
            }
        } else {
            $error = "El archivo no es una imagen válida.";
        }
    } else if (isset($_FILES["imagen_categoria"]) && $_FILES["imagen_categoria"]["error"] != UPLOAD_ERR_NO_FILE) {
        $error = "Error en la subida del archivo: Código " . $_FILES["imagen_categoria"]["error"];
    }

    if (empty($nombre_categoria) || empty($id_publicacion)) {
        $error = "El nombre y la publicación no pueden estar vacíos.";
    } else if (empty($error)) {
        $sql_update = "UPDATE categorias SET nombre_categoria = ?, id_publicacion = ?, imagen_categoria = ?, estado = ? WHERE id_categorias = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("sisii", $nombre_categoria, $id_publicacion, $imagen_categoria_path, $estado, $id_categoria);
            if ($stmt_update->execute()) {
                $_SESSION['mensaje'] = "Categoría actualizada correctamente.";
                header("location: gestionar_categorias.php");
                exit;
            } else {
                $error = "Error al actualizar la categoría: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
    $categoria['nombre_categoria'] = $nombre_categoria;
    $categoria['id_publicacion'] = $id_publicacion;
    $categoria['imagen_categoria'] = $imagen_categoria_path;
    $categoria['estado'] = $estado;
}

// Obtener todas las publicaciones para el selector
$publicaciones = [];
$sql_publicaciones = "SELECT id_publicacion, titulo FROM publicacion ORDER BY titulo ASC";
$result_publicaciones = $conn->query($sql_publicaciones);
if ($result_publicaciones && $result_publicaciones->num_rows > 0) {
    while($row = $result_publicaciones->fetch_assoc()) {
        $publicaciones[] = $row;
    }
}
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">

        <h1>Editar Categoría: <?php echo htmlspecialchars($categoria['nombre_categoria']); ?></h1>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="editar_categoria.php?id=<?php echo $id_categoria; ?>" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nombre_categoria" class="form-label">Nombre de la Categoría</label>
                <input type="text" name="nombre_categoria" id="nombre_categoria" class="form-control" value="<?php echo htmlspecialchars($categoria['nombre_categoria']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="id_publicacion" class="form-label">Publicación</label>
                <select name="id_publicacion" id="id_publicacion" class="form-select" required>
                    <option value="">Selecciona una publicación</option>
                    <?php foreach ($publicaciones as $pub): ?>
                        <option value="<?php echo $pub['id_publicacion']; ?>" <?php echo ($categoria['id_publicacion'] == $pub['id_publicacion']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pub['titulo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="imagen_categoria" class="form-label">Imagen de la Categoría</label>
                <input type="file" name="imagen_categoria" id="imagen_categoria" class="form-control" accept="image/*">
                <?php if (!empty($categoria['imagen_categoria'])): ?>
                    <div class="mt-2">
                        <small class="form-text text-muted">Imagen actual:</small><br>
                        <img src="<?php echo htmlspecialchars($categoria['imagen_categoria']); ?>" alt="Imagen actual" style="max-width: 150px; height: auto; border-radius: 5px;">
                    </div>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label for="estado" class="form-label">Estado</label>
                <select name="estado" id="estado" class="form-select" required>
                    <option value="0" <?php echo (isset($categoria['estado']) && $categoria['estado'] == 0) ? 'selected' : ''; ?>>Inactivo</option>
                    <option value="1" <?php echo (isset($categoria['estado']) && $categoria['estado'] == 1) ? 'selected' : ''; ?>>Activo</option>
                </select>
            </div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="gestionar_categorias.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>

    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
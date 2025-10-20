<?php 
$page_title = "Gestionar Categorías";
require_once 'includes/header.php';

// Verificar si el usuario tiene el rol adecuado para gestionar categorías
if ($_SESSION['rol'] !== 'administrador') {
    header("location: index.php"); // Redirigir si no tiene permiso
    exit;
}

// Manejo de mensajes de sesión
$mensaje = '';
$error = '';

if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
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

// Procesar el formulario para añadir nueva categoría
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_categoria"])) {
    $nombre_categoria = trim($_POST["nombre_categoria"]);
    $id_publicacion = $_POST["id_publicacion"];
    $estado = $_POST["estado"]; // Capturar el estado
    $imagen_categoria_path = NULL;

    // Manejo de la subida de imagen
    if (isset($_FILES["imagen_categoria"]) && $_FILES["imagen_categoria"]["error"] == UPLOAD_ERR_OK) {
        $target_dir = "uploads/categorias/";
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0777, true)) {
                $_SESSION['error'] = "Error: No se pudo crear el directorio de subida: " . $target_dir;
                header("Location: gestionar_categorias.php");
                exit;
            }
        }
        $file_name = basename($_FILES["imagen_categoria"]["name"]);
        $target_file = $target_dir . uniqid() . "_" . $file_name;
        $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["imagen_categoria"]["tmp_name"]);
        if($check !== false) {
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
            && $imageFileType != "gif" ) {
                $_SESSION['error'] = "Solo se permiten archivos JPG, JPEG, PNG y GIF.";
                header("Location: gestionar_categorias.php");
                exit;
            } else {
                if (move_uploaded_file($_FILES["imagen_categoria"]["tmp_name"], $target_file)) {
                    $imagen_categoria_path = $target_file;
                } else {
                    $_SESSION['error'] = "Error al mover el archivo subido. Posible problema de permisos o ruta: " . $target_file;
                    header("Location: gestionar_categorias.php");
                    exit;
                }
            }
        } else {
            $_SESSION['error'] = "El archivo no es una imagen válida o está corrupto.";
            header("Location: gestionar_categorias.php");
            exit;
        }
    } else if (isset($_FILES["imagen_categoria"]) && $_FILES["imagen_categoria"]["error"] != UPLOAD_ERR_NO_FILE) {
        $_SESSION['error'] = "Error en la subida del archivo: Código de error " . $_FILES["imagen_categoria"]["error"];
        header("Location: gestionar_categorias.php");
        exit;
    }

    if (empty($nombre_categoria) || empty($id_publicacion)) {
        $_SESSION['error'] = "El nombre de la categoría y la publicación no pueden estar vacíos.";
        header("Location: gestionar_categorias.php");
        exit;
    } else {
        $sql = "INSERT INTO categorias (id_publicacion, nombre_categoria, imagen_categoria, estado) VALUES (?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issi", $id_publicacion, $nombre_categoria, $imagen_categoria_path, $estado);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Categoría \"" . htmlspecialchars($nombre_categoria) . "\" añadida exitosamente.";
            } else {
                $_SESSION['error'] = "Error al añadir la categoría: " . $stmt->error;
            }
            $stmt->close();
            header("Location: gestionar_categorias.php");
            exit;
        }
    }
}

// Obtener todas las categorías para mostrarlas
$categorias = [];
$sql_select = "SELECT c.id_categorias, c.nombre_categoria, p.titulo as publicacion_titulo, c.fecha_creacion, c.imagen_categoria, c.estado 
               FROM categorias c JOIN publicacion p ON c.id_publicacion = p.id_publicacion 
               ORDER BY p.titulo ASC, c.nombre_categoria ASC";
$result = $conn->query($sql_select);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $categorias[] = $row;
    }
}
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content" style="background-color: #f0f0f0;">

        <h3 class="mb-4">Gestionar Categorías</h3>

        <?php if(!empty($mensaje)): ?>
            <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <h4 class="mb-3">Añadir Nueva Categoría</h4>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="row">
            <div class="col-md-6">
                <label for="id_publicacion" class="form-label">Publicación</label>
                <select name="id_publicacion" id="id_publicacion" class="form-select" required>
                    <option value="">Selecciona una publicación</option>
                    <?php foreach ($publicaciones as $pub): ?>
                        <option value="<?php echo $pub['id_publicacion']; ?>"><?php echo htmlspecialchars($pub['titulo']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="nombre_categoria" class="form-label">Nombre de la Categoría</label>
                <input type="text" name="nombre_categoria" id="nombre_categoria" class="form-control" required>
            </div>
            </div>


            <div class="row">
            <div class="col-md-6">
                <label for="imagen_categoria" class="form-label">Imagen de la Categoría</label>
                <input type="file" name="imagen_categoria" id="imagen_categoria" class="form-control" accept="image/*">
            </div>
            <div class="col-md-6">
                <label for="estado" class="form-label">Estado</label>
                <select name="estado" id="estado" class="form-select" required>
                    <option value="0">Inactivo</option>
                    <option value="1">Activo</option>
                </select>
            </div>
            </div>



            <div class="d-grid">
                <button type="submit" name="add_categoria" class="btn btn-primary">Añadir Categoría</button>
            </div>
        </form>

        <div class="categorias-list mt-5">
            <h2 class="mb-3">Categorías Existentes</h2>
            <?php if (empty($categorias)): ?>
                <p class="text-muted">No hay categorías registradas aún.</p>
            <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Categoría</th>
                            <th>Publicación</th>
                            <th>Estado</th>
                            <th>Imagen</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cat['id_categorias']); ?></td>
                                <td><?php echo htmlspecialchars($cat['nombre_categoria']); ?></td>
                                <td><?php echo htmlspecialchars($cat['publicacion_titulo']); ?></td>
                                <td>
                                    <?php if ($cat['estado'] == 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($cat['imagen_categoria'])): ?>
                                        <img src="<?php echo htmlspecialchars($cat['imagen_categoria']); ?>" alt="Imagen de Categoría" class="img-thumbnail" style="max-width: 150px; height: auto;">
                                    <?php else: ?>
                                        No disponible
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($_SESSION['rol'] === 'administrador'): ?>
                                        <a href="editar_categoria.php?id=<?php echo $cat['id_categorias']; ?>" class="btn btn-warning btn-sm me-1">Editar</a>
                                        <a href="eliminar_categoria.php?id=<?php echo $cat['id_categorias']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres eliminar esta categoría?');">Eliminar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
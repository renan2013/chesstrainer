<?php 
$page_title = "Gestionar Publicaciones";
require_once 'includes/header.php';

// Verificar si el usuario tiene el rol adecuado
if ($_SESSION['rol'] !== 'administrador') {
    header("location: index.php");
    exit;
}

$mensaje = '';
$error = '';

// Procesar el formulario para añadir nueva publicación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_publicacion"])) {
    $titulo = trim($_POST["titulo"]);
    $descripcion = trim($_POST["descripcion"]);
    $tipo = trim($_POST["tipo"]);
    $estado = trim($_POST["estado"]);
    $orden = filter_input(INPUT_POST, 'orden', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $imagen_publicacion = '';

    if (isset($_FILES["imagen_publicacion"]) && $_FILES["imagen_publicacion"]["error"] == 0) {
        $target_dir = "uploads/publicaciones/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . uniqid() . '_' . basename($_FILES["imagen_publicacion"]["name"]);
        if (move_uploaded_file($_FILES["imagen_publicacion"]["tmp_name"], $target_file)) {
            $imagen_publicacion = $target_file;
        } else {
            $error = "Error al subir la imagen.";
        }
    }

    if (empty($titulo)) {
        $error = "El título de la publicación no puede estar vacío.";
    } elseif (empty($error)) {
        $sql = "INSERT INTO publicacion (titulo, descripcion, tipo, imagen_publicacion, estado, orden) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssi", $titulo, $descripcion, $tipo, $imagen_publicacion, $estado, $orden);
            if ($stmt->execute()) {
                $mensaje = "Publicación \"" . htmlspecialchars($titulo) . "\" añadida exitosamente.";
            } else {
                $error = "Error al añadir la publicación: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Obtener todas las publicaciones para mostrarlas
$publicaciones = [];
$sql_select = "SELECT id_publicacion, titulo, descripcion, tipo, imagen_publicacion, estado, fecha_creacion, orden FROM publicacion ORDER BY orden ASC, titulo ASC";
$result = $conn->query($sql_select);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $publicaciones[] = $row;
    }
}
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">

        <h1 class="mb-4">Gestionar Publicaciones</h1>

        <?php if(!empty($mensaje)): ?>
            <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <h2 class="mb-3">Añadir Nueva Publicación</h2>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="titulo" class="form-label">Título</label>
                <input type="text" name="titulo" id="titulo" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea name="descripcion" id="descripcion" class="form-control"></textarea>
            </div>
            <div class="mb-3">
                <label for="tipo" class="form-label">Tipo de Publicación</label>
                <select name="tipo" id="tipo" class="form-select" required>
                    <option value="Problema" selected>Problema (El usuario resuelve y puntúa)</option>
                    <option value="Estudio">Estudio (El usuario solo analiza)</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="imagen_publicacion" class="form-label">Imagen</label>
                <input type="file" name="imagen_publicacion" id="imagen_publicacion" class="form-control">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select name="estado" id="estado" class="form-select" required>
                        <option value="activo" selected>Activo (Publicado)</option>
                        <option value="inactivo">Inactivo (Oculto)</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="orden" class="form-label">Orden</label>
                    <input type="number" name="orden" id="orden" class="form-control" value="0" required>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" name="add_publicacion" class="btn btn-primary">Añadir Publicación</button>
            </div>
        </form>

        <div class="publicaciones-list mt-5">
            <h2 class="mb-3">Publicaciones Existentes</h2>
            <?php if (empty($publicaciones)): ?>
                <p class="text-muted">No hay publicaciones registradas aún.</p>
            <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Orden</th>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Imagen</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publicaciones as $pub): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pub['orden']); ?></td>
                                <td><?php echo htmlspecialchars($pub['id_publicacion']); ?></td>
                                <td><?php echo htmlspecialchars(substr($pub['titulo'], 0, 50)); ?></td>
                                <td><?php echo htmlspecialchars($pub['tipo']); ?></td>
                                <td>
                                    <?php if (!empty($pub['imagen_publicacion'])): ?>
                                        <img src="<?php echo htmlspecialchars($pub['imagen_publicacion']); ?>" alt="Imagen" style="max-width: 100px; max-height: 100px;">
                                    <?php else: ?>
                                        Sin imagen
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo ($pub['estado'] == 'activo') ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo htmlspecialchars(ucfirst($pub['estado'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($pub['fecha_creacion']); ?></td>
                                <td>
                                    <a href="editar_publicacion.php?id=<?php echo $pub['id_publicacion']; ?>" class="btn btn-warning btn-sm">Editar</a>
                                    <?php if ($_SESSION['rol'] === 'administrador'): ?>
                                        <a href="eliminar_publicacion.php?id=<?php echo $pub['id_publicacion']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres eliminar esta publicación?');">Eliminar</a>
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
<?php
$page_title = "Editar Entrada";
require_once 'includes/header.php';

// --- 1. Verificación de Permisos ---
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
    header("Location: index.php");
    exit;
}

// --- 2. Obtener y Validar ID ---
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "ID de entrada no válido.";
    header("Location: base_conocimiento.php");
    exit;
}
$id_entrada = $_GET['id'];

// --- 3. Lógica de Actualización (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['actualizar_entrada'])) {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);

    $sql_update = "UPDATE base_conocimiento SET titulo = ?, descripcion = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql_update)) {
        $stmt->bind_param("ssi", $titulo, $descripcion, $id_entrada);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Entrada actualizada con éxito.";
        } else {
            $_SESSION['error'] = "Error al actualizar la entrada: " . $stmt->error;
        }
        $stmt->close();
    }
    header("Location: base_conocimiento.php");
    exit;
}

// --- 4. Obtener Datos para el Formulario (GET) ---
$sql_select = "SELECT titulo, descripcion, id_usuario FROM base_conocimiento WHERE id = ?";
if ($stmt = $conn->prepare($sql_select)) {
    $stmt->bind_param("i", $id_entrada);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 1) {
        $entrada = $result->fetch_assoc();
        // Verificar si el usuario puede editar
        if (!($_SESSION['id_usuarios'] == $entrada['id_usuario'] || $_SESSION['rol'] === 'administrador')) {
            $_SESSION['error'] = "No tienes permiso para editar esta entrada.";
            header("Location: base_conocimiento.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "No se encontró la entrada.";
        header("Location: base_conocimiento.php");
        exit;
    }
    $stmt->close();
}
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">
        <h1 class="mb-4">Editar Entrada de Conocimiento</h1>

        <div class="card">
            <div class="card-header">
                <h3>Editando Entrada #<?php echo htmlspecialchars($id_entrada); ?></h3>
            </div>
            <div class="card-body">
                <form method="POST" action="editar_entrada_conocimiento.php?id=<?php echo htmlspecialchars($id_entrada); ?>">
                    <div class="form-group mb-3">
                        <label for="titulo" class="form-label">Título</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($entrada['titulo']); ?>" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="10" required><?php echo htmlspecialchars($entrada['descripcion']); ?></textarea>
                    </div>
                    <a href="base_conocimiento.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" name="actualizar_entrada" class="btn btn-primary">Actualizar Entrada</button>
                </form>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>

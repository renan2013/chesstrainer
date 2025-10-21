<?php
session_start();
require '../db_connect.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['id_usuarios']) || $_SESSION['rol'] !== 'administrador') {
    header("Location: ../login.php");
    exit;
}

$instructores = [];
$sql_instructores = "SELECT id_usuarios, nombre_usuario FROM usuarios WHERE rol = 'instructor' OR rol = 'administrador'";
$result_instructores = $conn->query($sql_instructores);
if ($result_instructores->num_rows > 0) {
    while ($row = $result_instructores->fetch_assoc()) {
        $instructores[] = $row;
    }
}

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre_grupo = $_POST['nombre_grupo'];
    $descripcion = $_POST['descripcion'];
    $id_instructor = $_POST['id_instructor'];

    if (!empty($nombre_grupo) && !empty($id_instructor)) {
        $stmt = $conn->prepare("INSERT INTO grupos (nombre, descripcion, id_instructor) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre_grupo, $descripcion, $id_instructor);

        if ($stmt->execute()) {
            $mensaje = "<div class='alert alert-success'>Grupo creado exitosamente.</div>";
        } else {
            $mensaje = "<div class='alert alert-danger'>Error al crear el grupo: " . $conn->error . "</div>";
        }
        $stmt->close();
    } else {
        $mensaje = "<div class='alert alert-warning'>El nombre del grupo y el instructor son obligatorios.</div>";
    }
}

$conn->close();
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <h2>Crear Nuevo Grupo</h2>
    <p>Aquí puedes crear un nuevo grupo o curso y asignarle un instructor.</p>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <form action="crear_grupo.php" method="post" class="mt-3">
        <div class="mb-3">
            <label for="nombre_grupo" class="form-label">Nombre del Grupo:</label>
            <input type="text" class="form-control" id="nombre_grupo" name="nombre_grupo" required>
        </div>
        <div class="mb-3">
            <label for="descripcion" class="form-label">Descripción:</label>
            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label for="id_instructor" class="form-label">Instructor:</label>
            <select class="form-select" id="id_instructor" name="id_instructor" required>
                <option value="">Selecciona un instructor</option>
                <?php foreach ($instructores as $instructor): ?>
                    <option value="<?php echo htmlspecialchars($instructor['id_usuarios']); ?>">
                        <?php echo htmlspecialchars($instructor['nombre_usuario']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Crear Grupo</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>

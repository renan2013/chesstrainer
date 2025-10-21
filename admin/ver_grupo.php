<?php
session_start();
require '../db_connect.php';

// Verificar si el usuario está autorizado
if (!isset($_SESSION['id_usuarios']) || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("Location: ../login.php");
    exit;
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: grupos.php");
    exit;
}
$id_grupo = $_GET['id'];

$mensaje = '';

// --- LÓGICA PARA AÑADIR MIEMBRO ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_member'])) {
    $id_usuario_a_anadir = $_POST['id_usuario'];
    if (!empty($id_usuario_a_anadir)) {
        // Verificar que el usuario no sea ya miembro
        $check_stmt = $conn->prepare("SELECT * FROM grupo_miembros WHERE id_grupo = ? AND id_usuario = ?");
        $check_stmt->bind_param("ii", $id_grupo, $id_usuario_a_anadir);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows == 0) {
            $insert_stmt = $conn->prepare("INSERT INTO grupo_miembros (id_grupo, id_usuario) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $id_grupo, $id_usuario_a_anadir);
            if (!$insert_stmt->execute()) {
                $mensaje = "<div class='alert alert-danger'>Error al añadir miembro.</div>";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
        // Redirigir para mostrar la lista actualizada y evitar reenvío de formulario
        header("Location: ver_grupo.php?id=" . $id_grupo);
        exit;
    }
}


// 1. Obtener detalles del grupo y del instructor
$stmt_grupo = $conn->prepare("SELECT g.id, g.nombre, g.descripcion, u.nombre_usuario AS instructor_nombre FROM grupos g JOIN usuarios u ON g.id_instructor = u.id_usuarios WHERE g.id = ?");
$stmt_grupo->bind_param("i", $id_grupo);
$stmt_grupo->execute();
$result_grupo = $stmt_grupo->get_result();
if ($result_grupo->num_rows === 0) {
    echo "Grupo no encontrado.";
    exit;
}
$grupo = $result_grupo->fetch_assoc();
$stmt_grupo->close();

// 2. Obtener miembros del grupo
$stmt_miembros = $conn->prepare("SELECT u.id_usuarios, u.nombre_completo FROM grupo_miembros gm JOIN usuarios u ON gm.id_usuario = u.id_usuarios WHERE gm.id_grupo = ? ORDER BY u.nombre_completo");
$stmt_miembros->bind_param("i", $id_grupo);
$stmt_miembros->execute();
$result_miembros = $stmt_miembros->get_result();
$miembros = [];
while ($row = $result_miembros->fetch_assoc()) {
    $miembros[] = $row;
}
$stmt_miembros->close();

// 3. Obtener usuarios que NO están en el grupo para el dropdown
$stmt_no_miembros = $conn->prepare("SELECT id_usuarios, nombre_completo FROM usuarios WHERE rol = 'usuario' AND id_usuarios NOT IN (SELECT id_usuario FROM grupo_miembros WHERE id_grupo = ?) ORDER BY nombre_completo");
$stmt_no_miembros->bind_param("i", $id_grupo);
$stmt_no_miembros->execute();
$result_no_miembros = $stmt_no_miembros->get_result();
$no_miembros = [];
while ($row = $result_no_miembros->fetch_assoc()) {
    $no_miembros[] = $row;
}
$stmt_no_miembros->close();


// 4. Obtener material asignado
$stmt_material = $conn->prepare("SELECT id, id_material, tipo_material FROM grupo_material WHERE id_grupo = ?");
$stmt_material->bind_param("i", $id_grupo);
$stmt_material->execute();
$result_material = $stmt_material->get_result();
$materiales = [];
while ($row = $result_material->fetch_assoc()) {
    $materiales[] = $row;
}
$stmt_material->close();

// Para cada material, obtener el nombre real
$materiales_con_nombre = [];
foreach ($materiales as $material) {
    $nombre_material = '[Material no encontrado]';
    if ($material['tipo_material'] == 'publicacion') {
        $stmt = $conn->prepare("SELECT titulo FROM publicacion WHERE id_publicacion = ?");
        $stmt->bind_param("i", $material['id_material']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $nombre_material = $res->fetch_assoc()['titulo'];
        }
        $stmt->close();
    } elseif ($material['tipo_material'] == 'categoria') {
        $stmt = $conn->prepare("SELECT nombre_categoria FROM categorias WHERE id_categorias = ?");
        $stmt->bind_param("i", $material['id_material']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $nombre_material = $res->fetch_assoc()['nombre_categoria'];
        }
        $stmt->close();
    }
    $materiales_con_nombre[] = [
        'id' => $material['id'],
        'nombre' => $nombre_material,
        'tipo' => ucfirst($material['tipo_material'])
    ];
}

$conn->close();
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <a href="grupos.php" class="btn btn-secondary mb-3">&#8592; Volver a la lista de Grupos</a>

    <h2><?php echo htmlspecialchars($grupo['nombre']); ?></h2>
    <p><strong>Instructor:</strong> <?php echo htmlspecialchars($grupo['instructor_nombre']); ?></p>
    <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($grupo['descripcion'])); ?></p>

    <hr>

    <div class="row">
        <!-- Columna de Miembros -->
        <div class="col-md-6">
            <h4>Miembros del Grupo</h4>
            <?php if (empty($miembros)): ?>
                <p>Aún no hay miembros en este grupo.</p>
            <?php else: ?>
                <ul class="list-group mb-3">
                    <?php foreach ($miembros as $miembro): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($miembro['nombre_completo']); ?>
                            <a href="#" class="btn btn-danger btn-sm">Quitar</a> <!-- Funcionalidad futura -->
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <!-- Formulario para añadir miembros -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Añadir Miembro</h5>
                    <form action="ver_grupo.php?id=<?php echo $id_grupo; ?>" method="post">
                        <input type="hidden" name="add_member" value="1">
                        <div class="mb-3">
                            <label for="id_usuario" class="form-label">Seleccionar Estudiante:</label>
                            <select class="form-select" id="id_usuario" name="id_usuario" required>
                                <option value="">-- Estudiantes disponibles --</option>
                                <?php foreach ($no_miembros as $estudiante): ?>
                                    <option value="<?php echo $estudiante['id_usuarios']; ?>">
                                        <?php echo htmlspecialchars($estudiante['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Añadir Miembro</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Columna de Material -->
        <div class="col-md-6">
            <h4>Material Asignado</h4>
            <?php if (empty($materiales_con_nombre)): ?>
                <p>Aún no hay material asignado a este grupo.</p>
            <?php else: ?>
                <ul class="list-group mb-3">
                    <?php foreach ($materiales_con_nombre as $material): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <?php echo htmlspecialchars($material['nombre']); ?>
                                <span class="badge bg-info ms-2"><?php echo htmlspecialchars($material['tipo']); ?></span>
                            </div>
                            <a href="#" class="btn btn-danger btn-sm">Quitar</a> <!-- Funcionalidad futura -->
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <!-- Futuro formulario para asignar material aquí -->
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

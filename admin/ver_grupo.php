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
$stmt_miembros = $conn->prepare("SELECT u.id_usuarios, u.nombre_usuario FROM grupo_miembros gm JOIN usuarios u ON gm.id_usuario = u.id_usuarios WHERE gm.id_grupo = ? ORDER BY u.nombre_usuario");
$stmt_miembros->bind_param("i", $id_grupo);
$stmt_miembros->execute();
$result_miembros = $stmt_miembros->get_result();
$miembros = [];
while ($row = $result_miembros->fetch_assoc()) {
    $miembros[] = $row;
}
$stmt_miembros->close();

// 3. Obtener material asignado
$stmt_material = $conn->prepare("SELECT id_material, tipo_material FROM grupo_material WHERE id_grupo = ?");
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
                <ul class="list-group">
                    <?php foreach ($miembros as $miembro): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($miembro['nombre_usuario']); ?>
                            <a href="#" class="btn btn-danger btn-sm">Quitar</a> <!-- Funcionalidad futura -->
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <!-- Futuro formulario para añadir miembros aquí -->
        </div>

        <!-- Columna de Material -->
        <div class="col-md-6">
            <h4>Material Asignado</h4>
            <?php if (empty($materiales_con_nombre)): ?>
                <p>Aún no hay material asignado a este grupo.</p>
            <?php else: ?>
                <ul class="list-group">
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

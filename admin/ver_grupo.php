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

// --- LÓGICA PARA PROCESAR FORMULARIOS POST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ACTUALIZAR DETALLES DEL GRUPO
    if (isset($_POST['update_group'])) {
        $nombre_grupo = trim($_POST['nombre_grupo']);
        $descripcion = trim($_POST['descripcion']);
        $id_instructor = $_POST['id_instructor'];
        if (!empty($nombre_grupo) && !empty($id_instructor)) {
            $stmt = $conn->prepare("UPDATE grupos SET nombre = ?, descripcion = ?, id_instructor = ? WHERE id = ?");
            $stmt->bind_param("ssii", $nombre_grupo, $descripcion, $id_instructor, $id_grupo);
            if ($stmt->execute()) {
                $mensaje = "<div class='alert alert-success'>Grupo actualizado exitosamente.</div>";
            } else {
                $mensaje = "<div class='alert alert-danger'>Error al actualizar el grupo.</div>";
            }
            $stmt->close();
        } else {
            $mensaje = "<div class='alert alert-warning'>El nombre y el instructor son obligatorios.</div>";
        }
    }

    // AÑADIR MIEMBRO
    if (isset($_POST['add_member'])) {
        $id_usuario_a_anadir = $_POST['id_usuario'];
        if (!empty($id_usuario_a_anadir)) {
            $stmt = $conn->prepare("INSERT INTO grupo_miembros (id_grupo, id_usuario) VALUES (?, ?) ON DUPLICATE KEY UPDATE id_usuario=id_usuario");
            $stmt->bind_param("ii", $id_grupo, $id_usuario_a_anadir);
            $stmt->execute(); $stmt->close();
            header("Location: ver_grupo.php?id=" . $id_grupo); exit;
        }
    }
    // ASIGNAR MATERIAL
    if (isset($_POST['add_material'])) {
        $material_compuesto = $_POST['material'];
        if (!empty($material_compuesto)) {
            list($tipo_material, $id_material) = explode('-', $material_compuesto);
            $id_material = intval($id_material);
            if (($tipo_material == 'publicacion' || $tipo_material == 'categoria') && $id_material > 0) {
                $stmt = $conn->prepare("INSERT INTO grupo_material (id_grupo, id_material, tipo_material) VALUES (?, ?, ?)");
                $stmt->bind_param("iis", $id_grupo, $id_material, $tipo_material);
                $stmt->execute(); $stmt->close();
                header("Location: ver_grupo.php?id=" . $id_grupo); exit;
            }
        }
    }
}

// --- LÓGICA PARA QUITAR (GET) ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    // QUITAR MIEMBRO
    if (isset($_GET['remove_member']) && filter_var($_GET['remove_member'], FILTER_VALIDATE_INT)) {
        $id_usuario_a_quitar = $_GET['remove_member'];
        $stmt = $conn->prepare("DELETE FROM grupo_miembros WHERE id_grupo = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_grupo, $id_usuario_a_quitar);
        $stmt->execute(); $stmt->close();
        header("Location: ver_grupo.php?id=" . $id_grupo); exit;
    }
    // QUITAR MATERIAL
    if (isset($_GET['remove_material']) && filter_var($_GET['remove_material'], FILTER_VALIDATE_INT)) {
        $id_material_a_quitar = $_GET['remove_material'];
        $stmt = $conn->prepare("DELETE FROM grupo_material WHERE id = ? AND id_grupo = ?");
        $stmt->bind_param("ii", $id_material_a_quitar, $id_grupo);
        $stmt->execute(); $stmt->close();
        header("Location: ver_grupo.php?id=" . $id_grupo); exit;
    }
}

// --- OBTENER DATOS PARA MOSTRAR ---

// 1. Detalles del grupo
$stmt_grupo = $conn->prepare("SELECT g.id, g.nombre, g.descripcion, g.id_instructor FROM grupos g WHERE g.id = ?");
$stmt_grupo->bind_param("i", $id_grupo);
$stmt_grupo->execute();
$result_grupo = $stmt_grupo->get_result();
if ($result_grupo->num_rows === 0) { echo "Grupo no encontrado."; exit; }
$grupo = $result_grupo->fetch_assoc();
$stmt_grupo->close();

// 2. Lista de todos los instructores para el dropdown
$instructores = $conn->query("SELECT id_usuarios, nombre_completo FROM usuarios WHERE rol IN ('instructor', 'administrador') ORDER BY nombre_completo")->fetch_all(MYSQLI_ASSOC);

// 3. Miembros del grupo
$stmt_miembros = $conn->prepare("SELECT u.id_usuarios, u.nombre_completo FROM grupo_miembros gm JOIN usuarios u ON gm.id_usuario = u.id_usuarios WHERE gm.id_grupo = ? ORDER BY u.nombre_completo");
$stmt_miembros->bind_param("i", $id_grupo);
$stmt_miembros->execute();
$miembros = $stmt_miembros->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_miembros->close();

// 4. Usuarios que NO están en el grupo
$stmt_no_miembros = $conn->prepare("SELECT id_usuarios, nombre_completo FROM usuarios WHERE rol = 'usuario' AND id_usuarios NOT IN (SELECT id_usuario FROM grupo_miembros WHERE id_grupo = ?) ORDER BY nombre_completo");
$stmt_no_miembros->bind_param("i", $id_grupo);
$stmt_no_miembros->execute();
$no_miembros = $stmt_no_miembros->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_no_miembros->close();

// 5. Material asignado
$stmt_material = $conn->prepare("SELECT id, id_material, tipo_material FROM grupo_material WHERE id_grupo = ?");
$stmt_material->bind_param("i", $id_grupo);
$stmt_material->execute();
$materiales_asignados = $stmt_material->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_material->close();

// 6. Material disponible
$publicaciones_disponibles = $conn->query("SELECT id_publicacion, titulo FROM publicacion WHERE id_publicacion NOT IN (SELECT id_material FROM grupo_material WHERE id_grupo = $id_grupo AND tipo_material = 'publicacion') ORDER BY titulo")->fetch_all(MYSQLI_ASSOC);
$categorias_disponibles = $conn->query("SELECT id_categorias, nombre_categoria FROM categorias WHERE id_categorias NOT IN (SELECT id_material FROM grupo_material WHERE id_grupo = $id_grupo AND tipo_material = 'categoria') ORDER BY nombre_categoria")->fetch_all(MYSQLI_ASSOC);

$materiales_con_nombre = [];
foreach ($materiales_asignados as $material) {
    $nombre_material = '[Material no encontrado]';
    if ($material['tipo_material'] == 'publicacion') {
        $stmt = $conn->prepare("SELECT titulo FROM publicacion WHERE id_publicacion = ?");
        $stmt->bind_param("i", $material['id_material']);
    } else {
        $stmt = $conn->prepare("SELECT nombre_categoria FROM categorias WHERE id_categorias = ?");
        $stmt->bind_param("i", $material['id_material']);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $nombre_material = $res->fetch_assoc()[($material['tipo_material'] == 'publicacion') ? 'titulo' : 'nombre_categoria'];
    }
    $stmt->close();
    $materiales_con_nombre[] = ['id' => $material['id'], 'nombre' => $nombre_material, 'tipo' => ucfirst($material['tipo_material'])];
}

$conn->close();
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <a href="grupos.php" class="btn btn-secondary mb-3">&#8592; Volver a la lista de Grupos</a>

    <?php if (!empty($mensaje)) echo $mensaje; ?>

    <form action="ver_grupo.php?id=<?php echo $id_grupo; ?>" method="post">
        <input type="hidden" name="update_group" value="1">
        <div class="card mb-4">
            <div class="card-header"><h4>Detalles del Grupo</h4></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="nombre_grupo" class="form-label">Nombre del Grupo:</label>
                    <input type="text" class="form-control" id="nombre_grupo" name="nombre_grupo" value="<?php echo htmlspecialchars($grupo['nombre']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción:</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo htmlspecialchars($grupo['descripcion']); ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="id_instructor" class="form-label">Instructor:</label>
                    <select class="form-select" id="id_instructor" name="id_instructor" required>
                        <?php foreach ($instructores as $instructor): ?>
                            <option value="<?php echo $instructor['id_usuarios']; ?>" <?php if($grupo['id_instructor'] == $instructor['id_usuarios']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($instructor['nombre_completo']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </div>
    </form>

    <hr>

    <div class="row">
        <!-- Columna de Miembros -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h4>Miembros del Grupo</h4></div>
                <div class="card-body">
                    <?php if (empty($miembros)): ?>
                        <p>Aún no hay miembros en este grupo.</p>
                    <?php else: ?>
                        <ul class="list-group mb-3">
                            <?php foreach ($miembros as $miembro): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo htmlspecialchars($miembro['nombre_completo']); ?>
                                    <a href="ver_grupo.php?id=<?php echo $id_grupo; ?>&remove_member=<?php echo $miembro['id_usuarios']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres quitar a este miembro del grupo?');">Quitar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <h5 class="card-title mt-4">Añadir Miembro</h5>
                    <form action="ver_grupo.php?id=<?php echo $id_grupo; ?>" method="post">
                        <input type="hidden" name="add_member" value="1">
                        <div class="mb-3">
                            <label for="id_usuario" class="form-label">Seleccionar Estudiante:</label>
                            <select class="form-select" id="id_usuario" name="id_usuario" required>
                                <option value="">-- Estudiantes disponibles --</option>
                                <?php foreach ($no_miembros as $estudiante): ?>
                                    <option value="<?php echo $estudiante['id_usuarios']; ?>"><?php echo htmlspecialchars($estudiante['nombre_completo']); ?></option>
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
            <div class="card">
                <div class="card-header"><h4>Material Asignado</h4></div>
                <div class="card-body">
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
                                    <a href="ver_grupo.php?id=<?php echo $id_grupo; ?>&remove_material=<?php echo $material['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres quitar este material del grupo?');">Quitar</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <h5 class="card-title mt-4">Asignar Material</h5>
                    <form action="ver_grupo.php?id=<?php echo $id_grupo; ?>" method="post">
                        <input type="hidden" name="add_material" value="1">
                        <div class="mb-3">
                            <label for="material" class="form-label">Seleccionar Material:</label>
                            <select class="form-select" id="material" name="material" required>
                                <option value="">-- Material disponible --</option>
                                <optgroup label="Publicaciones">
                                    <?php foreach ($publicaciones_disponibles as $pub): ?>
                                        <option value="publicacion-<?php echo $pub['id_publicacion']; ?>"><?php echo htmlspecialchars($pub['titulo']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="Categorías">
                                    <?php foreach ($categorias_disponibles as $cat): ?>
                                        <option value="categoria-<?php echo $cat['id_categorias']; ?>"><?php echo htmlspecialchars($cat['nombre_categoria']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Asignar Material</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

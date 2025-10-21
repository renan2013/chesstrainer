<?php
session_start();
require '../db_connect.php';

// Verificar si el usuario está autorizado (administrador o instructor)
if (!isset($_SESSION['id_usuarios']) || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("Location: ../login.php");
    exit;
}

// Obtener todos los grupos con el nombre del instructor
$sql = "SELECT g.id, g.nombre, g.descripcion, u.nombre_usuario AS instructor_nombre 
        FROM grupos g 
        JOIN usuarios u ON g.id_instructor = u.id_usuarios 
        ORDER BY g.fecha_creacion DESC";

$result = $conn->query($sql);

$grupos = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $grupos[] = $row;
    }
}

$conn->close();
?>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Gestionar Grupos</h2>
        <a href="crear_grupo.php" class="btn btn-success">+ Crear Nuevo Grupo</a>
    </div>
    <p>Aquí puedes ver, editar y eliminar los grupos de estudio.</p>

    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead class="table-dark">
                <tr>
                    <th>Nombre del Grupo</th>
                    <th>Descripción</th>
                    <th>Instructor</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($grupos)): ?>
                    <?php foreach ($grupos as $grupo): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($grupo['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($grupo['descripcion']); ?></td>
                            <td><?php echo htmlspecialchars($grupo['instructor_nombre']); ?></td>
                            <td>
                                <a href="ver_grupo.php?id=<?php echo $grupo['id']; ?>" class="btn btn-primary btn-sm">Ver/Editar</a>
                                <a href="eliminar_grupo.php?id=<?php echo $grupo['id']; ?>" class="btn btn-danger btn-sm" onclick="event.preventDefault(); confirmDelete(this.href);">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No hay grupos creados todavía.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function confirmDelete(url) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "¡No podrás revertir esta acción!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, ¡eliminar!',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?>

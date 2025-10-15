<?php
session_start();

// Verificar si el usuario ha iniciado sesión y tiene el rol adecuado
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['rol'], ['administrador', 'instructor'])) {
    header("location: ../login.php");
    exit;
}

$page_title = "Grupos de Estudio";
require_once 'includes/header.php';
require_once '../db_connect.php'; // Conexión a la base de datos

$grupos = [
    'Principiante' => [],
    'Intermedio' => [],
    'Avanzado' => []
];
$error = '';
$staff_members = [];

try {
    // Obtener personal del sistema
    $staff_roles = ['administrador', 'instructor', 'creador_contenido'];
    $placeholders = implode(',', array_fill(0, count($staff_roles), '?'));

    $sql_staff = "SELECT id_usuarios, nombre_usuario, nombre_completo, email, rol FROM usuarios WHERE rol IN ($placeholders) ORDER BY rol ASC, nombre_completo ASC";
    $stmt_staff = $conn->prepare($sql_staff);
    $stmt_staff->bind_param(str_repeat('s', count($staff_roles)), ...$staff_roles);
    $stmt_staff->execute();
    $result_staff = $stmt_staff->get_result();

    if ($result_staff->num_rows > 0) {
        while ($row = $result_staff->fetch_assoc()) {
            $staff_members[] = $row;
        }
    }
    $stmt_staff->close();

    // Obtener usuarios y clasificarlos por nivel, excluyendo roles de personal
    $student_roles_to_exclude = ['administrador', 'instructor', 'creador_contenido'];
    $student_placeholders = implode(',', array_fill(0, count($student_roles_to_exclude), '?'));

    $sql = "SELECT id_usuarios, nombre_usuario, nombre_completo, email, rol, nivel_ajedrez FROM usuarios WHERE autorizado = 1 AND rol NOT IN ($student_placeholders) ORDER BY nivel_ajedrez ASC, nombre_completo ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($student_roles_to_exclude)), ...$student_roles_to_exclude);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            if (array_key_exists($row['nivel_ajedrez'], $grupos)) {
                $grupos[$row['nivel_ajedrez']][] = $row;
            }
        }
    }
    $stmt->close();
} catch (Exception $e) {
    $error = "Error al cargar los usuarios: " . $e->getMessage();
}

$conn->close();
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">

        <h1 class="mb-4">Grupos de Estudio</h1>

        <!-- Staff Group Card -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h4 class="mb-0">Personal del Sistema (<?php echo count($staff_members); ?>)</h4>
            </div>
            <div class="card-body">
                <?php if (empty($staff_members)): ?>
                    <p class="text-muted">No hay personal registrado.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <!-- Staff table header -->
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre Completo</th>
                                    <th>Usuario</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_members as $staff_member): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($staff_member['id_usuarios']); ?></td>
                                        <td><?php echo htmlspecialchars($staff_member['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($staff_member['nombre_usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($staff_member['email']); ?></td>
                                        <td><?php echo htmlspecialchars($staff_member['rol']); ?></td>
                                        <td>
                                            <a href="editar_usuario.php?id=<?php echo $staff_member['id_usuarios']; ?>" class="btn btn-sm btn-info">Editar</a>
                                            <a href="eliminar_usuario.php?id=<?php echo $staff_member['id_usuarios']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar a este miembro del personal?');">Eliminar</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php else: ?>
            <?php foreach ($grupos as $nivel => $usuarios_nivel): ?>
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Nivel: <?php echo htmlspecialchars($nivel); ?> (<?php echo count($usuarios_nivel); ?> usuarios)</h4>
                    </div>
                    <div class="card-body">
                        <?php if (empty($usuarios_nivel)): ?>
                            <p class="text-muted">No hay usuarios en este nivel.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <!-- Student table header -->
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre Completo</th>
                                            <th>Usuario</th>
                                            <th>Email</th>
                                            <th>Rol</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios_nivel as $usuario): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($usuario['id_usuarios']); ?></td>
                                                <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                                <td><?php echo htmlspecialchars($usuario['nombre_usuario']); ?></td>
                                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                                <td><?php echo htmlspecialchars($usuario['rol']); ?></td>
                                                <td>
                                                    <a href="ver_reporte_usuario.php?id=<?php echo $usuario['id_usuarios']; ?>" class="btn btn-sm btn-info">Ver Reporte</a>
                                                    <a href="ver_grafica_usuario.php?id=<?php echo $usuario['id_usuarios']; ?>" class="btn btn-sm btn-primary">Ver Gráfica</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>
</div>

<?php require_once 'includes/footer.php'; ?>

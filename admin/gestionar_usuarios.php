<?php
session_start();

// Verificar si el usuario ha iniciado sesión y es administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'administrador') {
    header("location: ../login.php");
    exit;
}

$page_title = "Gestionar Usuarios";
require_once 'includes/header.php';
require_once '../db_connect.php'; // Conexión a la base de datos

// Manejo de mensajes de sesión
$mensaje_sesion = '';
$error_sesion = '';

if (isset($_SESSION['mensaje'])) {
    $mensaje_sesion = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}

if (isset($_SESSION['error'])) {
    $error_sesion = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Paginación y Búsqueda
$usuarios_por_pagina = 25;
$pagina_actual = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($pagina_actual < 1) {
    $pagina_actual = 1;
}
$offset = ($pagina_actual - 1) * $usuarios_por_pagina;

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

$usuarios = [];
$error = '';
$total_usuarios = 0;

try {
    // Primero, contar el total de usuarios para la paginación (con filtro de búsqueda)
    $sql_count = "SELECT COUNT(*) FROM usuarios";
    $params = [];
    $types = '';
    if (!empty($search_term)) {
        $sql_count .= " WHERE nombre_completo LIKE ? OR nombre_usuario LIKE ? OR email LIKE ?";
        $like_term = "%{$search_term}%";
        $params = [$like_term, $like_term, $like_term];
        $types = 'sss';
    }
    
    $stmt_count = $conn->prepare($sql_count);
    if ($types) {
        $stmt_count->bind_param($types, ...$params);
    }
    $stmt_count->execute();
    $stmt_count->bind_result($total_usuarios);
    $stmt_count->fetch();
    $stmt_count->close();

    $total_paginas = ceil($total_usuarios / $usuarios_por_pagina);

    // Ahora, obtener los usuarios para la página actual (con filtro y paginación)
    $sql = "SELECT id_usuarios, nombre_usuario, nombre_completo, email, rol, nivel_ajedrez, autorizado FROM usuarios";
    if (!empty($search_term)) {
        $sql .= " WHERE nombre_completo LIKE ? OR nombre_usuario LIKE ? OR email LIKE ?";
    }
    $sql .= " ORDER BY nombre_completo ASC LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $limit_params = $params;
    $limit_types = $types;
    $limit_params[] = $usuarios_por_pagina;
    $limit_params[] = $offset;
    $limit_types .= 'ii';

    $stmt->bind_param($limit_types, ...$limit_params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = $row;
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

        <h1 class="mb-4">Gestionar Usuarios</h1>

        <?php if (!empty($error_sesion)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error_sesion; ?></div>
        <?php endif; ?>

        <?php if (!empty($mensaje_sesion)): ?>
            <div class="alert alert-success" role="alert"><?php echo $mensaje_sesion; ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Listado de Usuarios</h4>
            </div>
            <div class="card-body">
                <!-- Formulario de Búsqueda -->
                <form action="gestionar_usuarios.php" method="GET" class="mb-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Buscar por nombre, usuario o email..." value="<?php echo htmlspecialchars($search_term); ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Buscar</button>
                    </div>
                </form>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                <?php elseif (count($usuarios) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombre Completo</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Nivel</th>
                                    <th>Autorizado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $index => $usuario): ?>
                                    <tr>
                                        <td><?php echo $offset + $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nombre_usuario']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['rol']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nivel_ajedrez']); ?></td>
                                        <td>
                                            <?php if ($usuario['autorizado'] == 1): ?>
                                                <span class="badge bg-success">Sí</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($usuario['autorizado'] == 0): ?>
                                                <a href="autorizar.php?id=<?php echo $usuario['id_usuarios']; ?>&action=authorize" class="btn btn-sm btn-success" title="Autorizar"><i class="fas fa-check"></i></a>
                                            <?php else: ?>
                                                <a href="autorizar.php?id=<?php echo $usuario['id_usuarios']; ?>&action=unauthorize" class="btn btn-sm btn-warning" title="Desautorizar"><i class="fas fa-ban"></i></a>
                                            <?php endif; ?>
                                            <a href="editar_usuario.php?id=<?php echo $usuario['id_usuarios']; ?>" class="btn btn-sm btn-info" title="Editar"><i class="fas fa-edit"></i></a>
                                            <a href="eliminar_usuario.php?id=<?php echo $usuario['id_usuarios']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar a este usuario?');" title="Eliminar"><i class="fas fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_paginas > 1): ?>
                        <nav aria-label="Paginación de usuarios">
                            <ul class="pagination justify-content-center">
                                <!-- Botón Anterior -->
                                <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $pagina_actual - 1; ?>&search=<?php echo urlencode($search_term); ?>">Anterior</a>
                                </li>

                                <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                                    <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <!-- Botón Siguiente -->
                                <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $pagina_actual + 1; ?>&search=<?php echo urlencode($search_term); ?>">Siguiente</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="alert alert-info" role="alert">No se encontraron usuarios que coincidan con la búsqueda.</div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
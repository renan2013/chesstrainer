<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION["rol"] !== 'administrador') {
    header("location: ../login.php");
    exit;
}

$page_title = "Editar Usuario";
require_once 'includes/header.php';
require_once '../db_connect.php';

$id_usuario = $nombre_usuario = $nombre_completo = $email = $rol = $autorizado = $nivel_ajedrez = "";
$error = $mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_usuario = trim($_POST["id_usuarios"]);
    $nombre_usuario = trim($_POST["nombre_usuario"]);
    $nombre_completo = trim($_POST["nombre_completo"]);
    $email = trim($_POST["email"]);
    $rol = trim($_POST["rol"]);
    $autorizado = isset($_POST["autorizado"]) ? 1 : 0;
    $nivel_ajedrez = trim($_POST["nivel_ajedrez"]);

    if (empty($nombre_usuario) || empty($nombre_completo) || empty($email) || empty($rol)) {
        $error = "Por favor, completa todos los campos requeridos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "El formato del email no es válido.";
    } else {
        $sql_check_email = "SELECT id_usuarios FROM usuarios WHERE email = ? AND id_usuarios != ?";
        if ($stmt_check_email = $conn->prepare($sql_check_email)) {
            $stmt_check_email->bind_param("si", $email, $id_usuario);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();
            if ($stmt_check_email->num_rows > 0) {
                $error = "Este email ya está registrado por otro usuario.";
            }
            $stmt_check_email->close();
        }
    }

    if (empty($error)) {
        $sql_update = "UPDATE usuarios SET nombre_usuario = ?, nombre_completo = ?, email = ?, rol = ?, autorizado = ?, nivel_ajedrez = ? WHERE id_usuarios = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssssssi", $nombre_usuario, $nombre_completo, $email, $rol, $autorizado, $nivel_ajedrez, $id_usuario);
            if ($stmt_update->execute()) {
                $_SESSION['mensaje'] = "Usuario actualizado correctamente.";
                header("location: gestionar_usuarios.php");
                exit;
            } else {
                $error = "Error al actualizar el usuario: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
} else {
    if (isset($_GET['id'])) {
        $id_usuario = $_GET['id'];
        $sql_select = "SELECT id_usuarios, nombre_usuario, nombre_completo, email, rol, autorizado, nivel_ajedrez FROM usuarios WHERE id_usuarios = ?";
        if ($stmt_select = $conn->prepare($sql_select)) {
            $stmt_select->bind_param("i", $id_usuario);
            $stmt_select->execute();
            $result = $stmt_select->get_result();
            if ($result->num_rows == 1) {
                $usuario = $result->fetch_assoc();
                $id_usuario = $usuario['id_usuarios'];
                $nombre_usuario = $usuario['nombre_usuario'];
                $nombre_completo = $usuario['nombre_completo'];
                $email = $usuario['email'];
                $rol = $usuario['rol'];
                $autorizado = $usuario['autorizado'];
                $nivel_ajedrez = $usuario['nivel_ajedrez'];
            } else {
                $error = "Usuario no encontrado.";
            }
            $stmt_select->close();
        }
    }
}

$conn->close();
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">

        <h1 class="mb-4">Editar Usuario</h1>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="editar_usuario.php?id=<?php echo htmlspecialchars($id_usuario); ?>" method="post">
            <input type="hidden" name="id_usuarios" value="<?php echo htmlspecialchars($id_usuario); ?>">
            
            <div class="mb-3">
                <label for="nombre_completo" class="form-label">Nombre Completo</label>
                <input type="text" name="nombre_completo" id="nombre_completo" class="form-control" value="<?php echo htmlspecialchars($nombre_completo); ?>" required>
            </div>
            <div class="mb-3">
                <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
                <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" value="<?php echo htmlspecialchars($nombre_usuario); ?>" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="mb-3">
                <label for="rol" class="form-label">Rol</label>
                <select name="rol" id="rol" class="form-select" required>
                    <option value="usuario" <?php echo ($rol === 'usuario') ? 'selected' : ''; ?>>Usuario</option>
                    <option value="instructor" <?php echo ($rol === 'instructor') ? 'selected' : ''; ?>>Instructor</option>
                    <option value="creador_contenido" <?php echo ($rol === 'creador_contenido') ? 'selected' : ''; ?>>Creador de Contenido</option>
                    <option value="administrador" <?php echo ($rol === 'administrador') ? 'selected' : ''; ?>>Administrador</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="nivel_ajedrez" class="form-label">Nivel de Ajedrez</label>
                <select name="nivel_ajedrez" id="nivel_ajedrez" class="form-select" required>
                    <option value="Principiante" <?php echo ($nivel_ajedrez === 'Principiante') ? 'selected' : ''; ?>>Principiante</option>
                    <option value="Intermedio" <?php echo ($nivel_ajedrez === 'Intermedio') ? 'selected' : ''; ?>>Intermedio</option>
                    <option value="Avanzado" <?php echo ($nivel_ajedrez === 'Avanzado') ? 'selected' : ''; ?>>Avanzado</option>
                </select>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="autorizado" id="autorizado" class="form-check-input" <?php echo ($autorizado == 1) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="autorizado">Autorizado para acceder</label>
            </div>
            <div class="d-grid gap-2 d-md-flex justify-content-md-start mt-4">
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                <a href="gestionar_usuarios.php" class="btn btn-secondary">Cancelar</a>
            </div>
        </form>

    </main>
</div>

<?php require_once 'includes/footer.php'; ?>

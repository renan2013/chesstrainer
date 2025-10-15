<?php
$page_title = "Restablecer Contraseña";
require_once 'includes/header.php';
require_once 'db_connect.php';

$error = '';
$mensaje = '';
$token_valido = false;
$id_usuario = null;
$token_from_url = $_GET['token'] ?? '';

if (empty($token_from_url)) {
    $error = "No se proporcionó un token de recuperación.";
} else {
    $token_hash = hash('sha256', $token_from_url);

    $sql = "SELECT id_usuarios, reset_token_expires_at FROM usuarios WHERE reset_token_hash = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id_usuario, $expires_at);
            $stmt->fetch();

            if (strtotime($expires_at) > time()) {
                $token_valido = true;
            } else {
                $error = "El enlace de recuperación ha expirado. Por favor, solicita uno nuevo.";
            }
        } else {
            $error = "El enlace de recuperación no es válido o ya fue utilizado.";
        }
        $stmt->close();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Re-validar el token y el id de usuario antes de cambiar la contraseña
    $token_post = $_POST['token'] ?? '';
    $id_usuario_post = $_POST['id_usuario'] ?? '';

    if ($token_valido && $token_from_url === $token_post && $id_usuario == $id_usuario_post) {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if (empty($password) || empty($confirm_password)) {
            $error = "Ambos campos de contraseña son obligatorios.";
        } elseif (strlen($password) < 8) {
            $error = "La contraseña debe tener al menos 8 caracteres.";
        } elseif ($password !== $confirm_password) {
            $error = "Las contraseñas no coinciden.";
        } else {
            $new_password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sql_update = "UPDATE usuarios SET password = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id_usuarios = ?";
            if ($stmt_update = $conn->prepare($sql_update)) {
                $stmt_update->bind_param("si", $new_password_hash, $id_usuario);
                if ($stmt_update->execute()) {
                    $mensaje = "¡Tu contraseña ha sido actualizada exitosamente!";
                    $token_valido = false; // Invalidar formulario para que no se muestre de nuevo
                } else {
                    $error = "Hubo un error al actualizar tu contraseña.";
                }
                $stmt_update->close();
            }
        }
    } else {
        $error = "La solicitud no es válida o ha expirado. Por favor, intenta de nuevo.";
        $token_valido = false;
    }
}

$conn->close();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">Restablecer Contraseña</h3>

                <?php if (!empty($mensaje)): ?>
                    <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-primary">Ir a Iniciar Sesión</a>
                    </div>
                <?php elseif (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                     <div class="text-center mt-3">
                        <a href="solicitar_recuperacion.php">Solicitar un nuevo enlace</a>
                    </div>
                <?php endif; ?>

                <?php if ($token_valido && empty($mensaje)): ?>
                    <form action="<?php echo htmlspecialchars($_SERVER["REQUEST_URI"]); ?>" method="post">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_from_url); ?>">
                        <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($id_usuario); ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva Contraseña (mín. 8 caracteres)</label>
                            <input type="password" name="password" id="password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

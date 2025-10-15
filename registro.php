<?php
$page_title = "Registro de Usuario";
require_once 'includes/header.php';

// Inicializar variables para mensajes
$mensaje = '';
$error = '';

// Verificar si el formulario ha sido enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar que los campos no estén vacíos
    if (empty(trim($_POST["nombre_usuario"])) || empty(trim($_POST["email"])) || empty(trim($_POST["password"]))) {
        $error = "Por favor, completa todos los campos.";
    } else {
        // Recoger y limpiar los datos del formulario
        $nombre_usuario = trim($_POST["nombre_usuario"]);
        $nombre_completo = trim($_POST["nombre_completo"]); // Nuevo campo
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);

        // Validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "El formato del email no es válido.";
        } else {
            // Preparar la consulta para verificar si el email ya existe
            $sql = "SELECT id_usuarios FROM usuarios WHERE email = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_email);
                $param_email = $email;
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {
                        $error = "Este email ya está registrado.";
                    } else {
                        // El email no existe, proceder con el registro
                        $sql_insert = "INSERT INTO usuarios (nombre_usuario, nombre_completo, email, password, rol, nivel_ajedrez) VALUES (?, ?, ?, ?, ?, ?)";
                        
                        if ($stmt_insert = $conn->prepare($sql_insert)) {
                            // Hashear la contraseña por seguridad
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $default_rol = 'usuario';
                            $default_nivel = 'Principiante';
                            
                            $stmt_insert->bind_param("ssssss", $nombre_usuario, $nombre_completo, $email, $hashed_password, $default_rol, $default_nivel);
                            
                            if ($stmt_insert->execute()) {
                                $mensaje = "¡Registro exitoso!, solo le falta la autorización del administrador de Chess Trainer para poder acceder a su entrenamiento.";
                            } else {
                                $error = "Algo salió mal. Por favor, inténtalo de nuevo más tarde.";
                            }
                            $stmt_insert->close();
                        }
                    }
                } else {
                    $error = "Error al ejecutar la consulta.";
                }
                $stmt->close();
            }
        }
    }
    $conn->close();
}
?>

<h2 class="text-center mb-4">Crear cuenta en Chess Trainer</h2>

<?php if(!empty($error)): ?>
    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
<?php endif; ?>

<?php if(!empty($mensaje)): ?>
    <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
<?php else: ?>
    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-3">
            <label for="nombre_completo" class="form-label">Nombre Completo</label>
            <input type="text" name="nombre_completo" id="nombre_completo" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="nombre_usuario" class="form-label">Nombre de Usuario</label>
            <input type="text" name="nombre_usuario" id="nombre_usuario" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña para Chess Trainer</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <br/>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Registrarse</button>
        </div>
    </form>
<?php endif; ?>

<div class="text-center mt-3">
    <p>¿Ya tienes una cuenta? <a href="login.php">Inicia sesión aquí</a></p>
    <p><a href="solicitar_recuperacion.php">¿Olvidaste tu contraseña?</a></p>
</div>

<?php require_once 'includes/footer.php'; ?>
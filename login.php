<?php
$page_title = "Iniciar Sesión";
require_once 'includes/header.php';

// Si el usuario ya ha iniciado sesión, redirigirlo a la página principal
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("location: index.php");
    exit;
}

// Inicializar variables
$email = $password = "";
$error = "";

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Validar que los campos no estén vacíos
    if (empty(trim($_POST["email"])) || empty(trim($_POST["password"]))) {
        $error = "Por favor, ingresa tu email y contraseña.";
    } else {
        $email = trim($_POST["email"]);
        $password = trim($_POST["password"]);
    }

    // Si no hay errores de validación, proceder a verificar las credenciales
    if (empty($error)) {
            $sql = "SELECT id_usuarios, nombre_usuario, nombre_completo, email, password, autorizado, rol, nivel_ajedrez, rating FROM usuarios WHERE email = ?";
            
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("s", $param_email);
                $param_email = $email;
                
                if ($stmt->execute()) {
                    $stmt->store_result();
                    
                    if ($stmt->num_rows == 1) {
                        $stmt->bind_result($id_usuarios, $nombre_usuario, $nombre_completo, $email_db, $hashed_password, $autorizado, $rol, $nivel_ajedrez, $rating);
                        if ($stmt->fetch()) {
                            if ($autorizado == 1) {
                                if (password_verify($password, $hashed_password)) {
                                    session_start();
                                    
                                    $_SESSION["loggedin"] = true;
                                    $_SESSION["id_usuarios"] = $id_usuarios;
                                    $_SESSION["nombre_usuario"] = $nombre_usuario;
                                    $_SESSION["nombre_completo"] = $nombre_completo; // Guardar el nombre completo en la sesión
                                    $_SESSION["rol"] = $rol; // Guardar el rol en la sesión
                                    $_SESSION["nivel_ajedrez"] = $nivel_ajedrez; // Guardar el nivel de ajedrez en la sesión
                                    $_SESSION["rating"] = $rating; // Guardar el rating en la sesión

                                    
                                    // Set session variable to show ranking modal on next page load
                                    $_SESSION['show_ranking_modal'] = true;

                                    // Redirigir según el rol
                                    if ($rol === 'administrador' || $rol === 'instructor' || $rol === 'creador_contenido') {
                                        header("location: admin/index.php");
                                    } else {
                                        header("location: index.php");
                                    }
                                    exit;
                                } else {
                                    $error = "La contraseña que ingresaste no es correcta.";
                                }
                            } else {
                                $error = "Tu cuenta aún no ha sido autorizada por un administrador.";
                            }
                        }
                    } else {
                        $error = "No se encontró ninguna cuenta con ese email.";
                    }
                } else {
                    $error = "Algo salió mal. Por favor, inténtalo de nuevo más tarde.";
                }
                $stmt->close();
            }
        }
    $conn->close();
}
?>

<div class="container">
    <div class="text-center mb-4">
        <img src="img/logo_ct.svg" alt="Chess Trainer Logo" style="width: 225px; height: auto;">
    </div>
    <h3 class="text-center mb-4">Inicia tu entrenamiento</h3>
       
    <?php if(!empty($error)): ?>
        <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" name="email" id="email" class="form-control" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">Contraseña para Chess Trainer</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-success">Entrar</button>
        </div>
    </form>

    <div class="text-center mt-3">
        <p>¿No tienes una cuenta? <a href="registro.php">Regístrate aquí</a></p>
        <p><a href="solicitar_recuperacion.php">¿Olvidaste tu contraseña?</a></p>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<audio id="transform-sound" src="mp3/mario.mp3" preload="auto"></audio>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('click', function() {
        document.getElementById('transform-sound').play();
    }, { once: true });
});
</script>
</body>
</html>
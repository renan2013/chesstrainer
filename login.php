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

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <div class="logo-wrapper-20 mb-2">
                <img src="img/logo_ct.svg" alt="Chess Trainer Logo" class="login-logo-img">
                <span class="badge-20 badge-20-lg">2.0</span>
            </div>
            <h3 class="login-title">Inicia tu entrenamiento</h3>
            <p class="login-subtitle">Accede a tus ejercicios y mejora tu nivel de ajedrez</p>
        </div>
       
        <?php if(!empty($error)): ?>
            <div class="login-alert" role="alert">
                <i class="fa-solid fa-circle-exclamation fs-5"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="login-form">
            <div class="mb-3">
                <label for="email" class="form-label"><i class="fa-regular fa-envelope me-1"></i> Email</label>
                <div class="input-icon-group">
                    <input type="email" name="email" id="email" class="form-control" placeholder="tu_email@ejemplo.com" value="<?php echo htmlspecialchars($email); ?>" required autofocus>
                    <i class="fa-solid fa-at input-icon-left"></i>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label"><i class="fa-solid fa-lock me-1"></i> Contraseña</label>
                <div class="input-icon-group">
                    <input type="password" name="password" id="password" class="form-control has-toggle" placeholder="••••••••" required>
                    <i class="fa-solid fa-key input-icon-left"></i>
                    <button type="button" class="password-toggle-btn" id="togglePasswordBtn" title="Mostrar/Ocultar contraseña">
                        <i class="fa-regular fa-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-login">
                    <i class="fa-solid fa-right-to-bracket me-2"></i>Entrar
                </button>
            </div>
        </form>

        <div class="login-divider"></div>

        <div class="login-footer-links">
            <p>¿No tienes una cuenta? <a href="registro.php" class="login-link">Regístrate aquí</a></p>
            <p><a href="solicitar_recuperacion.php" class="login-link-muted"><i class="fa-solid fa-unlock-keyhole me-1"></i>¿Olvidaste tu contraseña?</a></p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<audio id="transform-sound" src="mp3/mario.mp3" preload="auto"></audio>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reproducir sonido al primer clic en la página
    document.body.addEventListener('click', function() {
        const sound = document.getElementById('transform-sound');
        if (sound) sound.play().catch(function() {});
    }, { once: true });

    // Alternar visibilidad de la contraseña
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const passwordInput = document.getElementById('password');
    const toggleIcon = document.getElementById('togglePasswordIcon');

    if (toggleBtn && passwordInput && toggleIcon) {
        toggleBtn.addEventListener('click', function() {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            toggleIcon.classList.toggle('fa-eye', !isPassword);
            toggleIcon.classList.toggle('fa-eye-slash', isPassword);
        });
    }
});
</script>
</body>
</html>
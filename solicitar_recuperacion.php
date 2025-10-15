<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'libreria_ajedrez/PHPMailer/Exception.php';
require 'libreria_ajedrez/PHPMailer/PHPMailer.php';
require 'libreria_ajedrez/PHPMailer/SMTP.php';

$page_title = "Recuperar Contraseña";
require_once 'includes/header.php';

$mensaje = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty(trim($_POST["email"]))) {
        $error = "Por favor, ingresa tu dirección de correo electrónico.";
    } else {
        $email = trim($_POST["email"]);
    }

    if (empty($error)) {
        require 'db_connect.php';

        $sql = "SELECT id_usuarios FROM usuarios WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                $stmt->bind_result($id_usuario);
                $stmt->fetch();

                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date("Y-m-d H:i:s", strtotime('+1 hour'));

                $sql_update = "UPDATE usuarios SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id_usuarios = ?";
                if ($stmt_update = $conn->prepare($sql_update)) {
                    $stmt_update->bind_param("ssi", $token_hash, $expires_at, $id_usuario);
                    $stmt_update->execute();

                    // Cargar configuración SMTP
                    $smtp_config = require 'includes/smtp_config.php';
                    $mail = new PHPMailer(true);

                    try {
                        // Configuración del servidor
                        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Descomenta esta línea para ver el log detallado del servidor
                        $mail->isSMTP();
                        $mail->Host       = $smtp_config['host'];
                        $mail->SMTPAuth   = true;
                        $mail->Username   = $smtp_config['username'];
                        $mail->Password   = $smtp_config['password'];
                        $mail->SMTPSecure = $smtp_config['encryption'];
                        $mail->Port       = $smtp_config['port'];

                        // Remitente y Destinatario
                        $mail->setFrom($smtp_config['username'], 'Chess Trainer Support');
                        $mail->addAddress($email);

                        // Contenido del correo
                        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];
                        $path = rtrim(dirname($_SERVER['PHP_SELF']), '\\/');
                        $reset_link = "{$protocol}://{$host}{$path}/resetear_password.php?token={$token}";

                        $mail->isHTML(true);
                        $mail->Subject = 'Recuperacion de Contrasena - Chess Trainer';
                        $mail->Body    = "<p>Hola,</p><p>Has solicitado restablecer tu contraseña. Haz clic en el siguiente enlace para continuar:</p>" .
                                       "<a href='{$reset_link}'>{$reset_link}</a>" .
                                       "<p>Este enlace expirará en 1 hora.</p><p>Si no solicitaste esto, puedes ignorar este correo.</p>";
                        $mail->AltBody = "Para restablecer tu contraseña, copia y pega el siguiente enlace en tu navegador: {$reset_link}";

                        $mail->send();
                        $mensaje = "Si tu correo electrónico está en nuestro sistema, recibirás un enlace para restablecer tu contraseña en breve.";

                    } catch (Exception $e) {
                        // Error detallado para depuración. NUNCA mostrar esto en producción.
                        $error = "El mensaje no pudo ser enviado. Error de PHPMailer: {" . $mail->ErrorInfo . "}";
                    }
                }
            } else {
                 $mensaje = "Si tu correo electrónico está en nuestro sistema, recibirás un enlace para restablecer tu contraseña en breve.";
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h3 class="card-title text-center mb-4">Recuperar Contraseña</h3>
                <p class="text-center text-muted">Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.</p>

                <?php if(!empty($mensaje)): ?>
                    <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
                <?php endif; ?>
                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if(empty($mensaje)): // Ocultar formulario tras mostrar mensaje de éxito ?>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Enviar Enlace de Recuperación</button>
                    </div>
                </form>
                <?php endif; ?>

                <div class="text-center mt-3">
                    <a href="login.php">Volver a Iniciar Sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
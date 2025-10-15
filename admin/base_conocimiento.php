<?php
$page_title = "Base de Conocimiento";
require_once 'includes/header.php';

// Verificar si el usuario es administrador
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
    header("Location: index.php"); // Redirigir si no tiene permiso
    exit;
}

// Manejo de mensajes de sesión
$mensaje = '';
$error = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Guardar nueva entrada
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['guardar_entrada'])) {
    $titulo = $conn->real_escape_string($_POST['titulo']);
    $descripcion = $conn->real_escape_string($_POST['descripcion']);
    $id_usuario = $_SESSION['id_usuarios'];

    $sql = "INSERT INTO base_conocimiento (titulo, descripcion, id_usuario) VALUES (?, ?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssi", $titulo, $descripcion, $id_usuario);
        if ($stmt->execute()) {
            $_SESSION['mensaje'] = "Entrada guardada con éxito.";
        } else {
            if ($conn->errno == 1146) {
                $_SESSION['error'] = "Error: La tabla 'base_conocimiento' no existe. Por favor, créala en la base de datos.";
            } else {
                $_SESSION['error'] = "Error al guardar la entrada: " . $stmt->error;
            }
        }
        $stmt->close();
    }
    header("Location: base_conocimiento.php");
    exit;
}

// Obtener todas las entradas
$entradas = [];
$sql_select = "SELECT bc.id, bc.titulo, bc.descripcion, bc.fecha_creacion, u.nombre_usuario, bc.id_usuario 
               FROM base_conocimiento bc
               LEFT JOIN usuarios u ON bc.id_usuario = u.id_usuarios
               ORDER BY bc.fecha_creacion DESC";
$result = $conn->query($sql_select);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $entradas[] = $row;
    }
}
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">
        <h1 class="mb-4">Base de Conocimiento</h1>
        <p>Aquí puedes registrar y consultar descubrimientos y notas importantes del sistema.</p>

        <?php if(!empty($mensaje)): ?>
            <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
        <?php endif; ?>
        <?php if(!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Formulario para nueva entrada -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>Añadir Nueva Entrada</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="base_conocimiento.php">
                    <div class="form-group mb-3">
                        <label for="titulo" class="form-label">Título</label>
                        <input type="text" class="form-control" id="titulo" name="titulo" required>
                    </div>
                    <div class="form-group mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="5" required></textarea>
                    </div>
                    <button type="submit" name="guardar_entrada" class="btn btn-primary">Guardar Entrada</button>
                </form>
            </div>
        </div>

        <!-- Lista de entradas existentes -->
        <div class="card">
            <div class="card-header">
                <h3>Entradas Registradas</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($entradas)): ?>
                    <div class="accordion" id="accordionConocimiento">
                        <?php foreach($entradas as $index => $row): ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="heading<?php echo $row['id']; ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $row['id']; ?>" aria-expanded="false" aria-controls="collapse<?php echo $row['id']; ?>">
                                        <strong><?php echo htmlspecialchars($row['titulo']); ?></strong>&nbsp;-&nbsp;<small>por <?php echo htmlspecialchars($row['nombre_usuario'] ?? 'Usuario desconocido'); ?> el <?php echo date("d/m/Y H:i", strtotime($row['fecha_creacion'])); ?></small>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $row['id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $row['id']; ?>" data-bs-parent="#accordionConocimiento">
                                    <div class="accordion-body">
                                        <p><?php echo nl2br(htmlspecialchars($row['descripcion'])); ?></p>
                                        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador'): ?>
                                            <hr>
                                            <a href="eliminar_entrada_conocimiento.php?id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres eliminar esta entrada?');">Eliminar</a>
                                        <?php endif; ?>

                                        <?php if (isset($_SESSION['id_usuarios']) && ($row['id_usuario'] == $_SESSION['id_usuarios'] || $_SESSION['rol'] === 'administrador')): ?>
                                            <a href="editar_entrada_conocimiento.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm">Editar</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php if ($conn->errno != 0 && $conn->errno != 1146): ?>
                        <p class="text-danger">Error al consultar la base de datos: <?php echo $conn->error; ?></p>
                    <?php elseif ($conn->errno == 1146): ?>
                        <p>La tabla 'base_conocimiento' parece no existir. Por favor, créala para empezar a usar esta función.</p>
                    <?php else: ?>
                        <p>No hay entradas en la base de conocimiento todavía.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<?php require_once 'includes/footer.php'; ?>
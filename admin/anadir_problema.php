<?php
ob_start();
session_start();
require '../db_connect.php';

// Verificar si el usuario tiene el rol adecuado
if (!isset($_SESSION['id_usuarios']) || !in_array($_SESSION['rol'], ['administrador', 'creador_contenido'])) {
    header("location: index.php");
    exit;
}

$mensaje = '';
$error = '';

// Obtener todas las categorías para el selector
$categorias = [];
$sql_categorias = "SELECT id_categorias, nombre_categoria FROM categorias ORDER BY nombre_categoria ASC";
$result_categorias = $conn->query($sql_categorias);
if ($result_categorias && $result_categorias->num_rows > 0) {
    while($row = $result_categorias->fetch_assoc()) {
        $categorias[] = $row;
    }
}

// Procesar el formulario para añadir nuevo problema
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_problema"])) {
    $id_categoria = $_POST["id_categoria"];
    $fen = trim($_POST["fen"]);
    $solucion = trim($_POST["solucion"]);
    $dificultad = $_POST["dificultad"];
    $puntos = $_POST["puntos"];
    $movimientos_alternativos = trim($_POST["movimientos_alternativos"]);

    if (empty($id_categoria) || empty($fen) || empty($solucion)) {
        $error = "La categoría, FEN y solución no pueden estar vacíos.";
    } else {
        $sql = "INSERT INTO problemas (id_categoria, fen, solucion, dificultad, puntos, movimientos_alternativos) VALUES (?, ?, ?, ?, ?, ?)";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("issiis", $id_categoria, $fen, $solucion, $dificultad, $puntos, $movimientos_alternativos);
            if ($stmt->execute()) {
                header("Location: anadir_problema.php?success=1");
                exit;
            } else {
                $error = "Error al añadir el problema: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Obtener todos los problemas para mostrarlos
$problemas = [];
$sql_problemas = "SELECT p.id_problema, c.nombre_categoria, p.fen, p.solucion, p.dificultad, p.puntos, p.movimientos_alternativos 
                  FROM problemas p JOIN categorias c ON p.id_categoria = c.id_categorias 
                  ORDER BY c.nombre_categoria ASC, p.id_problema DESC";
$result_problemas = $conn->query($sql_problemas);

if ($result_problemas && $result_problemas->num_rows > 0) {
    while($row = $result_problemas->fetch_assoc()) {
        $problemas[] = $row;
    }
}

$conn->close();
?>

<?php include 'includes/header.php'; ?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">

        <h3 class="mb-4">Añadir y Gestionar Problemas</h3>

        <?php if(!empty($mensaje)): ?>
            <div class="alert alert-success" role="alert"><?php echo $mensaje; ?></div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <h4 class="mb-3">Añadir Nuevo Problema</h4>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="mb-3">
                <label for="id_categoria" class="form-label">Categoría</label>
                <select name="id_categoria" id="id_categoria" class="form-select" required>
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?php echo $cat['id_categorias']; ?>"><?php echo htmlspecialchars($cat['nombre_categoria']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="fen" class="form-label">FEN (Notación Forsyth-Edwards)</label>
                <input type="text" name="fen" id="fen" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="solucion" class="form-label">Solución (Movimiento en notación algebraica)</label>
                <input type="text" name="solucion" id="solucion" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="movimientos_alternativos" class="form-label">Movimientos Alternativos (separados por |)</label>
                <input type="text" name="movimientos_alternativos" id="movimientos_alternativos" class="form-control" placeholder="Ej: Cgxf6+|Cexf6+">
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="dificultad" class="form-label">Dificultad (1-5)</label>
                    <input type="number" name="dificultad" id="dificultad" class="form-control" min="1" max="5" value="3" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="puntos" class="form-label">Puntos</label>
                    <input type="number" name="puntos" id="puntos" class="form-control" min="1" value="100" required>
                </div>
            </div>
            <button type="submit" name="add_problema" class="btn btn-primary">Añadir Problema</button>
        </form>

        <div class="problemas-list mt-5">
            <h4 class="mb-3">Problemas Existentes</h4>
            <?php if (empty($problemas)): ?>
                <p class="text-muted">No hay problemas registrados aún.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Categoría</th>
                                <th>FEN</th>
                                <th>Solución</th>
                                <th>Dificultad</th>
                                <th>Puntos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($problemas as $problema): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($problema['id_problema']); ?></td>
                                    <td><?php echo htmlspecialchars($problema['nombre_categoria']); ?></td>
                                    <td><?php echo htmlspecialchars($problema['fen']); ?></td>
                                    <td><?php echo htmlspecialchars($problema['solucion']); ?></td>
                                    <td><?php echo htmlspecialchars($problema['dificultad']); ?></td>
                                    <td><?php echo htmlspecialchars($problema['puntos']); ?></td>
                                    <td>
                                        <a href="editar_problema.php?id=<?php echo $problema['id_problema']; ?>" class="btn btn-warning btn-sm me-1">Editar</a>
                                        <a href="eliminar_problema.php?id=<?php echo $problema['id_problema']; ?>" class="btn btn-danger btn-sm" onclick="event.preventDefault(); confirmDelete(this.href);">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
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

<?php require_once 'includes/footer.php'; ?>
<?php ob_end_flush(); ?>
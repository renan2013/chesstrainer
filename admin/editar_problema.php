<?php
session_start();
require_once '../db_connect.php';

// Fetch id_problema early, as it's needed for POST handling and later display
$id_problema = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize variables to prevent undefined variable warnings
$fen = '';
$solucion = '';
$dificultad = '';
$juega = '';
$tipo_problema = '';
$desarrollo = '';
$id_categorias = '';
$modo = '';
$variante_nombre = null;
$orden = 0;
$pgn = '';
$error = '';
$problema = []; // Will hold problem data for display

// --- START: Move POST handling logic here to ensure headers are not sent --- 
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fen = trim($_POST["fen"]);
    $solucion = trim($_POST["solucion"]);
    $dificultad = $_POST["dificultad"];
    $juega = $_POST["juega"];

    // Traducir 'w' y 'b' a los valores esperados por la base de datos
    if ($juega === 'w') {
        $juega = 'blancas';
    } elseif ($juega === 'b') {
        $juega = 'negras';
    }
    $tipo_problema = $_POST["tipo_problema"];
    $desarrollo = trim($_POST["desarrollo"]);
    $id_categorias = $_POST["id_categorias"];
    $modo = $_POST["modo"];
    $variante_nombre = $_POST["variante_nombre"] ?? null;
    $orden = $_POST["orden"] ?? 0;
    $pgn = trim($_POST["pgn"]);

    // Si se proporciona un PGN, intenta extraer el FEN de él.
    if (!empty($pgn)) {
        preg_match('/\[FEN "([^"]+)"\]/', $pgn, $matches);
        if (isset($matches[1])) {
            $fen = $matches[1];
        }
    }

    if (empty($fen) || (empty($solucion) && empty($pgn)) || empty($id_categorias) || empty($modo)) {
        $error = "Los campos FEN, Solución, Categoría y Modo son obligatorios.";
    } else {
        $sql_update = "UPDATE problemas SET fen = ?, solucion = ?, dificultad = ?, juega = ?, tipo_problema = ?, desarrollo = ?, id_categorias = ?, modo = ?, variante_nombre = ?, orden = ?, pgn = ? WHERE id_problemas = ?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssssssissisi", $fen, $solucion, $dificultad, $juega, $tipo_problema, $desarrollo, $id_categorias, $modo, $variante_nombre, $orden, $pgn, $id_problema);
            if ($stmt_update->execute()) {
                $_SESSION['mensaje'] = "Problema actualizado correctamente.";
                header("location: anadir_problema.php");
                exit;
            } else {
                $error = "Error al actualizar el problema: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
    // If there was an error, we need to re-populate $problema for the form to display current values
    if (!empty($error)) {
        $problema = $_POST;
        $problema['id_problemas'] = $id_problema;
    }
}
// --- END: Move POST handling logic here --- 

// If no problem ID or problem not found, redirect or show error
if ($id_problema == 0) {
    $_SESSION['error'] = "ID de problema no proporcionado.";
    header("location: anadir_problema.php");
    exit;
}

// Fetch problem data for display (or re-populate if POST failed)
if (empty($problema)) { // Only fetch if POST didn't happen or had no error
    $sql_select = "SELECT p.fen, p.solucion, p.dificultad, p.juega, p.tipo_problema, p.desarrollo, p.id_categorias, p.modo, p.variante_nombre, p.orden, p.pgn, c.id_publicacion FROM problemas p JOIN categorias c ON p.id_categorias = c.id_categorias WHERE p.id_problemas = ?";
    if ($stmt_select = $conn->prepare($sql_select)) {
        $stmt_select->bind_param("i", $id_problema);
        $stmt_select->execute();
        $result_select = $stmt_select->get_result();
        if ($result_select->num_rows == 1) {
            $problema = $result_select->fetch_assoc();
            // Translate 'blancas'/'negras' back to 'w'/'b' for form display
            if ($problema['juega'] === 'blancas') {
                $problema['juega'] = 'w';
            } elseif ($problema['juega'] === 'negras') {
                $problema['juega'] = 'b';
            }
        } else {
            $_SESSION['error'] = "Problema no encontrado.";
            header("location: anadir_problema.php");
            exit;
        }
        $stmt_select->close();
    } else {
        $_SESSION['error'] = "Error al preparar la consulta de selección: " . $conn->error;
        header("location: anadir_problema.php");
        exit;
    }
}

// Security check: Only allow admins/instructors/content creators to edit
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
    $_SESSION['error'] = "No tienes permiso para editar problemas.";
    header("location: ../login.php"); // Redirect to login if not authorized
    exit;
}

// Fetch all publications and categories for dropdowns
$publicaciones = [];
$categorias_por_publicacion = [];
$sql_publicaciones = "SELECT id_publicacion, titulo FROM publicacion ORDER BY titulo";
$result_publicaciones = $conn->query($sql_publicaciones);
if ($result_publicaciones->num_rows > 0) {
    while($row = $result_publicaciones->fetch_assoc()) {
        $publicaciones[] = $row;
        $sql_categorias = "SELECT id_categorias, nombre_categoria FROM categorias WHERE id_publicacion = ? ORDER BY nombre_categoria";
        if ($stmt_cat = $conn->prepare($sql_categorias)) {
            $stmt_cat->bind_param("i", $row['id_publicacion']);
            $stmt_cat->execute();
            $result_cat = $stmt_cat->get_result();
            while($cat_row = $result_cat->fetch_assoc()) {
                $categorias_por_publicacion[$row['id_publicacion']][] = $cat_row;
            }
            $stmt_cat->close();
        }
    }
}

$page_title = "Editar Problema";
require_once 'includes/header.php';
?>

    <main class="main-content">

        <h2>Editar Problema #<?php echo htmlspecialchars($id_problema); ?></h2>
        <link rel="stylesheet" href="../css/chessboard-1.0.0.min.css">
        <style>
            #board {
                width: 100%; /* Make it responsive within its container */
                max-width: 100%; /* Ensure it never exceeds its container's width */
                margin: 0 auto; /* Center it within its column */
            }
            .spare-pieces-7492f { /* Assuming this is the correct class for the spare pieces container */
                display: flex;
                flex-wrap: nowrap; /* Prevent wrapping */
                overflow-x: auto; /* Allow horizontal scrolling if needed */
                justify-content: center; /* Center the pieces */
            }
        </style>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="editar_problema.php?id=<?php echo $id_problema; ?>" method="post">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="id_publicacion" class="form-label">Publicación</label>
                    <select class="form-select" id="id_publicacion" name="id_publicacion" required>
                        <option value="">Selecciona una publicación</option>
                        <?php foreach ($publicaciones as $pub): ?>
                            <option value="<?php echo $pub['id_publicacion']; ?>" <?php echo (isset($problema['id_publicacion']) && $problema['id_publicacion'] == $pub['id_publicacion']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($pub['titulo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="id_categorias" class="form-label">Categoría</label>
                    <select class="form-select" id="id_categorias" name="id_categorias" required>
                        <option value="">Selecciona una categoría</option>
                        <?php foreach ($publicaciones as $pub): ?>
                            <?php if (isset($categorias_por_publicacion[$pub['id_publicacion']])): ?>
                                <optgroup label="<?php echo htmlspecialchars($pub['titulo']); ?>">
                                    <?php foreach ($categorias_por_publicacion[$pub['id_publicacion']] as $cat): ?>
                                        <option value="<?php echo $cat['id_categorias']; ?>" <?php echo (isset($problema['id_categorias']) && $problema['id_categorias'] == $cat['id_categorias']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nombre_categoria']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label for="fen" class="form-label">FEN</label>
                <input type="text" class="form-control" id="fen" name="fen" value="<?php echo htmlspecialchars($problema['fen'] ?? ''); ?>" required>
            </div>
            <div class="mb-3">
                <label for="solucion" class="form-label">Solución (ej: e4, d4, Cgxf6+|Cexf6+)</label>
                <input type="text" class="form-control" id="solucion" name="solucion" value="<?php echo htmlspecialchars($problema['solucion'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="pgn" class="form-label">PGN (Opcional, si se usa, el FEN se extraerá de aquí)</label>
                <textarea class="form-control" id="pgn" name="pgn" rows="3"><?php echo htmlspecialchars($problema['pgn'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="dificultad" class="form-label">Dificultad</label>
                <select class="form-select" id="dificultad" name="dificultad" required>
                    <option value="Fácil" <?php echo (isset($problema['dificultad']) && $problema['dificultad'] == 'Fácil') ? 'selected' : ''; ?>>Fácil</option>
                    <option value="Intermedio" <?php echo (isset($problema['dificultad']) && $problema['dificultad'] == 'Intermedio') ? 'selected' : ''; ?>>Intermedio</option>
                    <option value="Difícil" <?php echo (isset($problema['dificultad']) && $problema['dificultad'] == 'Difícil') ? 'selected' : ''; ?>>Difícil</option>
                    <option value="Experto" <?php echo (isset($problema['dificultad']) && $problema['dificultad'] == 'Experto') ? 'selected' : ''; ?>>Experto</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="juega" class="form-label">Juega</label>
                <select class="form-select" id="juega" name="juega" required>
                    <option value="w" <?php echo (isset($problema['juega']) && $problema['juega'] == 'w') ? 'selected' : ''; ?>>Blancas</option>
                    <option value="b" <?php echo (isset($problema['juega']) && $problema['juega'] == 'b') ? 'selected' : ''; ?>>Negras</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="tipo_problema" class="form-label">Tipo de Problema</label>
                <input type="text" class="form-control" id="tipo_problema" name="tipo_problema" value="<?php echo htmlspecialchars($problema['tipo_problema'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="desarrollo" class="form-label">Desarrollo (Comentarios/Explicación)</label>
                <textarea class="form-control" id="desarrollo" name="desarrollo" rows="5"><?php echo htmlspecialchars($problema['desarrollo'] ?? ''); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="modo" class="form-label">Modo</label>
                <select class="form-select" id="modo" name="modo" required>
                    <option value="problema" <?php echo (isset($problema['modo']) && $problema['modo'] == 'problema') ? 'selected' : ''; ?>>Problema</option>
                    <option value="estudio" <?php echo (isset($problema['modo']) && $problema['modo'] == 'estudio') ? 'selected' : ''; ?>>Estudio</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="variante_nombre" class="form-label">Nombre de la Variante (Solo para Modo Estudio)</label>
                <input type="text" class="form-control" id="variante_nombre" name="variante_nombre" value="<?php echo htmlspecialchars($problema['variante_nombre'] ?? ''); ?>">
            </div>
            <div class="mb-3">
                <label for="orden" class="form-label">Orden</label>
                <input type="number" class="form-control" id="orden" name="orden" value="<?php echo htmlspecialchars($problema['orden'] ?? ''); ?>">
            </div>
            <button type="submit" class="btn btn-primary">Actualizar Problema</button>
            <a href="anadir_problema.php" class="btn btn-secondary">Cancelar</a>
        </form>

        <h3 class="mt-5">Visualizador de Tablero</h3>
        <div id="board" style="width: 400px"></div>
        <div class="mt-3">
            <button id="startPositionBtn" class="btn btn-info">Posición Inicial</button>
            <button id="clearBoardBtn" class="btn btn-warning">Limpiar Tablero</button>
            <button id="flipBoardBtn" class="btn btn-secondary">Voltear Tablero</button>
        </div>
        <div class="mt-3">
            <p>FEN actual: <span id="currentFen"></span></p>
        </div>

    </main>

<?php require_once 'includes/footer.php'; ?>

<script src="../js/jquery-3.6.0.min.js"></script>
<script src="../js/chessboard-1.0.0.min.js"></script>
<script src="../js/chess.js"></script>
<script>
    var board = null;
    var game = new Chess();

    function onDragStart (source, piece, position, orientation) {
        // do not pick up pieces if the game is over
        if (game.game_over()) return false;

        // only pick up pieces for the side to move
        if ((game.turn() === 'w' && piece.search(/^b/) !== -1) ||
            (game.turn() === 'b' && piece.search(/^w/) !== -1)) {
            return false;
        }
    }

    function onDrop (source, target) {
        // see if the move is legal
        var move = game.move({
            from: source,
            to: target,
            promotion: 'q' // NOTE: always promote to a queen for example purposes
        });

        // illegal move
        if (move === null) return 'snapback';

        updateFen();
    }

    // update the board position after the piece snap
    // for castling, en passant, pawn promotion
    function onSnapEnd () {
        board.position(game.fen());
    }

    function updateFen() {
        var fen = game.fen();
        $('#fen').val(fen);
        $('#currentFen').text(fen);
    }

    function initBoard() {
        var config = {
            draggable: true,
            position: '<?php echo htmlspecialchars($problema['fen'] ?? 'start'); ?>',
            onDragStart: onDragStart,
            onDrop: onDrop,
            onSnapEnd: onSnapEnd
        };
        board = Chessboard('board', config);
        updateFen();
    }

    $(document).ready(function() {
        initBoard();

        $('#startPositionBtn').on('click', function() {
            game.reset();
            board.start();
            updateFen();
        });

        $('#clearBoardBtn').on('click', function() {
            game.clear();
            board.clear();
            updateFen();
        });

        $('#flipBoardBtn').on('click', function() {
            board.flip();
        });

        // Update board when FEN input changes manually
        $('#fen').on('keyup', function() {
            var newFen = $(this).val();
            if (game.validate_fen(newFen).valid) {
                game.load(newFen);
                board.position(newFen);
                $('#currentFen').text(newFen);
            } else {
                $('#currentFen').text('FEN inválido');
            }
        });

        // Handle publication/category dropdown dependency
        $('#id_publicacion').change(function() {
            var publicacionId = $(this).val();
            $('#id_categorias option').hide();
            $('#id_categorias optgroup').hide();
            if (publicacionId) {
                $('#id_categorias optgroup[label="' + $('#id_publicacion option:selected').text() + '"]').show();
                $('#id_categorias optgroup[label="' + $('#id_publicacion option:selected').text() + '"] option').show();
            }
            $('#id_categorias').val(''); // Reset category selection
        }).trigger('change'); // Trigger on load to set initial state

        // Set initial category selection if editing an existing problem
        var initialCategoryId = '<?php echo htmlspecialchars($problema['id_categorias'] ?? ''); ?>';
        if (initialCategoryId) {
            $('#id_categorias').val(initialCategoryId);
            // Also ensure the correct publication is selected and its categories are visible
            var initialPublicationId = '<?php echo htmlspecialchars($problema['id_publicacion'] ?? ''); ?>';
            if (initialPublicationId) {
                $('#id_publicacion').val(initialPublicationId).trigger('change');
            }
        }

        // Handle PGN input
        $('#pgn').on('keyup', function() {
            var pgn = $(this).val();
            if (pgn.trim() !== '') {
                try {
                    game.load_pgn(pgn);
                    board.position(game.fen());
                    updateFen();
                } catch (e) {
                    $('#currentFen').text('PGN inválido');
                }
            }
        });

    });
</script>

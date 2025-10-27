<?php
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);
$page_title = "Editar Problema";
require_once 'includes/header.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
    header("location: ../login.php");
    exit;
}

$error = '';
$mensaje = '';
$problema = null;
$id_problema = $_GET['id'] ?? null;

if (!$id_problema) {
    header("location: anadir_problema.php");
    exit;
}

$sql_problema = "SELECT * FROM problemas WHERE id_problemas = ?";
if ($stmt = $conn->prepare($sql_problema)) {
    $stmt->bind_param("i", $id_problema);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $problema = $result->fetch_assoc();
        $modo = $problema['modo'] ?? 'problema';
    } else {
        $_SESSION['error'] = "No se encontró el problema especificado.";
        header("location: anadir_problema.php");
        exit;
    }
    $stmt->close();
}

$id_publicacion_actual = null;
if (isset($problema['id_categorias'])) {
    $sql_pub_cat = "SELECT id_publicacion FROM categorias WHERE id_categorias = ?";
    if ($stmt_pub_cat = $conn->prepare($sql_pub_cat)) {
        $stmt_pub_cat->bind_param("i", $problema['id_categorias']);
        $stmt_pub_cat->execute();
        $stmt_pub_cat->bind_result($id_publicacion_actual);
        $stmt_pub_cat->fetch();
        $stmt_pub_cat->close();
    }
}

if ($_SESSION['rol'] === 'instructor' && $problema['creado_por_id_usuario'] != $_SESSION['id_usuarios']) {
    $_SESSION['error'] = "No tienes permiso para editar este problema.";
    header("location: anadir_problema.php");
    exit;
}

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
    // $modo = $_POST["modo"]; // Ya no se usa
    $variante_nombre = $_POST["variante_nombre"] ?? null;
    $orden = $_POST["orden"] ?? 0;
    $pgn = trim($_POST["pgn"]);

    // Si se proporciona un PGN, intenta extraer el FEN de él.
    if (!empty($pgn)) {
        preg_match('/\\\[FEN "([^"]+)"\\\]/', $pgn, $matches);
        if (isset($matches[1])) {
            $fen = $matches[1];
        }
    }

    if (empty($fen) || (empty($solucion) && empty($pgn)) || empty($id_categorias)) {
        $error = "Por favor, completa todos los campos obligatorios.";
    } else {
        // Heredar el modo desde la publicación padre
        $modo_heredado = '';
        $sql_get_tipo = "SELECT p.tipo FROM publicacion p JOIN categorias c ON p.id_publicacion = c.id_publicacion WHERE c.id_categorias = ?";
        if ($stmt_tipo = $conn->prepare($sql_get_tipo)) {
            $stmt_tipo->bind_param("i", $id_categorias);
            $stmt_tipo->execute();
            $result_tipo = $stmt_tipo->get_result();
            if ($result_tipo->num_rows == 1) {
                $modo_heredado = $result_tipo->fetch_assoc()['tipo'];
            }
            $stmt_tipo->close();
        }

        if (empty($modo_heredado)) {
            $error = "No se pudo determinar el modo (Estudio/Problema) desde la publicación padre.";
        } else {
            $sql_update = "UPDATE problemas SET fen = ?, solucion = ?, dificultad = ?, juega = ?, tipo_problema = ?, desarrollo = ?, id_categorias = ?, modo = ?, variante_nombre = ?, orden = ?, pgn = ? WHERE id_problemas = ?";
            if ($stmt_update = $conn->prepare($sql_update)) {
                $stmt_update->bind_param("ssssssissisi", $fen, $solucion, $dificultad, $juega, $tipo_problema, $desarrollo, $id_categorias, $modo_heredado, $variante_nombre, $orden, $pgn, $id_problema);
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
    }
    $problema = $_POST;
    $problema['id_problemas'] = $id_problema;
}

$publicaciones = [];
$categorias_por_publicacion = [];
$sql_publicaciones = "SELECT id_publicacion, titulo, tipo FROM publicacion ORDER BY titulo"; // Añadido tipo
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
?>



    <main class="main-content" style="background-color: #f0f0f0;">

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
            <div class="row mb-2">
                <div class="col-md-4">
                    <label for="id_publicacion" class="form-label">Publicación (Define el Modo)</label>
                    <select id="id_publicacion" name="id_publicacion" class="form-select" required>
                        <option value="">Selecciona una publicación</option>
                        <?php foreach ($publicaciones as $pub): ?>
                            <option value="<?php echo $pub['id_publicacion']; ?>" <?php echo ($id_publicacion_actual == $pub['id_publicacion']) ? 'selected' : ''; ?> data-tipo="<?php echo $pub['tipo']; ?>">
                                <?php echo htmlspecialchars($pub['titulo']); ?> (<?php echo htmlspecialchars($pub['tipo']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="id_categorias" class="form-label">Categoría</label>
                    <select id="id_categorias" name="id_categorias" class="form-select" required>
                        <option value="">Selecciona una categoría</option>
                    </select>
                </div>
                <div class="col-md-4" id="variante_nombre_wrapper" style="display: none;">
                    <label for="variante_nombre" class="form-label">Nombre de la Variante (para modo Estudio)</label>
                    <input type="text" name="variante_nombre" id="variante_nombre" class="form-control" value="<?php echo htmlspecialchars($problema['variante_nombre'] ?? ''); ?>">
                </div>
            </div>

          

            <div class="row align-items-start">
                <div class="col-md-4"> <!-- For the board -->
                    <div style="width: 70%; max-width: 100%; margin: 0 auto;">
                        <div id="board"></div>
                    </div>
                    <div class="text-center mt-3"> <!-- New div for buttons below board -->
                        <button type="button" id="startBtn" class="btn btn-secondary btn-sm">Posición Inicial</button>
                        <button type="button" id="clearBtn" class="btn btn-secondary btn-sm">Limpiar Tablero</button>
                        <div class="mt-2"> <!-- Add a small margin-top for separation -->
                            <button type="button" id="toggleTurnBtn" class="btn btn-info btn-sm">Cambiar Turno</button>
                        </div>
                        <div class="mt-2"> <!-- Add a small margin-top for separation -->
                            <button type="button" id="pgn-first" class="btn btn-primary btn-sm"><<</button>
                            <button type="button" id="pgn-prev" class="btn btn-primary btn-sm"><</button>
                            <button type="button" id="pgn-next" class="btn btn-primary btn-sm">></button>
                            <button type="button" id="pgn-last" class="btn btn-primary btn-sm">>></button>
                        </div>
                    </div>
                </div>


                <div id="pgn-container" class="col-md-4 mt-3" > <!-- For the PGN moves -->
                    <p>Movimientos</p>
                    <div id="pgn-moves" class="border p-3 bg-white" style="height: 360px; overflow-y: auto; border-radius: 10px;">
                        <div class="d-flex justify-content-between">
                            <p class="text-center">Blancas</p>
                            <p class="text-center">Negras</p>
                        </div>
                    </div>
                </div>



                <div class="col-md-4"> <!-- Solucion and PGN inputs -->
                 
                
                <div class="col-md-12">
                    <label for="fen" class="form-label">FEN</label>
                    <input type="text" name="fen" id="fen" class="form-control" required value="<?php echo htmlspecialchars($problema['fen'] ?? ''); ?>">
                </div>
           
                <div class="col-md-12">
                        <label for="solucion" class="form-label">Solución</label>
                        <textarea name="solucion" id="solucion" class="form-control" required><?php echo htmlspecialchars($problema['solucion'] ?? ''); ?></textarea>
                        <div id="solucion-feedback" class="form-text"></div>
                </div>

                <div class="col-md-12">
                        <label for="pgn" class="form-label">PGN</label>
                        <textarea name="pgn" id="pgn" class="form-control"><?php echo htmlspecialchars($problema['pgn'] ?? ''); ?></textarea>
                </div>

                <div class="col-md-12">
                        <label for="dificultad" class="form-label">Dificultad</label>
                        <select name="dificultad" id="dificultad" class="form-select" required>
                            <option value="">Selecciona</option>
                            <option value="Fácil" <?php echo (($problema['dificultad'] ?? '') == 'Fácil') ? 'selected' : ''; ?>>Fácil</option>
                            <option value="Intermedio" <?php echo (($problema['dificultad'] ?? '') == 'Intermedio') ? 'selected' : ''; ?>>Intermedio</option>
                            <option value="Difícil" <?php echo (($problema['dificultad'] ?? '') == 'Difícil') ? 'selected' : ''; ?>>Difícil</option>
                            <option value="Experto" <?php echo (($problema['dificultad'] ?? '') == 'Experto') ? 'selected' : ''; ?>>Experto</option>
                        </select>
                </div>

                    <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Juega (cambiar turno)</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="juega_radio" id="juega_blancas" value="w" <?php echo (in_array($problema['juega'] ?? 'w', ['blancas', 'w'])) ? 'checked' : ''; ?> disabled="true">
                                <label class="form-check-label" for="juega_blancas">Blancas</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="juega_radio" id="juega_negras" value="b" <?php echo (in_array($problema['juega'] ?? '', ['negras', 'b'])) ? 'checked' : ''; ?> disabled="true">
                                <label class="form-check-label" for="juega_negras">Negras</label>
                            </div>
                            <input type="hidden" name="juega" id="juega_hidden" value="<?php echo htmlspecialchars(substr($problema['juega'] ?? 'w', 0, 1)); ?>">
                        </div>
                    </div>

                    <div class="col-md-4" id="orden_wrapper">
                    <label for="orden" class="form-label">Orden</label>
                    <input type="number" name="orden" id="orden" class="form-control" value="<?php echo htmlspecialchars($problema['orden'] ?? '0'); ?>">
                    </div>
                    </div>




                    <div class="col-md-12">
                        <label for="tipo_problema" class="form-label">Tipo de Problema</label>
                        <select name="tipo_problema" id="tipo_problema" class="form-select" required>
                            <option value="">Selecciona</option>
                            <option value="Mate en 1" <?php echo (($problema['tipo_problema'] ?? '') == 'Mate en 1') ? 'selected' : ''; ?>>Mate en 1</option>
                            <option value="Mate en 2" <?php echo (($problema['tipo_problema'] ?? '') == 'Mate en 2') ? 'selected' : ''; ?>>Mate en 2</option>
                            <option value="Mate en 3" <?php echo (($problema['tipo_problema'] ?? '') == 'Mate en 3') ? 'selected' : ''; ?>>Mate en 3</option>
                            
                            <option value="Ganan Blancas" <?php echo (($problema['tipo_problema'] ?? '') == 'Ganan Blancas') ? 'selected' : ''; ?>>Ganan Blancas</option>
                            <option value="Ganan Negras" <?php echo (($problema['tipo_problema'] ?? '') == 'Ganan Negras') ? 'selected' : ''; ?>>Ganan Negras</option>
                            <option value="Ventaja Blanca" <?php echo (($problema['tipo_problema'] ?? '') == 'Ventaja Blanca') ? 'selected' : ''; ?>>Ventaja Blanca</option>
                            <option value="Ventaja Negra" <?php echo (($problema['tipo_problema'] ?? '') == 'Ventaja Negra') ? 'selected' : ''; ?>>Ventaja Negra</option>
                            <option value="Tablas" <?php echo (($problema['tipo_problema'] ?? '') == 'Tablas') ? 'selected' : ''; ?>>Tablas</option>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label for="desarrollo" class="form-label">Desarrollo</label>
                        <textarea name="desarrollo" id="desarrollo" class="form-control"><?php echo htmlspecialchars($problema['desarrollo'] ?? ''); ?></textarea>
                    </div>
                    <br/>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                        <a href="anadir_problema.php" class="btn btn-secondary">Cancelar</a>
                    </div>


                </div>
            </div>
        </form>
    </main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="../js/chessboard-1.0.0.min.js"></script>
<script src="../js/chess.js"></script>

<?php require_once 'includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const publicacionSelect = document.getElementById('id_publicacion');
            const categoriaSelect = document.getElementById('id_categorias');
            const varianteWrapper = document.getElementById('variante_nombre_wrapper');
            const categoriasPorPublicacion = <?php echo json_encode($categorias_por_publicacion); ?>;
            const idCategoriaActual = '<?php echo $problema['id_categorias'] ?? ''; ?>';

            const solucionTextarea = document.getElementById('solucion');
            const pgnTextarea = document.getElementById('pgn');
            const fenInput = document.getElementById('fen');
            const juegaHiddenInput = document.getElementById('juega_hidden');
            const juegaBlancasRadio = document.getElementById('juega_blancas');
            const juegaNegrasRadio = document.getElementById('juega_negras');
            const startBtn = document.getElementById('startBtn');
            const clearBtn = document.getElementById('clearBtn');
            const toggleTurnBtn = document.getElementById('toggleTurnBtn');
            const pgnMovesContainer = document.getElementById('pgn-moves');
            let board = null;
            let game = new Chess();
            let history = [];
            let currentMove = -1;

            function toggleSolucionRequired() {
                solucionTextarea.required = pgnTextarea.value.trim() === '';
            }

            function validateSolution() {
                const fen = fenInput.value;
                const solucion = solucionTextarea.value.trim();
                const feedbackDiv = document.getElementById('solucion-feedback');
                if (!solucion) {
                    feedbackDiv.textContent = '';
                    return;
                }
                const tempGame = new Chess();
                if (!tempGame.load(fen)) {
                    feedbackDiv.textContent = 'FEN inválido.';
                    feedbackDiv.style.color = 'red';
                    return;
                }
                const moves = solucion.split(' ').filter(m => m);
                let isValid = true;
                for (let move of moves) {
                    let moveResult = null;
                    if (move.includes('|')) {
                        const firstAlternative = move.replace(/[()]/g, '').split('|')[0];
                        moveResult = tempGame.move(firstAlternative, { sloppy: true });
                    } else {
                        moveResult = tempGame.move(move, { sloppy: true });
                    }

                    if (moveResult === null) {
                        isValid = false;
                        break;
                    }
                }
                if (isValid) {
                    feedbackDiv.textContent = 'Solución válida.';
                    feedbackDiv.style.color = 'green';
                } else {
                    feedbackDiv.textContent = 'Solución inválida.';
                    feedbackDiv.style.color = 'red';
                }
            }

            function updateCategorias() {
                const selectedPublicacionId = publicacionSelect.value;
                const selectedOption = publicacionSelect.options[publicacionSelect.selectedIndex];
                const tipoPublicacion = selectedOption ? selectedOption.getAttribute('data-tipo') : '';
                const isEstudio = tipoPublicacion === 'Estudio';
                varianteWrapper.style.display = isEstudio ? 'block' : 'none';
                categoriaSelect.innerHTML = '<option value="">Selecciona una categoría</option>';
                if (selectedPublicacionId && categoriasPorPublicacion[selectedPublicacionId]) {
                    categoriasPorPublicacion[selectedPublicacionId].forEach(categoria => {
                        const option = document.createElement('option');
                        option.value = categoria.id_categorias;
                        option.textContent = categoria.nombre_categoria;
                        categoriaSelect.appendChild(option);
                    });
                }
            }

            publicacionSelect.addEventListener('change', updateCategorias);

            let initialFen = fenInput.value || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
            game.load(initialFen);

            function updateJuegaFromFen(fen) {
                try {
                    const tempGame = new Chess(fen);
                    const turn = tempGame.turn();
                    juegaHiddenInput.value = turn;
                    juegaBlancasRadio.checked = turn === 'w';
                    juegaNegrasRadio.checked = turn === 'b';
                } catch (e) {
                    // handle error
                }
                validateSolution();
            }

            function onPieceDrop(source, target, piece, newPos, oldPos, orientation) {
                if (source === 'spare') {
                    if (game.put({ type: piece[1].toLowerCase(), color: piece[0] }, target)) {
                        fenInput.value = game.fen();
                        updateJuegaFromFen(game.fen());
                    } else {
                        return 'snapback';
                    }
                } else {
                    const move = game.move({ from: source, to: target, promotion: 'q' });
                    if (move === null) return 'snapback';
                    fenInput.value = game.fen();
                    updateJuegaFromFen(game.fen());
                }
            }

            function onSnapEnd() {
                board.position(game.fen());
            }

            const config = {
                draggable: true,
                dropOffBoard: 'trash',
                sparePieces: true,
                position: initialFen,
                pieceTheme: '../img/chesspieces/wikipedia/{piece}.png',
                onDrop: onPieceDrop,
                onSnapEnd: onSnapEnd
            };
            board = Chessboard('board', config);
            updateJuegaFromFen(initialFen);
            window.addEventListener('resize', board.resize);

            startBtn.addEventListener('click', () => {
                game.reset();
                board.start();
                fenInput.value = game.fen();
                updateJuegaFromFen(game.fen());
            });

            clearBtn.addEventListener('click', () => {
                const emptyFen = '8/8/8/8/8/8/8/8 w - - 0 1';
                game.load(emptyFen);
                board.position(emptyFen);
                fenInput.value = emptyFen;
                updateJuegaFromFen(emptyFen);
            });

            toggleTurnBtn.addEventListener('click', () => {
                const currentFen = game.fen();
                const parts = currentFen.split(' ');
                parts[1] = parts[1] === 'w' ? 'b' : 'w';
                const newFen = parts.join(' ');
                game.load(newFen);
                fenInput.value = newFen;
                board.position(newFen);
                updateJuegaFromFen(newFen);
            });

            fenInput.addEventListener('input', () => {
                const fen = fenInput.value;
                if (game.load(fen)) {
                    board.position(fen);
                    updateJuegaFromFen(fen);
                }
            });

            function renderPgnMoves() {
                pgnMovesContainer.innerHTML = '<div class="pgn-moves-header d-flex"><div class="w-50">Blancas</div><div class="w-50 header-black-moves">Negras</div></div>';
                history = game.history({ verbose: true });
                let movesHtml = '<div class="pgn-moves-body">';
                let moveNumber = 1;
                for (let i = 0; i < history.length; i += 2) {
                    const whiteMove = history[i];
                    const blackMove = history[i + 1];
                    movesHtml += `<div class="pgn-move-pair d-flex">`;
                    movesHtml += `<div class="pgn-move white w-50" data-move-index="${i}"><span>${moveNumber}.</span> ${whiteMove.san}</div>`;
                    if (blackMove) {
                        movesHtml += `<div class="pgn-move black w-50" data-move-index="${i + 1}">${blackMove.san}</div>`;
                    } else {
                        movesHtml += `<div class="pgn-move black w-50"></div>`;
                    }
                    movesHtml += `</div>`;
                    moveNumber++;
                }
                movesHtml += '</div>';
                pgnMovesContainer.innerHTML += movesHtml;

                document.querySelectorAll('.pgn-move').forEach(el => {
                    el.addEventListener('click', () => goToMove(parseInt(el.dataset.moveIndex)));
                });
            }

            function goToMove(index) {
                const tempGame = new Chess();
                const initialFenHeader = game.header().FEN || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
                tempGame.load(initialFenHeader);
                for (let i = 0; i <= index; i++) {
                    tempGame.move(history[i].san);
                }
                board.position(tempGame.fen());
                currentMove = index;
                updateActiveMove();
            }
            
            function updateActiveMove() {
                document.querySelectorAll('.pgn-move').forEach(el => el.classList.remove('active'));
                if (currentMove > -1) {
                    const activeEl = document.querySelector(`[data-move-index='${currentMove}']`);
                    if (activeEl) {
                        activeEl.classList.add('active');
                        activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
            }

            document.getElementById('pgn-first').addEventListener('click', () => {
                const initialFenHeader = game.header().FEN || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
                board.position(initialFenHeader);
                currentMove = -1;
                updateActiveMove();
            });

            document.getElementById('pgn-prev').addEventListener('click', () => {
                if (currentMove > -1) {
                    currentMove--;
                    goToMove(currentMove);
                }
            });

            document.getElementById('pgn-next').addEventListener('click', () => {
                if (currentMove < history.length - 1) {
                    currentMove++;
                    goToMove(currentMove);
                }
            });

            document.getElementById('pgn-last').addEventListener('click', () => {
                const tempGame = new Chess();
                const initialFenHeader = game.header().FEN || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
                tempGame.load_pgn(pgnTextarea.value.trim());
                board.position(tempGame.fen());
                currentMove = history.length - 1;
                updateActiveMove();
            });

            pgnTextarea.addEventListener('input', function() {
                const pgn = pgnTextarea.value.trim();
                if (pgn) {
                    const tempGame = new Chess();
                    if (tempGame.load_pgn(pgn)) {
                        game = tempGame;
                        const fen = game.fen();
                        fenInput.value = fen;
                        board.position(fen);
                        updateJuegaFromFen(fen);
                        renderPgnMoves();
                        currentMove = -1;
                        updateActiveMove();
                    }
                }
                toggleSolucionRequired();
            });

            // --- Initial Page Load Logic ---
            toggleSolucionRequired();
            validateSolution();

            // Populate and select categories on load
            if (publicacionSelect.value) {
                updateCategorias(); // This will also handle the variante_nombre_wrapper visibility
                setTimeout(() => {
                    if (idCategoriaActual) {
                        categoriaSelect.value = idCategoriaActual;
                    }
                }, 100); // Timeout to allow options to be rendered
            } else {
                // If no publication is selected initially, still check for variante
                updateCategorias();
            }

            // Load PGN moves on initial load
            const initialPgn = pgnTextarea.value.trim();
            if (initialPgn) {
                const tempGame = new Chess();
                if (tempGame.load_pgn(initialPgn)) {
                    game = tempGame;
                    renderPgnMoves();
                }
            }
        });
</script>

<?php ob_end_flush(); ?>
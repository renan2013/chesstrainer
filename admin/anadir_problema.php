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
        <link rel="stylesheet" href="../css/chessboard-1.0.0.min.css">
        <style>
            #board {
                margin: 0 auto; /* Center it within its column */
            }
            .spare-pieces-7492f { /* Assuming this is the correct class for the spare pieces container */
                display: flex;
                flex-wrap: nowrap; /* Prevent wrapping */
                overflow-x: auto; /* Allow horizontal scrolling if needed */
                justify-content: center; /* Center the pieces */
            }
            .form-control, .form-select {
                background-color: #ffffff;
            }
        </style>

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
                                        <a href="eliminar_problema.php?id=<?php echo $problema['id_problema']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que quieres eliminar este problema?');">Eliminar</a>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="../js/chessboard-1.0.0.min.js"></script>
<script src="../js/chess.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const publicacionSelect = document.getElementById('id_publicacion');
        const categoriaSelect = document.getElementById('id_categorias');
        const varianteWrapper = document.getElementById('variante_nombre_wrapper');
        const categoriasPorPublicacion = <?php echo json_encode($categorias_por_publicacion); ?>;
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
                    // Check for alternative moves like (move1|move2)
                    if (move.includes('|')) {
                        // Extract the first alternative to validate the sequence.
                        const firstAlternative = move.replace(/[()]/g, '').split('|')[0];
                        moveResult = tempGame.move(firstAlternative, { sloppy: true });
                    } else {
                        // Standard validation for a single move
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

        pgnTextarea.addEventListener('input', toggleSolucionRequired);
        solucionTextarea.addEventListener('input', validateSolution);
        toggleSolucionRequired();

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

        publicacionSelect.addEventListener('change', function() {
            updateCategorias();
            localStorage.setItem('lastSelectedPublicacion', this.value);
            localStorage.removeItem('lastSelectedCategoria');
        });

        categoriaSelect.addEventListener('change', function() {
            localStorage.setItem('lastSelectedCategoria', this.value);
        });

        const lastSelectedPublicacion = localStorage.getItem('lastSelectedPublicacion');
        const lastSelectedCategoria = localStorage.getItem('lastSelectedCategoria');

        if (lastSelectedPublicacion) {
            publicacionSelect.value = lastSelectedPublicacion;
            updateCategorias();
            if (lastSelectedCategoria) {
                categoriaSelect.value = lastSelectedCategoria;
            }
        }

        const clearSelectionBtn = document.createElement('button');
        clearSelectionBtn.type = 'button';
        clearSelectionBtn.className = 'btn btn-secondary btn-sm mt-2';
        clearSelectionBtn.textContent = 'Limpiar selección guardada';
        clearSelectionBtn.addEventListener('click', function() {
            localStorage.removeItem('lastSelectedPublicacion');
            localStorage.removeItem('lastSelectedCategoria');
            publicacionSelect.value = '';
            updateCategorias();
            categoriaSelect.value = '';
        });
        categoriaSelect.parentNode.appendChild(clearSelectionBtn);

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
        window.addEventListener('resize', board.resize); // Add this line for responsiveness

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
            pgnMovesContainer.innerHTML = '<div class="pgn-moves-header d-flex"><div class="w-50">Blancas</div><div class="w-50 header-black-moves">Negras</div></div>'; // Add headers
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

            // Add event listeners after rendering
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
    });
</script>

<?php require_once 'includes/footer.php'; ?>
<?php ob_end_flush(); ?>
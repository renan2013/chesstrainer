<?php
$page_title = "Entrenador de Tácticas de Ajedrez";
require_once 'includes/header.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$is_admin = isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido']);

// Fetch user's rating
$user_rating = 0;
$sql_user_rating = "SELECT rating FROM usuarios WHERE id_usuarios = ?";
if ($stmt_user_rating = $conn->prepare($sql_user_rating)) {
    $stmt_user_rating->bind_param("i", $_SESSION["id_usuarios"]);
    $stmt_user_rating->execute();
    $stmt_user_rating->bind_result($user_rating);
    $stmt_user_rating->fetch();
    $stmt_user_rating->close();
}

if (!isset($_GET['category_id']) || empty($_GET['category_id'])) {
    header("location: index.php");
    exit;
}

$id_publicacion = null; // Initialize to null
$current_category_id = (int)$_GET['category_id'];

// Fetch category details to get publication_id and name
$category_name = '';
$category_estado = 0;
$sql_category_details = "SELECT nombre_categoria, id_publicacion, estado FROM categorias WHERE id_categorias = ?";
if ($stmt_cat_details = $conn->prepare($sql_category_details)) {
    $stmt_cat_details->bind_param("i", $current_category_id);
    $stmt_cat_details->execute();
    $stmt_cat_details->bind_result($category_name, $id_publicacion, $category_estado);
    if (!$stmt_cat_details->fetch()) {
        header("location: index.php");
        exit;
    }
    $stmt_cat_details->close();
}

// Security check: If category is inactive, only allow admins
if ($category_estado == 0 && (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'creador_contenido']))) {
    $_SESSION['error'] = "Esta categoría no está disponible actualmente.";
    header("location: secciones.php?id_publicacion=" . $id_publicacion);
    exit;
}

// Fetch publication details (for breadcrumb)
$nombre_publicacion = '';
if ($id_publicacion) {
    $sql_publicacion = "SELECT titulo FROM publicacion WHERE id_publicacion = ?";
    if ($stmt_publicacion = $conn->prepare($sql_publicacion)) {
        $stmt_publicacion->bind_param("i", $id_publicacion);
        $stmt_publicacion->execute();
        $stmt_publicacion->bind_result($nombre_publicacion);
        $stmt_publicacion->fetch();
        $stmt_publicacion->close();
    }
}

$id_usuario_actual = $_SESSION["id_usuarios"];
$all_problems = []; // Array to hold all problems with their details and solved status
$current_problem_index = 0; // Index of the problem to display initially

// --- Fetch all problems along with user's progress for the current category ---
$sql_problems = "
    SELECT
        p.id_problemas,
        p.fen,
        p.solucion,
        p.pgn,
        p.juega,
        p.id_categorias,
        p.dificultad,
        p.modo,
        p.desarrollo,
        c.nombre_categoria,
        c.id_publicacion, -- Added for breadcrumb link
        pub.titulo AS nombre_publicacion,
        p.variante_nombre, -- Added for study mode list
        COALESCE(pu.resuelto_correctamente, 0) AS solved_by_user,
        COALESCE(pu.intentos, 0) AS attempts_by_user -- NEW: Fetch attempts
    FROM
        problemas p
    JOIN
        categorias c ON p.id_categorias = c.id_categorias
    JOIN
        publicacion pub ON c.id_publicacion = pub.id_publicacion
    LEFT JOIN
        progreso_usuarios pu ON p.id_problemas = pu.id_problemas AND pu.id_usuarios = ?
    WHERE
        p.id_categorias = ?
    GROUP BY
        p.id_problemas
    ORDER BY
        p.orden ASC, FIELD(p.dificultad, 'Fácil', 'Intermedio', 'Difícil', 'Experto'), p.id_problemas ASC";

if ($stmt_problems = $conn->prepare($sql_problems)) {
    $stmt_problems->bind_param("ii", $id_usuario_actual, $current_category_id);
    $stmt_problems->execute();
    $result_problems = $stmt_problems->get_result();
    while ($row = $result_problems->fetch_assoc()) {
        $all_problems[] = $row;
    }
    $stmt_problems->close();
}

// Determine if there are any study problems to adjust the layout
$has_study_problems = false;
foreach ($all_problems as $problem) {
    if ($problem['modo'] === 'estudio') {
        $has_study_problems = true;
        break;
    }
}

// Determine the initial problem to display (first unsolved, or first if all solved)
if (!empty($all_problems)) {
    foreach ($all_problems as $index => $problem) {
        if ($problem['solved_by_user'] == 0 && $problem['attempts_by_user'] < 2) { // Check attempts too
            $current_problem_index = $index;
            break;
        }
    }
}

// Fetch top 3 users for this category
$top_users_category = [];
if ($current_category_id) {
    $sql_top_users = "
        SELECT
            u.nombre_usuario,
            rc.problemas_resueltos
        FROM
            resultados_categorias rc
        JOIN
            usuarios u ON rc.id_usuarios = u.id_usuarios
        WHERE
            rc.id_categorias = ?
        ORDER BY
            rc.problemas_resueltos DESC, rc.fecha_creacion ASC
        LIMIT 3;
    ";
    if ($stmt_top_users = $conn->prepare($sql_top_users)) {
        $stmt_top_users->bind_param("i", $current_category_id);
        $stmt_top_users->execute();
        $result_top_users = $stmt_top_users->get_result();
        while ($row = $result_top_users->fetch_assoc()) {
            $top_users_category[] = $row;
        }
        $stmt_top_users->close();
    }
}

$total_problems_in_current_category = count($all_problems);

// $conn->close(); // Closed in footer.php
?>

<?php include 'includes/lichess_menu.php'; ?>

<div class="container-fluid lichess-container py-3">
    <div id="result-message-container"></div>

    <!-- LICHESS 2-COLUMN GRID -->
    <div class="row g-4 justify-content-center">
        
        <!-- LEFT COLUMN: CHESSBOARD -->
        <div class="col-lg-7 col-xl-7 d-flex flex-column align-items-center">
            <div class="lichess-board-card w-100" style="max-width: 620px;">
                <div id="board-wrapper" style="width: 100%;">
                    <div id="board" style="width: 100%; margin: 0 auto;"></div>
                </div>

                <!-- BOARD NAVIGATION & CONTROL BAR -->
                <div class="lichess-board-controls">
                    <div id="study-controls">
                        <button id="firstMoveBtn" class="btn lichess-btn-ctrl" title="Primer movimiento"><i class="fas fa-fast-backward"></i></button>
                        <button id="prevMoveBtn" class="btn lichess-btn-ctrl" title="Anterior"><i class="fas fa-step-backward"></i></button>
                        <button id="nextMoveBtn" class="btn lichess-btn-ctrl" title="Siguiente"><i class="fas fa-step-forward"></i></button>
                        <button id="lastMoveBtn" class="btn lichess-btn-ctrl" title="Último movimiento"><i class="fas fa-fast-forward"></i></button>
                    </div>
                    <button id="flipBoardBtn" class="btn lichess-btn-ctrl me-auto" title="Rotar tablero"><i class="fas fa-retweet"></i> Rotar</button>
                    <span id="stopwatch" class="badge bg-dark text-white p-2 fs-6"><i class="far fa-clock me-1"></i> 00:00</span>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: LICHESS SIDE PANEL -->
        <div class="col-lg-5 col-xl-5">
            
            <!-- 1. TURN BANNER CARD -->
            <div id="turn-card-wrapper" class="lichess-card lichess-turn-card mb-3 turn-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="d-flex align-items-center">
                            <span id="turn-dot" class="turn-icon-dot white-dot"></span>
                            <h5 id="turn-title" class="fw-bold mb-0">Juegan Blancas</h5>
                        </div>
                        <small id="turn-subtitle" class="text-muted d-block mt-1">Encuentra la mejor jugada para las blancas.</small>
                    </div>
                    <div id="difficulty-stars" class="text-warning fs-6">
                        <!-- Difficulty stars -->
                    </div>
                </div>
            </div>

            <!-- 2. FEEDBACK / STATUS BOX -->
            <div id="lichess-feedback-wrapper" class="mb-3">
                <div id="move-feedback-box" class="lichess-feedback-box status-neutral">
                    <i class="fas fa-chess me-2 fs-5"></i>
                    <span id="move-feedback-text">Tu turno. Realiza la jugada en el tablero.</span>
                </div>
            </div>

            <!-- 3. ACTION BUTTON (SIGUIENTE EJERCICIO) -->
            <div id="action-button-container" class="mb-3" style="display: none;">
                <button id="nextItemBtn" class="btn lichess-next-btn">
                    Siguiente Ejercicio <i class="fas fa-arrow-right ms-2"></i>
                </button>
            </div>

            <!-- 4. MOVES NOTATION LIST & EXPLANATION -->
            <div class="lichess-card mb-3">
                <h6 class="fw-bold text-secondary mb-2"><i class="fas fa-list-ol me-2"></i>Secuencia de Jugadas</h6>
                <div id="move-sequence-display" class="lichess-moves-panel">
                    <span class="text-muted small">Haz tu jugada para iniciar la secuencia...</span>
                </div>
                <div id="desarrollo-content" class="p-3 bg-light border rounded mt-3 small" style="display: none;">
                    <!-- Desarrollo comment -->
                </div>
            </div>

            <!-- 5. PROGRESS & STATS CARD -->
            <div class="lichess-card mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="lichess-stat-badge">
                        <i class="fas fa-trophy text-warning me-1"></i> Rating: <strong id="rating-display"><?php echo isset($_SESSION['rating']) ? htmlspecialchars($_SESSION['rating']) : '1200'; ?></strong>
                    </span>
                    <span id="attempts-line" class="lichess-stat-badge">
                        <i class="fas fa-redo me-1 text-primary"></i> Intentos: <strong id="attempts-display">0</strong> / 2
                    </span>
                    <span class="lichess-stat-badge">
                        <i class="fas fa-hashtag me-1 text-secondary"></i> ID: <strong id="current-diagram-id">-</strong>
                    </span>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>Progreso de la categoría</span>
                        <span><strong id="solved-count">0</strong> / <strong id="total-problems-display">0</strong> (<span id="percentage-display">0</span>%)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div id="progress-bar-fill" class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <button id="resetCategoryBtn" class="btn btn-outline-danger btn-sm w-100" style="display: none;">
                        <i class="fas fa-redo me-2"></i>Reiniciar categoría completa
                    </button>
                </div>
            </div>

            <!-- 6. ESTUDIOS EN ESTA CATEGORÍA (SI APLICA) -->
            <?php if ($has_study_problems): ?>
            <div class="lichess-card">
                <h6 class="fw-bold mb-3"><i class="fas fa-book-open me-2 text-primary"></i>Estudios en esta categoría</h6>
                <div id="study-problem-list" class="list-group list-group-flush small">
                    <?php
                    $studyProblemCount = 0;
                    foreach ($all_problems as $index => $problem) {
                        if ($problem['modo'] === 'estudio') {
                            $studyProblemCount++;
                            $variant_name = htmlspecialchars($problem['variante_nombre'] ?? 'Estudio ' . $studyProblemCount);
                            echo '<a href="#" class="list-group-item list-group-item-action study-item-' . $index . '" data-index="' . $index . '" onclick="event.preventDefault(); loadProblemByIndex(' . $index . ');">' . $studyProblemCount . '. ' . $variant_name . '</a>';
                        }
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php if (!empty($all_problems)): ?>
<div class="card mt-4" style="display: none;">
    <div class="card-header">Lista de Problemas</div>
    <div class="card-body">
        <ul class="list-inline d-flex flex-wrap justify-content-center" id="problemList">
            <!-- Problem list items will be generated by JavaScript -->
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if ($is_admin): ?>
<div class="container mt-5">
    <hr>
    <h4 class="mb-3 text-center">Navegador de Diagramas (Admin)</h4>
    <div id="diagram-navigator" class="row justify-content-center"></div>
</div>
<?php endif; ?>

<!-- Promotion Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promotionModalLabel">Elige una pieza para coronar</h5>
            </div>
            <div class="modal-body text-center">
                <img src="img/chesspieces/wikipedia/wQ.png" class="promotion-piece" data-piece="q" alt="Queen" style="cursor: pointer; width: 60px; height: 60px;"/>
                <img src="img/chesspieces/wikipedia/wR.png" class="promotion-piece" data-piece="r" alt="Rook" style="cursor: pointer; width: 60px; height: 60px;"/>
                <img src="img/chesspieces/wikipedia/wB.png" class="promotion-piece" data-piece="b" alt="Bishop" style="cursor: pointer; width: 60px; height: 60px;"/>
                <img src="img/chesspieces/wikipedia/wN.png" class="promotion-piece" data-piece="n" alt="Knight" style="cursor: pointer; width: 60px; height: 60px;"/>
            </div>
        </div>
    </div>
</div>

<!-- Report Error Modal -->
<div class="modal fade" id="reportErrorModal" tabindex="-1" aria-labelledby="reportErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportErrorModalLabel">Reportar un Error en el Problema</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="reportErrorForm">
                    <div class="mb-3">
                        <label for="problemIdInput" class="form-label">ID del Problema</label>
                        <input type="text" class="form-control" id="problemIdInput" name="problem_id" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="errorDescriptionInput" class="form-label">Descripción del Error</label>
                        <textarea class="form-control" id="errorDescriptionInput" name="error_description" rows="4" required></textarea>
                    </div>
                    <div id="report-response-message" class="mt-3"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="submitErrorButton">Enviar Reporte</button>
            </div>
        </div>
    </div>
</div>

<!-- Score Modal -->
<div id="score-modal-overlay" class="modal fade" tabindex="-1" aria-labelledby="scoreModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="scoreModalLabel">Resultado Categoria: <?php echo !empty($all_problems) ? htmlspecialchars($all_problems[0]['nombre_categoria']) : ''; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeScoreModal()"></button>
            </div>
            <div class="modal-body text-center">
                <h2><strong><?php echo htmlspecialchars($_SESSION["nombre_usuario"]); ?></strong></h2>
                <p>Usted hizo <span id="score-solved-count"></span> de <span id="score-total-count"></span> problemas.</p>
                <p>Obtuvo el <span id="score-percentage"></span>% de aciertos.</p>
                <hr>
                <h5 class="mt-3"><i class="fas fa-trophy me-2"></i> Top 3 en esta Categoría</h5>
                <div id="leaderboard-container">
                    <!-- Leaderboard will be loaded here by JS -->
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-primary" onclick="saveAndFinish()">Guardar y Finalizar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="js/chessboard-1.0.0.min.js"></script>
<script src="js/chess.js"></script>
<script>
    var currentProblemAttempts = 0;
    const MAX_ATTEMPTS = 2;
    var currentSolutionMoves = [];
    var currentMoveIndex = 0;
    let lastKingSquare = null;
    let puzzleFinished = false; // Flag to lock the board when a puzzle is done
    var stopwatchInterval = null;
    var moveSound = new Audio('mp3/movimiento.mp3');
    moveSound.volume = 1.0;

    function playMoveSound() {
        try {
            var sound = new Audio('mp3/movimiento.mp3');
            sound.volume = 1.0;
            sound.currentTime = 0;
            sound.play().catch(function(e) {});
        } catch(e) {}
    }

    function playMateSound() {
        try {
            var audio = new Audio('mp3/mario.mp3');
            audio.volume = 1.0;
            audio.currentTime = 0;
            audio.play().catch(function(e) {});
        } catch(e) {}
    }
    var initialTurn; // Variable to store the starting turn of the problem

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Pass all problems data from PHP to JavaScript
    var allProblems = <?php echo json_encode($all_problems); ?>;
    var topUsers = <?php echo json_encode($top_users_category); ?>;
    var currentProblemIndex = <?php echo json_encode($current_problem_index); ?>;
    var userId = <?php echo json_encode($id_usuario_actual); ?>;
    var isAdmin = <?php echo json_encode($is_admin); ?>;

    // --- Document Ready ---
    $(document).ready(function() {
        <?php if (!empty($all_problems)): ?>
        initBoard();
        
        $('#nextItemBtn, #mobile-next-btn, #desktop-nav-next-btn').on('click', loadNextProblem);

        $('#flipBoardBtn').on('click', function() {
            if (board) {
                board.flip();
            }
        });

        // Event listeners for study controls
        $('#firstMoveBtn').on('click', firstMove);
        $('#prevMoveBtn').on('click', prevMove);
        $('#nextMoveBtn').on('click', nextMove);
        $('#lastMoveBtn').on('click', lastMove);

        // Reset button handler
        $('#resetCategoryBtn').on('click', function() {
            if (!confirm('¿Estás seguro de que quieres resetear tu progreso en esta categoría? Tu puntuación se ajustará y podrás volver a hacer los ejercicios.')) {
                return;
            }
            var categoryId = allProblems[0].id_categorias;
            $.ajax({
                url: 'reset_category_progress.php',
                type: 'POST',
                data: { category_id: categoryId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Actualizar el rating en la UI en tiempo real
                        $('#rating-display').text(response.new_rating);
                        alert('Progreso reseteado. Tu puntuación ha sido actualizada. La página se recargará.');
                        location.reload();
                    } else {
                        alert('Error al resetear el progreso: ' + response.message);
                    }
                },
                error: function() {
                    alert('Ocurrió un error de conexión al intentar resetear el progreso.');
                }
            });
        });

        $(window).on('resize', function() {
            if (board) {
                board.resize();
            }
        }).trigger('resize');

        // Prevent page scroll on mobile when interacting with the board
        var boardElement = document.getElementById('board');
        if (boardElement) {
            boardElement.addEventListener('touchstart', function(e) {
                e.preventDefault();
            }, { passive: false });
        }
        <?php endif; ?>
    });

    // --- Core Functions ---

    function checkCompletion() {
        let allAttempted = true;
        for (let i = 0; i < allProblems.length; i++) {
            if (allProblems[i].solved_by_user == 0 && allProblems[i].attempts_by_user < MAX_ATTEMPTS) { // Check attempts too
                allAttempted = false;
                break;
            }
        }
        if (allAttempted) {
            $('#resetCategoryBtn').show();
        }
    }

    function initBoard() {
        if (allProblems.length === 0) {
            $('#result-message-container').html('<div class="alert alert-info">No hay problemas de ajedrez disponibles en esta categoría.</div>');
            return;
        }
        // Load the initial problem determined by PHP
        loadProblemByIndex(currentProblemIndex);
        checkCompletion(); // Check on initial load as well

        if (isAdmin) {
            generateDiagramNavigator();
        }
    }

    function setFeedbackStatus(type, htmlContent) {
        var $box = $('#move-feedback-box');
        if ($box.length) {
            $box.removeClass('status-neutral status-success status-warning status-error');
            $box.addClass('status-' + type);
            $('#move-feedback-text').html(htmlContent);
        }
    }

    function loadProblemByIndex(index) {
        $('#result-message-container').empty();
        setFeedbackStatus('neutral', '<i class="fas fa-chess me-2"></i>Tu turno. Realiza la jugada en el tablero.');
        $('#action-button-container').hide();

        if (index < 0 || index >= allProblems.length) {
            showScoreModal();
            return;
        }

        currentProblemIndex = index;
        var currentProblem = allProblems[currentProblemIndex];

        // If PGN is available for a study, use it. Otherwise, use FEN.
        if (currentProblem.modo === 'estudio' && currentProblem.pgn && currentProblem.pgn.trim() !== '') {
            game = new Chess(); // Create a new game instance
            game.load_pgn(currentProblem.pgn);
            currentSolutionMoves = game.history(); // Get all moves from the PGN

            const headers = game.header();
            const startFen = headers['FEN'] || 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
            game.load(startFen);
        } else {
            game = new Chess(currentProblem.fen);
            currentSolutionMoves = currentProblem.solucion.split(' ').filter(move => move.length > 0);
        }
        
        initialTurn = game.turn(); // Store the initial turn

        currentMoveIndex = 0;
        puzzleFinished = false;
        currentProblemAttempts = currentProblem.attempts_by_user; // Initialize from DB

        if (isAdmin) {
            $('.diagram-card').removeClass('border-primary');
            var diagramId = 'diagram-' + currentProblem.id_problemas;
            $('#card-' + diagramId).addClass('border-primary');
        }


        // Usar setTimeout para asegurar que el DOM está estable antes de dibujar el tablero
        setTimeout(function() {
            var isBlackTurn = (game.turn() === 'b' || currentProblem.juega.toLowerCase() === 'negras');
            var config = {
                draggable: (currentProblem.modo === 'problema'),
                position: game.fen(),
                orientation: isBlackTurn ? 'black' : 'white',
                onDrop: onDrop
            };
            board = Chessboard('board', config);

            // Configurar la UI según el modo del problema
            if (currentProblem.modo === 'problema') {
                $('#nextItemBtn').removeClass('btn-info btn-light').addClass('btn-success').html('Siguiente Ejercicio <i class="fas fa-arrow-right ms-2"></i>');
                $('#study-controls').hide();
                $('#stopwatch').show();
                $('#turn-card-wrapper').show();
                $('#attempts-line').show();
                startStopwatch();
            } else { // modo === 'estudio'
                if (hasNextStudyProblem()) {
                    $('#nextItemBtn').removeClass('btn-primary').addClass('btn-light').html('Siguiente Estudio <i class="fas fa-arrow-right ms-2"></i>');
                    $('#action-button-container').show();
                }
                $('#study-controls').show();
                $('#stopwatch').hide();
                $('#attempts-line').hide();
                stopStopwatch();
            }

            updateProblemDisplay();
            generateProblemList();
            updateActiveStudyProblem();
            updateMoveSequenceDisplay();
            
            // Forzar un redimensionamiento final como medida de seguridad
            if (board) {
                board.resize();
            }
            $(window).trigger('resize');

        }, 50); // Un pequeño retraso de 50ms es más robusto que 0
    }

    function updateMoveSequenceDisplay() {
        var $moveSequenceDisplay = $('#move-sequence-display');
        $moveSequenceDisplay.empty();

        var currentProblem = allProblems[currentProblemIndex];
        var movesToDisplay = [];

        if (currentProblem.modo === 'estudio') {
            movesToDisplay = currentSolutionMoves;
        } else {
            // En modo problema, obtenemos la lista de jugadas realizadas hasta el momento
            movesToDisplay = (typeof game !== 'undefined') ? game.history() : [];
        }

        if (movesToDisplay.length > 0) {
            var movesHtml = '<table class="table table-sm table-borderless lichess-moves-table"><thead><tr><th>#</th><th>Blancas</th><th>Negras</th></tr></thead><tbody>';
            
            var moveOffset = (initialTurn === 'b') ? 1 : 0;

            for (var i = 0; i < movesToDisplay.length; i++) {
                var move = movesToDisplay[i];
                var moveNumber = Math.floor((i + moveOffset) / 2) + 1;
                var isWhiteMove = ((i + moveOffset) % 2 === 0);
                var isActive = (i === movesToDisplay.length - 1) ? 'active-move' : '';

                if (isWhiteMove) {
                    movesHtml += `<tr><td>${moveNumber}.</td><td class="${isActive}">${escapeHtml(move)}</td>`;
                    if (i + 1 >= movesToDisplay.length) {
                        movesHtml += `<td></td></tr>`;
                    }
                } else {
                    if (i === 0 && initialTurn === 'b') {
                        movesHtml += `<tr><td>${moveNumber}.</td><td>...</td>`;
                    }
                    movesHtml += `<td class="${isActive}">${escapeHtml(move)}</td></tr>`;
                }
            }
            movesHtml += '</tbody></table>';
            $moveSequenceDisplay.html(movesHtml);

            // Auto-scroll para mantener la última jugada visible
            var panel = document.querySelector('.lichess-moves-panel');
            if (panel) {
                panel.scrollTop = panel.scrollHeight;
            }
        } else {
            $moveSequenceDisplay.html('<div class="text-muted text-center small py-2"><em>Las jugadas aparecerán aquí a medida que las realices...</em></div>');
        }

        var $desarrolloContent = $('#desarrollo-content');
        if ($desarrolloContent.length) {
            if (currentProblem.modo === 'estudio' && currentProblem.desarrollo) {
                $desarrolloContent.html(currentProblem.desarrollo).show();
            } else {
                $desarrolloContent.hide();
            }
        }
    }

    function updateActiveStudyProblem() {
        document.querySelectorAll('[class*="study-item-"]').forEach(item => {
            item.classList.remove('active');
        });

        const currentProblem = allProblems[currentProblemIndex];
        if (currentProblem && currentProblem.modo === 'estudio') {
            const activeItem = document.querySelector('.study-item-' + currentProblemIndex);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }
    }

    function loadNextProblem() {
        if (lastKingSquare) {
            $('#board [data-square=' + lastKingSquare + ']').removeClass('in-check-glow');
            lastKingSquare = null;
        }

        $('#action-button-container').hide();
        puzzleFinished = false;

        var currentProblem = allProblems[currentProblemIndex];

        if (currentProblem.modo === 'problema') {
            showNextDiagram();
        } else { // currentProblem.modo === 'estudio'
            let nextStudyProblemIndex = -1;
            for (let i = currentProblemIndex + 1; i < allProblems.length; i++) {
                if (allProblems[i].modo === 'estudio') {
                    nextStudyProblemIndex = i;
                    break;
                }
            }

            if (nextStudyProblemIndex !== -1) {
                loadProblemByIndex(nextStudyProblemIndex);
            } else {
                showScoreModal();
            }
        }
    }

    function hasNextStudyProblem() {
        for (let i = currentProblemIndex + 1; i < allProblems.length; i++) {
            if (allProblems[i].modo === 'estudio') {
                return true;
            }
        }
        return false;
    }

    function showNextDiagram() {
        let nextProblemIndex = -1;
        for (let i = 0; i < allProblems.length; i++) {
            if (allProblems[i].solved_by_user == 0 && allProblems[i].attempts_by_user < MAX_ATTEMPTS) { // Check attempts too
                nextProblemIndex = i;
                break;
            }
        }

        if (nextProblemIndex !== -1) {
            loadProblemByIndex(nextProblemIndex);
        } else {
            showScoreModal();
        }
    }

    function onDrop (source, target) {
        var currentProblem = allProblems[currentProblemIndex];
        if (currentProblem.modo === 'estudio' || puzzleFinished) {
            return 'snapback';
        }

        var piece = game.get(source);
        var move = null;

        // see if the move is a promotion
        if (piece && piece.type === 'p' &&
            ((piece.color === 'w' && source[1] === '7' && target[1] === '8') ||
             (piece.color === 'b' && source[1] === '2' && target[1] === '1'))) {
            
            var promotionModal = new bootstrap.Modal(document.getElementById('promotionModal'));
            promotionModal.show();

            // Use .one() to avoid binding multiple handlers
            $('.promotion-piece').one('click', function() {
                var promotion = $(this).data('piece');
                promotionModal.hide();
                move = game.move({ from: source, to: target, promotion: promotion });
                
                if (move === null) {
                    return; 
                }

                setTimeout(function() { handleMove(move); }, 0);
            });
            return; 
        } else {
            move = game.move({ from: source, to: target });
        }

        if (move === null) {
            return 'snapback';
        }

        setTimeout(function() { handleMove(move); }, 0);
    }

    function normalizeMoveString(str) {
        if (!str) return '';
        return str.replace(/[+#!?\s]/g, '').trim().toLowerCase();
    }

    function makeEngineMove(gameInstance, targetSan) {
        if (!targetSan) return null;
        var cleanTarget = targetSan.replace(/[()]/g, '').split('|')[0].trim();
        
        // Try direct move first
        var res = gameInstance.move(cleanTarget);
        if (res) return res;

        // Fallback: match normalized SAN against legal moves
        var normTarget = normalizeMoveString(cleanTarget);
        var legalMoves = gameInstance.moves({ verbose: true });
        for (var i = 0; i < legalMoves.length; i++) {
            if (normalizeMoveString(legalMoves[i].san) === normTarget) {
                return gameInstance.move(legalMoves[i]);
            }
        }
        return null;
    }

    function handleMove(move) {
        var expectedUserMove = currentSolutionMoves[currentMoveIndex];
        var isCorrect = false;

        var userSanNorm = normalizeMoveString(move.san);

        // Check for alternative moves like (move1|move2)
        if (expectedUserMove.includes('|')) {
            var possibleMoves = expectedUserMove.replace(/[()]/g, '').split('|');
            if (possibleMoves.map(m => normalizeMoveString(m)).includes(userSanNorm)) {
                isCorrect = true;
            }
        } else {
            if (userSanNorm === normalizeMoveString(expectedUserMove)) {
                isCorrect = true;
            }
        }

        if (isCorrect) {
            playMoveSound();
            currentMoveIndex++;
            updateMoveSequenceDisplay();
            if (currentMoveIndex === currentSolutionMoves.length) {
                setTimeout(function() {
                    handleProblemSolved(allProblems[currentProblemIndex]);
                }, 350);
            } else {
                // Opponent counter-move delay (650ms for realistic, perfectly synchronized rhythm)
                setTimeout(function() {
                    var engineMoveString = currentSolutionMoves[currentMoveIndex];
                    var playedMove = makeEngineMove(game, engineMoveString);

                    if (playedMove) {
                        board.position(game.fen());
                        playMoveSound();
                        currentMoveIndex++;
                        updateMoveSequenceDisplay();
                        highlightKingInCheck();

                        if (currentMoveIndex === currentSolutionMoves.length) {
                            setTimeout(function() {
                                handleProblemSolved(allProblems[currentProblemIndex]);
                            }, 400);
                        } else {
                            setFeedbackStatus('success', '<i class="fas fa-check-circle me-2"></i>¡Correcto! Tu turno de nuevo.');
                        }
                    } else {
                        console.error('Could not play engine move:', engineMoveString);
                        handleProblemSolved(allProblems[currentProblemIndex]);
                    }
                }, 650);
            }
        } else {
            game.undo(); 
            handleIncorrectMove();
        }
    }

    function handleProblemSolved(currentProblem) {
        stopStopwatch();
        puzzleFinished = true;
        playMateSound();
        setTimeout(() => board.position(game.fen()), 0);

        if (game.in_checkmate()) {
            highlightKingInCheck();
        }

        setFeedbackStatus('success', '<i class="fas fa-trophy me-2"></i>¡Excelente! Ejercicio completado.');
        allProblems[currentProblemIndex].solved_by_user = 1;
        $('button[data-index="' + currentProblemIndex + '"]').addClass('btn-success').removeClass('btn-outline-secondary');

        allProblems[currentProblemIndex].attempts_by_user++;
        updateProblemDisplay();

        $.ajax({
            url: 'update_rating.php',
            type: 'POST',
            dataType: 'json',
            data: {
                problem_id: currentProblem.id_problemas,
                outcome: 1,
                difficulty: currentProblem.dificultad
            },
            success: function(response) {
                if (response.success) {
                    $('#rating-display').text(response.new_rating);
                }
                $('#action-button-container, #mobile-next-btn').show();
                checkCompletion();
            }
        });
    }

    function handleIncorrectMove() {
        currentProblemAttempts++;
        allProblems[currentProblemIndex].attempts_by_user++;
        var attemptsLeft = MAX_ATTEMPTS - currentProblemAttempts;
        var currentProblem = allProblems[currentProblemIndex];

        if (attemptsLeft > 0) {
            setFeedbackStatus('warning', '<i class="fas fa-exclamation-triangle me-2"></i>Jugada incorrecta. Te quedan ' + attemptsLeft + ' intento(s).');
        } else {
            stopStopwatch();
            setFeedbackStatus('error', '<i class="fas fa-times-circle me-2"></i>Has agotado tus intentos.');
            puzzleFinished = true;

            allProblems[currentProblemIndex].solved_by_user = 2;
            $('button[data-index="' + currentProblemIndex + '"]').addClass('btn-danger').removeClass('btn-outline-secondary');

            $.ajax({
                url: 'update_rating.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    problem_id: currentProblem.id_problemas,
                    outcome: 2,
                    difficulty: currentProblem.dificultad
                },
                success: function(response) {
                    if (response.success) {
                        $('#rating-display').text(response.new_rating);
                    }
                    $('#action-button-container, #mobile-next-btn').show();
                    checkCompletion();
                }
            });
        }
        game.undo();
        board.position(game.fen());
        updateProblemDisplay();
    }

    function onSnapEnd () {
        board.position(game.fen());
    }

    function updateProblemDisplay() {
        if (allProblems.length > 0) {
            var currentProblem = allProblems[currentProblemIndex];
            $('#current-problem-number').text(currentProblemIndex + 1);
            $('#current-problem-number-mobile').text(currentProblemIndex + 1);
            $('#total-problems').text(allProblems.length);
            $('#current-diagram-id').text(currentProblem.id_problemas);
            $('#attempts-display').text(currentProblem.attempts_by_user);

            var isWhite = (currentProblem.juega.toLowerCase() === 'blancas' || game.turn() === 'w');
            var $turnCard = $('#turn-card-wrapper');
            var $turnDot = $('#turn-dot');
            var $turnTitle = $('#turn-title');
            var $turnSubtitle = $('#turn-subtitle');

            if (isWhite) {
                $turnCard.removeClass('turn-black').addClass('turn-white');
                $turnDot.removeClass('black-dot').addClass('white-dot');
                $turnTitle.text('Juegan Blancas');
                $turnSubtitle.text('Encuentra la mejor jugada para las blancas.');

                $('#mobile-turn-badge').removeClass('bg-dark text-white').addClass('bg-light text-dark border');
                $('#mobile-turn-dot').removeClass('black-dot').addClass('white-dot');
                $('#mobile-turn-text').text('Juegan Blancas');
            } else {
                $turnCard.removeClass('turn-white').addClass('turn-black');
                $turnDot.removeClass('white-dot').addClass('black-dot');
                $turnTitle.text('Juegan Negras');
                $turnSubtitle.text('Encuentra la mejor jugada para las negras.');

                $('#mobile-turn-badge').removeClass('bg-light text-dark border').addClass('bg-dark text-white');
                $('#mobile-turn-dot').removeClass('white-dot').addClass('black-dot');
                $('#mobile-turn-text').text('Juegan Negras');
            }

            updateDifficultyStars(currentProblem.dificultad);

            var solvedProblemsCount = allProblems.filter(p => p.solved_by_user == 1).length;
            var totalProblemsCount = allProblems.length;
            var solvedPercentage = (totalProblemsCount > 0) ? Math.round((solvedProblemsCount / totalProblemsCount) * 100) : 0;

            $('#solved-count').text(solvedProblemsCount);
            $('#total-problems-display').text(totalProblemsCount);
            $('#percentage-display').text(solvedPercentage);
            $('#progress-bar-fill').css('width', solvedPercentage + '%');
        }
    }
    
    function generateProblemList() {
        var $problemList = $('#problemList');
        $problemList.empty();

        $.each(allProblems, function(index, problem) {
            var $li = $('<li class="list-inline-item mb-1"></li>');
            var $button = $('<button class="btn btn-sm problem-list-item"></button>')
                .text(index + 1)
                .attr('data-index', index);

            // Determine button style based on problem status
            if (problem.solved_by_user == 1) {
                $button.addClass('btn-success');
            } else if (problem.solved_by_user == 2 || problem.attempts_by_user >= MAX_ATTEMPTS) { // Failed or attempts exhausted
                $button.addClass('btn-danger');
            } else {
                $button.addClass('btn-outline-secondary');
            }

            if (index === currentProblemIndex) $button.addClass('active');

            $button.on('click', function() {
                var clickedIndex = parseInt($(this).attr('data-index'));
                var clickedProblem = allProblems[clickedIndex];

                // Allow viewing any problem, but show message if attempts exhausted/solved
                if (clickedProblem.solved_by_user == 1) {
                    $('#result-message-container').html('<div class="alert alert-info">Ya has resuelto este problema. Puedes revisarlo.</div>');
                } else if (clickedProblem.attempts_by_user >= MAX_ATTEMPTS) {
                    $('#result-message-container').html('<div class="alert alert-warning">Has agotado tus intentos para este problema. Puedes revisarlo.</div>');
                } else {
                    $('#result-message-container').empty(); // Clear message if problem is active
                }
                loadProblemByIndex(clickedIndex);
            });

            $problemList.append($li.append($button));
        });
    }

    function firstMove() {
        game.load(allProblems[currentProblemIndex].fen);
        board.position(game.fen());
        currentMoveIndex = 0;
        updateMoveSequenceDisplay();
        moveSound.play();
        highlightKingInCheck();
    }

    function prevMove() {
        if (currentMoveIndex > 0) {
            game.undo();
            board.position(game.fen());
            currentMoveIndex--;
            updateMoveSequenceDisplay();
            moveSound.play();
            highlightKingInCheck();
        }
    }

    function nextMove() {
        if (currentMoveIndex < currentSolutionMoves.length) {
            game.move(currentSolutionMoves[currentMoveIndex]);
            board.position(game.fen());
            currentMoveIndex++;
            updateMoveSequenceDisplay();
            moveSound.play();
            highlightKingInCheck();
        }
    }

    function lastMove() {
        game.load(allProblems[currentProblemIndex].fen);
        for (var i = 0; i < currentSolutionMoves.length; i++) {
            game.move(currentSolutionMoves[i]);
        }
        board.position(game.fen());
        currentMoveIndex = currentSolutionMoves.length;
        updateMoveSequenceDisplay();
        moveSound.play();
        highlightKingInCheck();
    }

    function showScoreModal() {
        var solvedCount = allProblems.filter(p => p.solved_by_user == 1).length;
        var totalCount = allProblems.length;
        var percentage = (totalCount > 0) ? Math.round((solvedCount / totalCount) * 100) : 0;

        $('#score-solved-count').text(solvedCount);
        $('#score-total-count').text(totalCount);
        $('#score-percentage').text(percentage);

        var $leaderboardContainer = $('#leaderboard-container').empty();
        if (topUsers && topUsers.length > 0) {
            var table = '<table class="table table-striped mt-3"><thead><tr><th>Puesto</th><th>Usuario</th><th>Problemas Resueltos</th></tr></thead><tbody>';
            $.each(topUsers, function(index, player) {
                var medal = ['<i class="fas fa-medal text-warning me-2 fa-lg"></i>', '<i class="fas fa-medal text-secondary me-2 fa-lg"></i>', '<i class="fas fa-medal text-bronze me-2 fa-lg"></i>'][index] || '';
                table += `<tr><td>${medal}${index + 1}</td><td>${player.nombre_usuario}</td><td>${player.problemas_resueltos}</td></tr>`;
            });
            table += '</tbody></table>';
            $leaderboardContainer.html(table);
        } else {
            $leaderboardContainer.html('<p class="text-muted mt-3">Aún no hay un ranking para esta categoría. ¡Sé el primero!</p>');
        }

        var scoreModal = new bootstrap.Modal(document.getElementById('score-modal-overlay'));
        scoreModal.show();
    }

    function closeScoreModal() {
        var scoreModal = bootstrap.Modal.getInstance(document.getElementById('score-modal-overlay'));
        scoreModal.hide();
    }

    function saveAndFinish() {
        var solvedCount = allProblems.filter(p => p.solved_by_user == 1).length;
        var totalCount = allProblems.length;
        var percentage = (totalCount > 0) ? Math.round((solvedCount / totalCount) * 100) : 0;

        saveCategoryResults(solvedCount, totalCount, percentage);
        closeScoreModal();
        window.location.href = 'index.php';
    }

    function saveCategoryResults(solvedCount, totalCount, percentage) {
        $.ajax({
            url: 'save_category_results.php',
            type: 'POST',
            data: {
                category_id: allProblems[0].id_categorias,
                publicacion_id: allProblems[0].id_publicacion, // <-- AÑADIDO
                solved_problems: solvedCount,
                total_problems: totalCount,
                percentage: percentage
            },
            success: response => console.log("Category results saved:", response),
            error: (xhr, status, error) => console.error("Error saving category results:", xhr, status, error)
        });
    }

    function formatTime(seconds) {
        var minutes = Math.floor(seconds / 60);
        var remainingSeconds = seconds % 60;
        return (minutes < 10 ? '0' : '') + minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
    }

    function startStopwatch() {
        clearInterval(stopwatchInterval);
        var startTime = Date.now();
        $('#stopwatch').text('00:00');
        stopwatchInterval = setInterval(() => {
            var elapsedTime = Math.floor((Date.now() - startTime) / 1000);
            $('#stopwatch').text(formatTime(elapsedTime));
        }, 1000);
    }

    function stopStopwatch() {
        clearInterval(stopwatchInterval);
    }

    function updateDifficultyStars(difficulty) {
        var starMap = { 'Fácil': 1, 'Intermedio': 2, 'Difícil': 3, 'Experto': 4 };
        var starCount = starMap[difficulty] || 0;
        var $starsContainer = $('#difficulty-stars').empty();

        $starsContainer.addClass('d-flex justify-content-center align-items-center');

        for (var i = 0; i < starCount; i++) {
            $starsContainer.append('<i class="fas fa-star mx-1"></i>');
        }
    }

    function generateDiagramNavigator() {
        var $navigator = $('#diagram-navigator');
        if (!$navigator.length) return;
        $navigator.empty();

        $.each(allProblems, function(index, problem) {
            var diagramId = 'diagram-' + problem.id_problemas;
            var cardId = 'card-' + diagramId;
            var cardClass = (index === currentProblemIndex) ? 'border-primary' : '';

            var $diagramWrapper = $(`
                <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-4">
                    <div id="${cardId}" class="card h-100 diagram-card ${cardClass}" style="cursor: pointer;">
                        <div class="card-header text-center p-1">
                            <small>ID: ${problem.id_problemas} (${problem.dificultad.substring(0,1)})</small>
                        </div>
                        <div class="card-body p-1">
                            <div id="${diagramId}" style="width: 100%;"></div>
                        </div>
                    </div>
                </div>
            `);

            $diagramWrapper.on('click', function() {
                loadProblemByIndex(index);
                $('html, body').animate({ scrollTop: $('#board-wrapper').offset().top - 20 }, 300);
            });

            $navigator.append($diagramWrapper);

            setTimeout(function() {
                var boardConfig = {
                    position: problem.fen,
                    pieceTheme: 'img/chesspieces/wikipedia/{piece}.png'
                };
                Chessboard(diagramId, boardConfig);
                $(window).trigger('resize');
            }, 100);
        });
    }

    function highlightKingInCheck() {
        if (lastKingSquare) {
            $('#board [data-square=' + lastKingSquare + ']').removeClass('in-check-glow');
            lastKingSquare = null;
        }

        if (game.in_check()) {
            var kingColor = game.turn();
            var boardState = game.board();
            for (var i = 0; i < 8; i++) {
                for (var j = 0; j < 8; j++) {
                    var piece = boardState[i][j];
                    if (piece && piece.type === 'k' && piece.color === kingColor) {
                        var kingSquare = 'abcdefgh'[j] + (8 - i);
                        lastKingSquare = kingSquare;
                        $('#board [data-square=' + kingSquare + ']').addClass('in-check-glow');
                        return;
                    }
                }
            }
        }
    }
</script>

</div>

<?php require_once 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Add a page-specific class for CSS scoping
    $('body').addClass('categoria-page');


    // --- Report Error Modal Logic ---
    var reportErrorModal = new bootstrap.Modal(document.getElementById('reportErrorModal'));

    // When the modal is about to be shown, populate the problem ID
    document.getElementById('reportErrorModal').addEventListener('show.bs.modal', function () {
        var currentProblem = allProblems[currentProblemIndex];
        document.getElementById('problemIdInput').value = currentProblem.id_problemas;
        // Clear previous messages
        $('#report-response-message').empty();
        $('#errorDescriptionInput').val('');
    });

    // Handle the error report submission
    $('#submitErrorButton').on('click', function() {
        var problemId = $('#problemIdInput').val();
        var description = $('#errorDescriptionInput').val();

        if (!description.trim()) {
            $('#report-response-message').html('<div class="alert alert-warning">Por favor, escribe una descripción del error.</div>');
            return;
        }

        $.ajax({
            url: 'guardar_reporte.php',
            type: 'POST',
            data: {
                problem_id: problemId,
                description: description
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#report-response-message').html('<div class="alert alert-success">' + response.message + '</div>');
                    setTimeout(function() {
                        reportErrorModal.hide();
                    }, 2000); // Hide modal after 2 seconds
                } else {
                    $('#report-response-message').html('<div class="alert alert-danger">' + response.message + '</div>');
                }
            },
            error: function() {
                $('#report-response-message').html('<div class="alert alert-danger">Ocurrió un error al enviar el reporte. Inténtalo de nuevo.</div>');
            }
        });
    });

    // --- Test Board Logic ---
    $('#test-board-btn').on('click', function() {
        var config = {
            position: 'start',
            draggable: true
        };
        var board = Chessboard('board-test', config);
        $(this).hide(); // Hide button after click
    });
});
</script>
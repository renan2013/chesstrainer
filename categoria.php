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

<div class="container-fluid">
<?php if (!empty($all_problems)): ?>
    <div class="row">
        <div class="col-md-6 text-center text-md-start">
            <p class="mb-0">Problema <span id="current-problem-number"><?php echo $current_problem_index + 1; ?></span> de <span id="total-problems"><?php echo $total_problems_in_current_category; ?></span> - ID: <span id="current-diagram-id"></span> - Juegan: <strong><span id="juega-display"><?php echo htmlspecialchars($all_problems[$current_problem_index]['juega']); ?></span></strong></p>
            <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                <p class="mb-0 me-3" id="attempts-line">Intentos: <span id="attempts-display">0</span> / 2</p>
                <p id="move-feedback-message" class="text-success fw-bold mb-0"></p>
            </div>
        </div>
        <div class="col-md-6 text-center text-md-end">
            <p class="mb-0" id="solved-line">Resueltos: <span id="solved-count">0</span> / <span id="total-problems-display">0</span> (<span id="percentage-display">0</span>%)</p>
        </div>
    </div>
<?php endif; ?>

<div id="result-message-container"></div>

<div class="row <?php if (!$has_study_problems) echo 'd-flex justify-content-center'; ?>">
    <?php if ($has_study_problems): ?>
    <!-- Left Column: Study Diagrams List -->
    <div class="col-lg-3">
        <h5 class="mb-3">Estudios en esta categoría</h5>
        <div id="study-problem-list" class="list-group">
            <?php
            $studyProblemCount = 0;
            foreach ($all_problems as $index => $problem) {
                if ($problem['modo'] === 'estudio') {
                    $studyProblemCount++;
                    $variant_name = htmlspecialchars($problem['variante_nombre'] ?? 'Estudio ' . $studyProblemCount);
                    echo '<a href="#" class="list-group-item list-group-item-action study-item-' . $index . '" data-index="' . $index . '" onclick="event.preventDefault(); loadProblemByIndex(' . $index . ');">' . $studyProblemCount . '. ' . $variant_name . ' (ID: ' . $problem['id_problemas'] . ')</a>';
                }
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Center Column: Chessboard -->
    <div class="col-lg-6 d-flex justify-content-center chessboard-container">
        <div id="board-wrapper" style="max-width: 100%;">
            <!-- Info Header -->
            <div id="info-header" class="d-flex justify-content-center align-items-center mb-2" style="min-height: 40px;">
                <div class="me-3">
                    <strong id="rating-display" style="font-size: 1.1rem;"><?php echo isset($_SESSION['rating']) ? htmlspecialchars($_SESSION['rating']) : '1200'; ?></strong>
                </div>
                <div id="difficulty-stars" class="me-3">
                    <!-- Stars will be generated by JS -->
                </div>
                <div>
                    <div id="turn-indicator" class="d-inline-block"></div>
                </div>
                <div id="stopwatch" class="fw-bold" style="font-size: 1.1rem; color: #333; margin-left: 1rem;">00:00</div>
            </div>
            <div id="board" style="margin: 0 auto;"></div>
            <div id="study-controls" class="text-center mt-3">
                <button id="firstMoveBtn" class="btn btn-secondary btn-sm me-2">|< Inicio</button>
                <button id="prevMoveBtn" class="btn btn-secondary btn-sm me-2">< Ant.</button>
                <button id="nextMoveBtn" class="btn btn-secondary btn-sm me-2">Sig. ></button>
                <button id="lastMoveBtn" class="btn btn-secondary btn-sm">Fin >|</button>
            </div>
            <div class="d-flex justify-content-center mt-3">
                <button id="nextItemBtn" class="btn btn-primary btn-sm" style="display: none;">Siguiente &rarr;</button>
            </div>
            <div class="text-center mt-4">
                <button id="resetCategoryBtn" class="btn btn-danger" style="display: none;"><i class="fas fa-redo me-2"></i>Quiero hacerlo todo de nuevo</button>
            </div>
        </div>
    </div>

    <?php if ($has_study_problems): ?>
    <!-- Right Column: Move Sequence and Comment -->
    <div class="col-lg-3">
        <h5 class="mb-3">Secuencia de Jugadas</h5>
        <div id="move-sequence-display" class="mb-3 p-3 bg-white border rounded">
            <!-- Move sequence will be populated by JS -->
        </div>
        <?php if (!empty($all_problems) && !empty($all_problems[$current_problem_index]['desarrollo'])): ?>
        <h5 class="mb-3 mt-4">Comentario</h5>
        <div id="desarrollo-content" class="p-3 bg-light rounded">
            <?php echo htmlspecialchars($all_problems[$current_problem_index]['desarrollo']); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
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
        
        $('#nextItemBtn').on('click', loadNextProblem);

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

    function loadProblemByIndex(index) {
        $('#result-message-container').empty();
        $('#move-feedback-message').empty();

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


        var wrapperWidth = $('#board-wrapper').parent().width();
        $('#board-wrapper').width(wrapperWidth);

        // Usar setTimeout para asegurar que el DOM está estable antes de dibujar el tablero
        setTimeout(function() {
            var config = {
                draggable: (currentProblem.modo === 'problema'),
                position: game.fen(),
                onDrop: onDrop
            };
            board = Chessboard('board', config);

            // Configurar la UI según el modo del problema
            if (currentProblem.modo === 'problema') {
                $('#nextItemBtn').show().removeClass('btn-info').addClass('btn-primary').text('Siguiente Problema →');
                $('#study-controls').hide();
                $('#stopwatch').show();
                $('#turn-indicator').show(); // Always show for problema mode
                $('#attempts-line').show();
                $('#solved-line').show();
                startStopwatch();
            } else { // modo === 'estudio'
                if (hasNextStudyProblem()) {
                    $('#nextItemBtn').show().removeClass('btn-primary').addClass('btn-light').text('Siguiente Estudio →');
                }
                $('#study-controls').show();
                $('#stopwatch').hide();
                $('#turn-indicator').show(); // Show for estudio mode
                $('#attempts-line').hide();
                $('#solved-line').hide();
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

        if (currentProblem.modo === 'estudio' && currentSolutionMoves.length > 0) {
            var movesHtml = '<table class="table table-sm table-borderless"><thead><tr><th>#</th><th>Blancas</th><th>Negras</th></tr></thead><tbody>';
            
            var moveOffset = (initialTurn === 'b') ? 1 : 0;

            for (var i = 0; i < currentSolutionMoves.length; i++) {
                var move = currentSolutionMoves[i];
                var moveNumber = Math.floor((i + moveOffset) / 2) + 1;
                var isWhiteMove = ((i + moveOffset) % 2 === 0);
                var isActive = (i === currentMoveIndex - 1) ? 'active-move' : '';

                if (isWhiteMove) {
                    movesHtml += `<tr><td>${moveNumber}.</td><td class="${isActive}">${move}</td>`;
                    if (i + 1 >= currentSolutionMoves.length) {
                        movesHtml += `<td></td></tr>`;
                    }
                } else {
                    if (i === 0 && initialTurn === 'b') {
                        movesHtml += `<tr><td>${moveNumber}.</td><td>...</td>`;
                    }
                    movesHtml += `<td class="${isActive}">${move}</td></tr>`;
                }
            }
            movesHtml += '</tbody></table>';
            $moveSequenceDisplay.html(movesHtml);
        } else {
            $moveSequenceDisplay.empty();
        }

        var $desarrolloContent = $('#desarrollo-content');
        if ($desarrolloContent.length) {
            if (currentProblem.modo === 'estudio' && currentProblem.desarrollo) {
                $desarrolloContent.html(currentProblem.desarrollo).show();
            }
            else {
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

        $('#nextItemBtn').hide();
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
                    // This case is tricky, as snapback is needed but we are in an event handler
                    // For now, we'll just let the piece snap back visually by not updating the board
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

    function handleMove(move) {
        var expectedUserMove = currentSolutionMoves[currentMoveIndex];
        var isCorrect = false;

        // Check for alternative moves like (move1|move2)
        if (expectedUserMove.includes('|')) {
            // Remove parentheses and split into an array of possible moves
            var possibleMoves = expectedUserMove.replace(/[()]/g, '').split('|');
            // Check if the user's move is one of the possible moves (case-insensitive)
            if (possibleMoves.map(m => m.toLowerCase()).includes(move.san.toLowerCase())) {
                isCorrect = true;
            }
        } else {
            // Standard comparison for a single move
            if (move.san.toLowerCase() === expectedUserMove.toLowerCase()) {
                isCorrect = true;
            }
        }

        if (isCorrect) {
            moveSound.play();
            currentMoveIndex++;
            if (currentMoveIndex === currentSolutionMoves.length) {
                handleProblemSolved(allProblems[currentProblemIndex]);
            } else {
                setTimeout(function() {
                var engineMoveString = currentSolutionMoves[currentMoveIndex];
                var move_to_play = engineMoveString;

                // Check if the engine's move has alternatives
                if (engineMoveString.includes('|')) {
                    // Just pick the first valid move for the engine to play
                    move_to_play = engineMoveString.replace(/[()]/g, '').split('|')[0];
                }

                game.move(move_to_play); // Use the parsed move
                board.position(game.fen());
                moveSound.play();
                currentMoveIndex++;
                highlightKingInCheck();
                if (currentMoveIndex === currentSolutionMoves.length) {
                    handleProblemSolved(allProblems[currentProblemIndex]);
                } else {
                    $('#result-message-container').empty();
                    $('#move-feedback-message').html('<i class="fas fa-thumbs-up me-2"></i>¡Correcto! Ahora tu turno.').removeClass('text-danger text-warning').addClass('text-success');
                }
            }, 300);
            }
        } else {
            game.undo(); 
            handleIncorrectMove();
        }
    }

    function handleProblemSolved(currentProblem) {
        stopStopwatch();
        puzzleFinished = true;
        setTimeout(() => board.position(game.fen()), 0);

        if (game.in_checkmate()) {
            highlightKingInCheck();
        }

        $('#result-message-container').empty();
        $('#move-feedback-message').html('<i class="fas fa-thumbs-up me-2"></i>¡Correcto! Problema resuelto.').removeClass('text-danger text-warning').addClass('text-success');
        allProblems[currentProblemIndex].solved_by_user = 1;
        $('button[data-index="' + currentProblemIndex + '"]').addClass('btn-success').removeClass('btn-outline-secondary');

        // Update attempts_by_user in JS array for consistency
        allProblems[currentProblemIndex].attempts_by_user++;
        updateProblemDisplay(); // Refresh attempts display

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
                // Mostrar el botón SIGUIENTE solo después de que el AJAX se complete
                $('#nextItemBtn').show();
                checkCompletion();
            }
        });
    }

    function handleIncorrectMove() {
        currentProblemAttempts++;
        allProblems[currentProblemIndex].attempts_by_user++; // Update in JS array
        var attemptsLeft = MAX_ATTEMPTS - currentProblemAttempts;
        var currentProblem = allProblems[currentProblemIndex];

        if (attemptsLeft > 0) {
            $('#result-message-container').empty();
            $('#move-feedback-message').html(`Buen intento, te quedan ${attemptsLeft} intento(s).`).removeClass('text-success text-danger').addClass('text-warning');
        } else {
            stopStopwatch();
            $('#result-message-container').empty();
            $('#move-feedback-message').html('Has agotado tus intentos.').removeClass('text-success text-warning').addClass('text-danger');
            puzzleFinished = true;

            allProblems[currentProblemIndex].solved_by_user = 2; // 2 = failed
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
                    // Mostrar el botón SIGUIENTE solo después de que el AJAX se complete
                    $('#nextItemBtn').show();
                    checkCompletion();
                }
            });
        }
        game.undo();
        board.position(game.fen());
        updateProblemDisplay(); // Refresh attempts display
    }

    function onSnapEnd () {
        board.position(game.fen());
    }

    function updateProblemDisplay() {
        if (allProblems.length > 0) {
            var currentProblem = allProblems[currentProblemIndex];
            $('#current-problem-number').text(currentProblemIndex + 1);
            $('#total-problems').text(allProblems.length);
            $('#current-diagram-id').text(currentProblem.id_problemas);
            $('#juega-display').text(currentProblem.juega);
            $('#attempts-display').text(currentProblem.attempts_by_user); // Update attempts display

            var $indicator = $('#turn-indicator');
            $indicator.removeClass('turn-white turn-black').addClass(currentProblem.juega.toLowerCase() === 'blancas' ? 'turn-white' : 'turn-black');

            updateDifficultyStars(currentProblem.dificultad);

            var solvedProblemsCount = allProblems.filter(p => p.solved_by_user == 1).length;
            var totalProblemsCount = allProblems.length;
            var solvedPercentage = (totalProblemsCount > 0) ? Math.round((solvedProblemsCount / totalProblemsCount) * 100) : 0;

            $('#solved-count').text(solvedProblemsCount);
            $('#total-problems-display').text(totalProblemsCount);
            $('#percentage-display').text(solvedPercentage);
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
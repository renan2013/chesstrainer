<?php 
ob_start();
$page_title = "Añadir Diagrama";
require_once 'includes/header.php';

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
    header("location: index.php");
    exit;
}

$mensaje = '';
$error = '';

// Obtener publicaciones y categorías para los selectores
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

$todas_las_categorias = [];
$sql_todas_cat = "SELECT id_categorias, nombre_categoria FROM categorias ORDER BY nombre_categoria ASC";
$result_todas_cat = $conn->query($sql_todas_cat);
if ($result_todas_cat) {
    while($row_cat = $result_todas_cat->fetch_assoc()) {
        $todas_las_categorias[] = $row_cat;
    }
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

    if (empty($fen) || (empty($solucion) && empty($pgn)) || empty($dificultad) || empty($juega) || empty($tipo_problema) || empty($id_categorias)) {
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
            $sql = "INSERT INTO problemas (id_categorias, fen, solucion, dificultad, juega, tipo_problema, desarrollo, creado_por_id_usuario, modo, variante_nombre, orden, pgn) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            if ($stmt = $conn->prepare($sql)) {
                $creado_por_id_usuario = $_SESSION['id_usuarios'];
                // El modo ahora es $modo_heredado
                $stmt->bind_param("issssssissis", $id_categorias, $fen, $solucion, $dificultad, $juega, $tipo_problema, $desarrollo, $creado_por_id_usuario, $modo_heredado, $variante_nombre, $orden, $pgn);
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
}
?>



    <main class="main-content" style="background-color: #f0f0f0;">

        <h3>Añadir Nuevo Diagrama</h3>
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
                            .pgn-move-pair:nth-child(odd) {
                                background-color: #f8f8f8; /* Un gris muy claro */
                            }
                                                                            .pgn-move-pair:nth-child(even) {
                                                                                background-color: #ffffff; /* Blanco */
                                                                            }
                                                                            .header-black-moves {
                                                                                font-weight: bold;
                                                                            }
                                                                        </style>        <?php if(isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success" role="alert">Diagrama añadido exitosamente.</div>
        <?php endif; ?>

        <?php if(!empty($error)): ?>
            <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="row mb-2">
                <div class="col-md-4">
                    <label for="id_publicacion" class="form-label">Publicación (Define el Modo)</label>
                    <select id="id_publicacion" name="id_publicacion" class="form-select" required>
                        <option value="">Selecciona una publicación</option>
                        <?php foreach ($publicaciones as $pub): ?>
                            <option value="<?php echo $pub['id_publicacion']; ?>" data-tipo="<?php echo $pub['tipo']; ?>">
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
                    <input type="text" name="variante_nombre" id="variante_nombre" class="form-control">
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
                    <input type="text" name="fen" id="fen" class="form-control" required>
                </div>
           
                <div class="col-md-12">
                        <label for="solucion" class="form-label">Solución</label>
                        <textarea name="solucion" id="solucion" class="form-control" required></textarea>
                        <div id="solucion-feedback" class="form-text"></div>
                </div>

                <div class="col-md-12">
                        <label for="pgn" class="form-label">PGN</label>
                        <textarea name="pgn" id="pgn" class="form-control"></textarea>
                </div>

                <div class="col-md-12">
                        <label for="dificultad" class="form-label">Dificultad</label>
                        <select name="dificultad" id="dificultad" class="form-select" required>
                            <option value="">Selecciona</option>
                            <option value="Fácil">Fácil</option>
                            <option value="Intermedio">Intermedio</option>
                            <option value="Difícil">Difícil</option>
                            <option value="Experto">Experto</option>
                        </select>
                </div>

                    <div class="row">
                    <div class="col-md-8">
                        <label class="form-label">Juega (cambiar turno)</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="juega_radio" id="juega_blancas" value="w" checked disabled="true">
                                <label class="form-check-label" for="juega_blancas">Blancas</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="juega_radio" id="juega_negras" value="b" disabled="true">
                                <label class="form-check-label" for="juega_negras">Negras</label>
                            </div>
                            <input type="hidden" name="juega" id="juega_hidden" value="w">
                        </div>
                    </div>

                    <div class="col-md-4" id="orden_wrapper">
                    <label for="orden" class="form-label">Orden</label>
                    <input type="number" name="orden" id="orden" class="form-control" value="0">
                    </div>
                    </div>




                    <div class="col-md-12">
                        <label for="tipo_problema" class="form-label">Tipo de Problema</label>
                        <select name="tipo_problema" id="tipo_problema" class="form-select" required>
                            <option value="">Selecciona</option>
                            <option value="Mate en 1">Mate en 1</option>
                            <option value="Mate en 2">Mate en 2</option>
                            <option value="Mate en 3">Mate en 3</option>
                            
                            <option value="Ganan Blancas">Ganan Blancas</option>
                            <option value="Ganan Negras">Ganan Negras</option>
                            <option value="Ventaja Blanca">Ventaja Blanca</option>
                            <option value="Ventaja Negra">Ventaja Negra</option>
                            <option value="Tablas">Tablas</option>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label for="desarrollo" class="form-label">Desarrollo</label>
                        <textarea name="desarrollo" id="desarrollo" class="form-control"></textarea>
                    </div>
                    <br/>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Añadir Diagrama - Estudio</button>
                    </div>


                </div>
            </div>

            

            

            
            
            
            
        </form>
        <hr class="my-5">

        <h2 class="mb-4">Problemas Existentes</h2>

        <!-- Formulario de Búsqueda -->
        <form action="" method="GET" class="mb-4">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Buscar por ID, FEN, categoría o modo..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button class="btn btn-primary" type="submit">Buscar</button>
            </div>
        </form>

        <?php
        $problemas_por_pagina = 20;
        $pagina_actual = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($pagina_actual < 1) {
            $pagina_actual = 1;
        }
        $offset = ($pagina_actual - 1) * $problemas_por_pagina;

        $search_term = '';
        if (isset($_GET['search'])) {
            $search_term = $conn->real_escape_string($_GET['search']);
        }

        // --- Construcción de la consulta con filtros y control de rol ---
        $base_sql_count = "SELECT COUNT(*) as total FROM problemas p JOIN categorias c ON p.id_categorias = c.id_categorias";
        $base_sql_problemas = "SELECT p.id_problemas, p.fen, p.modo, p.dificultad, c.nombre_categoria 
                               FROM problemas p 
                               JOIN categorias c ON p.id_categorias = c.id_categorias";
        
        $where_conditions = [];
        $params = [];
        $types = '';

        // Filtro por término de búsqueda
        if (!empty($search_term)) {
            $search_param = "%" . $search_term . "%";
            $numeric_search_term = is_numeric($search_term) ? intval($search_term) : null;

            $search_parts = ["p.fen LIKE ?", "c.nombre_categoria LIKE ?", "p.modo LIKE ?"];
            $params = array_merge($params, [$search_param, $search_param, $search_param]);
            $types .= 'sss';
            
            if ($numeric_search_term !== null) {
                $search_parts[] = "p.id_problemas = ?";
                $params[] = $numeric_search_term;
                $types .= 'i';
            }
            $where_conditions[] = "(" . implode(" OR ", $search_parts) . ")";
        }

        // Filtro por rol de usuario
        if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'creador_contenido') {
            $where_conditions[] = "p.creado_por_id_usuario = ?";
            $params[] = $_SESSION['id_usuarios'];
            $types .= 'i';
        }

        $where_clause = "";
        if (!empty($where_conditions)) {
            $where_clause = " WHERE " . implode(" AND ", $where_conditions);
        }

        // Ejecutar consulta de conteo
        $sql_count = $base_sql_count . $where_clause;
        $stmt_count = $conn->prepare($sql_count);
        if (!empty($params)) {
            $stmt_count->bind_param($types, ...$params);
        }
        $stmt_count->execute();
        $total_problemas = $stmt_count->get_result()->fetch_assoc()['total'];
        $total_paginas = ceil($total_problemas / $problemas_por_pagina);
        $stmt_count->close();

        // Ejecutar consulta de problemas para la página actual
        $sql_problemas = $base_sql_problemas . $where_clause . " ORDER BY p.id_problemas DESC LIMIT ? OFFSET ?";
        
        // Limpiar tipos y parámetros para la segunda consulta
        $problemas_params = $params;
        $problemas_types = $types;
        $problemas_types .= 'ii';
        $problemas_params[] = $problemas_por_pagina;
        $problemas_params[] = $offset;

        $stmt_problemas = $conn->prepare($sql_problemas);
        if (!empty($problemas_params)) {
            $stmt_problemas->bind_param($problemas_types, ...$problemas_params);
        }
        $stmt_problemas->execute();
        $result_problemas = $stmt_problemas->get_result();
        ?>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Categoría</th>
                        <th>FEN</th>
                        <th>Dificultad</th>
                        <th>Modo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result_problemas && $result_problemas->num_rows > 0): ?>
                        <?php while($problema = $result_problemas->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $problema['id_problemas']; ?></td>
                                <td><?php echo htmlspecialchars($problema['nombre_categoria']); ?></td>
                                <td><small><?php echo htmlspecialchars($problema['fen']); ?></small></td>
                                <td><?php echo htmlspecialchars(ucfirst($problema['dificultad'])); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($problema['modo'])); ?></td>
                                <td>
                                    <a href="editar_problema.php?id=<?php echo $problema['id_problemas']; ?>" class="btn btn-sm btn-info">Editar</a>
                                    <a href="eliminar_problema.php?id=<?php echo $problema['id_problemas']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro de que quieres eliminar este problema?');">Eliminar</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No hay problemas para mostrar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <nav aria-label="Paginación de problemas">
            <ul class="pagination justify-content-center">
                <?php if ($total_paginas > 1): ?>
                    <!-- Botón Anterior -->
                    <li class="page-item <?php echo ($pagina_actual <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $pagina_actual - 1; ?>&search=<?php echo urlencode($search_term); ?>">Anterior</a>
                    </li>

                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <li class="page-item <?php echo ($i == $pagina_actual) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search_term); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <!-- Botón Siguiente -->
                    <li class="page-item <?php echo ($pagina_actual >= $total_paginas) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $pagina_actual + 1; ?>&search=<?php echo urlencode($search_term); ?>">Siguiente</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

    </main>

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
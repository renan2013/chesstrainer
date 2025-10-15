<?php
$page_title = "Añadir Problema de Ajedrez";
require_once 'includes/header.php'; // Admin header
?>

<div class="container-fluid mt-5"> <!-- Use container-fluid for full width -->
    <h1>Añadir Nuevo Problema de Ajedrez</h1>

    <div id="board"></div>

    <form action="save_puzzle.php" method="POST">
        <div class="form-group">
            <label for="theme">Tema del Problema (Ej: Clavada, Mate en 2)</label>
            <input type="text" id="theme" name="theme" class="form-control" required>
        </div>

        <div class="form-group">
            <label for="fen">Posición (FEN)</label>
            <input type="text" id="fen" name="fen" class="form-control" readonly required>
            <small>Este campo se actualiza automáticamente con el tablero.</small>
        </div>

        <div class="form-group">
            <label for="solution">Solución (Ej: 1. Dxh7#)</label>
            <input type="text" id="solution" name="solution" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Problema</button>
        <button type="button" id="clearBoardBtn" class="btn btn-secondary">Limpiar Tablero</button>
        <button type="button" id="startPositionBtn" class="btn btn-info">Posición Inicial</button>

    </form>
</div>

<?php require_once 'includes/footer.php'; // Admin footer ?>

<script>
    $(function() {
        var board = null; // La variable del tablero
        var fenInput = $('#fen');

        // Configuración inicial del tablero
        var config = {
            draggable: true,
            position: 'start',
            onDrop: onDrop,
            sparePieces: true // Permite añadir piezas desde fuera del tablero
        };
        board = Chessboard('board', config);

        // Función que se ejecuta cuando se mueve una pieza
        function onDrop (source, target) {
            // No se necesita lógica compleja aquí, solo actualizar el FEN
            updateFen();
        }

        // Función para actualizar el campo FEN
        function updateFen() {
            fenInput.val(board.fen());
        }

        // Botones de control
        $('#clearBoardBtn').on('click', function() {
            board.clear();
            updateFen();
        });

        $('#startPositionBtn').on('click', function() {
            board.start();
            updateFen();
        });

        // Actualizar el FEN al cargar la página
        updateFen();
    });
</script>

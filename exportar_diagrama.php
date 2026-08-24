<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    die("Acceso denegado. Inicie sesión.");
}

$problem_id = filter_input(INPUT_GET, 'problem_id', FILTER_VALIDATE_INT);

if (!$problem_id) {
    die("ID de problema no especificado.");
}

// Fetch problem details along with category and publication title
$sql = "
    SELECT 
        p.*, 
        c.nombre_categoria, 
        pub.titulo AS nombre_publicacion 
    FROM problemas p
    JOIN categorias c ON p.id_categorias = c.id_categorias
    JOIN publicacion pub ON c.id_publicacion = pub.id_publicacion
    WHERE p.id_problemas = ?
";

$problem = null;
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $problem_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $problem = $row;
    }
    $stmt->close();
}

if (!$problem) {
    die("Problema no encontrado.");
}

$fen = $problem['fen'];
$juega = ucfirst(strtolower($problem['juega']));
$tipo = !empty($problem['tipo_problema']) ? $problem['tipo_problema'] : 'Ejercicio de Táctica';
$dificultad = !empty($problem['dificultad']) ? $problem['dificultad'] : 'Normal';
$desarrollo = !empty($problem['desarrollo']) ? $problem['desarrollo'] : '';
$solucion = $problem['solucion'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha de Diagrama #<?php echo $problem['id_problemas']; ?> - Chess Trainer</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chessboard.js CSS -->
    <link rel="stylesheet" href="libreria_ajedrez/css/chessboard-1.0.0.min.css">

    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #1e293b;
        }

        .export-card {
            max-width: 680px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 35px;
            border: 1px solid #e2e8f0;
        }

        .export-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .logo-img {
            max-height: 48px;
        }

        .diagram-container {
            width: 100%;
            max-width: 440px;
            margin: 0 auto 25px auto;
            border: 4px solid #334155;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .enunciado-box {
            background-color: #f1f5f9;
            border-left: 5px solid #2563eb;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 25px;
        }

        .turn-badge {
            font-weight: 700;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
        }

        .turn-badge-blancas {
            background: #ffffff;
            color: #0f172a;
            border: 1px solid #cbd5e1;
        }

        .turn-badge-negras {
            background: #0f172a;
            color: #ffffff;
        }

        .solucion-box {
            background-color: #fafafa;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            padding: 14px 18px;
            font-size: 0.9rem;
            color: #64748b;
        }

        .solucion-inverted {
            transform: rotate(180deg);
            display: inline-block;
        }

        /* Estilos de Impresión */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background-color: #ffffff !important;
            }
            .export-card {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                margin: 0 auto !important;
                max-width: 100% !important;
            }
            .diagram-container {
                max-width: 480px !important;
            }
        }
    </style>
</head>
<body>

    <!-- BOTONES DE ACCIÓN (NO IMPRIMIBLES) -->
    <div class="container text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-success btn-lg fw-bold px-4 me-2 shadow-sm">
            <i class="fas fa-print me-2"></i> Imprimir Diagrama / Guardar PDF
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary btn-lg px-4 shadow-sm">
            <i class="fas fa-times me-2"></i> Cerrar
        </button>
    </div>

    <!-- TARJETA DE LA FICHA DEL DIAGRAMA -->
    <div class="export-card">
        
        <!-- ENCABEZADO -->
        <div class="export-header d-flex justify-content-between align-items-center">
            <div>
                <img src="img/logo_ct.svg" alt="Chess Trainer" class="logo-img mb-1">
                <span class="badge bg-primary ms-1 fs-6">2.0</span>
                <h5 class="fw-bold mb-0 text-primary mt-2">
                    <?php echo htmlspecialchars($problem['nombre_publicacion']); ?>
                </h5>
                <span class="text-muted small fw-semibold">
                    Categoría: <?php echo htmlspecialchars($problem['nombre_categoria']); ?>
                </span>
            </div>
            <div class="text-end">
                <span class="badge bg-dark fs-6 px-3 py-2">Diagrama #<?php echo $problem['id_problemas']; ?></span>
                <div class="text-warning small mt-1">
                    <i class="fas fa-star"></i> <?php echo htmlspecialchars($dificultad); ?>
                </div>
            </div>
        </div>

        <!-- ENUNCIADO DEL EJERCICIO -->
        <div class="enunciado-box">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="turn-badge <?php echo (strtolower($juega) === 'negras') ? 'turn-badge-negras' : 'turn-badge-blancas'; ?>">
                    <?php if (strtolower($juega) === 'negras'): ?>
                        <i class="fas fa-circle text-white"></i> Juegan Negras
                    <?php else: ?>
                        <i class="far fa-circle text-dark"></i> Juegan Blancas
                    <?php endif; ?>
                </span>
                <h6 class="fw-bold text-primary mb-0"><?php echo htmlspecialchars($tipo); ?></h6>
            </div>
            <p class="mb-0 text-secondary small">
                Encuentra la mejor secuencia de jugadas para las <?php echo htmlspecialchars(strtolower($juega)); ?> en esta posición.
            </p>
        </div>

        <!-- TABLERO DE AJEDREZ EN ALTA RESOLUCIÓN -->
        <div id="export-board" class="diagram-container"></div>

        <?php if (!empty($desarrollo)): ?>
            <div class="mb-3 p-3 bg-light rounded border small">
                <strong><i class="fas fa-info-circle me-1 text-info"></i> Notas tácticas:</strong>
                <div><?php echo nl2br(htmlspecialchars($desarrollo)); ?></div>
            </div>
        <?php endif; ?>

        <!-- SECCIÓN DE SOLUCIÓN AL PIE -->
        <div class="solucion-box d-flex justify-content-between align-items-center mt-4">
            <div>
                <i class="fas fa-key me-1 text-success"></i> <strong>Solución:</strong>
            </div>
            <div class="solucion-inverted fw-bold font-monospace text-dark fs-6">
                <?php echo htmlspecialchars($solucion); ?>
            </div>
        </div>

        <div class="text-center text-muted mt-3" style="font-size: 0.75rem;">
            Chess Trainer 2.0 &bull; Entrenador Personal de Ajedrez &bull; https://github.com/renan2013/chesstrainer.git
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="libreria_ajedrez/js/chessboard-1.0.0.min.js"></script>
    <script>
        $(document).ready(function() {
            var fenPosition = <?php echo json_encode($fen); ?>;
            var juega = <?php echo json_encode(strtolower($juega)); ?>;
            
            var config = {
                position: fenPosition,
                orientation: (juega === 'negras') ? 'black' : 'white',
                showNotation: true,
                pieceTheme: 'img/chesspieces/wikipedia/{piece}.png'
            };

            Chessboard('export-board', config);
        });
    </script>
</body>
</html>

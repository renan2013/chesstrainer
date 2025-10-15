<?php
require_once 'includes/db.php';

// --- Validar y obtener el ID de la categoría ---
if (!isset($_GET['category_id']) || !is_numeric($_GET['category_id'])) {
    die('Error: ID de categoría no válido.');
}
$category_id = (int)$_GET['category_id'];

// --- Consultar la base de datos para obtener los problemas (añadiendo dificultad) ---
$sql = "
    SELECT 
        p.fen, 
        p.juega, 
        p.dificultad,
        c.nombre_categoria
    FROM problemas p
    JOIN categorias c ON p.id_categorias = c.id_categorias
    WHERE p.id_categorias = ?
    ORDER BY FIELD(p.dificultad, 'Fácil', 'Intermedio', 'Difícil', 'Experto'), p.id_problemas ASC";

$problems = [];
$category_name = 'Clase de Tácticas';

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $problems[] = $row;
    }
    if (!empty($problems)) {
        $category_name = $problems[0]['nombre_categoria'];
    }
    $stmt->close();
}
$conn->close();

function get_stars($difficulty) {
    switch ($difficulty) {
        case 'Fácil': return '&#9733;';
        case 'Intermedio': return '&#9733;&#9733;';
        case 'Difícil': return '&#9733;&#9733;&#9733;';
        case 'Experto': return '&#9733;&#9733;&#9733;&#9733;';
        default: return '';
    }
}

// Generar la URL para el QR dinámicamente
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domain_name = $_SERVER['HTTP_HOST'];
$qr_url = $protocol . $domain_name . dirname($_SERVER['PHP_SELF']) . '/index.php';

// --- Obtener mes y año actual en español (método robusto) ---
$months = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
];
$current_month = $months[date('n') - 1];
$current_year = date('Y');
$date_string = " - " . $current_month . " " . $current_year;

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Descargar Clase: <?php echo htmlspecialchars($category_name); ?></title>
    <link rel="stylesheet" href="css/chessboard-1.0.0.min.css">
    <script src="https://cdn.jsdelivr.net/npm/davidshimjs-qrcodejs@0.0.2/qrcode.min.js"></script>
    <style>
        html, body {
            height: 100%; /* Asegura que html y body ocupen toda la altura */
            margin: 0; /* Elimina márgenes predeterminados */
            padding: 0; /* Elimina padding predeterminado */
            overflow-x: hidden; /* Evita el scroll horizontal */
        }
        .page {
            width: 210mm;
            /* height: 297mm; */ /* Eliminado para permitir que el contenido defina la altura */
            padding: 5mm 10mm; /* Padding reducido para más espacio */
            box-sizing: border-box;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* Restaurado para la visualización en pantalla */
            page-break-after: always; /* Asegura el salto de página */
            /* border: 1px solid purple; */ /* DEBUG */
        }
        .page:last-child { page-break-after: avoid; }
        @media print {
            body { -webkit-print-color-adjust: exact; color-adjust: exact; margin: 0 !important; }
            .no-print { display: none !important; }
            .page { 
                box-shadow: none; 
                margin: 0; 
                padding: 2mm 5mm; /* Padding reducido para impresión */
                width: 98%; /* Ligeramente menos del 100% para márgenes de impresora */
                display: block; /* Forzar a block para impresión */
                height: 297mm; /* Forzar altura de A4 para impresión */
            } 
        }
        body { font-family: sans-serif; margin: 0; background-color: #f0f0f0; }

        .diagram-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr); /* 3 columnas flexibles */
            grid-template-rows: repeat(3, 1fr);    /* 3 filas flexibles */
            gap: 1mm; /* Espacio entre diagramas ligeramente aumentado para impresión */
            flex-grow: 1; /* Restaurado para la visualización en pantalla */
            width: 100%; /* Ocupa el 100% del espacio disponible */
            height: 100%; /* Restaurado para la visualización en pantalla */
            box-sizing: border-box;
            margin: 0 auto; /* Centra el grid */
        }
        .diagram-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 0;
            box-sizing: border-box;
            height: 100%; /* Restaurado para la visualización en pantalla */
            position: relative;
            width: 88%; /* Ampliado un 10% */
            margin-bottom: 3mm; /* Espacio para respuestas reducido */
            page-break-inside: avoid; /* Evita que el diagrama se divida entre páginas */
        }
        .diagram-header {
            width: 88%; /* Alineado con el ancho del diagrama */
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1mm;
            font-size: 0.616em; /* Ampliado un 10% */
            flex-shrink: 0;
            height: 8mm;
        }
        .diagram-container {
            width: 88%; /* Ampliado un 10% */
            padding-bottom: 88%; /* Mantiene la relación de aspecto cuadrada */
            position: relative;
            filter: grayscale(100%);
            flex-shrink: 0;
            height: 0;
            overflow: hidden;
        }
        .diagram-container #board- {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%; /* El tablero llenará este espacio */
        }
        .answer-lines {
            width: 100%; /* Mismo ancho que el diagrama */
            margin-top: 2mm; /* Distancia de la primera línea al diagrama reducida */
            text-align: left;
        }
        .answer-lines hr {
            border: none;
            border-top: 1px dashed #ccc;
            margin: 4mm 0;
        }
        .page-header {
            display: flex;
            justify-content: space-between; /* Distribuye el espacio entre las columnas */
            align-items: center; /* Centra verticalmente los elementos */
            width: 100%;
            margin-top: 10mm; /* Mayor espacio de margin-top */
            margin-bottom: 5mm; /* Espacio debajo del encabezado */
        }
        .header-left-col {
            display: flex;
            align-items: center;
            justify-content: flex-start; /* Alinea el logo a la izquierda */
            flex: 1; /* Ocupa el espacio disponible */
        }
        .header-center-col {
            flex: 2; /* Ocupa más espacio para el título */
            text-align: center;
        }
        .header-right-col {
            display: flex;
            flex-direction: row; /* Alinea los elementos horizontalmente */
            align-items: center; /* Centra verticalmente los elementos */
            justify-content: flex-end; /* Alinea los elementos a la derecha */
            flex: 1; /* Ocupa el espacio disponible */
        }
        .contact-info {
            font-size: 0.9em; /* Tamaño de fuente aumentado */
            text-align: right; /* Alinea el texto a la derecha */
            margin-right: 2mm; /* Espacio entre la info de contacto y el QR */
            margin-top: 0; /* Eliminar margin-top si está en la misma línea que el QR */
        }
        .qrcode-container {
            width: 20mm; /* Tamaño del QR reducido */
            height: 20mm;
        }
        .page-footer {
            text-align: right;
            width: 100%;
            margin-top: 5mm; /* Espacio encima del pie de página */
            font-size: 0.8em;
        }
        .category-title {
            font-size: 1.2em;
            font-weight: bold;
            margin: 0;
        }
        .author {
            font-size: 0.7em;
            margin: 0;
        }
        .turn-indicator {
            width: 8px;
            height: 8px;
            border-radius: 0; /* Cuadrado */
            border: 1px solid #333;
        }
        .turn-indicator.white {
            background-color: white;
        }
        .turn-indicator.black {
            background-color: black;
        }
        /* Ajustes para el tablero de ajedrez */
        .chessboard-63129 { /* Clase generada por chessboard.js, puede variar */
            width: 100% !important;
            height: 100% !important;
        }
        .page-break {
            page-break-before: always;
        }
        .page-break:first-child {
            page-break-before: avoid;
        }
        .logo {
            height: 9mm; /* Tamaño del logo reducido un 40% */
            margin-right: 5mm;
        }
        /* Ajustes para los colores del tablero de ajedrez */
        .white-1e1d7 {
            background-color: #ffffff !important; /* Cuadros blancos */
        }
        .black-3c85d {
            background-color: #b0b0b0 !important; /* Cuadros grises más oscuros */
        }
    </style>
        
</head>
<body>

<div class="print-header no-print">
    <h1>Clase de Tácticas: <?php echo htmlspecialchars($category_name); ?></h1>
    <p>Haz clic en el botón para preparar la impresión o guardar como PDF.</p>
    <button onclick="window.print();">Imprimir o Guardar como PDF</button>
    <a href="index.php">Volver</a>
</div>

<?php
$problem_count = count($problems);
$diagrams_per_page = 9;

for ($i = 0; $i < $problem_count; $i++):
    if ($i % $diagrams_per_page === 0) {
        if ($i > 0) {
            echo '<div class="page-footer"><span class="page-number">Página ' . (floor(($i-1) / $diagrams_per_page) + 1) . '</span></div></div>'; // Cierra .diagram-grid y .page
        }
        echo "<div class='page page-break'>";
        echo '<div class="page-header">';
        echo '    <div class="header-left-col">';
        echo '        <img src="https://ajedrezpuriscal.com/webmanager4.0/Sailor/assets/SVG/logo_ajedrez_2.svg" alt="Logo" class="logo">';
        echo '    </div>';
        echo '    <div class="header-center-col">';
        echo '        <p class="category-title">' . htmlspecialchars($category_name) . '</p>';
        echo '    </div>';
        echo '    <div class="header-right-col">';
        echo '        <div class="contact-info">';
        echo '            <p class="author">developed by renangalvan.net</p>';
        echo '            <p class="author">+506 8777-7849' . $date_string . '</p>';
        echo '        </div>';
        echo '        <div class="qrcode-container" id="qrcode-' . floor($i / $diagrams_per_page) . '"></div>';
        echo '    </div>';
        echo '</div>';
        echo "<div class='diagram-grid'>";
    }
?>
    <div class="diagram-wrapper">
        <div class="diagram-header">
            <span class="diagram-number"><?php echo $i + 1; ?></span>
            <span class="diagram-difficulty"><?php echo get_stars($problems[$i]['dificultad']); ?></span>
            <div class="turn-indicator <?php echo ($problems[$i]['juega'] === 'blancas') ? 'white' : 'black'; ?>"></div>
        </div>
        <div class="diagram-container">
            <div id="board-<?php echo $i; ?>"></div>
        </div>
        <div class="answer-lines">
            <hr>
            <hr>
            <hr>
        </div>
    </div>
<?php
endfor;

if ($problem_count > 0) {
    echo '<div class="page-footer"><span class="page-number">Página ' . (floor(($i-1) / $diagrams_per_page) + 1) . '</span></div></div>'; // Cierra los divs de la última página
}
?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="js/chessboard-1.0.0.min.js"></script>
<script>
    $(document).ready(function() {
        // --- Renderizar los tableros estáticos ---
        <?php foreach ($problems as $index => $problem): ?>
        var config_<?php echo $index; ?> = {
            position: '<?php echo $problem['fen']; ?>',
            showNotation: false
        };
        var board_<?php echo $index; ?> = Chessboard('board-<?php echo $index; ?>', config_<?php echo $index; ?>);
        $(window).on('resize', board_<?php echo $index; ?>.resize);
        <?php endforeach; ?>

        // --- Generar los Códigos QR ---
        var qrUrl = "<?php echo $qr_url; ?>";
        var numPages = Math.ceil(<?php echo $problem_count; ?> / <?php echo $diagrams_per_page; ?>);
        for (var i = 0; i < numPages; i++) {
            var qrcodeContainer = document.getElementById("qrcode-" + i);
            if (qrcodeContainer) {
                new QRCode(qrcodeContainer, {
                    text: qrUrl,
                    width: 51,
                height: 51,
                    colorDark : "#000000",
                    colorLight : "#ffffff",
                    correctLevel : QRCode.CorrectLevel.H
                });
            }
        }
    });
</script>

</body>
</html>
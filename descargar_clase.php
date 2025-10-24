<?php
session_start();
require_once 'db_connect.php';
require('libreria_ajedrez/fpdf/fpdf.php');

// 1. Security & Validation
// =================================================================
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
    die("Acceso denegado. Esta función es solo para administradores.");
}
if (!isset($_GET['id_categoria']) || !is_numeric($_GET['id_categoria'])) {
    die("ID de categoría no válido.");
}
$id_categoria = (int)$_GET['id_categoria'];

// 2. Data Fetching
// =================================================================
$category_name = '';
$id_publicacion = null;
$nombre_publicacion = '';

// Fetch category name and publication ID
$stmt_cat = $conn->prepare("SELECT nombre_categoria, id_publicacion FROM categorias WHERE id_categorias = ?");
$stmt_cat->bind_param("i", $id_categoria);
$stmt_cat->execute();
$result_cat = $stmt_cat->get_result();
if (!($row_cat = $result_cat->fetch_assoc())) {
    die("Categoría no encontrada.");
}
$category_name = $row_cat['nombre_categoria'];
$id_publicacion = $row_cat['id_publicacion'];
$stmt_cat->close();

// Fetch publication name
if ($id_publicacion) {
    $stmt_pub = $conn->prepare("SELECT titulo FROM publicacion WHERE id_publicacion = ?");
    $stmt_pub->bind_param("i", $id_publicacion);
    $stmt_pub->execute();
    $result_pub = $stmt_pub->get_result();
    if ($row_pub = $result_pub->fetch_assoc()) {
        $nombre_publicacion = $row_pub['titulo'];
    }
    $stmt_pub->close();
}

$stmt_prob = $conn->prepare("SELECT fen, juega FROM problemas WHERE id_categorias = ? ORDER BY orden ASC, id_problemas ASC");
$stmt_prob->bind_param("i", $id_categoria);
$stmt_prob->execute();
$problems = $stmt_prob->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_prob->close();
$conn->close();

if (empty($problems)) {
    die("No hay problemas en esta categoría para exportar.");
}

// 3. Image Generation Function (using GD)
// =================================================================
function generateBoardImage($fen, $problem_index, $turn)
{
    $square_size = 50; // 50x50 pixels per square
    $board_size = 8 * $square_size;
    $text_height = 20; // Height for the text area above the board
    $total_image_height = $board_size + $text_height;

    $path_piezas = __DIR__ . '/img/chesspieces/wikipedia/';
    $temp_dir = __DIR__ . '/admin/uploads/temp_diagrams/';

    // Ensure the temporary directory exists
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0775, true);
    }

    // Create blank board canvas with extra space for text
    $board_img = imagecreatetruecolor($board_size, $total_image_height);
    $light_color = imagecolorallocate($board_img, 240, 217, 181); // Light square color
    $dark_color = imagecolorallocate($board_img, 181, 136, 99);  // Dark square color
    $text_bg_color = imagecolorallocate($board_img, 50, 50, 50); // Dark background for text
    $text_color = imagecolorallocate($board_img, 255, 255, 255); // White text

    // Fill the text background area
    imagefilledrectangle($board_img, 0, 0, $board_size, $text_height, $text_bg_color);

    // Add text (Problem number and turn) at the top
    $text_to_display = 'Diag. ' . ($problem_index + 1) . ' - Juegan: ' . ucfirst($turn);
    $font_size = 3; // GD font size (1-5)
    $text_width = imagefontwidth($font_size) * strlen($text_to_display);
    $text_x = ($board_size - $text_width) / 2; // Center the text
    imagestring($board_img, $font_size, (int)$text_x, 3, $text_to_display, $text_color);

    // Draw board squares below the text area
    for ($row = 0; $row < 8; $row++) {
        for ($col = 0; $col < 8; $col++) {
            $color = (($row + $col) % 2 == 0) ? $light_color : $dark_color;
            imagefilledrectangle($board_img, $col * $square_size, $row * $square_size + $text_height, ($col + 1) * $square_size, ($row + 1) * $square_size + $text_height, $color);
        }
    }

    // Parse FEN and place pieces
    $fen_parts = explode(' ', $fen);
    $fen_board = $fen_parts[0];
    $rows = explode('/', $fen_board);

    for ($row = 0; $row < 8; $row++) {
        $col = 0;
        $fen_row = $rows[$row];
        for ($i = 0; $i < strlen($fen_row); $i++) {
            $char = $fen_row[$i];
            if (ctype_digit($char)) {
                $col += (int)$char;
            } else {
                $color = ctype_upper($char) ? 'w' : 'b';
                $piece_type = strtoupper($char);
                $piece_file = $path_piezas . $color . $piece_type . '.png';

                if (file_exists($piece_file)) {
                    $piece_img = imagecreatefrompng($piece_file);
                    // Resize piece image to square_size if necessary (assuming original is larger)
                    imagecopyresampled($board_img, $piece_img, $col * $square_size, $row * $square_size + $text_height, 0, 0, $square_size, $square_size, imagesx($piece_img), imagesy($piece_img));
                    imagedestroy($piece_img);
                }
                $col++;
            }
        }
    }

    // Save final image to a temporary file
    $output_file = $temp_dir . uniqid('board_', true) . '.png';
    imagepng($board_img, $output_file);
    imagedestroy($board_img);

    return $output_file;
}

// 4. PDF Generation Class & Logic
// =================================================================
class PDF extends FPDF
{
    // Page header
    function Header()
    {
        global $category_name, $nombre_publicacion;

        // Logo Izquierdo (Chess Trainer Logo)
        $logo_left_path = __DIR__ . '/img/chess_trainer_logo.png'; // Assuming this is the path
        if (file_exists($logo_left_path)) {
            $this->Image($logo_left_path, 10, 8, 30); // X, Y, Width
        }
        
        // Logo Derecho (car_ajedrez.png)
        $logo_right_path = __DIR__ . '/img/car_ajedrez.png'; // Assuming this is the path
        if (file_exists($logo_right_path)) {
            $this->Image($logo_right_path, $this->GetPageWidth() - 40, 8, 30); // X, Y, Width (adjust X for right alignment)
        }

        // Title: Publication Name - Category Name
        $this->SetFont('Arial', 'B', 15);
        $this->SetY(20); // Position below logos
        $this->Cell(0, 7, utf8_decode($nombre_publicacion . ' - ' . $category_name), 0, 1, 'C');

        // Date
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, utf8_decode(date('d/m/Y')), 0, 1, 'C');
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Pagina ' . $this->PageNo() . '/{nb} - developed by renangalvan.net'), 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Layout calculations for 12 diagrams (3 columns, 4 rows)
$margin = 10; // mm
$usable_width = 210 - (2 * $margin); // A4 width - left/right margin
$usable_height = 297 - 30 - 15; // A4 height - header space - footer space (approx)

$num_cols = 3;
$num_rows = 4;
$diagrams_per_page = $num_cols * $num_rows;

$padding_h = 5; // Horizontal padding between images
$padding_v = 5; // Vertical padding between images

$image_width_mm = ($usable_width - (($num_cols - 1) * $padding_h)) / $num_cols;
$image_height_mm = ($usable_height - (($num_rows - 1) * $padding_v)) / $num_rows;

// Ensure image_width_mm and image_height_mm are roughly equal for square diagrams
$image_size_mm = min($image_width_mm, $image_height_mm); // Use the smaller dimension to maintain aspect ratio

$x_positions = [];
for ($i = 0; $i < $num_cols; $i++) {
    $x_positions[] = $margin + ($i * ($image_size_mm + $padding_h));
}

$y_start_content = 30; // Start content below header/logo

$problem_count = 0;
$temp_files = [];

foreach ($problems as $index => $problem) {
    if ($problem_count > 0 && $problem_count % $diagrams_per_page == 0) {
        $pdf->AddPage();
    }

    $grid_pos = $problem_count % $diagrams_per_page;
    $col = $grid_pos % $num_cols;
    $row = floor($grid_pos / $num_cols);

    $x = $x_positions[$col];
    $y = $y_start_content + ($row * ($image_size_mm + $padding_v));

    // Generate the board image for the current problem
    $image_path = generateBoardImage($problem['fen'], $index, $problem['juega']);
    $temp_files[] = $image_path;

    // Place the image in the PDF
    $pdf->Image($image_path, $x, $y, $image_size_mm, $image_size_mm, 'PNG');

    $problem_count++;
}

// 5. Output and Cleanup
// =================================================================
$pdf->Output('D', 'Clase-' . preg_replace("/[^a-zA-Z0-9]+/", "", $category_name) . '.pdf');

// Clean up temporary image files
foreach ($temp_files as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}

exit;
?>
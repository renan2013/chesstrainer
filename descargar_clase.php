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

// MODIFIED: Fetch 'dificultad' for each problem
$stmt_prob = $conn->prepare("SELECT fen, juega, dificultad FROM problemas WHERE id_categorias = ? ORDER BY orden ASC, id_problemas ASC");
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
function generateBoardImage($fen, $problem_index, $turn, $difficulty)
{
    $square_size = 50; // 50x50 pixels per square
    $board_size = 8 * $square_size;
    $path_piezas = __DIR__ . '/img/chesspieces/wikipedia/';
    $temp_dir = __DIR__ . '/admin/uploads/temp_diagrams/';

    // Ensure the temporary directory exists
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0775, true);
    }

    // Create blank board canvas (no extra space for text at top)
    $board_img = imagecreatetruecolor($board_size, $board_size);
    
    // Colors for board, text, and indicators
    $light_square_color = imagecolorallocate($board_img, 255, 255, 255); // White
    $dark_square_color = imagecolorallocate($board_img, 150, 150, 150);  // Gray
    $text_color = imagecolorallocate($board_img, 0, 0, 0); // Black text
    $star_color = imagecolorallocate($board_img, 0, 0, 0); // Black for stars
    $white_piece_color_indicator = imagecolorallocate($board_img, 255, 255, 255);
    $black_piece_color_indicator = imagecolorallocate($board_img, 0, 0, 0);
    $border_color = imagecolorallocate($board_img, 0, 0, 0); // Black border

    // Draw board squares
    for ($row = 0; $row < 8; $row++) {
        for ($col = 0; $col < 8; $col++) {
            $color = (($row + $col) % 2 == 0) ? $light_square_color : $dark_square_color;
            imagefilledrectangle($board_img, $col * $square_size, $row * $square_size, ($col + 1) * $square_size, ($row + 1) * $square_size, $color);
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
                    // Ensure transparency for PNGs
                    imagealphablending($board_img, true);
                    imagesavealpha($board_img, true);
                    imagecopyresampled($board_img, $piece_img, $col * $square_size, $row * $square_size, 0, 0, $square_size, $square_size, imagesx($piece_img), imagesy($piece_img));
                    imagedestroy($piece_img);
                }
                $col++;
            }
        }
    }

    // Add Board Border
    imagerectangle($board_img, 0, 0, $board_size - 1, $board_size - 1, $border_color);

    // Difficulty Stars (Top-Left)
    $num_stars = 0;
    switch ($difficulty) {
        case 'Fácil': $num_stars = 1; break;
        case 'Intermedio': $num_stars = 2; break;
        case 'Difícil': $num_stars = 3; break;
        case 'Experto': $num_stars = 4; break;
    }
    $star_x_start = 5; // Start drawing stars from left
    $star_y = 5; // Y position for stars
    for ($i = 0; $i < $num_stars; $i++) {
        imagestring($board_img, 2, $star_x_start + ($i * 10), $star_y, '*', $star_color); // Use '*' for stars
    }

    // Add Diagram Number and Turn Text (Top-Right)
    $text_diag_turn = ($problem_index + 1) . ' - '; 
    $font_size = 3; // GD font size (1-5)
    $text_diag_turn_width = imagefontwidth($font_size) * strlen($text_diag_turn);
    $text_diag_turn_x = $board_size - $text_diag_turn_width - 20; // Position from right edge
    imagestring($board_img, $font_size, (int)$text_diag_turn_x, $star_y, $text_diag_turn, $text_color);

    // Add Color Indicator Square for Turn (Next to Diagram Number)
    $square_indicator_size = 10;
    $square_indicator_x = (int)$text_diag_turn_x + $text_diag_turn_width + 2; // Position right after text
    $square_indicator_y = $star_y; 
    $color_indicator = (strtolower($turn) == 'blancas') ? $white_piece_color_indicator : $black_piece_color_indicator;
    imagefilledrectangle($board_img, $square_indicator_x, $square_indicator_y, $square_indicator_x + $square_indicator_size, $square_indicator_y + $square_indicator_size, $color_indicator);

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
        $right_logo_x = $this->GetPageWidth() - 40; // Adjust X for right alignment
        if (file_exists($logo_right_path)) {
            $this->Image($logo_right_path, $right_logo_x, 8, 30); // X, Y, Width
        }

        // Title: Publication Name - Category Name
        $this->SetFont('Arial', 'B', 15);
        $this->SetY(20); // Position below logos
        $this->Cell(0, 7, utf8_decode($nombre_publicacion . ' - ' . $category_name), 0, 1, 'C');

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

// Layout calculations for 9 diagrams (3 columns, 3 rows)
$margin = 10; // mm
$usable_width = 210 - (2 * $margin); // A4 width - left/right margin
$usable_height = 297 - 30 - 15; // A4 height - header space - footer space (approx)

$num_cols = 3;
$num_rows = 3;
$diagrams_per_page = $num_cols * $num_rows;

$image_size_mm = 55; // Diagram size
$solution_space_mm = 20; // Space for student to write solution (2 cm)

// Calculate padding based on new image size and solution space
$padding_h = ($usable_width - ($num_cols * $image_size_mm)) / ($num_cols - 1); // Horizontal padding
$total_row_height = $image_size_mm + $solution_space_mm; // Total height for a diagram slot including solution space
$padding_v = ($usable_height - ($num_rows * $total_row_height)) / ($num_rows - 1); // Vertical padding

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
    $y = $y_start_content + ($row * ($total_row_height + $padding_v));

    // Generate the board image for the current problem
    // Pass the difficulty to the function
    $image_path = generateBoardImage($problem['fen'], $index, $problem['juega'], $problem['dificultad']);
    $temp_files[] = $image_path;

    // Place the image in the PDF
    $pdf->Image($image_path, $x, $y, $image_size_mm, $image_size_mm, 'PNG');

    // Add solution lines below the diagram
    $pdf->SetDrawColor(0, 0, 0); // Black lines
    $line_x_start = $x; // Start line at diagram's X position
    $line_width = $image_size_mm; // Line width same as diagram
    $line_spacing = 5; // 5mm between lines
    $line_y_start = $y + $image_size_mm + 2; // 2mm below diagram

    for ($i = 0; $i < 3; $i++) {
        $pdf->Line($line_x_start, $line_y_start + ($i * $line_spacing), $line_x_start + $line_width, $line_y_start + ($i * $line_spacing));
    }

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
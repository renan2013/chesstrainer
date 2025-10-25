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
function generateBoardImage($fen)
{
    $square_size = 50; // 50x50 pixels per square
    $board_size = 8 * $square_size;

    $path_piezas = __DIR__ . '/img/chesspieces/wikipedia/';
    $temp_dir = __DIR__ . '/admin/uploads/temp_diagrams/';

    // Ensure the temporary directory exists
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0775, true);
    }

    // Create blank board canvas (just the board, no extra space for text)
    $board_img = imagecreatetruecolor($board_size, $board_size);
    
    // Colors for board
    $light_square_color = imagecolorallocate($board_img, 255, 255, 255); // White
    $dark_square_color = imagecolorallocate($board_img, 200, 200, 200);  // Lighter Gray
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

        $logo_width = 30; // Reverted to original size
        $logo_height = 10; // New requirement
        $logo_y = 8; // Top position for logos

        // Left Logo (Chess Trainer Logo)
        $logo_left_path = __DIR__ . '/img/chess_trainer_logo.png';
        if (file_exists($logo_left_path)) {
            $this->Image($logo_left_path, 10, $logo_y, $logo_width, $logo_height); 
        }
        
        // Right Logo (car_ajedrez.png)
        $logo_right_path = __DIR__ . '/img/car_ajedrez.png';
        $right_logo_x = $this->GetPageWidth() - 10 - $logo_width; // Right margin 10mm
        if (file_exists($logo_right_path)) {
            $this->Image($logo_right_path, $right_logo_x, $logo_y, $logo_width, $logo_height); 
        }

        // Title: Publication Name - Category Name (Centered)
        $title_text = utf8_decode($nombre_publicacion . ' - ' . $category_name);
        $this->SetFont('Arial', 'B', 12); // Smaller font
        $title_width = $this->GetStringWidth($title_text); 
        $title_x = ($this->GetPageWidth() - $title_width) / 2; // Center horizontally
        $title_y = $logo_y + ($logo_height / 2) - (7 / 2); // Vertically center with logos (7 is approx font height)
        
        $this->SetXY($title_x, $title_y); 
        $this->Cell($title_width, 7, $title_text, 0, 0, 'C');

        // Move cursor below the header elements for content
        $this->SetY($logo_y + $logo_height + 2); // 2mm below the bottom of the logos
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Pagina ' . $this->PageNo() . '/{nb} - developed by renangalvan.net - 2025'), 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Layout calculations for 9 diagrams (3 columns, 3 rows)
$margin = 10; // mm
$usable_width = 210 - (2 * $margin); // A4 width - left/right margin
$usable_height = 297 - 20 - 15; // A4 height - header end Y - footer space (20mm is $logo_y + $logo_height + 2)

$num_cols = 3;
$num_rows = 3;
$diagrams_per_page = $num_cols * $num_rows;

$image_size_mm = 55; // Diagram size
$solution_space_mm = 13; // Space for student to write solution (adjusted for even rows)

// Calculate padding based on new image size and solution space
$padding_h = ($usable_width - ($num_cols * $image_size_mm)) / ($num_cols - 1); // Horizontal padding
$total_row_height = $image_size_mm + $solution_space_mm; // Total height for a diagram slot including solution space
$padding_v = 20.7; // Increased by 7% from 19.33mm (approx)

$x_positions = [];
for ($i = 0; $i < $num_cols; $i++) {
    $x_positions[] = $margin + ($i * ($image_size_mm + $padding_h));
}

$y_start_content = 22; // Start content below header/logo (2mm below header end)

$problem_count = 0;
$temp_files = [];

foreach ($problems as $index => $problem) {
    if ($problem_count > 0 && $problem_count % $diagrams_per_page == 0) {
        $pdf->AddPage();
        $pdf->SetY($y_start_content); // Reset Y position for new page
    }

    $grid_pos = $problem_count % $diagrams_per_page;
    $col = $grid_pos % $num_cols;
    $row = floor($grid_pos / $num_cols);

    $x = $x_positions[$col];
    $y = $y_start_content + ($row * ($total_row_height + $padding_v));

    // --- Diagram Description (outside board) ---
    $stars_text = str_repeat('*', (function($d){ 
        switch ($d) { 
            case 'Fácil': return 1; 
            case 'Intermedio': return 2; 
            case 'Difícil': return 3; 
            case 'Experto': return 4; 
            default: return 0; 
        } 
    })($problem['dificultad']));

    $description_text = utf8_decode(($index + 1) . ' ' . $stars_text);

    $pdf->SetFont('Arial', 'B', 10); // Font for description
    
    // Calculate width of description text
    $text_width_mm = $pdf->GetStringWidth($description_text); 
    $square_size_mm = 3; // Size of the color square
    $gap_between_text_square = 2; // Gap between text and square

    // Total width of the description block (text + gap + square)
    $total_desc_block_width = $text_width_mm + $gap_between_text_square + $square_size_mm;

    // Calculate X position to center the entire block above the diagram
    $desc_block_x = $x + (($image_size_mm - $total_desc_block_width) / 2);

    // Set position for the text
    $pdf->SetXY($desc_block_x, $y); 
    $pdf->Cell($text_width_mm, 5, $description_text, 0, 0, 'L'); // Left aligned within its cell

    // Turn Indicator Square (next to description text)
    $turn_indicator_color = (strtolower($problem['juega']) == 'blancas') ? [255, 255, 255] : [0, 0, 0];
    $pdf->SetFillColor($turn_indicator_color[0], $turn_indicator_color[1], $turn_indicator_color[2]);
    $pdf->SetDrawColor(0, 0, 0); // Black border
    
    // Position the square right after the text
    $square_x_pos = $desc_block_x + $text_width_mm + $gap_between_text_square;
    $square_y_pos = $y + 1; // Align with text vertically

    $pdf->Rect($square_x_pos, $square_y_pos, $square_size_mm, $square_size_mm, 'FD'); // Filled with border

    // Generate the board image for the current problem
    $image_path = generateBoardImage($problem['fen']); // No text/indicators in image
    $temp_files[] = $image_path;

    // Place the image in the PDF (adjust Y to be below description text)
    $pdf->Image($image_path, $x, $y + 5, $image_size_mm, $image_size_mm, 'PNG');

    // Add solution lines below the diagram
    $pdf->SetDrawColor(0, 0, 0); // Black lines
    $line_x_start = $x; // Start line at diagram's X position
    $line_width = $image_size_mm; // Line width same as diagram
    $line_spacing = 7; // Increased line spacing
    $line_y_start = $y + 5 + $image_size_mm + 6; // 6mm below diagram

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
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
$stmt_cat = $conn->prepare("SELECT nombre_categoria FROM categorias WHERE id_categorias = ?");
$stmt_cat->bind_param("i", $id_categoria);
$stmt_cat->execute();
$result_cat = $stmt_cat->get_result();
if (!($row_cat = $result_cat->fetch_assoc())) {
    die("Categoría no encontrada.");
}
$category_name = $row_cat['nombre_categoria'];
$stmt_cat->close();

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
    $path_piezas = __DIR__ . '/img/chesspieces/wikipedia/';
    $temp_dir = __DIR__ . '/admin/uploads/temp_diagrams/';

    // Create blank board canvas
    $board_img = imagecreatetruecolor($board_size, $board_size);
    $light_color = imagecolorallocate($board_img, 240, 217, 181); // Light square color
    $dark_color = imagecolorallocate($board_img, 181, 136, 99);  // Dark square color

    for ($row = 0; $row < 8; $row++) {
        for ($col = 0; $col < 8; $col++) {
            $color = (($row + $col) % 2 == 0) ? $light_color : $dark_color;
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
                    imagecopyresampled($board_img, $piece_img, $col * $square_size, $row * $square_size, 0, 0, $square_size, $square_size, imagesx($piece_img), imagesy($piece_img));
                    imagedestroy($piece_img);
                }
                $col++;
            }
        }
    }
    
    // Add text (Problem number and turn)
    $text_color = imagecolorallocate($board_img, 255, 255, 255);
    $bg_color = imagecolorallocatealpha($board_img, 0, 0, 0, 50);
    imagefilledrectangle($board_img, 0, $board_size - 20, $board_size, $board_size, $bg_color);
    imagestring($board_img, 5, 5, $board_size - 18, 'Diag: ' . ($problem_index + 1) . ' - Juegan: ' . ucfirst($turn), $text_color);

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
    function Header()
    {
        global $category_name;
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Clase de Ajedrez - ' . utf8_decode($category_name), 0, 1, 'C');
        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

$margin = 10;
$image_size = 60; // 60mm x 60mm
$padding = 5;

$x_positions = [$margin, $margin + $image_size + $padding, $margin + 2 * ($image_size + $padding)];
$y_start = $pdf->GetY();

$problem_count = 0;
$temp_files = [];

foreach ($problems as $index => $problem) {
    if ($problem_count > 0 && $problem_count % 9 == 0) {
        $pdf->AddPage();
    }

    $grid_pos = $problem_count % 9;
    $col = $grid_pos % 3;
    $row = floor($grid_pos / 3);

    $x = $x_positions[$col];
    $y = $y_start + ($row * ($image_size + $padding + 5));

    // Generate the board image for the current problem
    $image_path = generateBoardImage($problem['fen'], $index, $problem['juega']);
    $temp_files[] = $image_path;

    // Place the image in the PDF
    $pdf->Image($image_path, $x, $y, $image_size, $image_size, 'PNG');

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
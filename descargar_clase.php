<?php
session_start();
require_once 'db_connect.php';

// 1. Security Check: Ensure user is an administrator
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'administrador') {
    die("Acceso denegado. Esta función es solo para administradores.");
}

// 2. Validate Category ID
if (!isset($_GET['id_categoria']) || !is_numeric($_GET['id_categoria'])) {
    die("ID de categoría no válido.");
}
$id_categoria = (int)$_GET['id_categoria'];

// 3. Fetch Category and Problems Data
$category_name = '';
$problems = [];

// Fetch category name
$stmt_cat = $conn->prepare("SELECT nombre_categoria FROM categorias WHERE id_categorias = ?");
$stmt_cat->bind_param("i", $id_categoria);
$stmt_cat->execute();
$result_cat = $stmt_cat->get_result();
if ($row_cat = $result_cat->fetch_assoc()) {
    $category_name = $row_cat['nombre_categoria'];
} else {
    die("Categoría no encontrada.");
}
$stmt_cat->close();

// Fetch problems
$stmt_prob = $conn->prepare("SELECT fen, juega, solucion FROM problemas WHERE id_categorias = ? ORDER BY orden ASC, id_problemas ASC");
$stmt_prob->bind_param("i", $id_categoria);
$stmt_prob->execute();
$result_prob = $stmt_prob->get_result();
while ($row_prob = $result_prob->fetch_assoc()) {
    $problems[] = $row_prob;
}
$stmt_prob->close();
$conn->close();

if (empty($problems)) {
    die("No hay problemas en esta categoría para exportar.");
}

// 4. PDF Generation using FPDF
require('libreria_ajedrez/fpdf/fpdf.php');

class PDF extends FPDF
{
    // Page header
    function Header()
    {
        global $category_name;
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(0, 10, 'Clase de Ajedrez - ' . utf8_decode($category_name), 0, 1, 'C');
        $this->Ln(5);
    }

    // Page footer
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
$pdf->SetFont('Arial', '', 10);

// 5. Layout and Image Placement
$margin = 10;
$image_size = 60; // 60mm x 60mm for each diagram
$padding = 5; // Padding between images

// 3x3 grid positions
$x_positions = [$margin, $margin + $image_size + $padding, $margin + 2 * ($image_size + $padding)];
$y_positions = [$pdf->GetY(), $pdf->GetY() + $image_size + $padding + 10, $pdf->GetY() + 2 * ($image_size + $padding + 10)];

$problem_count = 0;

foreach ($problems as $index => $problem) {
    if ($problem_count > 0 && $problem_count % 9 == 0) {
        $pdf->AddPage();
    }

    $grid_pos = $problem_count % 9;
    $col = $grid_pos % 3;
    $row = floor($grid_pos / 3);

    $x = $x_positions[$col];
    $y = $y_positions[$row];

    // Generate FEN image URL
    // Note: Using an external service. Make sure this service is reliable.
    $fen_encoded = rawurlencode($problem['fen']);
    $image_url = "https://chessboardimage.com/{$fen_encoded}.png";

    // Place the image
    // Use @ to suppress warnings if the image URL is temporarily unavailable
    @$pdf->Image($image_url, $x, $y, $image_size, $image_size, 'PNG');

    // Add problem number and who plays
    $pdf->SetXY($x, $y + $image_size);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($image_size, 5, 'Diagrama ' . ($index + 1), 0, 2, 'C');
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell($image_size, 5, 'Juegan: ' . ucfirst($problem['juega']), 0, 0, 'C');

    $problem_count++;
}

// 6. Output the PDF
$pdf->Output('D', 'Clase-' . preg_replace("/[^a-zA-Z0-9]+/", "", $category_name) . '.pdf');
exit;
?>
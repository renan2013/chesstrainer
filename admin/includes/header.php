<?php
session_start();
date_default_timezone_set('America/Costa_Rica');

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: ../login.php");
    exit;
}

try {
    require_once '../includes/db.php';
} catch (Exception $e) {
    echo "<div style='color: red; background-color: yellow; padding: 10px; border: 1px solid red;'>Error de Base de Datos: " . htmlspecialchars($e->getMessage()) . "</div>";
    // Optionally, you might want to exit here if DB connection is critical
    // exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Panel de Administración'; ?> - Chess Trainer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../css/style.css">
    
</head>
<body style="background-color: #f8f8f8;">
        <?php require_once 'includes/sidebar.php'; ?>
        <!-- El logo superior ha sido eliminado para dar prioridad al menú lateral. -->

        <!-- La barra de navegación superior ha sido eliminada. -->
<?php
session_start();
date_default_timezone_set('America/Costa_Rica');

require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Chess Trainer'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="css/chessboard-1.0.0.min.css">
    <link rel="stylesheet" href="css/style.css?v=<?php echo time(); ?>">
    <style>
        .list-group-item .bi {
            font-family: "bootstrap-icons" !important; /* Fuerza la fuente del icono */
            color: #333 !important; /* Fuerza un color oscuro */
            font-size: 1.1em !important; /* Asegura un tamaño visible */
            display: inline-block !important; /* Asegura que el elemento se renderice */
            vertical-align: -0.125em; /* Alineación vertical */
        }
    </style>
    </head>
<body class="<?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'login-page' : ''; ?>">

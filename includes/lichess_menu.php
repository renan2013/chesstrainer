<div class="lichess-menu">
    <div class="lichess-menu-left">
        <a href="index.php"><img src="img/logo_ct.svg" width="90" alt="Logo Chess Trainer"></a>
        <a href="secciones.php?id_publicacion=<?php echo $id_publicacion; ?>" class="btn btn-link"><i class="fas fa-arrow-left"></i></a>
    </div>
    <div class="lichess-menu-center">
        <h3 class="mb-0">
            <?php
            echo htmlspecialchars($nombre_publicacion);
            if (isset($category_name) && !empty($category_name)) {
                echo " - " . htmlspecialchars($category_name);
            }
            ?>
        </h3>
    </div>
    <div class="lichess-menu-right">
        <span class="user-name"><?php echo htmlspecialchars($_SESSION["nombre_usuario"]); ?></span>
        <button type="button" class="btn btn-link" data-bs-toggle="modal" data-bs-target="#reportErrorModal">
            <i class="fas fa-flag"></i>
        </button>
        <a href="logout.php" class="btn btn-link"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</div>
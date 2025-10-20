<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="../index.php">
            <img src="../img/logo_blanco.svg" alt="Chess Trainer Logo" width="60"  class="d-inline-block align-text-top">
            Administrador
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownEdicion" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Edición
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownEdicion">
                        <li><a class="dropdown-item" href="gestionar_publicaciones.php">Publicaciones</a></li>
                        <li><a class="dropdown-item" href="gestionar_categorias.php">Categorías</a></li>
                        <li><a class="dropdown-item" href="anadir_problema.php">Crear Diagrama</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownInformes" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Informes
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownInformes">
                        <li><a class="dropdown-item" href="gestionar_reportes.php">Reportes</a></li>
                        <li><a class="dropdown-item" href="base_conocimiento.php">Base de conocimiento</a></li>
                        <?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['administrador', 'instructor'])): ?>
                        <li><a class="dropdown-item" href="ver_resultados.php">Ver Resultados</a></li>
                        <li><a class="dropdown-item" href="ranking_general.php">Ranking General</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['administrador', 'instructor'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownGrupos" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Grupos
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdownGrupos">
                        <li><a class="dropdown-item" href="grupos.php">Lista de grupos creados</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link" href="gestionar_usuarios.php">Usuarios</a></li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="../index.php" target="_blank" title="Abrir la vista de usuario en una nueva pestaña">Ver como usuario</a></li>
                <li class="nav-item"><a class="nav-link" href="../logout.php">Cerrar Sesión</a></li>
            </ul>
        </div>
    </div>
</nav>
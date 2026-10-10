<?php
$is_exercise_page = (basename($_SERVER['PHP_SELF']) === 'categoria.php');
$is_secciones_page = (basename($_SERVER['PHP_SELF']) === 'secciones.php');
?>
<div class="lichess-menu">
    <!-- LEFT SIDE: Logo 2.0 + Volver Button + Siguiente Button (Desktop) -->
    <div class="lichess-menu-left gap-2 align-items-center">
        <a href="index.php" class="logo-wrapper-20">
            <img src="img/logo_ct.svg" width="90" alt="Logo Chess Trainer">
            <span class="badge-20">2.0</span>
        </a>
        <?php if ($is_exercise_page && isset($id_publicacion) && !empty($id_publicacion)): ?>
            <a href="secciones.php?id_publicacion=<?php echo $id_publicacion; ?>" class="btn btn-sm btn-outline-secondary ms-2 d-none d-md-inline-flex" title="Volver a capítulos">
                <i class="fas fa-arrow-left me-1"></i> Volver
            </a>
        <?php elseif ($is_secciones_page): ?>
            <a href="index.php" class="btn btn-sm btn-outline-secondary ms-2 d-none d-md-inline-flex" title="Volver al inicio">
                <i class="fas fa-arrow-left me-1"></i> Volver
            </a>
        <?php endif; ?>
        <?php if ($is_exercise_page): ?>
            <button id="desktop-nav-next-btn" class="btn btn-sm btn-success fw-bold d-none d-md-inline-flex align-items-center gap-1 ms-1 shadow-sm" title="Siguiente Ejercicio">
                Siguiente <i class="fas fa-arrow-right ms-1"></i>
            </button>
        <?php endif; ?>
    </div>

    <!-- CENTER: Title & Exercise Counter (Desktop) -->
    <div class="lichess-menu-center d-none d-md-block">
        <h4 class="mb-0 fw-bold fs-5 text-dark">
            <?php
            if (isset($nombre_publicacion) && !empty($nombre_publicacion)) {
                echo htmlspecialchars($nombre_publicacion);
            }
            if ($is_exercise_page && isset($category_name) && !empty($category_name)) {
                echo " - " . htmlspecialchars($category_name);
            }
            ?>
        </h4>
        <?php if ($is_exercise_page && isset($total_problems_in_current_category)): ?>
            <span class="text-muted small">
                Ejercicio <span id="current-problem-number"><?php echo (isset($current_problem_index) ? $current_problem_index + 1 : 1); ?></span> de <span id="total-problems"><?php echo $total_problems_in_current_category; ?></span>
            </span>
        <?php endif; ?>
    </div>

    <!-- RIGHT SIDE: Dropdown Menu with All Options -->
    <div class="lichess-menu-right">
        <div class="dropdown">
            <button class="btn btn-light btn-sm border dropdown-toggle fw-semibold" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-user-circle me-1 text-primary"></i>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION["nombre_usuario"] ?? ''); ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userMenuDropdown">
                <?php if ($is_exercise_page): ?>
                    <li>
                        <h6 class="dropdown-header text-uppercase small">Opciones de Ejercicio</h6>
                    </li>
                    <?php if (isset($current_category_id) && isset($_SESSION['rol']) && $_SESSION['rol'] === 'administrador'): ?>
                    <li>
                        <a class="dropdown-item" href="descargar_clase.php?id_categoria=<?php echo $current_category_id; ?>" target="_blank">
                            <i class="fas fa-file-pdf text-danger me-2"></i> Exportar Clase a PDF
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="admin/editar_categoria.php?id_categoria=<?php echo $current_category_id; ?>">
                            <i class="fas fa-edit text-primary me-2"></i> Editar Categoría
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li>
                        <a id="dropdownExportDiagramLink" class="dropdown-item text-primary fw-semibold" href="#" target="_blank">
                            <i class="fas fa-file-export me-2 text-primary"></i> Exportar Diagrama Actual
                        </a>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reportErrorModal">
                            <i class="fas fa-flag text-warning me-2"></i> Reportar Error en Diagrama
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <?php if (isset($_SESSION['rol']) && in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])): ?>
                <li>
                    <a class="dropdown-item" href="admin/index.php">
                        <i class="fas fa-cogs me-2 text-secondary"></i> Panel de Administración
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <?php endif; ?>
                <li>
                    <a class="dropdown-item text-danger" href="logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- SECOND LINE FOR MOBILE DEVICES (< 768px) -->
<?php if ($is_exercise_page): ?>
<div class="lichess-menu-mobile-subbar d-flex d-md-none justify-content-between align-items-center px-3 py-2 border-bottom bg-white">
    <div class="d-flex align-items-center gap-2">
        <?php if (isset($id_publicacion) && !empty($id_publicacion)): ?>
            <a href="secciones.php?id_publicacion=<?php echo $id_publicacion; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Volver
            </a>
        <?php endif; ?>
        <!-- BADGE DEL TURNO DEL JUGADOR AL LADO DEL BOTÓN VOLVER -->
        <span id="mobile-turn-badge" class="badge rounded-pill bg-light text-dark border px-2 py-1 align-items-center gap-1 d-inline-flex" style="font-size: 0.8rem; font-weight: 600;">
            <span id="mobile-turn-dot" class="turn-icon-dot white-dot me-1" style="width: 10px; height: 10px; margin-right: 4px;"></span>
            <span id="mobile-turn-text">Juegan Blancas</span>
        </span>
        <!-- BOTÓN SIGUIENTE EJERCICIO EN MÓVIL (Al lado de Volver) -->
        <button id="mobile-next-btn" class="btn btn-sm btn-success fw-bold d-inline-flex align-items-center gap-1 shadow-sm" style="font-size: 0.8rem;" title="Siguiente Ejercicio">
            Siguiente <i class="fas fa-arrow-right"></i>
        </button>
    </div>
    <div class="text-end">
        <span class="fw-bold text-dark d-block small">
            <?php echo isset($category_name) ? htmlspecialchars($category_name) : ''; ?>
        </span>
        <?php if (isset($total_problems_in_current_category)): ?>
            <span class="text-muted extra-small">
                Ejercicio <span id="current-problem-number-mobile"><?php echo (isset($current_problem_index) ? $current_problem_index + 1 : 1); ?></span> de <?php echo $total_problems_in_current_category; ?>
            </span>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($is_secciones_page): ?>
<div class="lichess-menu-mobile-subbar d-flex d-md-none justify-content-between align-items-center px-3 py-2 border-bottom bg-white">
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i> Volver
        </a>
    </div>
    <div class="text-end">
        <span class="fw-bold text-dark d-block small">
            <?php echo isset($nombre_publicacion) ? htmlspecialchars($nombre_publicacion) : ''; ?>
        </span>
    </div>
</div>
<?php endif; ?>
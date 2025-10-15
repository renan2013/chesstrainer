<?php
$page_title = "Gestionar Reportes de Errores";
// Usar el header de admin, que maneja sesión, BD, y el <head> HTML.
require_once 'includes/header.php';

// El header ya comprueba el login, aquí solo validamos el rol específico.
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['administrador', 'instructor', 'creador_contenido'])) {
    header("location: ../login.php");
    exit;
}

$reports = [];

// La conexión $conn ya está disponible gracias al header.
$sql = "SELECT
            re.id_reporte,
            re.id_problemas,
            re.descripcion_error,
            re.fecha_reporte,
            re.resuelto,
            p.fen,
            p.solucion,
            u.nombre_usuario,
            c.nombre_categoria
        FROM
            reporte_errores re
        JOIN
            problemas p ON re.id_problemas = p.id_problemas
        JOIN
            usuarios u ON re.id_usuarios = u.id_usuarios
        JOIN
            categorias c ON p.id_categorias = c.id_categorias";

// Si el rol es creador_contenido, filtramos para que solo vea reportes de sus problemas
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'creador_contenido') {
    $sql .= " WHERE p.creado_por_id_usuario = ?";
}

$sql .= " ORDER BY re.fecha_reporte DESC";

if ($stmt = $conn->prepare($sql)) {
    // Si es creador de contenido, bindeamos su ID a la consulta
    if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'creador_contenido') {
        $stmt->bind_param("i", $_SESSION['id_usuarios']);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt->close();
} else {
    echo "<div class='alert alert-danger'>Error al preparar la consulta de reportes: " . $conn->error . "</div>";
}

// No cerramos la conexión aquí, por si el footer la necesita.
// $conn->close(); 
?>

<div class="admin-container">
    <?php require_once 'includes/sidebar.php'; ?>
    <main class="main-content">

        <h1 class="mb-4">Gestionar Reportes de Errores</h1>

        <?php if (empty($reports)): ?>
            <div class="alert alert-info">No hay reportes de errores registrados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID Reporte</th>
                            <th>ID Problema</th>
                            <th>Categoría</th>
                            <th>Descripción del Error</th>
                            <th>Reportado Por</th>
                            <th>Fecha Reporte</th>
                            <th>Resuelto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['id_reporte']); ?></td>
                                <td><?php echo htmlspecialchars($report['id_problemas']); ?></td>
                                <td><?php echo htmlspecialchars($report['nombre_categoria']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($report['descripcion_error'])); ?></td>
                                <td><?php echo htmlspecialchars($report['nombre_usuario']); ?></td>
                                <td><?php echo htmlspecialchars(date("d/m/Y H:i", strtotime($report['fecha_reporte'] . ' -5 hours'))); ?></td>
                                <td>
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input report-resolved-checkbox" role="switch" id="report_<?php echo $report['id_reporte']; ?>" data-id="<?php echo $report['id_reporte']; ?>" <?php echo $report['resuelto'] ? 'checked' : ''; ?>>
                                    </div>
                                </td>
                                <td>
                                    <a href="editar_problema.php?id=<?php echo $report['id_problemas']; ?>" class="btn btn-sm btn-primary">Revisar Problema</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </main>
</div>

<?php require_once '../includes/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('.report-resolved-checkbox').on('change', function() {
        const reportId = $(this).data('id');
        const isResolved = $(this).is(':checked');

        $.ajax({
            url: 'update_report_status.php',
            type: 'POST',
            data: {
                id_reporte: reportId,
                resuelto: isResolved ? 1 : 0
            },
            dataType: 'json',
            success: function(response) {
                if (!response.success) {
                    console.error(response.message);
                    $('#report_' + reportId).prop('checked', !isResolved);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error: ' + status + error);
                $('#report_' + reportId).prop('checked', !isResolved);
            }
        });
    });
});
</script>
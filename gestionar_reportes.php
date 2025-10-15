<?php
$page_title = "Gestionar Reportes de Errores";
require_once '../includes/header.php';
require_once '../includes/db.php'; // Ensure DB connection is available

// Verificar si el usuario ha iniciado sesión y tiene el rol de administrador
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || $_SESSION['rol'] !== 'administrador') {
    header("location: ../login.php");
    exit;
}

$reports = [];

// Query to fetch reports with problem and user details
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
            categorias c ON p.id_categorias = c.id_categorias
        ORDER BY
            re.fecha_reporte DESC";

if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
    $stmt->close();
} else {
    // Handle error if query preparation fails
    echo "<div class='alert alert-danger'>Error al preparar la consulta de reportes: " . $conn->error . "</div>";
}

$conn->close();
?>

<h2>Gestionar Reportes de Errores</h2>

<div class="mb-3">
    <a href="index.php" class="btn btn-secondary">&larr; Volver al Panel</a>
</div>

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
                            <input type="checkbox" class="form-check-input report-resolved-checkbox" id="report_<?php echo $report['id_reporte']; ?>" data-id="<?php echo $report['id_reporte']; ?>" <?php echo $report['resuelto'] ? 'checked' : ''; ?>>
                        </td>
                        <td>
                            <!-- Add actions here, e.g., a button to mark as resolved or delete -->
                            <a href="editar_problema.php?id=<?php echo $report['id_problemas']; ?>" class="btn btn-sm btn-primary">Revisar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    $('.report-resolved-checkbox').on('change', function() {
        console.log("Checkbox change event fired!");
        const reportId = $(this).data('id');
        const isResolved = $(this).is(':checked');
        console.log('Sending AJAX request:', { id_reporte: reportId, resuelto: isResolved ? 1 : 0 });

        $.ajax({
            url: 'update_report_status.php',
            type: 'POST',
            data: {
                id_reporte: reportId,
                resuelto: isResolved ? 1 : 0
            },
            dataType: 'json',
            success: function(response) {
                console.log('AJAX Success Response:', response);
                if (response.success) {
                    console.log(response.message);
                } else {
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
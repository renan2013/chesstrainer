<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<pre>';

$page_title = "Inicio - Chess Trainer";
require_once 'includes/header.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

$id_usuario_actual = $_SESSION["id_usuarios"];
$rol_usuario_actual = $_SESSION['rol'];

// --- Lógica para Estudiantes ---
if ($rol_usuario_actual == 'usuario') {
    echo "--- MODO ESTUDIANTE ---
";
    // 1. Obtener los grupos del estudiante
    $mis_grupos = [];
    $stmt_grupos = $conn->prepare("SELECT g.id, g.nombre, u.nombre_completo AS instructor_nombre FROM grupos g JOIN grupo_miembros gm ON g.id = gm.id_grupo JOIN usuarios u ON g.id_instructor = u.id_usuarios WHERE gm.id_usuario = ? ORDER BY g.nombre");
    $stmt_grupos->bind_param("i", $id_usuario_actual);
    $stmt_grupos->execute();
    $result_grupos = $stmt_grupos->get_result();
    $grupos_data = $result_grupos->fetch_all(MYSQLI_ASSOC);
    $stmt_grupos->close();

    echo "\n--- GRUPOS ENCONTRADOS PARA EL USUARIO ---
";
    var_dump($grupos_data);

    foreach ($grupos_data as $grupo) {
        $id_grupo = $grupo['id'];
        $mis_grupos[$id_grupo] = [
            'nombre' => $grupo['nombre'],
            'instructor' => $grupo['instructor_nombre'],
            'materiales' => []
        ];

        // 2. Obtener los IDs de los materiales del grupo
        echo "\n--- BUSCANDO MATERIALES PARA GRUPO ID: $id_grupo ---
";
        $stmt_materiales = $conn->prepare("SELECT id_material, tipo_material FROM grupo_material WHERE id_grupo = ?");
        $stmt_materiales->bind_param("i", $id_grupo);
        $stmt_materiales->execute();
        $result_materiales = $stmt_materiales->get_result();
        $materiales_data = $result_materiales->fetch_all(MYSQLI_ASSOC);
        $stmt_materiales->close();

        echo "\n--- IDs DE MATERIAL ENCONTRADOS ---
";
        var_dump($materiales_data);

        // 3. Obtener detalles de cada material
        foreach ($materiales_data as $material) {
            echo "\n--- PROCESANDO MATERIAL ID: {$material['id_material']} (TIPO: {$material['tipo_material']}) ---
";
            if ($material['tipo_material'] == 'publicacion') {
                $stmt_pub = $conn->prepare("SELECT id_publicacion, titulo, descripcion, tipo, imagen_publicacion, estado FROM publicacion WHERE id_publicacion = ? AND estado = 'activo'");
                $stmt_pub->bind_param("i", $material['id_material']);
                $stmt_pub->execute();
                $res_pub = $stmt_pub->get_result();
                if ($pub = $res_pub->fetch_assoc()) {
                    echo "\n--- DETALLE DE PUBLICACIÓN ENCONTRADO ---
";
                    var_dump($pub);
                    $mis_grupos[$id_grupo]['materiales'][] = $pub;
                } else {
                    echo "\n--- PUBLICACIÓN NO ENCONTRADA O INACTIVA ---
";
                }
                $stmt_pub->close();
            } elseif ($material['tipo_material'] == 'categoria') {
                $stmt_cat = $conn->prepare("SELECT c.id_categorias, c.nombre_categoria, c.imagen_categoria, p.tipo, c.estado FROM categorias c JOIN publicacion p ON c.id_publicacion = p.id_publicacion WHERE c.id_categorias = ? AND c.estado = 'activo'");
                $stmt_cat->bind_param("i", $material['id_material']);
                $stmt_cat->execute();
                $res_cat = $stmt_cat->get_result();
                if ($cat = $res_cat->fetch_assoc()) {
                     echo "\n--- DETALLE DE CATEGORÍA ENCONTRADO ---
";
                    var_dump($cat);
                    $mis_grupos[$id_grupo]['materiales'][] = [
                        'id_categorias' => $cat['id_categorias'],
                        'titulo' => $cat['nombre_categoria'],
                        'descripcion' => 'Categoría de ' . $cat['tipo'],
                        'tipo' => $cat['tipo'],
                        'imagen_publicacion' => $cat['imagen_categoria']
                    ];
                } else {
                    echo "\n--- CATEGORÍA NO ENCONTRADA O INACTIVA ---
";
                }
                $stmt_cat->close();
            }
        }
    }
    echo "\n--- ESTRUCTURA FINAL DE DATOS ---
";
    var_dump($mis_grupos);

} else {
    // Lógica para no estudiantes...
}

$conn->close();
echo '</pre>';
die("--- FIN DE DEPURACIÓN ---");

?>

<?php
// Configuración unificada de la conexión a la base de datos
$root_db = __DIR__ . '/../db_connect.php';
if (file_exists($root_db)) {
    require_once $root_db;
}

if (!function_exists('get_image_url')) {
    function get_image_url($raw_path, $is_admin = false) {
        $fallback = $is_admin ? '../img/chess_trainer_logo.png' : 'img/chess_trainer_logo.png';
        if (empty($raw_path) || !is_string($raw_path)) {
            return $fallback;
        }

        $path = trim($raw_path);
        if (empty($path)) {
            return $fallback;
        }

        // Convert any full domain URL containing uploads/ or img/ to relative path
        if (preg_match('/^https?:\/\//i', $path)) {
            if (preg_match('/(uploads\/.*|img\/.*)$/i', $path, $matches)) {
                $path = $matches[1];
            } else {
                return $path;
            }
        }

        // Strip leading slashes
        $path = ltrim($path, '/\\');

        // Fix double admin prefixes if any
        while (strpos($path, 'admin/admin/') === 0) {
            $path = substr($path, 6);
        }

        if (!$is_admin) {
            // For root pages (index.php, secciones.php):
            // Return clean path (e.g. uploads/publicaciones/...)
            return $path;
        } else {
            // For admin pages (admin/index.php, admin/gestionar_publicaciones.php):
            if (strpos($path, 'admin/') === 0) {
                return substr($path, 6);
            }
            if (strpos($path, 'uploads/') === 0) {
                return $path;
            }
            if (strpos($path, 'img/') === 0) {
                return '../' . $path;
            }
            return $path;
        }
    }
}
?>
<?php
/**
 * Configuracion general de la aplicacion.
 */

declare(strict_types=1);

date_default_timezone_set('America/Lima');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Sesion nativa de PHP con cookie de sesion endurecida.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Rutas de almacenamiento de archivos (fuera de la BD, en volumen persistente).
define('BASE_PATH', dirname(__DIR__));
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('UPLOADS_CONTENIDOS', UPLOADS_PATH . '/contenidos');
define('UPLOADS_ENTREGAS', UPLOADS_PATH . '/entregas');

// Limites de subida de archivos.
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024); // 50 MB
define('ALLOWED_CONTENIDO_EXT', ['pdf', 'mp4', 'webm', 'avi', 'mov', 'mkv']);
define('ALLOWED_ENTREGA_EXT', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'jpg', 'jpeg', 'png']);

define('APP_NAME', 'Aula Virtual - IESTP Benjamín Franklin');

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
define('ALLOWED_COMPROBANTE_EXT', ['pdf', 'jpg', 'jpeg', 'png']);

define('APP_NAME', 'Aula Virtual - IESTP Benjamín Franklin');

// Credenciales OAuth2 de Google Cloud (cuenta educativa del instituto),
// usadas para que cada docente conecte su Google Calendar y el sistema
// pueda crear videollamadas de Google Meet en su nombre. Se dejan vacias
// por defecto: mientras no esten configuradas, el sistema sigue
// funcionando con el enlace de Meet pegado manualmente por el docente.
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
define('GOOGLE_REDIRECT_URI', getenv('GOOGLE_REDIRECT_URI') ?: 'https://aulavirtual.iestpbf.edu.pe/google_oauth_callback.php');

// Servidor SMTP para notificaciones por correo (nota nueva, aviso
// publicado, mensaje sin leer). Vacio por defecto: mientras no este
// configurado, el sistema sigue funcionando normalmente, solo sin enviar
// esos correos.
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int) (getenv('SMTP_PORT') ?: 587));
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_FROM', getenv('SMTP_FROM') ?: 'no-responder@iestpbf.edu.pe');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: APP_NAME);
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');

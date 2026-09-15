<?php
/**
 * Funciones auxiliares comunes: escape de salida, mensajes flash,
 * CSRF y manejo de subida de archivos.
 */

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function requireValidCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Token de seguridad invalido. Vuelva a intentarlo desde el formulario.');
    }
}

/**
 * Sube un archivo desde $_FILES[$fieldName] hacia $destSubdir dentro de
 * UPLOADS_PATH, validando extension y tamano. Devuelve la ruta relativa
 * (a partir de "uploads/") guardada en BD, o null si no se envio archivo.
 * Lanza RuntimeException si el archivo es invalido.
 */
function handleUpload(string $fieldName, string $destSubdir, array $allowedExt): ?string
{
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Error al subir el archivo (codigo ' . $file['error'] . ').');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('El archivo supera el tamano maximo permitido (50 MB).');
    }

    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Extension de archivo no permitida: .' . e($ext));
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Subida de archivo invalida.');
    }

    $destDir = UPLOADS_PATH . '/' . trim($destSubdir, '/');
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException('No se pudo crear el directorio de destino.');
    }

    $safeName = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
    $destPath = $destDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
    }

    return trim($destSubdir, '/') . '/' . $safeName;
}

function formatDateEs(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : '-';
}

function isPastDue(string $fechaLimite): bool
{
    return strtotime($fechaLimite) < time();
}

function formatFechaEs(?string $fecha): string
{
    if (!$fecha) {
        return '-';
    }
    $ts = strtotime($fecha);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function formatHoraEs(?string $hora): string
{
    if (!$hora) {
        return '-';
    }
    $ts = strtotime($hora);
    return $ts ? date('H:i', $ts) : '-';
}

function isSessionToday(string $fecha): bool
{
    return date('Y-m-d') === date('Y-m-d', strtotime($fecha));
}

function isSessionPast(string $fecha): bool
{
    return strtotime($fecha) < strtotime(date('Y-m-d'));
}

function formatSoles(?string $monto): string
{
    return 'S/ ' . number_format((float) $monto, 2);
}

/**
 * Devuelve el markup de un icono SVG en linea (sin dependencias externas).
 */
function avIcon(string $name): string
{
    $icons = [
        'grid' => '<rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'book' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>',
        'graduation' => '<path d="M22 10 12 5 2 10l10 5 10-5Z"></path><path d="M6 12v5c0 1.66 2.69 3 6 3s6-1.34 6-3v-5"></path>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path>',
        'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>',
        'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"></path><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"></path>',
        'clipboard' => '<rect x="8" y="2" width="8" height="4" rx="1"></rect><path d="M9 4H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-4"></path><path d="M9 12h6M9 16h6"></path>',
        'video' => '<path d="m22 8-6 4 6 4V8Z"></path><rect x="2" y="6" width="14" height="12" rx="2"></rect>',
        'check' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="M22 4 12 14.01l-3-3"></path>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="M7 10l5 5 5-5"></path><path d="M12 15V3"></path>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"></rect><path d="m22 6-10 7L2 6"></path>',
        'chart' => '<path d="M3 3v18h18"></path><path d="M18 17V9"></path><path d="M13 17V5"></path><path d="M8 17v-3"></path>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'award' => '<circle cx="12" cy="8" r="6"></circle><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path>',
    ];
    $inner = $icons[$name] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</svg>';
}

/**
 * Cantidad de mensajes no leidos para un usuario (badge en el sidebar).
 */
function unreadMensajesCount(PDO $pdo, int $userId): int
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM mensajes WHERE destinatario_id = :id AND leido = 0');
    $stmt->execute(['id' => $userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Lista de contactos con los que un usuario puede intercambiar mensajes,
 * segun su rol: el administrador puede escribir a cualquiera; docente y
 * estudiante solo a las personas con quienes comparten al menos un curso.
 */
function mensajesContactos(PDO $pdo, array $user): array
{
    if ($user['rol'] === 'administrador') {
        $stmt = $pdo->prepare(
            "SELECT id, nombre, apellidos, rol FROM usuarios WHERE id != :id ORDER BY rol, apellidos, nombre"
        );
        $stmt->execute(['id' => $user['id']]);
        return $stmt->fetchAll();
    }

    if ($user['rol'] === 'docente') {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT u.id, u.nombre, u.apellidos, u.rol
             FROM usuarios u
             JOIN matriculas m ON m.estudiante_id = u.id AND m.estado = 'activo'
             JOIN cursos c ON c.id = m.curso_id
             WHERE c.docente_id = :id
             ORDER BY u.apellidos, u.nombre"
        );
        $stmt->execute(['id' => $user['id']]);
        return $stmt->fetchAll();
    }

    if ($user['rol'] === 'estudiante') {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT u.id, u.nombre, u.apellidos, u.rol
             FROM usuarios u
             JOIN cursos c ON c.docente_id = u.id
             JOIN matriculas m ON m.curso_id = c.id AND m.estudiante_id = :id AND m.estado = 'activo'
             ORDER BY u.apellidos, u.nombre"
        );
        $stmt->execute(['id' => $user['id']]);
        return $stmt->fetchAll();
    }

    return [];
}

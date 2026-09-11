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

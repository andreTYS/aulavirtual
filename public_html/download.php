<?php
/**
 * Descarga de archivos protegida. Los archivos viven en uploads/ fuera
 * del alcance publico directo (denegado por .htaccess); todo acceso pasa
 * por aqui, verificando que el rol de la sesion tenga derecho al archivo.
 */
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$type = (string) ($_GET['type'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$user = currentUser();

if (!in_array($type, ['contenido', 'entrega'], true) || $id <= 0) {
    http_response_code(400);
    die('Solicitud inválida.');
}

$relativePath = null;
$downloadName = null;

if ($type === 'contenido') {
    $stmt = $pdo->prepare(
        'SELECT co.archivo_path, co.titulo, c.docente_id, c.id AS curso_id
         FROM contenidos co JOIN cursos c ON c.id = co.curso_id
         WHERE co.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row || !$row['archivo_path']) {
        http_response_code(404);
        die('Archivo no encontrado.');
    }

    $allowed = false;
    if ($user['rol'] === 'administrador') {
        $allowed = true;
    } elseif ($user['rol'] === 'docente' && (int) $row['docente_id'] === (int) $user['id']) {
        $allowed = true;
    } elseif ($user['rol'] === 'estudiante') {
        $chk = $pdo->prepare('SELECT 1 FROM matriculas WHERE curso_id = :curso_id AND estudiante_id = :estudiante_id');
        $chk->execute(['curso_id' => $row['curso_id'], 'estudiante_id' => $user['id']]);
        $allowed = (bool) $chk->fetchColumn();
    }

    if (!$allowed) {
        http_response_code(403);
        die('No tiene permisos para descargar este archivo.');
    }

    $relativePath = $row['archivo_path'];
    $downloadName = $row['titulo'];
} else {
    $stmt = $pdo->prepare(
        'SELECT e.archivo_path, e.estudiante_id, t.titulo, c.docente_id
         FROM entregas e
         JOIN tareas t ON t.id = e.tarea_id
         JOIN cursos c ON c.id = t.curso_id
         WHERE e.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        die('Archivo no encontrado.');
    }

    $allowed = false;
    if ($user['rol'] === 'administrador') {
        $allowed = true;
    } elseif ($user['rol'] === 'docente' && (int) $row['docente_id'] === (int) $user['id']) {
        $allowed = true;
    } elseif ($user['rol'] === 'estudiante' && (int) $row['estudiante_id'] === (int) $user['id']) {
        $allowed = true;
    }

    if (!$allowed) {
        http_response_code(403);
        die('No tiene permisos para descargar este archivo.');
    }

    $relativePath = $row['archivo_path'];
    $downloadName = $row['titulo'];
}

$fullPath = realpath(UPLOADS_PATH . '/' . $relativePath);
if ($fullPath === false || !str_starts_with($fullPath, realpath(UPLOADS_PATH)) || !is_file($fullPath)) {
    http_response_code(404);
    die('Archivo no encontrado en el servidor.');
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) . '.' . $ext;

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($fullPath));
header('X-Content-Type-Options: nosniff');
readfile($fullPath);
exit;

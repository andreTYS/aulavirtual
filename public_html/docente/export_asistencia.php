<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];
$cursoId = (int) ($_GET['curso_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM cursos WHERE id = :id AND docente_id = :docente_id');
$stmt->execute(['id' => $cursoId, 'docente_id' => $docenteId]);
$curso = $stmt->fetch();
if (!$curso) {
    setFlash('danger', 'Curso no encontrado o no tiene permisos sobre él.');
    redirect('/docente/index.php');
}

$asistenciaResumen = $pdo->prepare(
    "SELECT u.nombre, u.apellidos, u.dni,
            COUNT(a.id) AS sesiones_registradas,
            SUM(a.estado = 'presente') AS presentes,
            SUM(a.estado = 'tarde') AS tardes,
            SUM(a.estado = 'falta') AS faltas,
            SUM(a.estado = 'justificado') AS justificados
     FROM matriculas m
     JOIN usuarios u ON u.id = m.estudiante_id
     LEFT JOIN sesiones s ON s.curso_id = m.curso_id
     LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
     WHERE m.curso_id = :curso_id AND m.estado = 'activo'
     GROUP BY u.id, u.nombre, u.apellidos, u.dni
     ORDER BY u.apellidos, u.nombre"
);
$asistenciaResumen->execute(['curso_id' => $cursoId]);
$asistenciaResumen = $asistenciaResumen->fetchAll();

$filename = 'asistencia_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $curso['nombre']) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel detecte UTF-8
fputcsv($out, ['Estudiante', 'DNI', 'Sesiones registradas', 'Presente', 'Tarde', 'Falta', 'Justificado', '% Asistencia']);

foreach ($asistenciaResumen as $r) {
    $reg = (int) $r['sesiones_registradas'];
    $pct = $reg > 0 ? round((((int) $r['presentes'] + (int) $r['tardes']) / $reg) * 100) : '';
    fputcsv($out, [
        $r['nombre'] . ' ' . $r['apellidos'], $r['dni'] ?? '', $reg,
        (int) $r['presentes'], (int) $r['tardes'], (int) $r['faltas'], (int) $r['justificados'], $pct !== '' ? $pct . '%' : '',
    ]);
}
fclose($out);
exit;

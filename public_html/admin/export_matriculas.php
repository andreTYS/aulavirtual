<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

$cursoId = (int) ($_GET['curso_id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM cursos WHERE id = :id');
$stmt->execute(['id' => $cursoId]);
$curso = $stmt->fetch();
if (!$curso) {
    setFlash('danger', 'Curso no encontrado.');
    redirect('/admin/cursos.php');
}

$matriculados = $pdo->prepare(
    'SELECT u.nombre, u.apellidos, u.email, u.dni, m.estado, m.fecha_matricula, m.fecha_baja
     FROM matriculas m JOIN usuarios u ON u.id = m.estudiante_id
     WHERE m.curso_id = :curso_id ORDER BY m.estado, u.apellidos, u.nombre'
);
$matriculados->execute(['curso_id' => $cursoId]);
$matriculados = $matriculados->fetchAll();

$filename = 'matriculas_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $curso['nombre']) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Estudiante', 'Correo', 'DNI', 'Estado', 'Fecha de matrícula', 'Fecha de baja']);

foreach ($matriculados as $m) {
    fputcsv($out, [
        $m['nombre'] . ' ' . $m['apellidos'], $m['email'], $m['dni'] ?? '',
        $m['estado'] === 'activo' ? 'Activo' : 'Retirado',
        formatDateEs($m['fecha_matricula']), $m['fecha_baja'] ? formatDateEs($m['fecha_baja']) : '',
    ]);
}
fclose($out);
exit;

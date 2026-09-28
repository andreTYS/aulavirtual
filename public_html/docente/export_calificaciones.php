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

$stmt = $pdo->prepare('SELECT id, titulo, peso FROM tareas WHERE curso_id = :curso_id ORDER BY fecha_limite');
$stmt->execute(['curso_id' => $cursoId]);
$tareas = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT id, titulo, peso FROM examenes WHERE curso_id = :curso_id ORDER BY fecha_limite');
$stmt->execute(['curso_id' => $cursoId]);
$examenes = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT u.id, u.nombre, u.apellidos FROM usuarios u
     JOIN matriculas m ON m.estudiante_id = u.id
     WHERE m.curso_id = :curso_id AND m.estado = 'activo'
     ORDER BY u.apellidos, u.nombre"
);
$stmt->execute(['curso_id' => $cursoId]);
$estudiantes = $stmt->fetchAll();

$notas = [];
$stmt = $pdo->prepare(
    "SELECT e.estudiante_id, e.tarea_id, e.calificacion FROM entregas e
     JOIN tareas t ON t.id = e.tarea_id WHERE t.curso_id = :curso_id"
);
$stmt->execute(['curso_id' => $cursoId]);
foreach ($stmt->fetchAll() as $row) {
    $notas[(int) $row['estudiante_id']][(int) $row['tarea_id']] = $row['calificacion'];
}

$notasExamen = [];
$stmt = $pdo->prepare(
    "SELECT i.estudiante_id, i.examen_id, i.puntaje FROM examen_intentos i
     JOIN examenes ex ON ex.id = i.examen_id WHERE ex.curso_id = :curso_id AND i.fecha_envio IS NOT NULL"
);
$stmt->execute(['curso_id' => $cursoId]);
foreach ($stmt->fetchAll() as $row) {
    $notasExamen[(int) $row['estudiante_id']][(int) $row['examen_id']] = $row['puntaje'];
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="calificaciones_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $curso['nombre']) . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");

$encabezado = ['Estudiante'];
foreach ($tareas as $t) {
    $encabezado[] = $t['titulo'] . ' (peso ' . number_format((float) $t['peso'], 2) . ')';
}
foreach ($examenes as $ex) {
    $encabezado[] = $ex['titulo'] . ' (peso ' . number_format((float) $ex['peso'], 2) . ')';
}
$encabezado[] = 'Promedio ponderado';
fputcsv($out, $encabezado);

foreach ($estudiantes as $est) {
    $eid = (int) $est['id'];
    $fila = [$est['nombre'] . ' ' . $est['apellidos']];
    foreach ($tareas as $t) {
        $fila[] = $notas[$eid][$t['id']] ?? '';
    }
    foreach ($examenes as $ex) {
        $fila[] = $notasExamen[$eid][$ex['id']] ?? '';
    }
    $filasPromedio = array_merge(
        array_map(fn($t) => ['calificacion' => $notas[$eid][$t['id']] ?? null, 'peso' => $t['peso']], $tareas),
        array_map(fn($ex) => ['calificacion' => $notasExamen[$eid][$ex['id']] ?? null, 'peso' => $ex['peso']], $examenes)
    );
    $promedio = promedioPonderado($filasPromedio);
    $fila[] = $promedio !== null ? number_format($promedio, 2) : '';
    fputcsv($out, $fila);
}
fclose($out);
exit;

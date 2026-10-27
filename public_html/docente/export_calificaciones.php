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

$stmt = $pdo->prepare('SELECT * FROM unidades WHERE curso_id = :curso_id ORDER BY orden, id');
$stmt->execute(['curso_id' => $cursoId]);
$unidades = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT id, unidad_id, titulo, peso FROM tareas WHERE curso_id = :curso_id ORDER BY fecha_limite');
$stmt->execute(['curso_id' => $cursoId]);
$tareasPorUnidad = [];
foreach ($stmt->fetchAll() as $t) {
    $tareasPorUnidad[(int) $t['unidad_id']][] = $t;
}

$stmt = $pdo->prepare('SELECT id, unidad_id, titulo, peso FROM examenes WHERE curso_id = :curso_id ORDER BY fecha_limite');
$stmt->execute(['curso_id' => $cursoId]);
$examenesPorUnidad = [];
foreach ($stmt->fetchAll() as $ex) {
    $examenesPorUnidad[(int) $ex['unidad_id']][] = $ex;
}

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
foreach ($unidades as $u) {
    $uid = (int) $u['id'];
    foreach ($tareasPorUnidad[$uid] ?? [] as $t) {
        $encabezado[] = $u['nombre'] . ' - ' . $t['titulo'] . ' (peso ' . number_format((float) $t['peso'], 2) . ')';
    }
    foreach ($examenesPorUnidad[$uid] ?? [] as $ex) {
        $encabezado[] = $u['nombre'] . ' - ' . $ex['titulo'] . ' (peso ' . number_format((float) $ex['peso'], 2) . ')';
    }
    $encabezado[] = $u['nombre'] . ' - Promedio (peso ' . number_format((float) $u['peso'], 2) . ' en el final)';
}
$encabezado[] = 'Promedio final';
fputcsv($out, $encabezado);

foreach ($estudiantes as $est) {
    $eid = (int) $est['id'];
    $fila = [$est['nombre'] . ' ' . $est['apellidos']];
    $promediosUnidad = [];

    foreach ($unidades as $u) {
        $uid = (int) $u['id'];
        $tUnidad = $tareasPorUnidad[$uid] ?? [];
        $exUnidad = $examenesPorUnidad[$uid] ?? [];

        foreach ($tUnidad as $t) {
            $fila[] = $notas[$eid][$t['id']] ?? '';
        }
        foreach ($exUnidad as $ex) {
            $fila[] = $notasExamen[$eid][$ex['id']] ?? '';
        }

        $promedioU = promedioUnidad($eid, $tUnidad, $exUnidad, $notas, $notasExamen);
        $promediosUnidad[$uid] = $promedioU;
        $fila[] = $promedioU !== null ? number_format($promedioU, 2) : '';
    }

    $filasFinal = array_map(
        fn($u) => ['calificacion' => $promediosUnidad[(int) $u['id']], 'peso' => $u['peso']],
        $unidades
    );
    $promedioFinal = promedioPonderado($filasFinal);
    $fila[] = $promedioFinal !== null ? number_format($promedioFinal, 2) : '';
    fputcsv($out, $fila);
}
fclose($out);
exit;

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare('SELECT u.*, car.nombre AS carrera_nombre FROM usuarios u LEFT JOIN carreras car ON car.id = u.carrera_id WHERE u.id = :id');
$stmt->execute(['id' => $estudianteId]);
$estudiante = $stmt->fetch();

$stmt = $pdo->prepare(
    "SELECT c.id AS curso_id, c.nombre AS curso_nombre, c.ciclo, p.nombre AS periodo_nombre
     FROM matriculas m
     JOIN cursos c ON c.id = m.curso_id
     LEFT JOIN periodos_academicos p ON p.id = c.periodo_academico_id
     WHERE m.estudiante_id = :estudiante_id
     ORDER BY p.fecha_inicio, c.nombre"
);
$stmt->execute(['estudiante_id' => $estudianteId]);
$cursos = $stmt->fetchAll();

$notasStmt = $pdo->prepare(
    "SELECT t.curso_id, e.calificacion FROM entregas e
     JOIN tareas t ON t.id = e.tarea_id
     WHERE e.estudiante_id = :estudiante_id AND e.calificacion IS NOT NULL"
);
$notasStmt->execute(['estudiante_id' => $estudianteId]);
$notasPorCurso = [];
foreach ($notasStmt->fetchAll() as $row) {
    $notasPorCurso[(int) $row['curso_id']][] = (float) $row['calificacion'];
}
$promediosPorCurso = [];
foreach ($cursos as $c) {
    $notas = $notasPorCurso[(int) $c['curso_id']] ?? [];
    $promediosPorCurso[(int) $c['curso_id']] = $notas ? array_sum($notas) / count($notas) : null;
}

$fechaEmision = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Constancia de estudios</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
body{font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:#1c1917;max-width:760px;margin:40px auto;padding:0 20px}
.doc{border:2px solid #0e2370;border-radius:10px;padding:40px}
.doc__head{display:flex;align-items:center;gap:16px;border-bottom:2px solid #0e2370;padding-bottom:16px;margin-bottom:24px}
.doc__head img{width:56px;height:56px}
.doc__head h1{font-size:1.1rem;color:#0e2370;margin:0}
.doc__head p{font-size:.78rem;color:#555;margin:2px 0 0}
h2{text-align:center;font-size:1.2rem;letter-spacing:.05em;text-transform:uppercase;color:#0e2370;margin-bottom:20px}
p.lead{font-size:.95rem;line-height:1.8;text-align:justify}
table{width:100%;border-collapse:collapse;margin:20px 0;font-size:.85rem}
th,td{border:1px solid #ccc;padding:8px 10px;text-align:left}
th{background:#f0f2f5}
.resultado{font-weight:700}
.resultado.ok{color:#16a34a}
.resultado.no{color:#dc2626}
.doc__footer{margin-top:50px;text-align:center;font-size:.85rem}
.firma{margin-top:60px;border-top:1px solid #999;width:260px;margin-left:auto;margin-right:auto;padding-top:6px}
.print-btn{display:block;max-width:760px;margin:0 auto 16px;text-align:right}
.print-btn button{background:#3b82f6;color:#fff;border:none;padding:10px 20px;border-radius:6px;font-weight:700;cursor:pointer;font-family:inherit}
@media print{.print-btn{display:none}body{margin:0}.doc{border:none}}
</style>
</head>
<body>
<div class="print-btn"><button onclick="window.print()">Imprimir / Guardar como PDF</button></div>
<div class="doc">
    <div class="doc__head">
        <img src="/assets/img/logo.svg" alt="IESTPBF">
        <div>
            <h1>IESTP Benjamín Franklin</h1>
            <p>Moquegua, Perú &middot; Aula Virtual</p>
        </div>
    </div>
    <h2>Constancia de estudios</h2>
    <p class="lead">
        El Instituto de Educación Superior Tecnológico Público "Benjamín Franklin" hace constar que
        <strong><?= e($estudiante['nombre'] . ' ' . $estudiante['apellidos']) ?></strong>,
        identificado(a) con DNI <strong><?= e($estudiante['dni'] ?? 'N/A') ?></strong>,
        se encuentra registrado(a) como estudiante de la carrera de
        <strong><?= e($estudiante['carrera_nombre'] ?? 'N/A') ?></strong> en esta institución,
        con el siguiente historial de cursos:
    </p>
    <table>
        <thead><tr><th>Periodo</th><th>Curso</th><th>Ciclo</th><th>Promedio</th><th>Resultado</th></tr></thead>
        <tbody>
        <?php foreach ($cursos as $c): ?>
            <?php $promedio = $promediosPorCurso[(int) $c['curso_id']] ?? null; ?>
            <tr>
                <td><?= e($c['periodo_nombre'] ?? '-') ?></td>
                <td><?= e($c['curso_nombre']) ?></td>
                <td><?= $c['ciclo'] ? (int) $c['ciclo'] : '-' ?></td>
                <td><?= $promedio !== null ? number_format($promedio, 2) : '-' ?></td>
                <td>
                    <?php if ($promedio === null): ?>
                        En curso
                    <?php elseif ($promedio >= 11): ?>
                        <span class="resultado ok">Aprobado</span>
                    <?php else: ?>
                        <span class="resultado no">Desaprobado</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$cursos): ?>
            <tr><td colspan="5">Sin cursos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <div class="doc__footer">
        <p>Se expide la presente constancia a solicitud del interesado, para los fines que estime conveniente.</p>
        <p>Moquegua, <?= e($fechaEmision) ?></p>
        <div class="firma">Dirección Académica</div>
    </div>
</div>
</body>
</html>

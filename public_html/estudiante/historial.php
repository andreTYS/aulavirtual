<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT m.id AS matricula_id, m.estado AS matricula_estado, c.id AS curso_id, c.nombre AS curso_nombre, c.ciclo,
            car.nombre AS carrera_nombre, p.nombre AS periodo_nombre, p.id AS periodo_id
     FROM matriculas m
     JOIN cursos c ON c.id = m.curso_id
     LEFT JOIN carreras car ON car.id = c.carrera_id
     LEFT JOIN periodos_academicos p ON p.id = c.periodo_academico_id
     WHERE m.estudiante_id = :estudiante_id
     ORDER BY p.fecha_inicio DESC, c.nombre"
);
$stmt->execute(['estudiante_id' => $estudianteId]);
$matriculas = $stmt->fetchAll();

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

$porPeriodo = [];
$promediosCurso = [];
foreach ($matriculas as $m) {
    $cid = (int) $m['curso_id'];
    $notas = $notasPorCurso[$cid] ?? [];
    $promedio = $notas ? array_sum($notas) / count($notas) : null;
    $promediosCurso[$cid] = $promedio;
    $periodoKey = $m['periodo_nombre'] ?? 'Sin periodo';
    $porPeriodo[$periodoKey][] = $m + ['promedio' => $promedio];
}

$todosLosPromedios = array_filter($promediosCurso, fn($p) => $p !== null);
$promedioGeneral = $todosLosPromedios ? array_sum($todosLosPromedios) / count($todosLosPromedios) : null;
$totalAprobados = count(array_filter($todosLosPromedios, fn($p) => $p >= 11));
$totalDesaprobados = count($todosLosPromedios) - $totalAprobados;

$pageTitle = 'Historial académico';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2>Historial académico</h2>
    <a href="/estudiante/constancia.php" target="_blank" class="av-btn av-btn--outline"><?= avIcon('award') ?> Ver constancia de estudios</a>
</div>

<div class="av-stats-row" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr))">
    <div class="av-stat">
        <div class="av-stat__icon" style="background:var(--ab100);color:var(--ab700)"><?= avIcon('book') ?></div>
        <div class="av-stat__num"><?= count($matriculas) ?></div>
        <div class="av-stat__lbl">Cursos cursados</div>
        <div class="av-stat__bar" style="background:var(--ab500)"></div>
    </div>
    <div class="av-stat">
        <div class="av-stat__icon" style="background:var(--success-lt);color:var(--success)"><?= avIcon('check') ?></div>
        <div class="av-stat__num"><?= $totalAprobados ?></div>
        <div class="av-stat__lbl">Aprobados</div>
        <div class="av-stat__bar" style="background:var(--success)"></div>
    </div>
    <div class="av-stat">
        <div class="av-stat__icon" style="background:var(--danger-lt);color:var(--danger)"><?= avIcon('star') ?></div>
        <div class="av-stat__num"><?= $totalDesaprobados ?></div>
        <div class="av-stat__lbl">Desaprobados</div>
        <div class="av-stat__bar" style="background:var(--danger)"></div>
    </div>
    <div class="av-stat">
        <div class="av-stat__icon" style="background:var(--ag100);color:var(--ag700)"><?= avIcon('graduation') ?></div>
        <div class="av-stat__num"><?= $promedioGeneral !== null ? number_format($promedioGeneral, 2) : '-' ?></div>
        <div class="av-stat__lbl">Promedio general</div>
        <div class="av-stat__bar" style="background:var(--ag500)"></div>
    </div>
</div>

<?php foreach ($porPeriodo as $periodoNombre => $cursosPeriodo): ?>
    <div class="av-card" style="margin-bottom:18px">
        <h3><?= e($periodoNombre) ?></h3>
        <div class="av-table-wrap">
            <table class="av-table">
                <thead><tr><th>Curso</th><th>Carrera</th><th>Ciclo</th><th>Estado matrícula</th><th>Promedio</th><th>Resultado</th></tr></thead>
                <tbody>
                <?php foreach ($cursosPeriodo as $c): ?>
                    <tr>
                        <td><strong><?= e($c['curso_nombre']) ?></strong></td>
                        <td><?= e($c['carrera_nombre'] ?? '-') ?></td>
                        <td><?= $c['ciclo'] ? (int) $c['ciclo'] : '-' ?></td>
                        <td>
                            <?php if ($c['matricula_estado'] === 'activo'): ?>
                                <span class="av-badge av-badge--blue">Activo</span>
                            <?php else: ?>
                                <span class="av-badge av-badge--gray">Retirado</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $c['promedio'] !== null ? number_format($c['promedio'], 2) : '<span class="av-text-muted">-</span>' ?></td>
                        <td>
                            <?php if ($c['promedio'] === null): ?>
                                <span class="av-badge av-badge--amber">En curso</span>
                            <?php elseif ($c['promedio'] >= 11): ?>
                                <span class="av-badge av-badge--green">Aprobado</span>
                            <?php else: ?>
                                <span class="av-badge av-badge--red">Desaprobado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>
<?php if (!$matriculas): ?>
    <div class="av-alert av-alert--danger"><span>Aún no tiene cursos registrados.</span></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

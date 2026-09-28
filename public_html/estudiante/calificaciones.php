<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT c.id AS curso_id, c.nombre AS curso_nombre, t.titulo, t.fecha_limite, t.categoria, t.peso,
            e.calificacion, e.comentario, e.fecha_entrega
     FROM matriculas m
     JOIN cursos c ON c.id = m.curso_id
     JOIN tareas t ON t.curso_id = c.id
     LEFT JOIN entregas e ON e.tarea_id = t.id AND e.estudiante_id = m.estudiante_id
     WHERE m.estudiante_id = :estudiante_id
     ORDER BY c.nombre, t.fecha_limite'
);
$stmt->execute(['estudiante_id' => $estudianteId]);
$filas = $stmt->fetchAll();

$examenesStmt = $pdo->prepare(
    'SELECT c.id AS curso_id, c.nombre AS curso_nombre, ex.titulo, ex.fecha_limite, ex.peso,
            i.puntaje AS calificacion, i.fecha_envio
     FROM matriculas m
     JOIN cursos c ON c.id = m.curso_id
     JOIN examenes ex ON ex.curso_id = c.id
     LEFT JOIN examen_intentos i ON i.examen_id = ex.id AND i.estudiante_id = m.estudiante_id
     WHERE m.estudiante_id = :estudiante_id
     ORDER BY c.nombre, ex.fecha_limite'
);
$examenesStmt->execute(['estudiante_id' => $estudianteId]);
$filasExamenes = $examenesStmt->fetchAll();

$categoriaLabels = ['practica' => 'Práctica', 'examen' => 'Examen', 'participacion' => 'Participación', 'trabajo' => 'Trabajo'];

$porCurso = [];
foreach ($filas as $f) {
    $porCurso[$f['curso_id']]['nombre'] = $f['curso_nombre'];
    $porCurso[$f['curso_id']]['tareas'][] = $f;
}
foreach ($filasExamenes as $f) {
    $porCurso[$f['curso_id']]['nombre'] = $f['curso_nombre'];
    $porCurso[$f['curso_id']]['tareas'] ??= [];
    $porCurso[$f['curso_id']]['examenes'][] = $f;
}

$pageTitle = 'Mis calificaciones';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Mis calificaciones</h2></div>

<?php foreach ($porCurso as $curso): ?>
    <?php $promedio = promedioPonderado(array_merge($curso['tareas'], $curso['examenes'] ?? [])); ?>
    <div class="av-card" style="margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <h3 style="margin:0"><?= e($curso['nombre']) ?></h3>
            <?php if ($promedio !== null): ?>
                <span class="av-badge av-badge--<?= $promedio >= 11 ? 'green' : 'red' ?>">Promedio ponderado: <?= number_format($promedio, 2) ?> / 20</span>
            <?php endif; ?>
        </div>
        <div class="av-table-wrap">
            <table class="av-table">
                <thead><tr><th>Tarea</th><th>Categoría</th><th>Peso</th><th>Fecha límite</th><th>Calificación</th><th>Comentario</th></tr></thead>
                <tbody>
                <?php foreach ($curso['tareas'] as $t): ?>
                    <tr>
                        <td><?= e($t['titulo']) ?></td>
                        <td><span class="av-badge av-badge--gray"><?= e($categoriaLabels[$t['categoria']] ?? $t['categoria']) ?></span></td>
                        <td><?= number_format((float) $t['peso'], 2) ?></td>
                        <td><?= formatDateEs($t['fecha_limite']) ?></td>
                        <td>
                            <?php if ($t['calificacion'] !== null): ?>
                                <strong><?= e((string) $t['calificacion']) ?> / 20</strong>
                            <?php elseif ($t['fecha_entrega']): ?>
                                <span class="av-text-muted">Entregado, sin calificar</span>
                            <?php else: ?>
                                <span class="av-text-muted">Sin entregar</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($t['comentario'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($curso['examenes'] ?? null): ?>
            <div class="av-table-wrap" style="margin-top:14px">
                <table class="av-table">
                    <thead><tr><th>Examen</th><th>Peso</th><th>Fecha límite</th><th>Calificación</th></tr></thead>
                    <tbody>
                    <?php foreach ($curso['examenes'] as $ex): ?>
                        <tr>
                            <td><?= e($ex['titulo']) ?></td>
                            <td><?= number_format((float) $ex['peso'], 2) ?></td>
                            <td><?= formatDateEs($ex['fecha_limite']) ?></td>
                            <td>
                                <?php if ($ex['calificacion'] !== null): ?>
                                    <strong><?= e((string) $ex['calificacion']) ?> / 20</strong>
                                <?php elseif (isPastDue($ex['fecha_limite'])): ?>
                                    <span class="av-text-muted">Vencido, sin rendir</span>
                                <?php else: ?>
                                    <span class="av-text-muted">Pendiente</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
<?php if (!$porCurso): ?>
    <div class="av-alert av-alert--danger"><span>No hay tareas registradas todavía.</span></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

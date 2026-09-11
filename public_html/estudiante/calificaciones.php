<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    'SELECT c.id AS curso_id, c.nombre AS curso_nombre, t.titulo, t.fecha_limite,
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

$porCurso = [];
foreach ($filas as $f) {
    $porCurso[$f['curso_id']]['nombre'] = $f['curso_nombre'];
    $porCurso[$f['curso_id']]['tareas'][] = $f;
}

$pageTitle = 'Mis calificaciones';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Mis calificaciones</h2></div>

<?php foreach ($porCurso as $curso): ?>
    <?php
        $notas = array_filter(array_column($curso['tareas'], 'calificacion'), fn($n) => $n !== null);
        $promedio = $notas ? array_sum($notas) / count($notas) : null;
    ?>
    <div class="av-card" style="margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <h3 style="margin:0"><?= e($curso['nombre']) ?></h3>
            <?php if ($promedio !== null): ?>
                <span class="av-badge av-badge--<?= $promedio >= 11 ? 'green' : 'red' ?>">Promedio: <?= number_format($promedio, 2) ?> / 20</span>
            <?php endif; ?>
        </div>
        <div class="av-table-wrap">
            <table class="av-table">
                <thead><tr><th>Tarea</th><th>Fecha límite</th><th>Calificación</th><th>Comentario</th></tr></thead>
                <tbody>
                <?php foreach ($curso['tareas'] as $t): ?>
                    <tr>
                        <td><?= e($t['titulo']) ?></td>
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
    </div>
<?php endforeach; ?>
<?php if (!$porCurso): ?>
    <div class="av-alert av-alert--danger"><span>No hay tareas registradas todavía.</span></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

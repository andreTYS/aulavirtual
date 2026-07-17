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
<h2 class="mb-4">Mis calificaciones</h2>

<?php foreach ($porCurso as $curso): ?>
    <?php
        $notas = array_filter(array_column($curso['tareas'], 'calificacion'), fn($n) => $n !== null);
        $promedio = $notas ? array_sum($notas) / count($notas) : null;
    ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= e($curso['nombre']) ?></span>
            <?php if ($promedio !== null): ?>
                <span class="badge bg-<?= $promedio >= 11 ? 'success' : 'danger' ?>">Promedio: <?= number_format($promedio, 2) ?> / 20</span>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light"><tr><th>Tarea</th><th>Fecha límite</th><th>Calificación</th><th>Comentario</th></tr></thead>
                <tbody>
                <?php foreach ($curso['tareas'] as $t): ?>
                    <tr>
                        <td><?= e($t['titulo']) ?></td>
                        <td><?= formatDateEs($t['fecha_limite']) ?></td>
                        <td>
                            <?php if ($t['calificacion'] !== null): ?>
                                <?= e((string) $t['calificacion']) ?> / 20
                            <?php elseif ($t['fecha_entrega']): ?>
                                <span class="text-muted">Entregado, sin calificar</span>
                            <?php else: ?>
                                <span class="text-muted">Sin entregar</span>
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
    <div class="alert alert-info">No hay tareas registradas todavía.</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

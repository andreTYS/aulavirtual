<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

$cursosStmt = $pdo->prepare(
    "SELECT c.id, c.nombre FROM matriculas m JOIN cursos c ON c.id = m.curso_id
     WHERE m.estudiante_id = :estudiante_id ORDER BY c.nombre"
);
$cursosStmt->execute(['estudiante_id' => $estudianteId]);
$cursos = $cursosStmt->fetchAll();

$unidadesStmt = $pdo->prepare(
    "SELECT u.* FROM unidades u
     JOIN matriculas m ON m.curso_id = u.curso_id
     WHERE m.estudiante_id = :estudiante_id ORDER BY u.curso_id, u.orden, u.id"
);
$unidadesStmt->execute(['estudiante_id' => $estudianteId]);
$unidadesPorCurso = [];
foreach ($unidadesStmt->fetchAll() as $u) {
    $unidadesPorCurso[(int) $u['curso_id']][] = $u;
}

$stmt = $pdo->prepare(
    'SELECT c.id AS curso_id, t.unidad_id, t.titulo, t.fecha_limite, t.categoria, t.peso,
            e.calificacion, e.comentario, e.fecha_entrega
     FROM matriculas m
     JOIN cursos c ON c.id = m.curso_id
     JOIN tareas t ON t.curso_id = c.id
     LEFT JOIN entregas e ON e.tarea_id = t.id AND e.estudiante_id = m.estudiante_id
     WHERE m.estudiante_id = :estudiante_id
     ORDER BY c.nombre, t.fecha_limite'
);
$stmt->execute(['estudiante_id' => $estudianteId]);
$tareasPorUnidad = [];
foreach ($stmt->fetchAll() as $t) {
    $tareasPorUnidad[(int) $t['curso_id']][(int) $t['unidad_id']][] = $t;
}

$examenesStmt = $pdo->prepare(
    'SELECT c.id AS curso_id, ex.unidad_id, ex.titulo, ex.fecha_limite, ex.peso,
            i.puntaje AS calificacion, i.fecha_envio
     FROM matriculas m
     JOIN cursos c ON c.id = m.curso_id
     JOIN examenes ex ON ex.curso_id = c.id
     LEFT JOIN examen_intentos i ON i.examen_id = ex.id AND i.estudiante_id = m.estudiante_id
     WHERE m.estudiante_id = :estudiante_id
     ORDER BY c.nombre, ex.fecha_limite'
);
$examenesStmt->execute(['estudiante_id' => $estudianteId]);
$examenesPorUnidad = [];
foreach ($examenesStmt->fetchAll() as $ex) {
    $examenesPorUnidad[(int) $ex['curso_id']][(int) $ex['unidad_id']][] = $ex;
}

$categoriaLabels = ['practica' => 'Práctica', 'examen' => 'Examen', 'participacion' => 'Participación', 'trabajo' => 'Trabajo'];

$pageTitle = 'Mis calificaciones';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Mis calificaciones</h2></div>

<?php foreach ($cursos as $curso): ?>
    <?php
        $cid = (int) $curso['id'];
        $unidades = $unidadesPorCurso[$cid] ?? [];
        if (!$unidades) {
            continue;
        }

        $promediosUnidad = [];
        foreach ($unidades as $u) {
            $uid = (int) $u['id'];
            $tUnidad = $tareasPorUnidad[$cid][$uid] ?? [];
            $exUnidad = $examenesPorUnidad[$cid][$uid] ?? [];
            $filasUnidad = array_merge(
                array_map(fn($t) => ['calificacion' => $t['calificacion'], 'peso' => $t['peso']], $tUnidad),
                array_map(fn($ex) => ['calificacion' => $ex['calificacion'], 'peso' => $ex['peso']], $exUnidad)
            );
            $promediosUnidad[$uid] = promedioPonderado($filasUnidad);
        }
        $filasFinal = array_map(fn($u) => ['calificacion' => $promediosUnidad[(int) $u['id']], 'peso' => $u['peso']], $unidades);
        $promedioFinal = promedioPonderado($filasFinal);
    ?>
    <div class="av-card" style="margin-bottom:18px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <h3 style="margin:0"><?= e($curso['nombre']) ?></h3>
            <?php if ($promedioFinal !== null): ?>
                <span class="av-badge av-badge--<?= $promedioFinal >= 11 ? 'green' : 'red' ?>">Promedio final: <?= number_format($promedioFinal, 2) ?> / 20</span>
            <?php endif; ?>
        </div>

        <?php foreach ($unidades as $u): ?>
            <?php
                $uid = (int) $u['id'];
                $tUnidad = $tareasPorUnidad[$cid][$uid] ?? [];
                $exUnidad = $examenesPorUnidad[$cid][$uid] ?? [];
                $promedioU = $promediosUnidad[$uid];
            ?>
            <h4 style="display:flex;justify-content:space-between;align-items:center;margin:16px 0 8px">
                <span><?= e($u['nombre']) ?></span>
                <?php if ($promedioU !== null): ?>
                    <span class="av-badge av-badge--<?= $promedioU >= 11 ? 'green' : 'red' ?>"><?= number_format($promedioU, 2) ?> / 20</span>
                <?php endif; ?>
            </h4>
            <div class="av-table-wrap">
                <table class="av-table">
                    <thead><tr><th>Evaluación</th><th>Categoría</th><th>Peso</th><th>Fecha límite</th><th>Calificación</th><th>Comentario</th></tr></thead>
                    <tbody>
                    <?php foreach ($tUnidad as $t): ?>
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
                    <?php foreach ($exUnidad as $ex): ?>
                        <tr>
                            <td><?= e($ex['titulo']) ?></td>
                            <td><span class="av-badge av-badge--gray">Examen</span></td>
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
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$tUnidad && !$exUnidad): ?>
                        <tr><td colspan="6" class="av-empty">Sin evaluaciones publicadas en esta unidad.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
<?php if (!array_filter($unidadesPorCurso)): ?>
    <div class="av-alert av-alert--danger"><span>No hay evaluaciones registradas todavía.</span></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

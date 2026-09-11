<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];
$tareaId = (int) ($_GET['tarea_id'] ?? $_POST['tarea_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT t.*, c.nombre AS curso_nombre, c.id AS curso_id
     FROM tareas t JOIN cursos c ON c.id = t.curso_id
     WHERE t.id = :id AND c.docente_id = :docente_id'
);
$stmt->execute(['id' => $tareaId, 'docente_id' => $docenteId]);
$tarea = $stmt->fetch();
if (!$tarea) {
    setFlash('danger', 'Tarea no encontrada o no tiene permisos sobre ella.');
    redirect('/docente/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $entregaId = (int) ($_POST['entrega_id'] ?? 0);
    $calificacion = $_POST['calificacion'] ?? '';
    $comentario = trim((string) ($_POST['comentario'] ?? ''));

    if ($calificacion === '' || !is_numeric($calificacion) || (float) $calificacion < 0 || (float) $calificacion > 20) {
        setFlash('danger', 'La calificación debe ser un número entre 0 y 20.');
    } else {
        $upd = $pdo->prepare(
            'UPDATE entregas e
             JOIN tareas t ON t.id = e.tarea_id
             SET e.calificacion = :calificacion, e.comentario = :comentario, e.fecha_calificacion = NOW()
             WHERE e.id = :entrega_id AND t.curso_id = :curso_id'
        );
        $upd->execute([
            'calificacion' => number_format((float) $calificacion, 2, '.', ''),
            'comentario' => $comentario,
            'entrega_id' => $entregaId,
            'curso_id' => $tarea['curso_id'],
        ]);
        setFlash('success', 'Calificación registrada.');
    }
    redirect('/docente/tarea_entregas.php?tarea_id=' . $tareaId);
}

$alumnos = $pdo->prepare(
    'SELECT u.id AS estudiante_id, u.nombre, u.apellidos,
            e.id AS entrega_id, e.archivo_path, e.fecha_entrega, e.calificacion, e.comentario
     FROM matriculas m
     JOIN usuarios u ON u.id = m.estudiante_id
     LEFT JOIN entregas e ON e.tarea_id = :tarea_id AND e.estudiante_id = u.id
     WHERE m.curso_id = :curso_id AND m.estado = "activo"
     ORDER BY u.apellidos, u.nombre'
);
$alumnos->execute(['tarea_id' => $tareaId, 'curso_id' => $tarea['curso_id']]);
$alumnos = $alumnos->fetchAll();

$pageTitle = 'Entregas - ' . $tarea['titulo'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2><?= e($tarea['titulo']) ?></h2>
    <a href="/docente/curso.php?id=<?= (int) $tarea['curso_id'] ?>" class="av-btn av-btn--outline">&larr; <?= e($tarea['curso_nombre']) ?></a>
    <p>Fecha límite: <?= formatDateEs($tarea['fecha_limite']) ?></p>
</div>

<?php foreach ($alumnos as $a): ?>
    <?php if ($a['entrega_id']): ?>
        <form method="post" id="form-entrega-<?= (int) $a['entrega_id'] ?>">
            <?= csrfField() ?>
            <input type="hidden" name="tarea_id" value="<?= $tareaId ?>">
            <input type="hidden" name="entrega_id" value="<?= (int) $a['entrega_id'] ?>">
        </form>
    <?php endif; ?>
<?php endforeach; ?>

<div class="av-table-wrap">
    <table class="av-table">
        <thead>
            <tr><th>Estudiante</th><th>Entrega</th><th>Calificación (0-20)</th><th>Comentario</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($alumnos as $a): ?>
            <?php $formId = 'form-entrega-' . (int) $a['entrega_id']; ?>
            <tr>
                <td><strong><?= e($a['nombre'] . ' ' . $a['apellidos']) ?></strong></td>
                <td>
                    <?php if ($a['entrega_id']): ?>
                        <a href="/download.php?type=entrega&id=<?= (int) $a['entrega_id'] ?>" style="color:var(--ab600);font-weight:600">Descargar</a>
                        <div style="font-size:.75rem;color:var(--n500)"><?= formatDateEs($a['fecha_entrega']) ?></div>
                    <?php else: ?>
                        <span class="av-badge av-badge--gray">Sin entregar</span>
                    <?php endif; ?>
                </td>
                <?php if ($a['entrega_id']): ?>
                    <td style="max-width:110px">
                        <input type="number" name="calificacion" form="<?= $formId ?>" min="0" max="20" step="0.5"
                               value="<?= e($a['calificacion'] !== null ? (string) $a['calificacion'] : '') ?>" required
                               style="width:100%;padding:7px 10px;border:1.5px solid var(--n200);border-radius:6px">
                    </td>
                    <td>
                        <input type="text" name="comentario" form="<?= $formId ?>" value="<?= e($a['comentario'] ?? '') ?>"
                               style="width:100%;padding:7px 10px;border:1.5px solid var(--n200);border-radius:6px">
                    </td>
                    <td><button class="av-btn av-btn--primary av-btn--sm" form="<?= $formId ?>" type="submit">Guardar</button></td>
                <?php else: ?>
                    <td class="av-text-muted">-</td><td class="av-text-muted">-</td><td>-</td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (!$alumnos): ?>
            <tr><td colspan="5" class="av-empty">No hay estudiantes matriculados en este curso.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

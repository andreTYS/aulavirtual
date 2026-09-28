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
    $estudianteId = (int) ($_POST['estudiante_id'] ?? 0);
    $calificacion = $_POST['calificacion'] ?? '';
    $comentario = trim((string) ($_POST['comentario'] ?? ''));

    if ($calificacion === '' || !is_numeric($calificacion) || (float) $calificacion < 0 || (float) $calificacion > 20) {
        setFlash('danger', 'La calificación debe ser un número entre 0 y 20.');
    } else {
        // Si ya existe una entrega (el estudiante subió un archivo) solo se
        // actualiza la calificación, sin tocar archivo_path/fecha_entrega. Si
        // no existe, se crea una calificación directa (sin entrega digital),
        // por ejemplo para un examen oral o participación en clase.
        $upsert = $pdo->prepare(
            'INSERT INTO entregas (tarea_id, estudiante_id, calificacion, comentario, fecha_calificacion)
             VALUES (:tarea_id, :estudiante_id, :calificacion, :comentario, NOW())
             ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion), comentario = VALUES(comentario), fecha_calificacion = NOW()'
        );
        $upsert->execute([
            'tarea_id' => $tareaId,
            'estudiante_id' => $estudianteId,
            'calificacion' => number_format((float) $calificacion, 2, '.', ''),
            'comentario' => $comentario !== '' ? $comentario : null,
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

$categoriaLabels = ['practica' => 'Práctica', 'examen' => 'Examen', 'participacion' => 'Participación', 'trabajo' => 'Trabajo'];

$pageTitle = 'Entregas - ' . $tarea['titulo'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2><?= e($tarea['titulo']) ?></h2>
    <a href="/docente/curso.php?id=<?= (int) $tarea['curso_id'] ?>" class="av-btn av-btn--outline">&larr; <?= e($tarea['curso_nombre']) ?></a>
    <p>
        <span class="av-badge av-badge--gray"><?= e($categoriaLabels[$tarea['categoria']] ?? $tarea['categoria']) ?></span>
        Peso <?= number_format((float) $tarea['peso'], 2) ?> &middot; Fecha límite: <?= formatDateEs($tarea['fecha_limite']) ?>
    </p>
</div>

<?php foreach ($alumnos as $a): ?>
    <form method="post" id="form-est-<?= (int) $a['estudiante_id'] ?>">
        <?= csrfField() ?>
        <input type="hidden" name="tarea_id" value="<?= $tareaId ?>">
        <input type="hidden" name="estudiante_id" value="<?= (int) $a['estudiante_id'] ?>">
    </form>
<?php endforeach; ?>

<div class="av-table-wrap">
    <table class="av-table">
        <thead>
            <tr><th>Estudiante</th><th>Entrega</th><th>Calificación (0-20)</th><th>Comentario</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($alumnos as $a): ?>
            <?php $formId = 'form-est-' . (int) $a['estudiante_id']; ?>
            <tr>
                <td><strong><?= e($a['nombre'] . ' ' . $a['apellidos']) ?></strong></td>
                <td>
                    <?php if ($a['entrega_id'] && $a['archivo_path']): ?>
                        <a href="/download.php?type=entrega&id=<?= (int) $a['entrega_id'] ?>" style="color:var(--ab600);font-weight:600">Descargar</a>
                        <div style="font-size:.75rem;color:var(--n500)"><?= formatDateEs($a['fecha_entrega']) ?></div>
                    <?php else: ?>
                        <span class="av-badge av-badge--gray">Sin entrega digital</span>
                    <?php endif; ?>
                </td>
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
            </tr>
        <?php endforeach; ?>
        <?php if (!$alumnos): ?>
            <tr><td colspan="5" class="av-empty">No hay estudiantes matriculados en este curso.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

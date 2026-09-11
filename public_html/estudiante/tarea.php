<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];
$tareaId = (int) ($_GET['id'] ?? $_POST['tarea_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT t.*, c.nombre AS curso_nombre, c.id AS curso_id
     FROM tareas t
     JOIN cursos c ON c.id = t.curso_id
     JOIN matriculas m ON m.curso_id = c.id
     WHERE t.id = :id AND m.estudiante_id = :estudiante_id'
);
$stmt->execute(['id' => $tareaId, 'estudiante_id' => $estudianteId]);
$tarea = $stmt->fetch();
if (!$tarea) {
    setFlash('danger', 'Tarea no encontrada o no tiene acceso a ella.');
    redirect('/estudiante/index.php');
}

$entregaStmt = $pdo->prepare('SELECT * FROM entregas WHERE tarea_id = :tarea_id AND estudiante_id = :estudiante_id');
$entregaStmt->execute(['tarea_id' => $tareaId, 'estudiante_id' => $estudianteId]);
$entrega = $entregaStmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if ($entrega && $entrega['calificacion'] !== null) {
        $errors[] = 'Esta tarea ya fue calificada, no puede reemplazar la entrega.';
    } else {
        try {
            $archivoPath = handleUpload('archivo', 'entregas/' . $tarea['curso_id'] . '/' . $tareaId, ALLOWED_ENTREGA_EXT);
            if (!$archivoPath) {
                throw new RuntimeException('Debe adjuntar un archivo para entregar la tarea.');
            }
            if ($entrega) {
                $upd = $pdo->prepare('UPDATE entregas SET archivo_path = :archivo_path, fecha_entrega = NOW() WHERE id = :id');
                $upd->execute(['archivo_path' => $archivoPath, 'id' => $entrega['id']]);
                setFlash('success', 'Entrega actualizada correctamente.');
            } else {
                $ins = $pdo->prepare(
                    'INSERT INTO entregas (tarea_id, estudiante_id, archivo_path) VALUES (:tarea_id, :estudiante_id, :archivo_path)'
                );
                $ins->execute(['tarea_id' => $tareaId, 'estudiante_id' => $estudianteId, 'archivo_path' => $archivoPath]);
                setFlash('success', 'Tarea entregada correctamente.');
            }
            redirect('/estudiante/tarea.php?id=' . $tareaId);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = $tarea['titulo'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2><?= e($tarea['titulo']) ?></h2>
    <a href="/estudiante/curso.php?id=<?= (int) $tarea['curso_id'] ?>" class="av-btn av-btn--outline">&larr; <?= e($tarea['curso_nombre']) ?></a>
</div>

<div class="av-card">
    <p><strong>Fecha límite:</strong> <?= formatDateEs($tarea['fecha_limite']) ?>
        <?php if (isPastDue($tarea['fecha_limite'])): ?><span class="av-badge av-badge--red">Vencida</span><?php endif; ?>
    </p>
    <p style="margin-top:10px"><?= nl2br(e($tarea['descripcion'] ?? '')) ?></p>
</div>

<?php if ($errors): ?>
    <div class="av-alert av-alert--danger">
        <span><?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?></span>
    </div>
<?php endif; ?>

<div class="av-card">
    <h3>Mi entrega</h3>
    <?php if ($entrega): ?>
        <p>Entregado el <?= formatDateEs($entrega['fecha_entrega']) ?> &middot;
            <a href="/download.php?type=entrega&id=<?= (int) $entrega['id'] ?>" style="color:var(--ab600);font-weight:600">Descargar mi archivo</a></p>
        <?php if ($entrega['calificacion'] !== null): ?>
            <div class="av-grade-box" style="margin-top:12px">
                <strong>Calificación: <?= e((string) $entrega['calificacion']) ?> / 20</strong><br>
                <?php if ($entrega['comentario']): ?>
                    Comentario del docente: <?= nl2br(e($entrega['comentario'])) ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="av-text-muted" style="margin:10px 0">Aún no calificada. Puede reemplazar el archivo mientras no haya calificación.</p>
            <form method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="tarea_id" value="<?= $tareaId ?>">
                <div class="av-fg"><input type="file" name="archivo" required></div>
                <button type="submit" class="av-btn av-btn--primary">Reemplazar entrega</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="tarea_id" value="<?= $tareaId ?>">
            <div class="av-fg"><input type="file" name="archivo" required></div>
            <button type="submit" class="av-btn av-btn--primary">Entregar tarea</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];
$cursoId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT c.* FROM cursos c
     JOIN matriculas m ON m.curso_id = c.id
     WHERE c.id = :id AND m.estudiante_id = :estudiante_id'
);
$stmt->execute(['id' => $cursoId, 'estudiante_id' => $estudianteId]);
$curso = $stmt->fetch();
if (!$curso) {
    setFlash('danger', 'Curso no encontrado o no está matriculado en él.');
    redirect('/estudiante/index.php');
}

$unidades = $pdo->prepare('SELECT * FROM unidades WHERE curso_id = :curso_id ORDER BY orden, id');
$unidades->execute(['curso_id' => $cursoId]);
$unidades = $unidades->fetchAll();

$contenidosStmt = $pdo->prepare('SELECT * FROM contenidos WHERE curso_id = :curso_id ORDER BY fecha_publicacion');
$contenidosStmt->execute(['curso_id' => $cursoId]);
$contenidosPorUnidad = [];
foreach ($contenidosStmt->fetchAll() as $cont) {
    $contenidosPorUnidad[(int) $cont['unidad_id']][] = $cont;
}

$tareasStmt = $pdo->prepare(
    'SELECT t.*, e.id AS entrega_id, e.fecha_entrega, e.calificacion
     FROM tareas t
     LEFT JOIN entregas e ON e.tarea_id = t.id AND e.estudiante_id = :estudiante_id
     WHERE t.curso_id = :curso_id ORDER BY t.fecha_limite'
);
$tareasStmt->execute(['estudiante_id' => $estudianteId, 'curso_id' => $cursoId]);
$tareas = $tareasStmt->fetchAll();

$pageTitle = $curso['nombre'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2><?= e($curso['nombre']) ?></h2>
    <a href="/estudiante/index.php" class="av-btn av-btn--outline">&larr; Mis cursos</a>
    <p><?= nl2br(e($curso['descripcion'] ?? '')) ?></p>
</div>

<div class="av-tabs-wrap">
    <div class="av-tabs">
        <button type="button" class="av-tab active" data-tab-target="tab-contenido">Contenido</button>
        <button type="button" class="av-tab" data-tab-target="tab-tareas">Tareas</button>
    </div>

    <div class="av-tabpanel active" id="tab-contenido">
        <?php foreach ($unidades as $idx => $u): ?>
            <?php $uid = (int) $u['id']; $items = $contenidosPorUnidad[$uid] ?? []; ?>
            <div class="av-accordion-item<?= $idx === 0 ? ' open' : '' ?>">
                <button type="button" class="av-accordion-header">
                    <span><?= e($u['nombre']) ?></span>
                    <span class="av-badge av-badge--gray"><?= count($items) ?> contenido(s)</span>
                    <span class="chev">&#9660;</span>
                </button>
                <div class="av-accordion-body">
                    <div class="av-list">
                        <?php foreach ($items as $item): ?>
                            <div class="av-list-item">
                                <div>
                                    <span class="av-badge av-badge--blue"><?= e($item['tipo']) ?></span>
                                    <span class="content-title"><?= e($item['titulo']) ?></span>
                                    <?php if ($item['descripcion']): ?><div class="content-desc"><?= e($item['descripcion']) ?></div><?php endif; ?>
                                    <div style="margin-top:4px">
                                        <?php if ($item['tipo'] === 'enlace'): ?>
                                            <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener" style="color:var(--ab600);font-weight:600;font-size:.82rem">Abrir enlace</a>
                                        <?php else: ?>
                                            <a href="/download.php?type=contenido&id=<?= (int) $item['id'] ?>" style="color:var(--ab600);font-weight:600;font-size:.82rem">Descargar archivo</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <div class="av-empty">Sin contenido publicado en esta unidad.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$unidades): ?>
            <div class="av-alert av-alert--danger"><span>El docente aún no ha publicado unidades para este curso.</span></div>
        <?php endif; ?>
    </div>

    <div class="av-tabpanel" id="tab-tareas">
        <div class="av-table-wrap">
            <table class="av-table">
                <thead><tr><th>Tarea</th><th>Fecha límite</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($tareas as $t): ?>
                    <tr>
                        <td><strong><?= e($t['titulo']) ?></strong></td>
                        <td><?= formatDateEs($t['fecha_limite']) ?></td>
                        <td>
                            <?php if ($t['calificacion'] !== null): ?>
                                <span class="av-badge av-badge--green">Calificado: <?= e((string) $t['calificacion']) ?>/20</span>
                            <?php elseif ($t['entrega_id']): ?>
                                <span class="av-badge av-badge--blue">Entregado</span>
                            <?php elseif (isPastDue($t['fecha_limite'])): ?>
                                <span class="av-badge av-badge--red">Vencida - sin entregar</span>
                            <?php else: ?>
                                <span class="av-badge av-badge--amber">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="/estudiante/tarea.php?id=<?= (int) $t['id'] ?>" class="av-btn av-btn--secondary av-btn--sm">Ver detalle</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tareas): ?>
                    <tr><td colspan="4" class="av-empty">No hay tareas publicadas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

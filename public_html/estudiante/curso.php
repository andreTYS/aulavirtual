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
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= e($curso['nombre']) ?></h2>
    <a href="/estudiante/index.php" class="btn btn-outline-secondary">&larr; Mis cursos</a>
</div>
<p class="text-muted"><?= nl2br(e($curso['descripcion'] ?? '')) ?></p>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-contenido">Contenido</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tareas">Tareas</button></li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-contenido">
    <div class="accordion" id="unidadesAccordion">
        <?php foreach ($unidades as $idx => $u): ?>
            <?php $uid = (int) $u['id']; $items = $contenidosPorUnidad[$uid] ?? []; ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#unidad-<?= $uid ?>">
                        <?= e($u['nombre']) ?> <span class="badge bg-secondary ms-2"><?= count($items) ?> contenido(s)</span>
                    </button>
                </h2>
                <div id="unidad-<?= $uid ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#unidadesAccordion">
                    <div class="accordion-body">
                        <ul class="list-group">
                            <?php foreach ($items as $item): ?>
                                <li class="list-group-item">
                                    <span class="badge bg-info text-dark text-uppercase"><?= e($item['tipo']) ?></span>
                                    <strong><?= e($item['titulo']) ?></strong>
                                    <?php if ($item['descripcion']): ?><div class="small text-muted"><?= e($item['descripcion']) ?></div><?php endif; ?>
                                    <div>
                                        <?php if ($item['tipo'] === 'enlace'): ?>
                                            <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener">Abrir enlace</a>
                                        <?php else: ?>
                                            <a href="/download.php?type=contenido&id=<?= (int) $item['id'] ?>">Descargar archivo</a>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                            <?php if (!$items): ?>
                                <li class="list-group-item text-muted">Sin contenido publicado en esta unidad.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$unidades): ?>
            <div class="alert alert-info">El docente aún no ha publicado unidades para este curso.</div>
        <?php endif; ?>
    </div>
</div>

<div class="tab-pane fade" id="tab-tareas">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Tarea</th><th>Fecha límite</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($tareas as $t): ?>
                    <tr>
                        <td><?= e($t['titulo']) ?></td>
                        <td><?= formatDateEs($t['fecha_limite']) ?></td>
                        <td>
                            <?php if ($t['calificacion'] !== null): ?>
                                <span class="badge bg-success">Calificado: <?= e((string) $t['calificacion']) ?>/20</span>
                            <?php elseif ($t['entrega_id']): ?>
                                <span class="badge bg-primary">Entregado</span>
                            <?php elseif (isPastDue($t['fecha_limite'])): ?>
                                <span class="badge bg-danger">Vencida - sin entregar</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pendiente</span>
                            <?php endif; ?>
                        </td>
                        <td><a href="/estudiante/tarea.php?id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tareas): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No hay tareas publicadas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];
$cursoId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    "SELECT c.* FROM cursos c
     JOIN matriculas m ON m.curso_id = c.id
     WHERE c.id = :id AND m.estudiante_id = :estudiante_id AND m.estado = 'activo'"
);
$stmt->execute(['id' => $cursoId, 'estudiante_id' => $estudianteId]);
$curso = $stmt->fetch();
if (!$curso) {
    setFlash('danger', 'Curso no encontrado o no está matriculado en él.');
    redirect('/estudiante/index.php');
}

$sesiones = $pdo->prepare('SELECT * FROM sesiones WHERE curso_id = :curso_id ORDER BY fecha DESC, hora_inicio DESC');
$sesiones->execute(['curso_id' => $cursoId]);
$sesiones = $sesiones->fetchAll();

$contenidos = $pdo->prepare(
    'SELECT c.*, s.tema AS sesion_tema, s.fecha AS sesion_fecha FROM contenidos c
     LEFT JOIN sesiones s ON s.id = c.sesion_id
     WHERE c.curso_id = :curso_id ORDER BY c.fecha_publicacion DESC'
);
$contenidos->execute(['curso_id' => $cursoId]);
$contenidos = $contenidos->fetchAll();

$tareasStmt = $pdo->prepare(
    'SELECT t.*, e.id AS entrega_id, e.fecha_entrega, e.calificacion
     FROM tareas t
     LEFT JOIN entregas e ON e.tarea_id = t.id AND e.estudiante_id = :estudiante_id
     WHERE t.curso_id = :curso_id ORDER BY t.fecha_limite'
);
$tareasStmt->execute(['estudiante_id' => $estudianteId, 'curso_id' => $cursoId]);
$tareas = $tareasStmt->fetchAll();

$asistencias = $pdo->prepare(
    'SELECT s.id AS sesion_id, s.fecha, s.tema, s.estado AS sesion_estado, a.estado AS asistencia_estado
     FROM sesiones s
     LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = :estudiante_id
     WHERE s.curso_id = :curso_id AND s.estado != "cancelada"
     ORDER BY s.fecha DESC, s.hora_inicio DESC'
);
$asistencias->execute(['estudiante_id' => $estudianteId, 'curso_id' => $cursoId]);
$asistencias = $asistencias->fetchAll();
$registradas = array_filter($asistencias, fn($a) => $a['asistencia_estado'] !== null);
$presentesOTarde = array_filter($registradas, fn($a) => in_array($a['asistencia_estado'], ['presente', 'tarde'], true));
$pctAsistencia = count($registradas) > 0 ? round((count($presentesOTarde) / count($registradas)) * 100) : null;

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
        <button type="button" class="av-tab active" data-tab-target="tab-sesiones">Sesiones</button>
        <button type="button" class="av-tab" data-tab-target="tab-materiales">Materiales</button>
        <button type="button" class="av-tab" data-tab-target="tab-tareas">Tareas</button>
        <button type="button" class="av-tab" data-tab-target="tab-asistencia">Mi asistencia</button>
    </div>

    <!-- SESIONES -->
    <div class="av-tabpanel active" id="tab-sesiones">
        <div class="av-list">
            <?php foreach ($sesiones as $s): ?>
                <?php $esHoy = isSessionToday($s['fecha']); $esPasada = isSessionPast($s['fecha']); ?>
                <div class="av-list-item">
                    <div>
                        <div class="content-title">
                            <?= formatFechaEs($s['fecha']) ?> <?= formatHoraEs($s['hora_inicio']) ?> &middot; <?= e($s['tema']) ?>
                            <?php if ($s['estado'] === 'cancelada'): ?><span class="av-badge av-badge--red">Cancelada</span>
                            <?php elseif ($esHoy): ?><span class="av-badge av-badge--green">Hoy</span>
                            <?php elseif ($esPasada): ?><span class="av-badge av-badge--gray">Realizada</span>
                            <?php else: ?><span class="av-badge av-badge--blue">Programada</span><?php endif; ?>
                        </div>
                        <?php if ($esPasada && $s['link_grabacion']): ?>
                            <div class="content-desc"><a href="<?= e($s['link_grabacion']) ?>" target="_blank" rel="noopener" style="color:var(--ab600);font-weight:600">Ver grabación</a></div>
                        <?php elseif (!$esPasada && $s['estado'] !== 'cancelada' && !$esHoy): ?>
                            <div class="content-desc">El acceso se habilita el mismo día de la sesión.</div>
                        <?php endif; ?>
                    </div>
                    <?php if ($esHoy && $s['estado'] !== 'cancelada' && $s['link_zoom']): ?>
                        <a href="<?= e($s['link_zoom']) ?>" target="_blank" rel="noopener" class="av-btn av-btn--primary av-btn--sm">Ingresar a clase</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (!$sesiones): ?>
                <div class="av-empty">El docente aún no ha programado sesiones para este curso.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MATERIALES -->
    <div class="av-tabpanel" id="tab-materiales">
        <div class="av-list">
            <?php foreach ($contenidos as $item): ?>
                <div class="av-list-item">
                    <div>
                        <span class="av-badge av-badge--blue"><?= e($item['tipo']) ?></span>
                        <span class="content-title"><?= e($item['titulo']) ?></span>
                        <?php if ($item['sesion_tema']): ?><span class="av-badge av-badge--gray"><?= formatFechaEs($item['sesion_fecha']) ?> - <?= e($item['sesion_tema']) ?></span><?php endif; ?>
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
            <?php if (!$contenidos): ?>
                <div class="av-empty">Sin materiales publicados en este curso.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAREAS -->
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

    <!-- ASISTENCIA -->
    <div class="av-tabpanel" id="tab-asistencia">
        <?php if ($pctAsistencia !== null): ?>
            <div class="av-card" style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center">
                <span>Asistencia acumulada en este curso</span>
                <span class="av-badge av-badge--<?= $pctAsistencia >= 70 ? 'green' : 'red' ?>" style="font-size:.9rem;padding:6px 14px"><?= $pctAsistencia ?>%</span>
            </div>
        <?php endif; ?>
        <div class="av-table-wrap">
            <table class="av-table">
                <thead><tr><th>Sesión</th><th>Fecha</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($asistencias as $a): ?>
                    <tr>
                        <td><?= e($a['tema']) ?></td>
                        <td><?= formatFechaEs($a['fecha']) ?></td>
                        <td>
                            <?php
                                $badgeMap = ['presente' => 'green', 'tarde' => 'amber', 'falta' => 'red', 'justificado' => 'blue'];
                                $estado = $a['asistencia_estado'];
                            ?>
                            <?php if ($estado): ?>
                                <span class="av-badge av-badge--<?= $badgeMap[$estado] ?? 'gray' ?>"><?= ucfirst($estado) ?></span>
                            <?php else: ?>
                                <span class="av-text-muted">Sin registrar</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$asistencias): ?>
                    <tr><td colspan="3" class="av-empty">No hay sesiones registradas todavía.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

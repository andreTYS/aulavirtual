<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT c.*, car.nombre AS carrera_nombre, p.nombre AS periodo_nombre,
            CONCAT(d.nombre, ' ', d.apellidos) AS docente_nombre,
            (SELECT COUNT(*) FROM tareas t WHERE t.curso_id = c.id) AS total_tareas
     FROM matriculas m
     JOIN cursos c ON c.id = m.curso_id
     LEFT JOIN carreras car ON car.id = c.carrera_id
     LEFT JOIN periodos_academicos p ON p.id = c.periodo_academico_id
     LEFT JOIN usuarios d ON d.id = c.docente_id
     WHERE m.estudiante_id = :estudiante_id AND m.estado = 'activo'
     ORDER BY c.nombre"
);
$stmt->execute(['estudiante_id' => $estudianteId]);
$cursos = $stmt->fetchAll();

$proxima = $pdo->prepare(
    "SELECT s.*, c.nombre AS curso_nombre
     FROM sesiones s
     JOIN cursos c ON c.id = s.curso_id
     JOIN matriculas m ON m.curso_id = c.id
     WHERE m.estudiante_id = :estudiante_id AND m.estado = 'activo'
       AND s.estado != 'cancelada' AND s.fecha >= CURDATE()
     ORDER BY s.fecha, s.hora_inicio
     LIMIT 1"
);
$proxima->execute(['estudiante_id' => $estudianteId]);
$proxima = $proxima->fetch();

$avisos = $pdo->prepare(
    "SELECT a.*, c.nombre AS curso_nombre FROM avisos a
     LEFT JOIN cursos c ON c.id = a.curso_id
     WHERE a.curso_id IS NULL
        OR a.curso_id IN (SELECT curso_id FROM matriculas WHERE estudiante_id = :estudiante_id AND estado = 'activo')
     ORDER BY a.created_at DESC LIMIT 5"
);
$avisos->execute(['estudiante_id' => $estudianteId]);
$avisos = $avisos->fetchAll();

$pageTitle = 'Mis cursos';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($proxima): ?>
    <div class="av-welcome">
        <div class="av-welcome__text">
            <h2>Próxima sesión: <?= e($proxima['tema']) ?></h2>
            <p><?= e($proxima['curso_nombre']) ?> &middot; <?= formatFechaEs($proxima['fecha']) ?> a las <?= formatHoraEs($proxima['hora_inicio']) ?>
                <?php if (isSessionToday($proxima['fecha'])): ?><strong>&middot; ¡Es hoy!</strong><?php endif; ?>
            </p>
        </div>
        <?php if (isSessionToday($proxima['fecha']) && $proxima['link_zoom']): ?>
            <a href="<?= e($proxima['link_zoom']) ?>" target="_blank" rel="noopener" class="av-btn av-btn--white">Ingresar a clase</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="av-page-header">
    <h2>Mis cursos</h2>
    <a href="/estudiante/historial.php" class="av-btn av-btn--outline"><?= avIcon('award') ?> Historial académico</a>
    <a href="/mensajes.php" class="av-btn av-btn--outline"><?= avIcon('mail') ?> Mensajes</a>
</div>

<div class="av-course-grid" style="margin-bottom:22px">
    <?php foreach ($cursos as $c): ?>
        <div class="av-course-card">
            <div class="av-course-card__top"></div>
            <h3><?= e($c['nombre']) ?></h3>
            <div class="meta"><?= e($c['carrera_nombre'] ?? '-') ?><?= $c['periodo_nombre'] ? ' &middot; ' . e($c['periodo_nombre']) : '' ?></div>
            <p class="stats">Docente: <?= e($c['docente_nombre'] ?? 'Sin asignar') ?></p>
            <p class="stats"><?= (int) $c['total_tareas'] ?> tarea(s) publicadas</p>
            <a href="/estudiante/curso.php?id=<?= (int) $c['id'] ?>" class="av-btn av-btn--primary av-btn--block">Entrar al curso</a>
        </div>
    <?php endforeach; ?>
    <?php if (!$cursos): ?>
        <div class="av-alert av-alert--danger" style="grid-column:1/-1">
            <span>Aún no está matriculado en ningún curso. Contacte al administrador.</span>
        </div>
    <?php endif; ?>
</div>

<div class="av-card">
    <h3>Últimos avisos</h3>
    <div class="av-list">
        <?php foreach ($avisos as $a): ?>
            <div class="av-list-item">
                <div>
                    <div class="content-title">
                        <?= e($a['titulo']) ?>
                        <?php if ($a['curso_nombre']): ?><span class="av-badge av-badge--blue"><?= e($a['curso_nombre']) ?></span>
                        <?php else: ?><span class="av-badge av-badge--amber">General</span><?php endif; ?>
                    </div>
                    <div class="content-desc"><?= nl2br(e($a['contenido'])) ?></div>
                    <div class="content-desc" style="margin-top:4px"><?= formatDateEs($a['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$avisos): ?>
            <div class="av-empty">No hay avisos todavía.</div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

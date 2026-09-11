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

<div class="av-page-header"><h2>Mis cursos</h2></div>

<div class="av-course-grid">
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
<?php require __DIR__ . '/../includes/footer.php'; ?>

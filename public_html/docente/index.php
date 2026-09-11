<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT c.*, car.nombre AS carrera_nombre, p.nombre AS periodo_nombre,
            (SELECT COUNT(*) FROM matriculas m WHERE m.curso_id = c.id AND m.estado = 'activo') AS total_matriculados,
            (SELECT COUNT(*) FROM tareas t WHERE t.curso_id = c.id) AS total_tareas
     FROM cursos c
     LEFT JOIN carreras car ON car.id = c.carrera_id
     LEFT JOIN periodos_academicos p ON p.id = c.periodo_academico_id
     WHERE c.docente_id = :docente_id
     ORDER BY c.nombre"
);
$stmt->execute(['docente_id' => $docenteId]);
$cursos = $stmt->fetchAll();

$pageTitle = 'Mis cursos';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Mis cursos</h2></div>

<div class="av-course-grid">
    <?php foreach ($cursos as $c): ?>
        <div class="av-course-card">
            <div class="av-course-card__top"></div>
            <h3><?= e($c['nombre']) ?></h3>
            <div class="meta"><?= e($c['carrera_nombre'] ?? '-') ?><?= $c['ciclo'] ? ' &middot; Ciclo ' . (int) $c['ciclo'] : '' ?><?= $c['periodo_nombre'] ? ' &middot; ' . e($c['periodo_nombre']) : '' ?></div>
            <p class="stats"><?= nl2br(e($c['descripcion'] ?? '')) ?></p>
            <p class="stats"><?= (int) $c['total_matriculados'] ?> estudiante(s) &middot; <?= (int) $c['total_tareas'] ?> tarea(s)</p>
            <a href="/docente/curso.php?id=<?= (int) $c['id'] ?>" class="av-btn av-btn--primary av-btn--block">Entrar al curso</a>
        </div>
    <?php endforeach; ?>
    <?php if (!$cursos): ?>
        <div class="av-alert av-alert--danger" style="grid-column:1/-1">
            <span>Aún no tiene cursos asignados. Contacte al administrador.</span>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT c.*, car.nombre AS carrera_nombre, CONCAT(d.nombre, ' ', d.apellidos) AS docente_nombre,
            (SELECT COUNT(*) FROM tareas t WHERE t.curso_id = c.id) AS total_tareas
     FROM matriculas m
     JOIN cursos c ON c.id = m.curso_id
     LEFT JOIN carreras car ON car.id = c.carrera_id
     LEFT JOIN usuarios d ON d.id = c.docente_id
     WHERE m.estudiante_id = :estudiante_id
     ORDER BY c.nombre"
);
$stmt->execute(['estudiante_id' => $estudianteId]);
$cursos = $stmt->fetchAll();

$pageTitle = 'Mis cursos';
require __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-4">Mis cursos</h2>

<div class="row g-3">
    <?php foreach ($cursos as $c): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h5><?= e($c['nombre']) ?></h5>
                    <p class="text-muted small mb-1"><?= e($c['carrera_nombre'] ?? '-') ?></p>
                    <p class="small mb-1">Docente: <?= e($c['docente_nombre'] ?? 'Sin asignar') ?></p>
                    <p class="small text-muted flex-grow-1"><?= (int) $c['total_tareas'] ?> tarea(s) publicadas</p>
                    <a href="/estudiante/curso.php?id=<?= (int) $c['id'] ?>" class="btn btn-primary mt-auto">Entrar al curso</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$cursos): ?>
        <div class="col-12">
            <div class="alert alert-info">Aún no está matriculado en ningún curso. Contacte al administrador.</div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

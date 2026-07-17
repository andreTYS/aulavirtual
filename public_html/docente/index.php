<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];

$stmt = $pdo->prepare(
    "SELECT c.*, car.nombre AS carrera_nombre,
            (SELECT COUNT(*) FROM matriculas m WHERE m.curso_id = c.id) AS total_matriculados,
            (SELECT COUNT(*) FROM tareas t WHERE t.curso_id = c.id) AS total_tareas
     FROM cursos c
     LEFT JOIN carreras car ON car.id = c.carrera_id
     WHERE c.docente_id = :docente_id
     ORDER BY c.nombre"
);
$stmt->execute(['docente_id' => $docenteId]);
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
                    <p class="small flex-grow-1"><?= nl2br(e($c['descripcion'] ?? '')) ?></p>
                    <p class="small text-muted">
                        <?= (int) $c['total_matriculados'] ?> estudiante(s) &middot; <?= (int) $c['total_tareas'] ?> tarea(s)
                    </p>
                    <a href="/docente/curso.php?id=<?= (int) $c['id'] ?>" class="btn btn-primary mt-auto">Entrar al curso</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$cursos): ?>
        <div class="col-12">
            <div class="alert alert-info">Aún no tiene cursos asignados. Contacte al administrador.</div>
        </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

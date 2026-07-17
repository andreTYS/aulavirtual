<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

$cursoId = (int) ($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);
if ($cursoId <= 0) {
    setFlash('danger', 'Curso no especificado.');
    redirect('/admin/cursos.php');
}

$stmt = $pdo->prepare('SELECT * FROM cursos WHERE id = :id');
$stmt->execute(['id' => $cursoId]);
$curso = $stmt->fetch();
if (!$curso) {
    setFlash('danger', 'Curso no encontrado.');
    redirect('/admin/cursos.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'enroll') {
        $estudianteId = (int) ($_POST['estudiante_id'] ?? 0);
        if ($estudianteId > 0) {
            $ins = $pdo->prepare('INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (:curso_id, :estudiante_id)');
            $ins->execute(['curso_id' => $cursoId, 'estudiante_id' => $estudianteId]);
            setFlash('success', 'Estudiante matriculado.');
        }
    } elseif ($action === 'unenroll') {
        $matriculaId = (int) ($_POST['matricula_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM matriculas WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $matriculaId, 'curso_id' => $cursoId]);
        setFlash('success', 'Matrícula eliminada.');
    }
    redirect('/admin/matriculas.php?curso_id=' . $cursoId);
}

$matriculados = $pdo->prepare(
    'SELECT m.id AS matricula_id, u.id AS estudiante_id, u.nombre, u.apellidos, u.email
     FROM matriculas m JOIN usuarios u ON u.id = m.estudiante_id
     WHERE m.curso_id = :curso_id ORDER BY u.apellidos, u.nombre'
);
$matriculados->execute(['curso_id' => $cursoId]);
$matriculados = $matriculados->fetchAll();

$disponibles = $pdo->prepare(
    "SELECT id, nombre, apellidos, email FROM usuarios
     WHERE rol = 'estudiante' AND activo = 1
       AND id NOT IN (SELECT estudiante_id FROM matriculas WHERE curso_id = :curso_id)
     ORDER BY apellidos, nombre"
);
$disponibles->execute(['curso_id' => $cursoId]);
$disponibles = $disponibles->fetchAll();

$pageTitle = 'Matrículas - ' . $curso['nombre'];
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Matrículas: <?= e($curso['nombre']) ?></h2>
    <a href="/admin/cursos.php" class="btn btn-outline-secondary">&larr; Volver a cursos</a>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Matricular estudiante</div>
            <div class="card-body">
                <?php if ($disponibles): ?>
                <form method="post" class="row g-2">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="enroll">
                    <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                    <div class="col-8">
                        <select name="estudiante_id" class="form-select" required>
                            <?php foreach ($disponibles as $d): ?>
                                <option value="<?= (int) $d['id'] ?>"><?= e($d['nombre'] . ' ' . $d['apellidos'] . ' (' . $d['email'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <button class="btn btn-primary w-100" type="submit">Matricular</button>
                    </div>
                </form>
                <?php else: ?>
                    <p class="text-muted mb-0">No hay estudiantes disponibles para matricular.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">Estudiantes matriculados (<?= count($matriculados) ?>)</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($matriculados as $m): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <?= e($m['nombre'] . ' ' . $m['apellidos']) ?>
                            <div class="text-muted small"><?= e($m['email']) ?></div>
                        </div>
                        <form method="post" onsubmit="return confirm('¿Quitar esta matrícula?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="unenroll">
                            <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                            <input type="hidden" name="matricula_id" value="<?= (int) $m['matricula_id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (!$matriculados): ?>
                    <li class="list-group-item text-muted text-center py-4">Sin estudiantes matriculados.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

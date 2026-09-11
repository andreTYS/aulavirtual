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
<div class="av-page-header">
    <h2>Matrículas: <?= e($curso['nombre']) ?></h2>
    <a href="/admin/cursos.php" class="av-btn av-btn--outline">&larr; Volver a cursos</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px" class="av-form-grid">
    <div class="av-card">
        <h3>Matricular estudiante</h3>
        <?php if ($disponibles): ?>
        <form method="post" class="av-inline-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="enroll">
            <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
            <div class="av-fg">
                <select name="estudiante_id" required>
                    <?php foreach ($disponibles as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= e($d['nombre'] . ' ' . $d['apellidos'] . ' (' . $d['email'] . ')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="av-btn av-btn--primary" type="submit">Matricular</button>
        </form>
        <?php else: ?>
            <p class="av-text-muted">No hay estudiantes disponibles para matricular.</p>
        <?php endif; ?>
    </div>
    <div class="av-card">
        <h3>Estudiantes matriculados (<?= count($matriculados) ?>)</h3>
        <div class="av-list">
            <?php foreach ($matriculados as $m): ?>
                <div class="av-list-item">
                    <div>
                        <div class="content-title"><?= e($m['nombre'] . ' ' . $m['apellidos']) ?></div>
                        <div class="content-desc"><?= e($m['email']) ?></div>
                    </div>
                    <form method="post" onsubmit="return confirm('¿Quitar esta matrícula?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="unenroll">
                        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                        <input type="hidden" name="matricula_id" value="<?= (int) $m['matricula_id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Quitar</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$matriculados): ?>
                <div class="av-empty">Sin estudiantes matriculados.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

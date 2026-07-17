<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    requireValidCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM cursos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    setFlash('success', 'Curso eliminado.');
    redirect('/admin/cursos.php');
}

$cursos = $pdo->query(
    "SELECT c.*, car.nombre AS carrera_nombre,
            CONCAT(d.nombre, ' ', d.apellidos) AS docente_nombre,
            (SELECT COUNT(*) FROM matriculas m WHERE m.curso_id = c.id) AS total_matriculados
     FROM cursos c
     LEFT JOIN carreras car ON car.id = c.carrera_id
     LEFT JOIN usuarios d ON d.id = c.docente_id
     ORDER BY c.created_at DESC"
)->fetchAll();

$pageTitle = 'Cursos';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Cursos</h2>
    <a href="/admin/curso_form.php" class="btn btn-primary">+ Nuevo curso</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Curso</th><th>Carrera</th><th>Docente</th><th>Matriculados</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($cursos as $c): ?>
                <tr>
                    <td><?= e($c['nombre']) ?></td>
                    <td><?= e($c['carrera_nombre'] ?? '-') ?></td>
                    <td><?= e($c['docente_nombre'] ?? 'Sin asignar') ?></td>
                    <td><?= (int) $c['total_matriculados'] ?></td>
                    <td>
                        <?php if ($c['activo']): ?><span class="badge bg-success">Activo</span>
                        <?php else: ?><span class="badge bg-secondary">Inactivo</span><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/matriculas.php?curso_id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-secondary">Matrículas</a>
                        <a href="/admin/curso_form.php?id=<?= (int) $c['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este curso y todo su contenido asociado?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$cursos): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay cursos registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

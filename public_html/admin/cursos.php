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
<div class="av-page-header">
    <h2>Cursos</h2>
    <a href="/admin/curso_form.php" class="av-btn av-btn--primary">+ Nuevo curso</a>
</div>

<div class="av-table-wrap">
    <table class="av-table">
        <thead>
            <tr><th>Curso</th><th>Carrera</th><th>Docente</th><th>Matriculados</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($cursos as $c): ?>
            <tr>
                <td><strong><?= e($c['nombre']) ?></strong></td>
                <td><?= e($c['carrera_nombre'] ?? '-') ?></td>
                <td><?= e($c['docente_nombre'] ?? 'Sin asignar') ?></td>
                <td><?= (int) $c['total_matriculados'] ?></td>
                <td>
                    <?php if ($c['activo']): ?><span class="av-badge av-badge--green">Activo</span>
                    <?php else: ?><span class="av-badge av-badge--gray">Inactivo</span><?php endif; ?>
                </td>
                <td class="av-td-actions">
                    <a href="/admin/matriculas.php?curso_id=<?= (int) $c['id'] ?>" class="av-btn av-btn--outline av-btn--sm">Matrículas</a>
                    <a href="/admin/curso_form.php?id=<?= (int) $c['id'] ?>" class="av-btn av-btn--secondary av-btn--sm">Editar</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este curso y todo su contenido asociado?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$cursos): ?>
            <tr><td colspan="6" class="av-empty">No hay cursos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

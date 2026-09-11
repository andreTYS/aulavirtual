<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        if ($nombre === '') {
            setFlash('danger', 'El nombre de la carrera es obligatorio.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO carreras (nombre) VALUES (:nombre)');
                $stmt->execute(['nombre' => $nombre]);
                setFlash('success', 'Carrera creada.');
            } catch (PDOException $e) {
                setFlash('danger', 'Ya existe una carrera con ese nombre.');
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM carreras WHERE id = :id');
            $stmt->execute(['id' => $id]);
            setFlash('success', 'Carrera eliminada.');
        } catch (PDOException $e) {
            setFlash('danger', 'No se puede eliminar: hay cursos asociados a esta carrera.');
        }
    }
    redirect('/admin/carreras.php');
}

$carreras = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM cursos WHERE carrera_id = c.id) AS total_cursos FROM carreras c ORDER BY nombre')->fetchAll();

$pageTitle = 'Carreras';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Carreras</h2></div>

<div class="av-card" style="margin-bottom:20px">
    <form method="post" class="av-inline-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="av-fg">
            <label>Nueva carrera</label>
            <input type="text" name="nombre" placeholder="Nombre de la carrera" required>
        </div>
        <button type="submit" class="av-btn av-btn--primary">+ Agregar</button>
    </form>
</div>

<div class="av-table-wrap">
    <table class="av-table">
        <thead><tr><th>Carrera</th><th>Cursos</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($carreras as $c): ?>
            <tr>
                <td><strong><?= e($c['nombre']) ?></strong></td>
                <td><?= (int) $c['total_cursos'] ?></td>
                <td class="av-td-actions">
                    <form method="post" onsubmit="return confirm('¿Eliminar esta carrera?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$carreras): ?>
            <tr><td colspan="3" class="av-empty">No hay carreras registradas.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

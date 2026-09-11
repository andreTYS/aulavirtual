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
        $inicio = (string) ($_POST['fecha_inicio'] ?? '');
        $fin = (string) ($_POST['fecha_fin'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($nombre === '' || $inicio === '' || $fin === '') {
            setFlash('danger', 'Complete nombre, fecha de inicio y fecha de fin.');
        } elseif (strtotime($fin) < strtotime($inicio)) {
            setFlash('danger', 'La fecha de fin no puede ser anterior a la fecha de inicio.');
        } else {
            try {
                if ($activo) {
                    $pdo->exec('UPDATE periodos_academicos SET activo = 0');
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO periodos_academicos (nombre, fecha_inicio, fecha_fin, activo)
                     VALUES (:nombre, :inicio, :fin, :activo)'
                );
                $stmt->execute(['nombre' => $nombre, 'inicio' => $inicio, 'fin' => $fin, 'activo' => $activo]);
                setFlash('success', 'Periodo académico creado.');
            } catch (PDOException $e) {
                setFlash('danger', 'Ya existe un periodo académico con ese nombre.');
            }
        }
    } elseif ($action === 'activar') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->exec('UPDATE periodos_academicos SET activo = 0');
        $stmt = $pdo->prepare('UPDATE periodos_academicos SET activo = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
        setFlash('success', 'Periodo marcado como activo.');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM periodos_academicos WHERE id = :id');
            $stmt->execute(['id' => $id]);
            setFlash('success', 'Periodo eliminado.');
        } catch (PDOException $e) {
            setFlash('danger', 'No se puede eliminar: hay cursos asociados a este periodo.');
        }
    }
    redirect('/admin/periodos.php');
}

$periodos = $pdo->query(
    "SELECT p.*, (SELECT COUNT(*) FROM cursos WHERE periodo_academico_id = p.id) AS total_cursos
     FROM periodos_academicos p ORDER BY p.fecha_inicio DESC"
)->fetchAll();

$pageTitle = 'Periodos académicos';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Periodos académicos</h2></div>

<div class="av-card" style="margin-bottom:20px;max-width:760px">
    <h3>Nuevo periodo</h3>
    <form method="post" class="av-form-grid">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="av-fg"><label>Nombre</label><input type="text" name="nombre" placeholder="Ej. 2026-II" required></div>
        <div class="av-fg"><label>Fecha de inicio</label><input type="date" name="fecha_inicio" required></div>
        <div class="av-fg"><label>Fecha de fin</label><input type="date" name="fecha_fin" required></div>
        <div class="av-fg" style="align-self:end">
            <label style="text-transform:none;font-weight:600"><input type="checkbox" name="activo"> Marcar como periodo activo</label>
        </div>
        <div style="grid-column:1/-1"><button type="submit" class="av-btn av-btn--primary">+ Crear periodo</button></div>
    </form>
</div>

<div class="av-table-wrap">
    <table class="av-table">
        <thead><tr><th>Periodo</th><th>Inicio</th><th>Fin</th><th>Cursos</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($periodos as $p): ?>
            <tr>
                <td><strong><?= e($p['nombre']) ?></strong></td>
                <td><?= formatFechaEs($p['fecha_inicio']) ?></td>
                <td><?= formatFechaEs($p['fecha_fin']) ?></td>
                <td><?= (int) $p['total_cursos'] ?></td>
                <td>
                    <?php if ($p['activo']): ?><span class="av-badge av-badge--green">Activo</span>
                    <?php else: ?><span class="av-badge av-badge--gray">Inactivo</span><?php endif; ?>
                </td>
                <td class="av-td-actions">
                    <?php if (!$p['activo']): ?>
                        <form method="post" style="display:inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="activar">
                            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="av-btn av-btn--secondary av-btn--sm" type="submit">Marcar activo</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este periodo académico?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$periodos): ?>
            <tr><td colspan="6" class="av-empty">No hay periodos académicos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

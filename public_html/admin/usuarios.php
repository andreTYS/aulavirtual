<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    requireValidCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $_SESSION['user_id']) {
        setFlash('danger', 'No puede desactivar su propia cuenta.');
    } else {
        $stmt = $pdo->prepare('UPDATE usuarios SET activo = NOT activo WHERE id = :id');
        $stmt->execute(['id' => $id]);
        setFlash('success', 'Estado actualizado.');
    }
    redirect('/admin/usuarios.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    requireValidCsrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) $_SESSION['user_id']) {
        setFlash('danger', 'No puede eliminar su propia cuenta.');
    } else {
        try {
            $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
            $stmt->execute(['id' => $id]);
            setFlash('success', 'Usuario eliminado.');
        } catch (PDOException $e) {
            setFlash('danger', 'No se puede eliminar: el usuario tiene registros asociados (cursos, matrículas, entregas). Desactívelo en su lugar.');
        }
    }
    redirect('/admin/usuarios.php');
}

$rolFiltro = $_GET['rol'] ?? '';
$sql = 'SELECT id, nombre, apellidos, username, email, rol, activo FROM usuarios';
$params = [];
if (in_array($rolFiltro, ['administrador', 'docente', 'estudiante'], true)) {
    $sql .= ' WHERE rol = :rol';
    $params['rol'] = $rolFiltro;
}
$sql .= ' ORDER BY rol, apellidos, nombre';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$pageTitle = 'Usuarios';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2>Usuarios</h2>
    <a href="/admin/export_usuarios.php?rol=<?= e($rolFiltro) ?>" class="av-btn av-btn--outline"><?= avIcon('download') ?> Exportar CSV</a>
    <a href="/admin/usuario_form.php" class="av-btn av-btn--primary">+ Nuevo usuario</a>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <a href="/admin/usuarios.php" class="av-btn av-btn--<?= $rolFiltro === '' ? 'primary' : 'outline' ?> av-btn--sm">Todos</a>
    <a href="/admin/usuarios.php?rol=administrador" class="av-btn av-btn--<?= $rolFiltro === 'administrador' ? 'primary' : 'outline' ?> av-btn--sm">Administradores</a>
    <a href="/admin/usuarios.php?rol=docente" class="av-btn av-btn--<?= $rolFiltro === 'docente' ? 'primary' : 'outline' ?> av-btn--sm">Docentes</a>
    <a href="/admin/usuarios.php?rol=estudiante" class="av-btn av-btn--<?= $rolFiltro === 'estudiante' ? 'primary' : 'outline' ?> av-btn--sm">Estudiantes</a>
</div>

<div class="av-table-wrap">
    <table class="av-table">
        <thead>
            <tr><th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><strong><?= e($u['nombre'] . ' ' . $u['apellidos']) ?></strong></td>
                <td><?= e($u['username']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="av-badge av-badge--blue"><?= e($u['rol']) ?></span></td>
                <td>
                    <?php if ($u['activo']): ?>
                        <span class="av-badge av-badge--green">Activo</span>
                    <?php else: ?>
                        <span class="av-badge av-badge--red">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td class="av-td-actions">
                    <a href="/admin/usuario_form.php?id=<?= (int) $u['id'] ?>" class="av-btn av-btn--secondary av-btn--sm">Editar</a>
                    <form method="post" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button class="av-btn av-btn--outline av-btn--sm" type="submit">
                            <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                        </button>
                    </form>
                    <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este usuario definitivamente?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$usuarios): ?>
            <tr><td colspan="6" class="av-empty">No hay usuarios registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

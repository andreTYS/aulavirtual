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
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Usuarios</h2>
    <a href="/admin/usuario_form.php" class="btn btn-primary">+ Nuevo usuario</a>
</div>

<div class="mb-3">
    <a href="/admin/usuarios.php" class="btn btn-sm btn-outline-secondary <?= $rolFiltro === '' ? 'active' : '' ?>">Todos</a>
    <a href="/admin/usuarios.php?rol=administrador" class="btn btn-sm btn-outline-secondary <?= $rolFiltro === 'administrador' ? 'active' : '' ?>">Administradores</a>
    <a href="/admin/usuarios.php?rol=docente" class="btn btn-sm btn-outline-secondary <?= $rolFiltro === 'docente' ? 'active' : '' ?>">Docentes</a>
    <a href="/admin/usuarios.php?rol=estudiante" class="btn btn-sm btn-outline-secondary <?= $rolFiltro === 'estudiante' ? 'active' : '' ?>">Estudiantes</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Nombre</th><th>Usuario</th><th>Email</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= e($u['nombre'] . ' ' . $u['apellidos']) ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge bg-secondary text-uppercase"><?= e($u['rol']) ?></span></td>
                    <td>
                        <?php if ($u['activo']): ?>
                            <span class="badge bg-success">Activo</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/usuario_form.php?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario definitivamente?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$usuarios): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

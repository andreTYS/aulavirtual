<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;

$usuario = [
    'nombre' => '', 'apellidos' => '', 'username' => '', 'email' => '', 'rol' => 'estudiante', 'activo' => 1,
];

if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) {
        setFlash('danger', 'Usuario no encontrado.');
        redirect('/admin/usuarios.php');
    }
    $usuario = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $usuario['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $usuario['apellidos'] = trim((string) ($_POST['apellidos'] ?? ''));
    $usuario['username'] = trim((string) ($_POST['username'] ?? ''));
    $usuario['email'] = trim((string) ($_POST['email'] ?? ''));
    $usuario['rol'] = (string) ($_POST['rol'] ?? 'estudiante');
    $usuario['activo'] = isset($_POST['activo']) ? 1 : 0;
    $password = (string) ($_POST['password'] ?? '');

    if ($usuario['nombre'] === '') $errors[] = 'El nombre es obligatorio.';
    if ($usuario['apellidos'] === '') $errors[] = 'Los apellidos son obligatorios.';
    if ($usuario['username'] === '' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $usuario['username'])) {
        $errors[] = 'Usuario inválido (3-50 caracteres: letras, números, punto, guion).';
    }
    if (!filter_var($usuario['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Correo electrónico inválido.';
    }
    if (!in_array($usuario['rol'], ['administrador', 'docente', 'estudiante'], true)) {
        $errors[] = 'Rol inválido.';
    }
    if (!$editing && strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
    }

    if (!$errors) {
        $checkStmt = $pdo->prepare('SELECT id FROM usuarios WHERE (username = :username OR email = :email) AND id != :id');
        $checkStmt->execute(['username' => $usuario['username'], 'email' => $usuario['email'], 'id' => $id]);
        if ($checkStmt->fetch()) {
            $errors[] = 'Ya existe otro usuario con ese nombre de usuario o correo.';
        }
    }

    if (!$errors) {
        if ($editing) {
            if ($password !== '') {
                $stmt = $pdo->prepare(
                    'UPDATE usuarios SET nombre=:nombre, apellidos=:apellidos, username=:username, email=:email,
                     rol=:rol, activo=:activo, password_hash=:hash WHERE id=:id'
                );
                $stmt->execute([
                    'nombre' => $usuario['nombre'], 'apellidos' => $usuario['apellidos'],
                    'username' => $usuario['username'], 'email' => $usuario['email'],
                    'rol' => $usuario['rol'], 'activo' => $usuario['activo'],
                    'hash' => password_hash($password, PASSWORD_BCRYPT), 'id' => $id,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE usuarios SET nombre=:nombre, apellidos=:apellidos, username=:username, email=:email,
                     rol=:rol, activo=:activo WHERE id=:id'
                );
                $stmt->execute([
                    'nombre' => $usuario['nombre'], 'apellidos' => $usuario['apellidos'],
                    'username' => $usuario['username'], 'email' => $usuario['email'],
                    'rol' => $usuario['rol'], 'activo' => $usuario['activo'], 'id' => $id,
                ]);
            }
            setFlash('success', 'Usuario actualizado correctamente.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellidos, username, email, password_hash, rol, activo)
                 VALUES (:nombre, :apellidos, :username, :email, :hash, :rol, :activo)'
            );
            $stmt->execute([
                'nombre' => $usuario['nombre'], 'apellidos' => $usuario['apellidos'],
                'username' => $usuario['username'], 'email' => $usuario['email'],
                'hash' => password_hash($password, PASSWORD_BCRYPT),
                'rol' => $usuario['rol'], 'activo' => $usuario['activo'],
            ]);
            setFlash('success', 'Usuario creado correctamente.');
        }
        redirect('/admin/usuarios.php');
    }
}

$pageTitle = $editing ? 'Editar usuario' : 'Nuevo usuario';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2><?= e($pageTitle) ?></h2></div>

<?php if ($errors): ?>
    <div class="av-alert av-alert--danger">
        <span><?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?></span>
    </div>
<?php endif; ?>

<div class="av-card" style="max-width:640px">
    <form method="post" novalidate>
        <?= csrfField() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
        <div class="av-form-grid">
            <div class="av-fg">
                <label>Nombre</label>
                <input type="text" name="nombre" value="<?= e($usuario['nombre']) ?>" required>
            </div>
            <div class="av-fg">
                <label>Apellidos</label>
                <input type="text" name="apellidos" value="<?= e($usuario['apellidos']) ?>" required>
            </div>
            <div class="av-fg">
                <label>Usuario</label>
                <input type="text" name="username" value="<?= e($usuario['username']) ?>" required>
            </div>
            <div class="av-fg">
                <label>Correo electrónico</label>
                <input type="email" name="email" value="<?= e($usuario['email']) ?>" required>
            </div>
            <div class="av-fg">
                <label>Rol</label>
                <select name="rol">
                    <option value="administrador" <?= $usuario['rol'] === 'administrador' ? 'selected' : '' ?>>Administrador</option>
                    <option value="docente" <?= $usuario['rol'] === 'docente' ? 'selected' : '' ?>>Docente</option>
                    <option value="estudiante" <?= $usuario['rol'] === 'estudiante' ? 'selected' : '' ?>>Estudiante</option>
                </select>
            </div>
            <div class="av-fg">
                <label>Contraseña <?= $editing ? '(dejar en blanco para no cambiar)' : '' ?></label>
                <input type="password" name="password" <?= $editing ? '' : 'required' ?>>
            </div>
        </div>
        <div class="av-fg">
            <label style="text-transform:none;font-weight:600">
                <input type="checkbox" name="activo" <?= $usuario['activo'] ? 'checked' : '' ?>> Usuario activo
            </label>
        </div>
        <div style="margin-top:8px;display:flex;gap:10px">
            <button type="submit" class="av-btn av-btn--primary">Guardar</button>
            <a href="/admin/usuarios.php" class="av-btn av-btn--outline">Cancelar</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

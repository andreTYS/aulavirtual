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
    'dni' => '', 'telefono' => '', 'carrera_id' => '', 'especialidad' => '',
    'apoderado_nombre' => '', 'apoderado_telefono' => '',
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

    $dniRaw = trim((string) ($_POST['dni'] ?? ''));
    $telefonoRaw = trim((string) ($_POST['telefono'] ?? ''));
    $carreraIdRaw = (string) ($_POST['carrera_id'] ?? '');
    $especialidadRaw = trim((string) ($_POST['especialidad'] ?? ''));
    $apoderadoNombreRaw = trim((string) ($_POST['apoderado_nombre'] ?? ''));
    $apoderadoTelefonoRaw = trim((string) ($_POST['apoderado_telefono'] ?? ''));

    // Los campos extra solo aplican segun el rol elegido; se descartan los demas.
    $usuario['dni'] = $usuario['rol'] === 'estudiante' && $dniRaw !== '' ? $dniRaw : null;
    $usuario['telefono'] = $usuario['rol'] === 'estudiante' && $telefonoRaw !== '' ? $telefonoRaw : null;
    $usuario['carrera_id'] = $usuario['rol'] === 'estudiante' && $carreraIdRaw !== '' ? (int) $carreraIdRaw : null;
    $usuario['apoderado_nombre'] = $usuario['rol'] === 'estudiante' && $apoderadoNombreRaw !== '' ? $apoderadoNombreRaw : null;
    $usuario['apoderado_telefono'] = $usuario['rol'] === 'estudiante' && $apoderadoTelefonoRaw !== '' ? $apoderadoTelefonoRaw : null;
    $usuario['especialidad'] = $usuario['rol'] === 'docente' && $especialidadRaw !== '' ? $especialidadRaw : null;

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
    if ($usuario['dni'] !== null && !preg_match('/^[0-9A-Za-z]{6,15}$/', $usuario['dni'])) {
        $errors[] = 'DNI inválido.';
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
    if (!$errors && $usuario['dni'] !== null) {
        $checkDni = $pdo->prepare('SELECT id FROM usuarios WHERE dni = :dni AND id != :id');
        $checkDni->execute(['dni' => $usuario['dni'], 'id' => $id]);
        if ($checkDni->fetch()) {
            $errors[] = 'Ya existe otro usuario con ese DNI.';
        }
    }

    if (!$errors) {
        $fields = [
            'nombre' => $usuario['nombre'], 'apellidos' => $usuario['apellidos'],
            'username' => $usuario['username'], 'email' => $usuario['email'],
            'rol' => $usuario['rol'], 'activo' => $usuario['activo'],
            'dni' => $usuario['dni'], 'telefono' => $usuario['telefono'],
            'carrera_id' => $usuario['carrera_id'], 'especialidad' => $usuario['especialidad'],
            'apoderado_nombre' => $usuario['apoderado_nombre'], 'apoderado_telefono' => $usuario['apoderado_telefono'],
        ];

        if ($editing) {
            $sql = 'UPDATE usuarios SET nombre=:nombre, apellidos=:apellidos, username=:username, email=:email,
                    rol=:rol, activo=:activo, dni=:dni, telefono=:telefono, carrera_id=:carrera_id,
                    especialidad=:especialidad, apoderado_nombre=:apoderado_nombre, apoderado_telefono=:apoderado_telefono';
            if ($password !== '') {
                $sql .= ', password_hash=:hash';
                $fields['hash'] = password_hash($password, PASSWORD_BCRYPT);
            }
            $sql .= ' WHERE id=:id';
            $fields['id'] = $id;
            $pdo->prepare($sql)->execute($fields);
            setFlash('success', 'Usuario actualizado correctamente.');
        } else {
            $fields['hash'] = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellidos, username, email, password_hash, rol, activo,
                 dni, telefono, carrera_id, especialidad, apoderado_nombre, apoderado_telefono)
                 VALUES (:nombre, :apellidos, :username, :email, :hash, :rol, :activo,
                 :dni, :telefono, :carrera_id, :especialidad, :apoderado_nombre, :apoderado_telefono)'
            );
            $stmt->execute($fields);
            setFlash('success', 'Usuario creado correctamente.');
        }
        redirect('/admin/usuarios.php');
    }
}

$carreras = $pdo->query('SELECT id, nombre FROM carreras ORDER BY nombre')->fetchAll();

$pageTitle = $editing ? 'Editar usuario' : 'Nuevo usuario';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2><?= e($pageTitle) ?></h2></div>

<?php if ($errors): ?>
    <div class="av-alert av-alert--danger">
        <span><?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?></span>
    </div>
<?php endif; ?>

<div class="av-card" style="max-width:680px">
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
                <select name="rol" id="rolSelect" onchange="toggleRolFields()">
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

        <div id="camposEstudiante" style="display:none">
            <hr style="border:none;border-top:1px solid var(--n200);margin:16px 0">
            <p class="av-text-muted" style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Datos de estudiante</p>
            <div class="av-form-grid">
                <div class="av-fg"><label>DNI</label><input type="text" name="dni" value="<?= e((string) ($usuario['dni'] ?? '')) ?>"></div>
                <div class="av-fg"><label>Teléfono</label><input type="text" name="telefono" value="<?= e((string) ($usuario['telefono'] ?? '')) ?>"></div>
                <div class="av-fg">
                    <label>Carrera</label>
                    <select name="carrera_id">
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($carreras as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) ($usuario['carrera_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div></div>
                <div class="av-fg"><label>Nombre del apoderado (opcional)</label><input type="text" name="apoderado_nombre" value="<?= e((string) ($usuario['apoderado_nombre'] ?? '')) ?>"></div>
                <div class="av-fg"><label>Teléfono del apoderado</label><input type="text" name="apoderado_telefono" value="<?= e((string) ($usuario['apoderado_telefono'] ?? '')) ?>"></div>
            </div>
        </div>

        <div id="camposDocente" style="display:none">
            <hr style="border:none;border-top:1px solid var(--n200);margin:16px 0">
            <p class="av-text-muted" style="font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Datos de docente</p>
            <div class="av-fg"><label>Especialidad</label><input type="text" name="especialidad" value="<?= e((string) ($usuario['especialidad'] ?? '')) ?>" placeholder="Ej. Ingeniería de Software"></div>
        </div>

        <div class="av-fg" style="margin-top:10px">
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
<script>
function toggleRolFields() {
    const rol = document.getElementById('rolSelect').value;
    document.getElementById('camposEstudiante').style.display = rol === 'estudiante' ? 'block' : 'none';
    document.getElementById('camposDocente').style.display = rol === 'docente' ? 'block' : 'none';
}
toggleRolFields();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

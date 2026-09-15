<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$userId = (int) $_SESSION['user_id'];
$rol = $_SESSION['user_rol'];

$stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id');
$stmt->execute(['id' => $userId]);
$usuario = $stmt->fetch();
if (!$usuario) {
    redirect('/login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'datos') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $apellidos = trim((string) ($_POST['apellidos'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $telefono = trim((string) ($_POST['telefono'] ?? ''));
        $apoderadoNombre = trim((string) ($_POST['apoderado_nombre'] ?? ''));
        $apoderadoTelefono = trim((string) ($_POST['apoderado_telefono'] ?? ''));
        $especialidad = trim((string) ($_POST['especialidad'] ?? ''));

        if ($nombre === '' || $apellidos === '' || $email === '') {
            setFlash('danger', 'Nombre, apellidos y email son obligatorios.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'El email no es válido.');
        } else {
            try {
                $stmt = $pdo->prepare(
                    'UPDATE usuarios SET nombre = :nombre, apellidos = :apellidos, email = :email, telefono = :telefono,
                            apoderado_nombre = :apoderado_nombre, apoderado_telefono = :apoderado_telefono, especialidad = :especialidad
                     WHERE id = :id'
                );
                $stmt->execute([
                    'nombre' => $nombre,
                    'apellidos' => $apellidos,
                    'email' => $email,
                    'telefono' => $telefono !== '' ? $telefono : null,
                    'apoderado_nombre' => $rol === 'estudiante' && $apoderadoNombre !== '' ? $apoderadoNombre : ($rol === 'estudiante' ? null : $usuario['apoderado_nombre']),
                    'apoderado_telefono' => $rol === 'estudiante' && $apoderadoTelefono !== '' ? $apoderadoTelefono : ($rol === 'estudiante' ? null : $usuario['apoderado_telefono']),
                    'especialidad' => $rol === 'docente' && $especialidad !== '' ? $especialidad : ($rol === 'docente' ? null : $usuario['especialidad']),
                    'id' => $userId,
                ]);
                $_SESSION['user_nombre'] = $nombre;
                $_SESSION['user_apellidos'] = $apellidos;
                $_SESSION['user_email'] = $email;
                setFlash('success', 'Datos actualizados correctamente.');
            } catch (PDOException $e) {
                setFlash('danger', 'No se pudo actualizar: el email ya está en uso por otra cuenta.');
            }
        }
        redirect('/perfil.php');
    }

    if ($accion === 'password') {
        $actual = (string) ($_POST['password_actual'] ?? '');
        $nueva = (string) ($_POST['password_nueva'] ?? '');
        $confirmar = (string) ($_POST['password_confirmar'] ?? '');

        if (!password_verify($actual, $usuario['password_hash'])) {
            setFlash('danger', 'La contraseña actual no es correcta.');
        } elseif (strlen($nueva) < 8) {
            setFlash('danger', 'La nueva contraseña debe tener al menos 8 caracteres.');
        } elseif ($nueva !== $confirmar) {
            setFlash('danger', 'La confirmación no coincide con la nueva contraseña.');
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id');
            $stmt->execute(['hash' => password_hash($nueva, PASSWORD_BCRYPT), 'id' => $userId]);
            setFlash('success', 'Contraseña actualizada correctamente.');
        }
        redirect('/perfil.php');
    }
}

$pageTitle = 'Mi perfil';
require __DIR__ . '/includes/header.php';
?>
<div class="av-page-header"><h2>Mi perfil</h2></div>

<div class="av-card" style="margin-bottom:18px">
    <h3>Datos personales</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="accion" value="datos">
        <div class="av-form-grid">
            <div class="av-fg"><label>Nombres</label><input type="text" name="nombre" value="<?= e($usuario['nombre']) ?>" required></div>
            <div class="av-fg"><label>Apellidos</label><input type="text" name="apellidos" value="<?= e($usuario['apellidos']) ?>" required></div>
            <div class="av-fg"><label>Usuario</label><input type="text" value="<?= e($usuario['username']) ?>" disabled></div>
            <div class="av-fg"><label>Email</label><input type="email" name="email" value="<?= e($usuario['email']) ?>" required></div>
            <div class="av-fg"><label>Teléfono</label><input type="text" name="telefono" value="<?= e($usuario['telefono'] ?? '') ?>"></div>
            <?php if ($rol === 'estudiante'): ?>
                <div class="av-fg"><label>DNI</label><input type="text" value="<?= e($usuario['dni'] ?? '') ?>" disabled></div>
                <div class="av-fg"><label>Nombre del apoderado</label><input type="text" name="apoderado_nombre" value="<?= e($usuario['apoderado_nombre'] ?? '') ?>"></div>
                <div class="av-fg"><label>Teléfono del apoderado</label><input type="text" name="apoderado_telefono" value="<?= e($usuario['apoderado_telefono'] ?? '') ?>"></div>
            <?php elseif ($rol === 'docente'): ?>
                <div class="av-fg"><label>Especialidad</label><input type="text" name="especialidad" value="<?= e($usuario['especialidad'] ?? '') ?>"></div>
            <?php endif; ?>
        </div>
        <button type="submit" class="av-btn av-btn--primary">Guardar cambios</button>
    </form>
</div>

<div class="av-card">
    <h3>Cambiar contraseña</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="accion" value="password">
        <div class="av-form-grid">
            <div class="av-fg"><label>Contraseña actual</label><input type="password" name="password_actual" required></div>
            <div></div>
            <div class="av-fg"><label>Nueva contraseña</label><input type="password" name="password_nueva" minlength="8" required></div>
            <div class="av-fg"><label>Confirmar nueva contraseña</label><input type="password" name="password_confirmar" minlength="8" required></div>
        </div>
        <button type="submit" class="av-btn av-btn--secondary">Actualizar contraseña</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

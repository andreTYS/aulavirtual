<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    redirect(dashboardUrlForRole($_SESSION['user_rol']));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'Ingrese usuario/correo y contraseña.';
    } else {
        $user = attemptLogin($pdo, $identifier, $password);
        if ($user) {
            loginUser($user);
            redirect(dashboardUrlForRole($user['rol']));
        }
        $error = 'Credenciales incorrectas o usuario inactivo.';
    }
}

$pageTitle = 'Iniciar sesión - ' . APP_NAME;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card mt-4">
            <div class="card-body p-4">
                <h4 class="mb-1 text-center">Aula Virtual</h4>
                <p class="text-muted text-center mb-4">IESTP Benjamín Franklin</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Usuario o correo</label>
                        <input type="text" name="identifier" class="form-control" required autofocus
                               value="<?= e($_POST['identifier'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

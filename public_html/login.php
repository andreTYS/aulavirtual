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
<div class="av-login">
    <div class="av-login__panel">
        <div class="av-login__panel-logo"><img src="/assets/img/logo.svg" alt="IESTPBF"></div>
        <h2>Aula Virtual <span>IESTP Benjamín Franklin</span></h2>
        <p>Plataforma de gestión académica para matrícula, contenidos, tareas y calificaciones del instituto.</p>
        <div class="av-login__badges">
            <span class="av-login__badge">Docentes</span>
            <span class="av-login__badge">Estudiantes</span>
            <span class="av-login__badge">Moquegua, Perú</span>
        </div>
    </div>
    <div class="av-login__form">
        <div class="av-login__box">
            <h1>Bienvenido</h1>
            <p class="av-login__sub">Ingresa con tu usuario o correo institucional.</p>

            <?php if ($error): ?>
                <div class="av-alert av-alert--danger"><span><?= e($error) ?></span></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrfField() ?>
                <div class="av-fg">
                    <label>Usuario o correo</label>
                    <input type="text" name="identifier" required autofocus value="<?= e($_POST['identifier'] ?? '') ?>">
                </div>
                <div class="av-fg">
                    <label>Contraseña</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="av-btn av-btn--primary av-btn--block">Ingresar</button>
            </form>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

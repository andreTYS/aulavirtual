<?php
/**
 * Autenticacion y control de acceso por rol.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'nombre' => $_SESSION['user_nombre'],
        'apellidos' => $_SESSION['user_apellidos'],
        'username' => $_SESSION['user_username'],
        'email' => $_SESSION['user_email'],
        'rol' => $_SESSION['user_rol'],
    ];
}

function dashboardUrlForRole(string $rol): string
{
    return match ($rol) {
        'administrador' => '/admin/index.php',
        'docente' => '/docente/index.php',
        'estudiante' => '/estudiante/index.php',
        default => '/login.php',
    };
}

/**
 * Intenta autenticar con username o email + contrasena.
 * Devuelve el arreglo del usuario si es correcto, o null si falla.
 */
function attemptLogin(PDO $pdo, string $identifier, string $password): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, nombre, apellidos, username, email, password_hash, rol, activo
         FROM usuarios WHERE (username = :identifier1 OR email = :identifier2) LIMIT 1'
    );
    $stmt->execute(['identifier1' => $identifier, 'identifier2' => $identifier]);
    $user = $stmt->fetch();

    if (!$user || !$user['activo']) {
        return null;
    }
    if (!password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_nombre'] = $user['nombre'];
    $_SESSION['user_apellidos'] = $user['apellidos'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_rol'] = $user['rol'];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Exige que haya sesion iniciada; si no, redirige a login.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Exige uno de los roles indicados. Corta la ejecucion con 403 si no
 * corresponde, evitando que un rol acceda a vistas de otro.
 *
 * @param string|string[] $roles
 */
function requireRole(string|array $roles): void
{
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($_SESSION['user_rol'], $roles, true)) {
        http_response_code(403);
        require __DIR__ . '/../403.php';
        exit;
    }
}

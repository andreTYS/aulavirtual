<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/google_calendar.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];

if (isset($_GET['error'])) {
    setFlash('danger', 'No se completó la conexión con Google: el permiso fue cancelado.');
    redirect('/perfil.php');
}

$state = (string) ($_GET['state'] ?? '');
$code = (string) ($_GET['code'] ?? '');
$expectedState = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);

if ($code === '' || $state === '' || !hash_equals($expectedState, $state)) {
    setFlash('danger', 'No se pudo verificar la solicitud de conexión con Google. Intente nuevamente.');
    redirect('/perfil.php');
}

$tokens = googleExchangeCodeForTokens($code);
if (!$tokens || empty($tokens['access_token'])) {
    setFlash('danger', 'Google no devolvió un token válido. Intente conectar su cuenta nuevamente.');
    redirect('/perfil.php');
}

googleSaveTokens($pdo, $docenteId, $tokens);
setFlash('success', 'Cuenta de Google conectada correctamente. Las próximas sesiones que programe generarán su enlace de Meet automáticamente.');
redirect('/perfil.php');

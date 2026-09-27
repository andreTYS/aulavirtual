<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/google_calendar.php';
requireRole('docente');

if (!googleIsConfigured()) {
    setFlash('danger', 'La integración con Google Calendar aún no está configurada en el servidor. Contacte al administrador del sistema.');
    redirect('/perfil.php');
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

redirect(googleAuthUrl($state));

<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
$pageTitle = 'Acceso denegado';
require __DIR__ . '/includes/header.php';
?>
<div style="text-align:center;padding:60px 20px">
    <h1 style="font-size:2rem;font-weight:900;color:var(--n900)">403 &middot; Acceso denegado</h1>
    <p class="av-text-muted" style="margin:10px 0 20px">No tiene permisos para acceder a esta sección.</p>
    <a href="/index.php" class="av-btn av-btn--primary">Volver al inicio</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

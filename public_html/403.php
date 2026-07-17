<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Acceso denegado';
require __DIR__ . '/includes/header.php';
?>
<div class="text-center py-5">
    <h1 class="display-6">403 - Acceso denegado</h1>
    <p class="text-muted">No tiene permisos para acceder a esta sección.</p>
    <a href="/index.php" class="btn btn-primary">Volver al inicio</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

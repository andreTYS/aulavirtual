<?php
/**
 * Cabecera comun HTML. Espera opcionalmente $pageTitle definido antes
 * de incluir este archivo.
 */
declare(strict_types=1);

$user = currentUser();
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f6f8; }
        .navbar-brand { font-weight: 600; }
        .card { border: none; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        footer { color: #888; font-size: .85rem; }
    </style>
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= e(dashboardUrlForRole($user['rol'])) ?>">Aula Virtual IESTP-BF</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">
                <?php if ($user['rol'] === 'administrador'): ?>
                    <li class="nav-item"><a class="nav-link" href="/admin/index.php">Panel</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/usuarios.php">Usuarios</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/cursos.php">Cursos</a></li>
                    <li class="nav-item"><a class="nav-link" href="/admin/carreras.php">Carreras</a></li>
                <?php elseif ($user['rol'] === 'docente'): ?>
                    <li class="nav-item"><a class="nav-link" href="/docente/index.php">Mis cursos</a></li>
                <?php elseif ($user['rol'] === 'estudiante'): ?>
                    <li class="nav-item"><a class="nav-link" href="/estudiante/index.php">Mis cursos</a></li>
                    <li class="nav-item"><a class="nav-link" href="/estudiante/calificaciones.php">Calificaciones</a></li>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-light me-3">
                <?= e($user['nombre'] . ' ' . $user['apellidos']) ?>
                <span class="badge bg-secondary text-uppercase"><?= e($user['rol']) ?></span>
            </span>
            <a href="/logout.php" class="btn btn-outline-light btn-sm">Cerrar sesión</a>
        </div>
    </div>
</nav>
<?php endif; ?>
<div class="container pb-5">
    <?php foreach (getFlashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

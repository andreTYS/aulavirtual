<?php
/**
 * Cabecera comun HTML. Espera opcionalmente $pageTitle definido antes
 * de incluir este archivo. Usa el mismo sistema de diseno que el
 * panel administrativo institucional (sidebar oscuro, Plus Jakarta Sans).
 */
declare(strict_types=1);

$user = currentUser();
$pageTitle = $pageTitle ?? APP_NAME;

function navActive(string $path): string
{
    return str_ends_with($_SERVER['SCRIPT_NAME'], $path) ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="/assets/css/aula.css" rel="stylesheet">
</head>
<body>
<?php if ($user): ?>
<div class="av-shell">
    <aside class="av-sidebar">
        <div class="av-sidebar__brand">
            <img src="/assets/img/logo.svg" alt="IESTPBF">
            <div class="av-sidebar__brand-text">
                <span>Aula Virtual</span>
                <small>IESTP Benjamín Franklin</small>
            </div>
        </div>
        <nav class="av-nav">
            <?php if ($user['rol'] === 'administrador'): ?>
                <div class="av-nav__group">Gestión</div>
                <a class="av-nav__item<?= navActive('/admin/index.php') ?>" href="/admin/index.php">Panel</a>
                <a class="av-nav__item<?= navActive('/admin/usuarios.php') . navActive('/admin/usuario_form.php') ?>" href="/admin/usuarios.php">Usuarios</a>
                <a class="av-nav__item<?= navActive('/admin/cursos.php') . navActive('/admin/curso_form.php') . navActive('/admin/matriculas.php') ?>" href="/admin/cursos.php">Cursos</a>
                <a class="av-nav__item<?= navActive('/admin/carreras.php') ?>" href="/admin/carreras.php">Carreras</a>
            <?php elseif ($user['rol'] === 'docente'): ?>
                <div class="av-nav__group">Docencia</div>
                <a class="av-nav__item<?= navActive('/docente/index.php') ?>" href="/docente/index.php">Mis cursos</a>
            <?php elseif ($user['rol'] === 'estudiante'): ?>
                <div class="av-nav__group">Aprendizaje</div>
                <a class="av-nav__item<?= navActive('/estudiante/index.php') ?>" href="/estudiante/index.php">Mis cursos</a>
                <a class="av-nav__item<?= navActive('/estudiante/calificaciones.php') ?>" href="/estudiante/calificaciones.php">Calificaciones</a>
            <?php endif; ?>
        </nav>
        <div class="av-sidebar__footer">
            <a href="/logout.php" class="av-btn av-btn--white-ghost av-btn--sm av-btn--block">Cerrar sesión</a>
        </div>
    </aside>
    <div class="av-main">
        <header class="av-topbar">
            <span class="av-topbar__title"><?= e($pageTitle) ?></span>
            <span class="av-topbar__meta"><?= e($user['nombre'] . ' ' . $user['apellidos']) ?></span>
            <span class="av-topbar__status"><?= e($user['rol']) ?></span>
        </header>
        <div class="av-main__content">
            <?php foreach (getFlashes() as $flash): ?>
                <div class="av-alert av-alert--<?= $flash['type'] === 'danger' ? 'danger' : 'success' ?>">
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="av-alert__close" onclick="this.parentElement.remove()">&times;</button>
                </div>
            <?php endforeach; ?>
<?php else: ?>
    <?php foreach (getFlashes() as $flash): ?>
        <div class="av-alert av-alert--<?= $flash['type'] === 'danger' ? 'danger' : 'success' ?>" style="max-width:390px;margin:16px auto 0">
            <span><?= e($flash['message']) ?></span>
            <button type="button" class="av-alert__close" onclick="this.parentElement.remove()">&times;</button>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

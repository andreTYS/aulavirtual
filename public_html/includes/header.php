<?php
/**
 * Cabecera comun HTML. Espera opcionalmente $pageTitle definido antes
 * de incluir este archivo. Usa el mismo sistema de diseno que el
 * panel administrativo institucional (sidebar oscuro, Plus Jakarta Sans).
 */
declare(strict_types=1);

$user = currentUser();
$pageTitle = $pageTitle ?? APP_NAME;
$unreadMensajes = $user ? unreadMensajesCount($pdo, (int) $user['id']) : 0;

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
                <a class="av-nav__item<?= navActive('/admin/index.php') ?>" href="/admin/index.php"><?= avIcon('grid') ?> Panel</a>
                <a class="av-nav__item<?= navActive('/admin/usuarios.php') . navActive('/admin/usuario_form.php') ?>" href="/admin/usuarios.php"><?= avIcon('users') ?> Usuarios</a>
                <a class="av-nav__item<?= (($_GET['rol'] ?? '') === 'docente') ? ' active' : '' ?>" href="/admin/usuarios.php?rol=docente"><?= avIcon('graduation') ?> Docentes</a>
                <a class="av-nav__item<?= (($_GET['rol'] ?? '') === 'estudiante') ? ' active' : '' ?>" href="/admin/usuarios.php?rol=estudiante"><?= avIcon('users') ?> Estudiantes</a>
                <a class="av-nav__item<?= navActive('/admin/cursos.php') . navActive('/admin/curso_form.php') . navActive('/admin/matriculas.php') ?>" href="/admin/cursos.php"><?= avIcon('book') ?> Cursos</a>
                <a class="av-nav__item<?= navActive('/admin/carreras.php') ?>" href="/admin/carreras.php"><?= avIcon('graduation') ?> Carreras</a>
                <a class="av-nav__item<?= navActive('/admin/periodos.php') ?>" href="/admin/periodos.php"><?= avIcon('calendar') ?> Periodos académicos</a>
                <div class="av-nav__group">Finanzas</div>
                <a class="av-nav__item<?= navActive('/admin/pagos.php') ?>" href="/admin/pagos.php"><?= avIcon('check') ?> Pagos</a>
                <a class="av-nav__item<?= navActive('/admin/conceptos_pago.php') ?>" href="/admin/conceptos_pago.php"><?= avIcon('download') ?> Conceptos de pago</a>
                <div class="av-nav__group">Comunicación</div>
                <a class="av-nav__item<?= navActive('/admin/avisos.php') ?>" href="/admin/avisos.php"><?= avIcon('megaphone') ?> Avisos</a>
                <a class="av-nav__item<?= navActive('/mensajes.php') ?>" href="/mensajes.php"><?= avIcon('mail') ?> Mensajes<?php if ($unreadMensajes > 0): ?><span class="av-nav__badge"><?= $unreadMensajes ?></span><?php endif; ?></a>
                <div class="av-nav__group">Reportes</div>
                <a class="av-nav__item<?= navActive('/admin/reportes.php') ?>" href="/admin/reportes.php"><?= avIcon('chart') ?> Reportes y estadísticas</a>
                <div class="av-nav__group">Mi cuenta</div>
                <a class="av-nav__item<?= navActive('/perfil.php') ?>" href="/perfil.php"><?= avIcon('user') ?> Mi perfil</a>
                <div class="av-nav__group">Sistema</div>
                <a class="av-nav__item<?= navActive('/admin/demo_data.php') ?>" href="/admin/demo_data.php"><?= avIcon('star') ?> Datos de demostración</a>
            <?php elseif ($user['rol'] === 'docente'): ?>
                <div class="av-nav__group">Docencia</div>
                <a class="av-nav__item<?= navActive('/docente/index.php') ?>" href="/docente/index.php"><?= avIcon('book') ?> Mis cursos</a>
                <a class="av-nav__item<?= navActive('/docente/calendario.php') ?>" href="/docente/calendario.php"><?= avIcon('calendar') ?> Calendario</a>
                <a class="av-nav__item<?= navActive('/docente/libro_calificaciones.php') ?>" href="/docente/libro_calificaciones.php"><?= avIcon('award') ?> Libro de calificaciones</a>
                <div class="av-nav__group">Comunicación</div>
                <a class="av-nav__item<?= navActive('/mensajes.php') ?>" href="/mensajes.php"><?= avIcon('mail') ?> Mensajes<?php if ($unreadMensajes > 0): ?><span class="av-nav__badge"><?= $unreadMensajes ?></span><?php endif; ?></a>
                <div class="av-nav__group">Mi cuenta</div>
                <a class="av-nav__item<?= navActive('/perfil.php') ?>" href="/perfil.php"><?= avIcon('user') ?> Mi perfil</a>
            <?php elseif ($user['rol'] === 'estudiante'): ?>
                <div class="av-nav__group">Aprendizaje</div>
                <a class="av-nav__item<?= navActive('/estudiante/index.php') ?>" href="/estudiante/index.php"><?= avIcon('book') ?> Mis cursos</a>
                <a class="av-nav__item<?= navActive('/estudiante/calendario.php') ?>" href="/estudiante/calendario.php"><?= avIcon('calendar') ?> Calendario</a>
                <a class="av-nav__item<?= navActive('/estudiante/calificaciones.php') ?>" href="/estudiante/calificaciones.php"><?= avIcon('star') ?> Calificaciones</a>
                <a class="av-nav__item<?= navActive('/estudiante/historial.php') . navActive('/estudiante/constancia.php') ?>" href="/estudiante/historial.php"><?= avIcon('award') ?> Historial académico</a>
                <div class="av-nav__group">Finanzas</div>
                <a class="av-nav__item<?= navActive('/estudiante/pagos.php') ?>" href="/estudiante/pagos.php"><?= avIcon('check') ?> Mis pagos</a>
                <div class="av-nav__group">Comunicación</div>
                <a class="av-nav__item<?= navActive('/mensajes.php') ?>" href="/mensajes.php"><?= avIcon('mail') ?> Mensajes<?php if ($unreadMensajes > 0): ?><span class="av-nav__badge"><?= $unreadMensajes ?></span><?php endif; ?></a>
                <div class="av-nav__group">Mi cuenta</div>
                <a class="av-nav__item<?= navActive('/perfil.php') ?>" href="/perfil.php"><?= avIcon('user') ?> Mi perfil</a>
            <?php endif; ?>
        </nav>
        <div class="av-sidebar__footer">
            <a href="/logout.php" class="av-btn av-btn--white-ghost av-btn--sm av-btn--block"><?= avIcon('logout') ?> Cerrar sesión</a>
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

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

$totalUsuarios = (int) $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$totalDocentes = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'docente'")->fetchColumn();
$totalEstudiantes = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'estudiante'")->fetchColumn();
$totalCursos = (int) $pdo->query("SELECT COUNT(*) FROM cursos")->fetchColumn();
$totalMatriculas = (int) $pdo->query("SELECT COUNT(*) FROM matriculas WHERE estado = 'activo'")->fetchColumn();
$totalPagosPendientes = (int) $pdo->query("SELECT COUNT(*) FROM pagos WHERE estado = 'pendiente'")->fetchColumn();

$avisos = $pdo->query(
    "SELECT a.*, c.nombre AS curso_nombre FROM avisos a
     LEFT JOIN cursos c ON c.id = a.curso_id
     ORDER BY a.created_at DESC LIMIT 5"
)->fetchAll();

$pageTitle = 'Panel de Administración';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-welcome">
    <div class="av-welcome__text">
        <h2>Panel de Administración</h2>
        <p>Gestiona <strong>usuarios</strong>, <strong>cursos</strong> y <strong>matrículas</strong> del instituto.</p>
    </div>
</div>

<div class="av-stats-row">
    <div class="av-stat"><div class="av-stat__icon" style="background:var(--ab100);color:var(--ab700)"><?= avIcon('users') ?></div><div class="av-stat__num"><?= $totalUsuarios ?></div><div class="av-stat__lbl">Usuarios</div><div class="av-stat__bar" style="background:var(--ab500)"></div></div>
    <div class="av-stat"><div class="av-stat__icon" style="background:var(--t100);color:var(--t700)"><?= avIcon('graduation') ?></div><div class="av-stat__num"><?= $totalDocentes ?></div><div class="av-stat__lbl">Docentes</div><div class="av-stat__bar" style="background:var(--t500)"></div></div>
    <div class="av-stat"><div class="av-stat__icon" style="background:var(--ag100);color:var(--ag700)"><?= avIcon('users') ?></div><div class="av-stat__num"><?= $totalEstudiantes ?></div><div class="av-stat__lbl">Estudiantes</div><div class="av-stat__bar" style="background:var(--ag500)"></div></div>
    <div class="av-stat"><div class="av-stat__icon" style="background:var(--info-lt);color:var(--info)"><?= avIcon('book') ?></div><div class="av-stat__num"><?= $totalCursos ?></div><div class="av-stat__lbl">Cursos</div><div class="av-stat__bar" style="background:var(--info)"></div></div>
    <div class="av-stat"><div class="av-stat__icon" style="background:var(--success-lt);color:var(--success)"><?= avIcon('check') ?></div><div class="av-stat__num"><?= $totalMatriculas ?></div><div class="av-stat__lbl">Matrículas activas</div><div class="av-stat__bar" style="background:var(--success)"></div></div>
    <div class="av-stat"><div class="av-stat__icon" style="background:var(--ag100);color:var(--ag700)"><?= avIcon('download') ?></div><div class="av-stat__num"><?= $totalPagosPendientes ?></div><div class="av-stat__lbl">Pagos pendientes</div><div class="av-stat__bar" style="background:var(--ag500)"></div></div>
</div>

<div class="av-actions-grid" style="margin-bottom:22px">
    <a class="av-action-card" href="/admin/usuarios.php">
        <div class="av-action-card__icon" style="background:var(--ab100);color:var(--ab700)"><?= avIcon('users') ?></div>
        <strong>Usuarios</strong>
        <span>Crear y administrar docentes y estudiantes.</span>
    </a>
    <a class="av-action-card" href="/admin/cursos.php">
        <div class="av-action-card__icon" style="background:var(--info-lt);color:var(--info)"><?= avIcon('book') ?></div>
        <strong>Cursos</strong>
        <span>Crear cursos, asignar docente y carrera.</span>
    </a>
    <a class="av-action-card" href="/admin/carreras.php">
        <div class="av-action-card__icon" style="background:var(--t100);color:var(--t700)"><?= avIcon('graduation') ?></div>
        <strong>Carreras</strong>
        <span>Programas de estudio del instituto.</span>
    </a>
    <a class="av-action-card" href="/admin/periodos.php">
        <div class="av-action-card__icon" style="background:var(--ag100);color:var(--ag700)"><?= avIcon('calendar') ?></div>
        <strong>Periodos académicos</strong>
        <span>Ciclos de matrícula (ej. 2026-II).</span>
    </a>
    <a class="av-action-card" href="/admin/avisos.php">
        <div class="av-action-card__icon" style="background:var(--danger-lt);color:var(--danger)"><?= avIcon('megaphone') ?></div>
        <strong>Avisos</strong>
        <span>Publicar anuncios generales del instituto.</span>
    </a>
    <a class="av-action-card" href="/admin/pagos.php">
        <div class="av-action-card__icon" style="background:var(--ag100);color:var(--ag700)"><?= avIcon('check') ?></div>
        <strong>Pagos</strong>
        <span>Validar comprobantes y registrar pagos.</span>
    </a>
    <a class="av-action-card" href="/admin/reportes.php">
        <div class="av-action-card__icon" style="background:var(--t100);color:var(--t700)"><?= avIcon('chart') ?></div>
        <strong>Reportes</strong>
        <span>Estadísticas de matrícula, pagos y asistencia.</span>
    </a>
    <a class="av-action-card" href="/mensajes.php">
        <div class="av-action-card__icon" style="background:var(--ab100);color:var(--ab700)"><?= avIcon('mail') ?></div>
        <strong>Mensajes</strong>
        <span>Comunicación directa con docentes y estudiantes.</span>
    </a>
</div>

<div class="av-card">
    <h3>Últimos avisos</h3>
    <div class="av-list">
        <?php foreach ($avisos as $a): ?>
            <div class="av-list-item">
                <div>
                    <div class="content-title">
                        <?= e($a['titulo']) ?>
                        <?php if ($a['curso_nombre']): ?><span class="av-badge av-badge--blue"><?= e($a['curso_nombre']) ?></span>
                        <?php else: ?><span class="av-badge av-badge--amber">General</span><?php endif; ?>
                    </div>
                    <div class="content-desc"><?= formatDateEs($a['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$avisos): ?>
            <div class="av-empty">No hay avisos publicados. <a href="/admin/avisos.php" style="color:var(--ab600);font-weight:600">Publica el primero</a>.</div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

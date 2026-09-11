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
$totalMatriculas = (int) $pdo->query("SELECT COUNT(*) FROM matriculas")->fetchColumn();

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
    <div class="av-stat"><div class="av-stat__num"><?= $totalUsuarios ?></div><div class="av-stat__lbl">Usuarios</div><div class="av-stat__bar"></div></div>
    <div class="av-stat"><div class="av-stat__num"><?= $totalDocentes ?></div><div class="av-stat__lbl">Docentes</div><div class="av-stat__bar"></div></div>
    <div class="av-stat"><div class="av-stat__num"><?= $totalEstudiantes ?></div><div class="av-stat__lbl">Estudiantes</div><div class="av-stat__bar"></div></div>
    <div class="av-stat"><div class="av-stat__num"><?= $totalCursos ?></div><div class="av-stat__lbl">Cursos</div><div class="av-stat__bar"></div></div>
    <div class="av-stat"><div class="av-stat__num"><?= $totalMatriculas ?></div><div class="av-stat__lbl">Matrículas</div><div class="av-stat__bar"></div></div>
</div>

<div class="av-actions-grid">
    <a class="av-action-card" href="/admin/usuarios.php">
        <strong>Usuarios</strong>
        <span>Crear y administrar docentes y estudiantes.</span>
    </a>
    <a class="av-action-card" href="/admin/cursos.php">
        <strong>Cursos</strong>
        <span>Crear cursos, asignar docente y carrera.</span>
    </a>
    <a class="av-action-card" href="/admin/carreras.php">
        <strong>Carreras</strong>
        <span>Programas de estudio del instituto.</span>
    </a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

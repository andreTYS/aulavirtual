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
<h2 class="mb-4">Panel de Administración</h2>
<div class="row g-3">
    <div class="col-md-4 col-lg-2">
        <div class="card p-3 text-center"><div class="fs-3 fw-bold"><?= $totalUsuarios ?></div><div class="text-muted small">Usuarios</div></div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card p-3 text-center"><div class="fs-3 fw-bold"><?= $totalDocentes ?></div><div class="text-muted small">Docentes</div></div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card p-3 text-center"><div class="fs-3 fw-bold"><?= $totalEstudiantes ?></div><div class="text-muted small">Estudiantes</div></div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card p-3 text-center"><div class="fs-3 fw-bold"><?= $totalCursos ?></div><div class="text-muted small">Cursos</div></div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="card p-3 text-center"><div class="fs-3 fw-bold"><?= $totalMatriculas ?></div><div class="text-muted small">Matrículas</div></div>
    </div>
</div>

<div class="row mt-4 g-3">
    <div class="col-md-4">
        <div class="card p-3">
            <h5>Usuarios</h5>
            <p class="text-muted small">Crear y administrar docentes y estudiantes.</p>
            <a href="/admin/usuarios.php" class="btn btn-primary btn-sm">Gestionar usuarios</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <h5>Cursos</h5>
            <p class="text-muted small">Crear cursos, asignar docente y carrera.</p>
            <a href="/admin/cursos.php" class="btn btn-primary btn-sm">Gestionar cursos</a>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <h5>Carreras</h5>
            <p class="text-muted small">Programas de estudio del instituto.</p>
            <a href="/admin/carreras.php" class="btn btn-primary btn-sm">Gestionar carreras</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];
$examenId = (int) ($_GET['examen_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT ex.*, c.nombre AS curso_nombre, c.id AS curso_id FROM examenes ex
     JOIN cursos c ON c.id = ex.curso_id
     WHERE ex.id = :id AND c.docente_id = :docente_id'
);
$stmt->execute(['id' => $examenId, 'docente_id' => $docenteId]);
$examen = $stmt->fetch();
if (!$examen) {
    setFlash('danger', 'Examen no encontrado o no tiene permisos sobre él.');
    redirect('/docente/index.php');
}

$stmtCount = $pdo->prepare('SELECT COUNT(*) FROM examen_preguntas WHERE examen_id = :id');
$stmtCount->execute(['id' => $examenId]);
$totalPreguntas = (int) $stmtCount->fetchColumn();

$alumnos = $pdo->prepare(
    'SELECT u.id AS estudiante_id, u.nombre, u.apellidos, i.puntaje, i.fecha_envio
     FROM matriculas m
     JOIN usuarios u ON u.id = m.estudiante_id
     LEFT JOIN examen_intentos i ON i.examen_id = :examen_id AND i.estudiante_id = u.id
     WHERE m.curso_id = :curso_id AND m.estado = "activo"
     ORDER BY u.apellidos, u.nombre'
);
$alumnos->execute(['examen_id' => $examenId, 'curso_id' => $examen['curso_id']]);
$alumnos = $alumnos->fetchAll();

$pageTitle = 'Resultados - ' . $examen['titulo'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2><?= e($examen['titulo']) ?></h2>
    <a href="/docente/examenes.php?curso_id=<?= (int) $examen['curso_id'] ?>" class="av-btn av-btn--outline">&larr; <?= e($examen['curso_nombre']) ?></a>
    <p><?= $totalPreguntas ?> pregunta(s) &middot; Fecha límite: <?= formatDateEs($examen['fecha_limite']) ?></p>
</div>

<div class="av-table-wrap">
    <table class="av-table">
        <thead><tr><th>Estudiante</th><th>Estado</th><th>Puntaje</th><th>Fecha de envío</th></tr></thead>
        <tbody>
        <?php foreach ($alumnos as $a): ?>
            <tr>
                <td><strong><?= e($a['nombre'] . ' ' . $a['apellidos']) ?></strong></td>
                <td>
                    <?php if ($a['fecha_envio']): ?>
                        <span class="av-badge av-badge--green">Rendido</span>
                    <?php elseif (isPastDue($examen['fecha_limite'])): ?>
                        <span class="av-badge av-badge--red">No rendido (vencido)</span>
                    <?php else: ?>
                        <span class="av-badge av-badge--gray">Pendiente</span>
                    <?php endif; ?>
                </td>
                <td><?= $a['puntaje'] !== null ? number_format((float) $a['puntaje'], 2) . ' / 20' : '<span class="av-text-muted">-</span>' ?></td>
                <td><?= $a['fecha_envio'] ? formatDateEs($a['fecha_envio']) : '<span class="av-text-muted">-</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$alumnos): ?>
            <tr><td colspan="4" class="av-empty">No hay estudiantes matriculados en este curso.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

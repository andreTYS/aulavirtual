<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

$semanaParam = (string) ($_GET['semana'] ?? date('Y-m-d'));
if (!strtotime($semanaParam)) {
    $semanaParam = date('Y-m-d');
}
[$lunes, $domingo] = limitesSemana($semanaParam);
$dias = diasSemana($lunes);
$semanaAnterior = date('Y-m-d', strtotime($lunes . ' -7 days'));
$semanaSiguiente = date('Y-m-d', strtotime($lunes . ' +7 days'));

$stmt = $pdo->prepare(
    "SELECT s.*, c.nombre AS curso_nombre, CONCAT(d.nombre, ' ', d.apellidos) AS docente_nombre
     FROM sesiones s
     JOIN cursos c ON c.id = s.curso_id
     LEFT JOIN usuarios d ON d.id = c.docente_id
     WHERE s.fecha BETWEEN :lunes AND :domingo AND s.estado != 'cancelada'
     ORDER BY s.hora_inicio"
);
$stmt->execute(['lunes' => $lunes, 'domingo' => $domingo]);
$sesiones = $stmt->fetchAll();

$sesionesPorDia = [];
foreach ($sesiones as $s) {
    $sesionesPorDia[$s['fecha']][] = $s;
}

$diasLabel = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

$pageTitle = 'Horario semanal';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Horario semanal</h2><p>Todas las secciones de todos los docentes, útil para detectar cruces de horario.</p></div>

<div class="av-week-head">
    <a href="/admin/horario.php?semana=<?= $semanaAnterior ?>" class="av-btn av-btn--outline av-btn--sm">&larr; Semana anterior</a>
    <h3><?= formatFechaEs($lunes) ?> — <?= formatFechaEs($domingo) ?></h3>
    <a href="/admin/horario.php?semana=<?= $semanaSiguiente ?>" class="av-btn av-btn--outline av-btn--sm">Semana siguiente &rarr;</a>
</div>

<div class="av-week-grid">
    <?php foreach ($dias as $idx => $dia): ?>
        <div class="av-week-day<?= $dia === date('Y-m-d') ? ' is-today' : '' ?>">
            <div class="av-week-day__head"><?= $diasLabel[$idx] ?><br><?= formatFechaEs($dia) ?></div>
            <?php foreach ($sesionesPorDia[$dia] ?? [] as $s): ?>
                <div class="av-week-pill">
                    <strong><?= formatHoraEs($s['hora_inicio']) ?> &middot; <?= e($s['curso_nombre']) ?></strong>
                    <?= e($s['tema']) ?>
                    <small><?= e($s['docente_nombre'] ?? 'Sin docente') ?></small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

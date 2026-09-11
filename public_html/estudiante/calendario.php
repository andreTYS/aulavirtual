<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

$monthParam = (string) ($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
    $monthParam = date('Y-m');
}
$monthStart = DateTime::createFromFormat('Y-m-d', $monthParam . '-01');
if (!$monthStart) {
    $monthStart = new DateTime('first day of this month');
}
$monthStart->setTime(0, 0, 0);
$monthEnd = (clone $monthStart)->modify('last day of this month');
$prevMonth = (clone $monthStart)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $monthStart)->modify('+1 month')->format('Y-m');

$sesiones = $pdo->prepare(
    "SELECT s.fecha, s.hora_inicio, s.tema, s.estado, c.nombre AS curso_nombre, c.id AS curso_id
     FROM sesiones s
     JOIN cursos c ON c.id = s.curso_id
     JOIN matriculas m ON m.curso_id = c.id
     WHERE m.estudiante_id = :estudiante_id AND m.estado = 'activo'
       AND s.fecha BETWEEN :inicio AND :fin AND s.estado != 'cancelada'
     ORDER BY s.hora_inicio"
);
$sesiones->execute(['estudiante_id' => $estudianteId, 'inicio' => $monthStart->format('Y-m-d'), 'fin' => $monthEnd->format('Y-m-d')]);
$sesiones = $sesiones->fetchAll();

$tareas = $pdo->prepare(
    "SELECT t.fecha_limite, t.titulo, c.nombre AS curso_nombre, c.id AS curso_id
     FROM tareas t
     JOIN cursos c ON c.id = t.curso_id
     JOIN matriculas m ON m.curso_id = c.id
     WHERE m.estudiante_id = :estudiante_id AND m.estado = 'activo'
       AND DATE(t.fecha_limite) BETWEEN :inicio AND :fin"
);
$tareas->execute(['estudiante_id' => $estudianteId, 'inicio' => $monthStart->format('Y-m-d'), 'fin' => $monthEnd->format('Y-m-d')]);
$tareas = $tareas->fetchAll();

$eventosPorDia = [];
foreach ($sesiones as $s) {
    $dia = (int) date('j', strtotime($s['fecha']));
    $eventosPorDia[$dia][] = [
        'tipo' => 'sesion',
        'label' => formatHoraEs($s['hora_inicio']) . ' ' . $s['curso_nombre'],
        'link' => '/estudiante/curso.php?id=' . (int) $s['curso_id'],
    ];
}
foreach ($tareas as $t) {
    $dia = (int) date('j', strtotime($t['fecha_limite']));
    $eventosPorDia[$dia][] = [
        'tipo' => 'tarea',
        'label' => 'Vence: ' . $t['titulo'],
        'link' => '/estudiante/curso.php?id=' . (int) $t['curso_id'],
    ];
}

$diasEnMes = (int) $monthEnd->format('j');
$primerDiaSemana = (int) $monthStart->format('N');
$hoy = date('Y-m-d');

$pageTitle = 'Calendario';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Calendario</h2></div>

<div class="av-card">
    <div class="av-cal-head">
        <a href="/estudiante/calendario.php?month=<?= $prevMonth ?>" class="av-btn av-btn--outline av-btn--sm">&larr; Anterior</a>
        <h3><?php
            $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            echo e($meses[(int) $monthStart->format('n')] . ' ' . $monthStart->format('Y'));
        ?></h3>
        <a href="/estudiante/calendario.php?month=<?= $nextMonth ?>" class="av-btn av-btn--outline av-btn--sm">Siguiente &rarr;</a>
    </div>
    <div class="av-cal-grid">
        <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dow): ?>
            <div class="av-cal-dow"><?= $dow ?></div>
        <?php endforeach; ?>
        <?php for ($i = 1; $i < $primerDiaSemana; $i++): ?>
            <div class="av-cal-day is-empty"></div>
        <?php endfor; ?>
        <?php for ($dia = 1; $dia <= $diasEnMes; $dia++): ?>
            <?php $fechaCelda = $monthStart->format('Y-m-') . str_pad((string) $dia, 2, '0', STR_PAD_LEFT); ?>
            <div class="av-cal-day<?= $fechaCelda === $hoy ? ' is-today' : '' ?>">
                <div class="av-cal-day__num"><?= $dia ?></div>
                <?php foreach (($eventosPorDia[$dia] ?? []) as $ev): ?>
                    <a href="<?= e($ev['link']) ?>" class="av-cal-pill av-cal-pill--<?= $ev['tipo'] ?>" title="<?= e($ev['label']) ?>"><?= e($ev['label']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

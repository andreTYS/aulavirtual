<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

$porCarrera = $pdo->query(
    "SELECT car.nombre, COUNT(m.id) AS total
     FROM carreras car
     LEFT JOIN cursos c ON c.carrera_id = car.id
     LEFT JOIN matriculas m ON m.curso_id = c.id AND m.estado = 'activo'
     GROUP BY car.id, car.nombre
     ORDER BY total DESC"
)->fetchAll();
$totalesCarrera = array_map(fn($r) => (int) $r['total'], $porCarrera);
$maxCarrera = $totalesCarrera ? max(1, max($totalesCarrera)) : 1;

$porMes = $pdo->query(
    "SELECT DATE_FORMAT(fecha_pago, '%Y-%m') AS mes, SUM(monto) AS total
     FROM pagos
     WHERE estado = 'validado' AND fecha_pago >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY mes ORDER BY mes"
)->fetchAll();
$totalesMes = array_map(fn($r) => (float) $r['total'], $porMes);
$maxMes = $totalesMes ? max(1.0, max($totalesMes)) : 1.0;

$meses = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];

$asistenciaRaw = $pdo->query(
    "SELECT estado, COUNT(*) AS total FROM asistencias GROUP BY estado"
)->fetchAll();
$asistenciaPorEstado = array_column($asistenciaRaw, 'total', 'estado');
$totalAsistencia = array_sum($asistenciaPorEstado) ?: 1;
$asistenciaLabels = ['presente' => 'Presente', 'tarde' => 'Tarde', 'falta' => 'Falta', 'justificado' => 'Justificado'];

$pageTitle = 'Reportes';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Reportes y estadísticas</h2></div>

<div class="av-card" style="margin-bottom:18px">
    <h3>Matrícula activa por carrera</h3>
    <div class="av-barchart">
        <?php foreach ($porCarrera as $r): ?>
            <div class="av-barchart__row">
                <span class="av-barchart__label"><?= e($r['nombre']) ?></span>
                <div class="av-barchart__track"><div class="av-barchart__fill" style="width:<?= (int) $r['total'] === 0 ? 0 : round((int) $r['total'] / $maxCarrera * 100) ?>%"></div></div>
                <span class="av-barchart__value"><?= (int) $r['total'] ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$porCarrera): ?><div class="av-empty">No hay carreras registradas.</div><?php endif; ?>
    </div>
</div>

<div class="av-card" style="margin-bottom:18px">
    <h3>Pagos validados por mes (últimos 6 meses)</h3>
    <div class="av-barchart">
        <?php foreach ($porMes as $r): ?>
            <?php [$anio, $mes] = explode('-', $r['mes']); ?>
            <div class="av-barchart__row">
                <span class="av-barchart__label"><?= e($meses[(int) $mes] . ' ' . $anio) ?></span>
                <div class="av-barchart__track"><div class="av-barchart__fill" style="width:<?= round((float) $r['total'] / $maxMes * 100) ?>%;background:linear-gradient(90deg,#34d399,#16a34a)"></div></div>
                <span class="av-barchart__value"><?= formatSoles((string) $r['total']) ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$porMes): ?><div class="av-empty">No hay pagos validados en este rango.</div><?php endif; ?>
    </div>
</div>

<div class="av-card">
    <h3>Asistencia global</h3>
    <div class="av-barchart">
        <?php foreach ($asistenciaLabels as $key => $label): ?>
            <?php $total = (int) ($asistenciaPorEstado[$key] ?? 0); ?>
            <div class="av-barchart__row">
                <span class="av-barchart__label"><?= e($label) ?></span>
                <div class="av-barchart__track"><div class="av-barchart__fill" style="width:<?= round($total / $totalAsistencia * 100) ?>%"></div></div>
                <span class="av-barchart__value"><?= round($total / $totalAsistencia * 100) ?>%</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

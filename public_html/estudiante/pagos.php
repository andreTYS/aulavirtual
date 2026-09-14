<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $conceptoId = (int) ($_POST['concepto_pago_id'] ?? 0);
    $monto = (string) ($_POST['monto'] ?? '');
    $fechaPago = (string) ($_POST['fecha_pago'] ?? '');
    $numeroVoucher = trim((string) ($_POST['numero_voucher'] ?? ''));

    if ($conceptoId <= 0 || $monto === '' || $fechaPago === '') {
        setFlash('danger', 'Complete concepto, monto y fecha de pago.');
    } elseif (!is_numeric($monto) || (float) $monto <= 0) {
        setFlash('danger', 'El monto debe ser un número mayor a cero.');
    } else {
        try {
            $comprobantePath = handleUpload('comprobante', 'comprobantes/' . $estudianteId, ALLOWED_COMPROBANTE_EXT);
            if (!$comprobantePath) {
                throw new RuntimeException('Debe adjuntar el comprobante de pago (imagen o PDF).');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO pagos (estudiante_id, concepto_pago_id, monto, fecha_pago, numero_voucher, comprobante_path, estado)
                 VALUES (:estudiante_id, :concepto_id, :monto, :fecha_pago, :numero_voucher, :comprobante_path, "pendiente")'
            );
            $stmt->execute([
                'estudiante_id' => $estudianteId, 'concepto_id' => $conceptoId, 'monto' => $monto,
                'fecha_pago' => $fechaPago, 'numero_voucher' => $numeroVoucher !== '' ? $numeroVoucher : null,
                'comprobante_path' => $comprobantePath,
            ]);
            setFlash('success', 'Comprobante enviado. Quedará pendiente hasta que el administrador lo valide.');
        } catch (RuntimeException $e) {
            setFlash('danger', $e->getMessage());
        }
    }
    redirect('/estudiante/pagos.php');
}

$pagos = $pdo->prepare(
    "SELECT p.*, cp.nombre AS concepto_nombre
     FROM pagos p JOIN conceptos_pago cp ON cp.id = p.concepto_pago_id
     WHERE p.estudiante_id = :estudiante_id ORDER BY p.created_at DESC"
);
$pagos->execute(['estudiante_id' => $estudianteId]);
$pagos = $pagos->fetchAll();

$conceptos = $pdo->query('SELECT id, nombre, monto_sugerido FROM conceptos_pago WHERE activo = 1 ORDER BY nombre')->fetchAll();

$totalValidado = array_sum(array_map(fn($p) => $p['estado'] === 'validado' ? (float) $p['monto'] : 0, $pagos));
$totalPendiente = array_sum(array_map(fn($p) => $p['estado'] === 'pendiente' ? (float) $p['monto'] : 0, $pagos));

$pageTitle = 'Mis pagos';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Mis pagos</h2></div>

<div class="av-stats-row" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
    <div class="av-stat">
        <div class="av-stat__icon" style="background:var(--success-lt);color:var(--success)"><?= avIcon('check') ?></div>
        <div class="av-stat__num" style="font-size:1.4rem"><?= formatSoles((string) $totalValidado) ?></div>
        <div class="av-stat__lbl">Pagado y validado</div>
        <div class="av-stat__bar" style="background:var(--success)"></div>
    </div>
    <div class="av-stat">
        <div class="av-stat__icon" style="background:var(--ag100);color:var(--ag700)"><?= avIcon('download') ?></div>
        <div class="av-stat__num" style="font-size:1.4rem"><?= formatSoles((string) $totalPendiente) ?></div>
        <div class="av-stat__lbl">Pendiente de validar</div>
        <div class="av-stat__bar" style="background:var(--ag500)"></div>
    </div>
</div>

<div class="av-card" style="margin-bottom:20px">
    <h3>Subir comprobante de pago</h3>
    <p class="av-text-muted" style="margin-bottom:12px">Realiza el pago por transferencia, agente o en oficina, y sube aquí tu comprobante para que el administrador lo valide.</p>
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="av-form-grid">
            <div class="av-fg">
                <label>Concepto</label>
                <select name="concepto_pago_id" id="conceptoSelect" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($conceptos as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-monto="<?= e((string) ($c['monto_sugerido'] ?? '')) ?>"><?= e($c['nombre']) ?><?= $c['monto_sugerido'] !== null ? ' (' . formatSoles($c['monto_sugerido']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="av-fg"><label>Monto pagado (S/)</label><input type="number" name="monto" id="montoInput" step="0.01" min="0.01" required></div>
            <div class="av-fg"><label>Fecha de pago</label><input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" required></div>
            <div class="av-fg"><label>N° de voucher / operación</label><input type="text" name="numero_voucher"></div>
        </div>
        <div class="av-fg"><label>Comprobante (imagen o PDF)</label><input type="file" name="comprobante" required></div>
        <button type="submit" class="av-btn av-btn--primary">Enviar comprobante</button>
    </form>
</div>

<div class="av-table-wrap">
    <table class="av-table">
        <thead><tr><th>Concepto</th><th>Monto</th><th>Fecha</th><th>Comprobante</th><th>Estado</th></tr></thead>
        <tbody>
        <?php foreach ($pagos as $p): ?>
            <tr>
                <td><strong><?= e($p['concepto_nombre']) ?></strong></td>
                <td><?= formatSoles($p['monto']) ?></td>
                <td><?= formatFechaEs($p['fecha_pago']) ?></td>
                <td>
                    <?php if ($p['comprobante_path']): ?>
                        <a href="/download.php?type=comprobante&id=<?= (int) $p['id'] ?>" style="color:var(--ab600);font-weight:600">Ver</a>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td>
                    <?php if ($p['estado'] === 'validado'): ?><span class="av-badge av-badge--green">Validado</span>
                    <?php elseif ($p['estado'] === 'rechazado'): ?>
                        <span class="av-badge av-badge--red">Rechazado</span>
                        <?php if ($p['observacion']): ?><div class="content-desc"><?= e($p['observacion']) ?></div><?php endif; ?>
                    <?php else: ?><span class="av-badge av-badge--amber">Pendiente</span><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pagos): ?>
            <tr><td colspan="5" class="av-empty">No has registrado pagos todavía.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<script>
document.getElementById('conceptoSelect').addEventListener('change', function () {
    const monto = this.options[this.selectedIndex].dataset.monto;
    if (monto) document.getElementById('montoInput').value = monto;
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

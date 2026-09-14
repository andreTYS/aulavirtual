<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'registrar') {
        $estudianteId = (int) ($_POST['estudiante_id'] ?? 0);
        $conceptoId = (int) ($_POST['concepto_pago_id'] ?? 0);
        $monto = (string) ($_POST['monto'] ?? '');
        $fechaPago = (string) ($_POST['fecha_pago'] ?? '');
        $numeroVoucher = trim((string) ($_POST['numero_voucher'] ?? ''));

        if ($estudianteId <= 0 || $conceptoId <= 0 || $monto === '' || $fechaPago === '') {
            setFlash('danger', 'Complete estudiante, concepto, monto y fecha de pago.');
        } elseif (!is_numeric($monto) || (float) $monto <= 0) {
            setFlash('danger', 'El monto debe ser un número mayor a cero.');
        } else {
            try {
                $comprobantePath = handleUpload('comprobante', 'comprobantes/' . $estudianteId, ALLOWED_COMPROBANTE_EXT);
                $stmt = $pdo->prepare(
                    'INSERT INTO pagos (estudiante_id, concepto_pago_id, monto, fecha_pago, numero_voucher, comprobante_path,
                     estado, registrado_por, fecha_validacion)
                     VALUES (:estudiante_id, :concepto_id, :monto, :fecha_pago, :numero_voucher, :comprobante_path,
                     "validado", :registrado_por, NOW())'
                );
                $stmt->execute([
                    'estudiante_id' => $estudianteId, 'concepto_id' => $conceptoId, 'monto' => $monto,
                    'fecha_pago' => $fechaPago, 'numero_voucher' => $numeroVoucher !== '' ? $numeroVoucher : null,
                    'comprobante_path' => $comprobantePath, 'registrado_por' => $_SESSION['user_id'],
                ]);
                setFlash('success', 'Pago registrado y validado.');
            } catch (RuntimeException $e) {
                setFlash('danger', $e->getMessage());
            }
        }
    } elseif ($action === 'validar') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare(
            "UPDATE pagos SET estado = 'validado', registrado_por = :admin_id, fecha_validacion = NOW(), observacion = NULL WHERE id = :id"
        )->execute(['admin_id' => $_SESSION['user_id'], 'id' => $id]);
        setFlash('success', 'Pago validado.');
    } elseif ($action === 'rechazar') {
        $id = (int) ($_POST['id'] ?? 0);
        $observacion = trim((string) ($_POST['observacion'] ?? ''));
        $pdo->prepare(
            "UPDATE pagos SET estado = 'rechazado', registrado_por = :admin_id, fecha_validacion = NOW(), observacion = :observacion WHERE id = :id"
        )->execute(['admin_id' => $_SESSION['user_id'], 'observacion' => $observacion !== '' ? $observacion : 'Rechazado por el administrador.', 'id' => $id]);
        setFlash('success', 'Pago rechazado.');
    }
    redirect('/admin/pagos.php' . (isset($_GET['estado']) ? '?estado=' . urlencode((string) $_GET['estado']) : ''));
}

$estadoFiltro = $_GET['estado'] ?? '';
$sql = "SELECT p.*, CONCAT(u.nombre, ' ', u.apellidos) AS estudiante_nombre, cp.nombre AS concepto_nombre
        FROM pagos p
        JOIN usuarios u ON u.id = p.estudiante_id
        JOIN conceptos_pago cp ON cp.id = p.concepto_pago_id";
$params = [];
if (in_array($estadoFiltro, ['pendiente', 'validado', 'rechazado'], true)) {
    $sql .= ' WHERE p.estado = :estado';
    $params['estado'] = $estadoFiltro;
}
$sql .= ' ORDER BY p.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pagos = $stmt->fetchAll();

$estudiantes = $pdo->query("SELECT id, nombre, apellidos FROM usuarios WHERE rol = 'estudiante' AND activo = 1 ORDER BY apellidos")->fetchAll();
$conceptos = $pdo->query('SELECT id, nombre, monto_sugerido FROM conceptos_pago WHERE activo = 1 ORDER BY nombre')->fetchAll();

$pageTitle = 'Pagos';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2>Pagos</h2>
    <a href="/admin/conceptos_pago.php" class="av-btn av-btn--outline">Conceptos de pago</a>
</div>

<div class="av-card" style="margin-bottom:20px">
    <h3>Registrar pago (ya validado, ej. pago en oficina)</h3>
    <form method="post" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="registrar">
        <div class="av-form-grid">
            <div class="av-fg">
                <label>Estudiante</label>
                <select name="estudiante_id" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($estudiantes as $e): ?>
                        <option value="<?= (int) $e['id'] ?>"><?= e($e['nombre'] . ' ' . $e['apellidos']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="av-fg">
                <label>Concepto</label>
                <select name="concepto_pago_id" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($conceptos as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-monto="<?= e((string) ($c['monto_sugerido'] ?? '')) ?>"><?= e($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="av-fg"><label>Monto (S/)</label><input type="number" name="monto" id="montoInput" step="0.01" min="0.01" required></div>
            <div class="av-fg"><label>Fecha de pago</label><input type="date" name="fecha_pago" value="<?= date('Y-m-d') ?>" required></div>
            <div class="av-fg"><label>N° de voucher (opcional)</label><input type="text" name="numero_voucher"></div>
            <div class="av-fg"><label>Comprobante (opcional)</label><input type="file" name="comprobante"></div>
        </div>
        <button type="submit" class="av-btn av-btn--primary">Registrar pago</button>
    </form>
</div>

<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
    <a href="/admin/pagos.php" class="av-btn av-btn--<?= $estadoFiltro === '' ? 'primary' : 'outline' ?> av-btn--sm">Todos</a>
    <a href="/admin/pagos.php?estado=pendiente" class="av-btn av-btn--<?= $estadoFiltro === 'pendiente' ? 'primary' : 'outline' ?> av-btn--sm">Pendientes</a>
    <a href="/admin/pagos.php?estado=validado" class="av-btn av-btn--<?= $estadoFiltro === 'validado' ? 'primary' : 'outline' ?> av-btn--sm">Validados</a>
    <a href="/admin/pagos.php?estado=rechazado" class="av-btn av-btn--<?= $estadoFiltro === 'rechazado' ? 'primary' : 'outline' ?> av-btn--sm">Rechazados</a>
</div>

<?php foreach ($pagos as $p): ?>
    <?php if ($p['estado'] === 'pendiente'): ?>
        <form method="post" id="form-pago-<?= (int) $p['id'] ?>">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        </form>
    <?php endif; ?>
<?php endforeach; ?>

<div class="av-table-wrap">
    <table class="av-table">
        <thead><tr><th>Estudiante</th><th>Concepto</th><th>Monto</th><th>Fecha</th><th>Voucher</th><th>Comprobante</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pagos as $p): ?>
            <?php $formId = 'form-pago-' . (int) $p['id']; ?>
            <tr>
                <td><strong><?= e($p['estudiante_nombre']) ?></strong></td>
                <td><?= e($p['concepto_nombre']) ?></td>
                <td><?= formatSoles($p['monto']) ?></td>
                <td><?= formatFechaEs($p['fecha_pago']) ?></td>
                <td><?= e($p['numero_voucher'] ?? '-') ?></td>
                <td>
                    <?php if ($p['comprobante_path']): ?>
                        <a href="/download.php?type=comprobante&id=<?= (int) $p['id'] ?>" style="color:var(--ab600);font-weight:600">Ver</a>
                    <?php else: ?>-<?php endif; ?>
                </td>
                <td>
                    <?php if ($p['estado'] === 'validado'): ?><span class="av-badge av-badge--green">Validado</span>
                    <?php elseif ($p['estado'] === 'rechazado'): ?><span class="av-badge av-badge--red" title="<?= e($p['observacion'] ?? '') ?>">Rechazado</span>
                    <?php else: ?><span class="av-badge av-badge--amber">Pendiente</span><?php endif; ?>
                </td>
                <td class="av-td-actions">
                    <?php if ($p['estado'] === 'pendiente'): ?>
                        <button class="av-btn av-btn--secondary av-btn--sm" form="<?= $formId ?>" formaction="/admin/pagos.php" name="action" value="validar" type="submit">Validar</button>
                        <button class="av-btn av-btn--danger av-btn--sm" form="<?= $formId ?>" formaction="/admin/pagos.php" name="action" value="rechazar" type="submit" onclick="return promptRechazo(this, '<?= $formId ?>')">Rechazar</button>
                    <?php else: ?>
                        <span class="av-text-muted">-</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pagos): ?>
            <tr><td colspan="8" class="av-empty">No hay pagos registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelector('select[name="concepto_pago_id"]').addEventListener('change', function () {
    const monto = this.options[this.selectedIndex].dataset.monto;
    if (monto) document.getElementById('montoInput').value = monto;
});

function promptRechazo(btn, formId) {
    const motivo = prompt('Motivo del rechazo (visible para el estudiante):');
    if (motivo === null) return false;
    const form = document.getElementById(formId);
    let obs = form.querySelector('input[name="observacion"]');
    if (!obs) {
        obs = document.createElement('input');
        obs.type = 'hidden';
        obs.name = 'observacion';
        form.appendChild(obs);
    }
    obs.value = motivo;
    return true;
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

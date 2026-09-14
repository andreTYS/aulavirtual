<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $monto = (string) ($_POST['monto_sugerido'] ?? '');
        if ($nombre === '') {
            setFlash('danger', 'El nombre del concepto es obligatorio.');
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO conceptos_pago (nombre, monto_sugerido) VALUES (:nombre, :monto)');
                $stmt->execute(['nombre' => $nombre, 'monto' => $monto !== '' ? $monto : null]);
                setFlash('success', 'Concepto de pago creado.');
            } catch (PDOException $e) {
                setFlash('danger', 'Ya existe un concepto con ese nombre.');
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE conceptos_pago SET activo = NOT activo WHERE id = :id')->execute(['id' => $id]);
        setFlash('success', 'Estado actualizado.');
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM conceptos_pago WHERE id = :id')->execute(['id' => $id]);
            setFlash('success', 'Concepto eliminado.');
        } catch (PDOException $e) {
            setFlash('danger', 'No se puede eliminar: hay pagos registrados con este concepto. Desactívelo en su lugar.');
        }
    }
    redirect('/admin/conceptos_pago.php');
}

$conceptos = $pdo->query(
    "SELECT c.*, (SELECT COUNT(*) FROM pagos WHERE concepto_pago_id = c.id) AS total_pagos
     FROM conceptos_pago c ORDER BY c.nombre"
)->fetchAll();

$pageTitle = 'Conceptos de pago';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Conceptos de pago</h2></div>

<div class="av-card" style="margin-bottom:20px;max-width:760px">
    <h3>Nuevo concepto</h3>
    <form method="post" class="av-inline-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="av-fg"><label>Nombre</label><input type="text" name="nombre" placeholder="Ej. Matrícula" required></div>
        <div class="av-fg"><label>Monto sugerido (S/)</label><input type="number" name="monto_sugerido" step="0.01" min="0" placeholder="150.00"></div>
        <button type="submit" class="av-btn av-btn--primary">+ Agregar</button>
    </form>
</div>

<div class="av-table-wrap">
    <table class="av-table">
        <thead><tr><th>Concepto</th><th>Monto sugerido</th><th>Pagos registrados</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($conceptos as $c): ?>
            <tr>
                <td><strong><?= e($c['nombre']) ?></strong></td>
                <td><?= $c['monto_sugerido'] !== null ? formatSoles($c['monto_sugerido']) : '-' ?></td>
                <td><?= (int) $c['total_pagos'] ?></td>
                <td>
                    <?php if ($c['activo']): ?><span class="av-badge av-badge--green">Activo</span>
                    <?php else: ?><span class="av-badge av-badge--gray">Inactivo</span><?php endif; ?>
                </td>
                <td class="av-td-actions">
                    <form method="post" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button class="av-btn av-btn--outline av-btn--sm" type="submit"><?= $c['activo'] ? 'Desactivar' : 'Activar' ?></button>
                    </form>
                    <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este concepto?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$conceptos): ?>
            <tr><td colspan="5" class="av-empty">No hay conceptos de pago registrados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

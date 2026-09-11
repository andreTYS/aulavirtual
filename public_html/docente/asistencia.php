<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];
$sesionId = (int) ($_GET['sesion_id'] ?? $_POST['sesion_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT s.*, c.nombre AS curso_nombre, c.id AS curso_id
     FROM sesiones s JOIN cursos c ON c.id = s.curso_id
     WHERE s.id = :id AND c.docente_id = :docente_id'
);
$stmt->execute(['id' => $sesionId, 'docente_id' => $docenteId]);
$sesion = $stmt->fetch();
if (!$sesion) {
    setFlash('danger', 'Sesión no encontrada o no tiene permisos sobre ella.');
    redirect('/docente/index.php');
}

$estadosValidos = ['presente', 'tarde', 'falta', 'justificado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $alumnosIds = $pdo->prepare("SELECT estudiante_id FROM matriculas WHERE curso_id = :curso_id AND estado = 'activo'");
    $alumnosIds->execute(['curso_id' => $sesion['curso_id']]);
    $alumnosIds = array_column($alumnosIds->fetchAll(), 'estudiante_id');

    $upsert = $pdo->prepare(
        'INSERT INTO asistencias (sesion_id, estudiante_id, estado, observacion)
         VALUES (:sesion_id, :estudiante_id, :estado, :observacion)
         ON DUPLICATE KEY UPDATE estado = VALUES(estado), observacion = VALUES(observacion)'
    );

    foreach ($alumnosIds as $estudianteId) {
        $estado = (string) ($_POST['estado'][$estudianteId] ?? 'falta');
        if (!in_array($estado, $estadosValidos, true)) {
            $estado = 'falta';
        }
        $observacion = trim((string) ($_POST['observacion'][$estudianteId] ?? ''));
        $upsert->execute([
            'sesion_id' => $sesionId, 'estudiante_id' => $estudianteId,
            'estado' => $estado, 'observacion' => $observacion !== '' ? $observacion : null,
        ]);
    }

    $pdo->prepare("UPDATE sesiones SET estado = 'realizada' WHERE id = :id AND estado = 'programada'")
        ->execute(['id' => $sesionId]);

    setFlash('success', 'Asistencia registrada.');
    redirect('/docente/asistencia.php?sesion_id=' . $sesionId);
}

$alumnos = $pdo->prepare(
    'SELECT u.id AS estudiante_id, u.nombre, u.apellidos, a.estado, a.observacion
     FROM matriculas m
     JOIN usuarios u ON u.id = m.estudiante_id
     LEFT JOIN asistencias a ON a.sesion_id = :sesion_id AND a.estudiante_id = u.id
     WHERE m.curso_id = :curso_id AND m.estado = "activo"
     ORDER BY u.apellidos, u.nombre'
);
$alumnos->execute(['sesion_id' => $sesionId, 'curso_id' => $sesion['curso_id']]);
$alumnos = $alumnos->fetchAll();

$pageTitle = 'Asistencia - ' . $sesion['tema'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2>Asistencia: <?= e($sesion['tema']) ?></h2>
    <a href="/docente/curso.php?id=<?= (int) $sesion['curso_id'] ?>" class="av-btn av-btn--outline">&larr; <?= e($sesion['curso_nombre']) ?></a>
    <p><?= formatFechaEs($sesion['fecha']) ?> &middot; <?= formatHoraEs($sesion['hora_inicio']) ?></p>
</div>

<form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="sesion_id" value="<?= $sesionId ?>">
    <div class="av-table-wrap">
        <table class="av-table">
            <thead><tr><th>Estudiante</th><th>Estado</th><th>Observación</th></tr></thead>
            <tbody>
            <?php foreach ($alumnos as $a): ?>
                <tr>
                    <td><strong><?= e($a['nombre'] . ' ' . $a['apellidos']) ?></strong></td>
                    <td>
                        <select name="estado[<?= (int) $a['estudiante_id'] ?>]" style="width:auto;min-width:140px">
                            <?php foreach ($estadosValidos as $estado): ?>
                                <option value="<?= $estado ?>" <?= ($a['estado'] ?? 'falta') === $estado ? 'selected' : '' ?>><?= ucfirst($estado) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="observacion[<?= (int) $a['estudiante_id'] ?>]" value="<?= e($a['observacion'] ?? '') ?>"
                               style="width:100%;padding:7px 10px;border:1.5px solid var(--n200);border-radius:6px" placeholder="Opcional">
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$alumnos): ?>
                <tr><td colspan="3" class="av-empty">No hay estudiantes matriculados en este curso.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($alumnos): ?>
        <button type="submit" class="av-btn av-btn--primary" style="margin-top:16px">Guardar asistencia</button>
    <?php endif; ?>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>

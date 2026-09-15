<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];

$cursos = $pdo->prepare('SELECT id, nombre FROM cursos WHERE docente_id = :id ORDER BY nombre');
$cursos->execute(['id' => $docenteId]);
$cursos = $cursos->fetchAll();

$cursoId = (int) ($_GET['curso_id'] ?? ($cursos[0]['id'] ?? 0));
$cursoValido = null;
foreach ($cursos as $c) {
    if ((int) $c['id'] === $cursoId) {
        $cursoValido = $c;
        break;
    }
}

$tareas = [];
$estudiantes = [];
$notas = [];

if ($cursoValido) {
    $stmt = $pdo->prepare('SELECT id, titulo FROM tareas WHERE curso_id = :curso_id ORDER BY fecha_limite');
    $stmt->execute(['curso_id' => $cursoId]);
    $tareas = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT u.id, u.nombre, u.apellidos FROM usuarios u
         JOIN matriculas m ON m.estudiante_id = u.id
         WHERE m.curso_id = :curso_id AND m.estado = 'activo'
         ORDER BY u.apellidos, u.nombre"
    );
    $stmt->execute(['curso_id' => $cursoId]);
    $estudiantes = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT e.estudiante_id, e.tarea_id, e.calificacion FROM entregas e
         JOIN tareas t ON t.id = e.tarea_id WHERE t.curso_id = :curso_id"
    );
    $stmt->execute(['curso_id' => $cursoId]);
    foreach ($stmt->fetchAll() as $row) {
        $notas[(int) $row['estudiante_id']][(int) $row['tarea_id']] = $row['calificacion'];
    }
}

$pageTitle = 'Libro de calificaciones';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Libro de calificaciones</h2></div>

<div class="av-fg" style="max-width:340px">
    <label>Curso</label>
    <select onchange="location.href='/docente/libro_calificaciones.php?curso_id='+this.value">
        <?php foreach ($cursos as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $cursoId ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<?php if (!$cursos): ?>
    <div class="av-alert av-alert--danger"><span>Aún no tiene cursos asignados.</span></div>
<?php else: ?>
    <div class="av-table-wrap" style="margin-top:16px">
        <table class="av-table">
            <thead>
                <tr>
                    <th>Estudiante</th>
                    <?php foreach ($tareas as $t): ?><th><?= e($t['titulo']) ?></th><?php endforeach; ?>
                    <th>Promedio</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($estudiantes as $est): ?>
                <?php
                    $eid = (int) $est['id'];
                    $notasEst = array_filter($notas[$eid] ?? [], fn($n) => $n !== null);
                    $promedio = $notasEst ? array_sum($notasEst) / count($notasEst) : null;
                ?>
                <tr>
                    <td><strong><?= e($est['nombre'] . ' ' . $est['apellidos']) ?></strong></td>
                    <?php foreach ($tareas as $t): ?>
                        <?php $nota = $notas[$eid][$t['id']] ?? null; ?>
                        <td><?= $nota !== null ? number_format((float) $nota, 2) : '<span class="av-text-muted">-</span>' ?></td>
                    <?php endforeach; ?>
                    <td>
                        <?php if ($promedio !== null): ?>
                            <span class="av-badge av-badge--<?= $promedio >= 11 ? 'green' : 'red' ?>"><?= number_format($promedio, 2) ?></span>
                        <?php else: ?>
                            <span class="av-text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$estudiantes): ?>
                <tr><td colspan="<?= count($tareas) + 2 ?>" class="av-empty">No hay estudiantes matriculados en este curso.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

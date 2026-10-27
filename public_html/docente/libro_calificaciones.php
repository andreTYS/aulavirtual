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

$unidades = [];
$tareasPorUnidad = [];
$examenesPorUnidad = [];
$estudiantes = [];
$notas = [];
$notasExamen = [];

if ($cursoValido) {
    $stmt = $pdo->prepare('SELECT * FROM unidades WHERE curso_id = :curso_id ORDER BY orden, id');
    $stmt->execute(['curso_id' => $cursoId]);
    $unidades = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id, unidad_id, titulo, peso FROM tareas WHERE curso_id = :curso_id ORDER BY fecha_limite');
    $stmt->execute(['curso_id' => $cursoId]);
    foreach ($stmt->fetchAll() as $t) {
        $tareasPorUnidad[(int) $t['unidad_id']][] = $t;
    }

    $stmt = $pdo->prepare('SELECT id, unidad_id, titulo, peso FROM examenes WHERE curso_id = :curso_id ORDER BY fecha_limite');
    $stmt->execute(['curso_id' => $cursoId]);
    foreach ($stmt->fetchAll() as $ex) {
        $examenesPorUnidad[(int) $ex['unidad_id']][] = $ex;
    }

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

    $stmt = $pdo->prepare(
        "SELECT i.estudiante_id, i.examen_id, i.puntaje FROM examen_intentos i
         JOIN examenes ex ON ex.id = i.examen_id WHERE ex.curso_id = :curso_id AND i.fecha_envio IS NOT NULL"
    );
    $stmt->execute(['curso_id' => $cursoId]);
    foreach ($stmt->fetchAll() as $row) {
        $notasExamen[(int) $row['estudiante_id']][(int) $row['examen_id']] = $row['puntaje'];
    }
}

$pageTitle = 'Libro de calificaciones';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2>Libro de calificaciones</h2>
    <?php if ($cursoValido): ?>
        <a href="/docente/export_calificaciones.php?curso_id=<?= $cursoId ?>" class="av-btn av-btn--outline"><?= avIcon('download') ?> Exportar CSV</a>
    <?php endif; ?>
</div>

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
<?php elseif (!$unidades): ?>
    <div class="av-alert av-alert--danger"><span>Este curso todavía no tiene unidades creadas. Créelas desde la pestaña "Unidades" dentro del curso.</span></div>
<?php else: ?>

    <?php foreach ($unidades as $u): ?>
        <?php
            $uid = (int) $u['id'];
            $tUnidad = $tareasPorUnidad[$uid] ?? [];
            $exUnidad = $examenesPorUnidad[$uid] ?? [];
        ?>
        <div class="av-card" style="margin:18px 0 0">
            <h3><?= e($u['nombre']) ?> <span class="av-text-muted" style="font-weight:600">— peso <?= number_format((float) $u['peso'], 2) ?> en el promedio final</span></h3>
            <div class="av-table-wrap">
                <table class="av-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <?php foreach ($tUnidad as $t): ?><th><?= e($t['titulo']) ?> <span class="av-text-muted">(x<?= number_format((float) $t['peso'], 1) ?>)</span></th><?php endforeach; ?>
                            <?php foreach ($exUnidad as $ex): ?><th><?= e($ex['titulo']) ?> <span class="av-text-muted">(x<?= number_format((float) $ex['peso'], 1) ?>)</span></th><?php endforeach; ?>
                            <th>Promedio unidad</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($estudiantes as $est): ?>
                        <?php
                            $eid = (int) $est['id'];
                            $promedioU = promedioUnidad($eid, $tUnidad, $exUnidad, $notas, $notasExamen);
                        ?>
                        <tr>
                            <td><strong><?= e($est['nombre'] . ' ' . $est['apellidos']) ?></strong></td>
                            <?php foreach ($tUnidad as $t): ?>
                                <?php $nota = $notas[$eid][$t['id']] ?? null; ?>
                                <td><?= $nota !== null ? number_format((float) $nota, 2) : '<span class="av-text-muted">-</span>' ?></td>
                            <?php endforeach; ?>
                            <?php foreach ($exUnidad as $ex): ?>
                                <?php $nota = $notasExamen[$eid][$ex['id']] ?? null; ?>
                                <td><?= $nota !== null ? number_format((float) $nota, 2) : '<span class="av-text-muted">-</span>' ?></td>
                            <?php endforeach; ?>
                            <td>
                                <?php if ($promedioU !== null): ?>
                                    <span class="av-badge av-badge--<?= $promedioU >= 11 ? 'green' : 'red' ?>"><?= number_format($promedioU, 2) ?></span>
                                <?php else: ?>
                                    <span class="av-text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$estudiantes): ?>
                        <tr><td colspan="<?= count($tUnidad) + count($exUnidad) + 2 ?>" class="av-empty">No hay estudiantes matriculados en este curso.</td></tr>
                    <?php endif; ?>
                    <?php if (!$tUnidad && !$exUnidad): ?>
                        <tr><td colspan="2" class="av-empty">Esta unidad todavía no tiene tareas ni exámenes.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($estudiantes): ?>
        <div class="av-card" style="margin:18px 0">
            <h3>Promedio final del curso</h3>
            <div class="av-table-wrap">
                <table class="av-table">
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <?php foreach ($unidades as $u): ?><th><?= e($u['nombre']) ?> <span class="av-text-muted">(x<?= number_format((float) $u['peso'], 1) ?>)</span></th><?php endforeach; ?>
                            <th>Promedio final</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($estudiantes as $est): ?>
                        <?php
                            $eid = (int) $est['id'];
                            $promediosUnidad = [];
                            foreach ($unidades as $u) {
                                $uid = (int) $u['id'];
                                $promediosUnidad[$uid] = promedioUnidad($eid, $tareasPorUnidad[$uid] ?? [], $examenesPorUnidad[$uid] ?? [], $notas, $notasExamen);
                            }
                            $filasFinal = array_map(
                                fn($u) => ['calificacion' => $promediosUnidad[(int) $u['id']], 'peso' => $u['peso']],
                                $unidades
                            );
                            $promedioFinal = promedioPonderado($filasFinal);
                        ?>
                        <tr>
                            <td><strong><?= e($est['nombre'] . ' ' . $est['apellidos']) ?></strong></td>
                            <?php foreach ($unidades as $u): ?>
                                <?php $pu = $promediosUnidad[(int) $u['id']]; ?>
                                <td><?= $pu !== null ? number_format($pu, 2) : '<span class="av-text-muted">-</span>' ?></td>
                            <?php endforeach; ?>
                            <td>
                                <?php if ($promedioFinal !== null): ?>
                                    <span class="av-badge av-badge--<?= $promedioFinal >= 11 ? 'green' : 'red' ?>"><?= number_format($promedioFinal, 2) ?></span>
                                <?php else: ?>
                                    <span class="av-text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

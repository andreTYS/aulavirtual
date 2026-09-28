<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];
$cursoId = (int) ($_GET['curso_id'] ?? $_POST['curso_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM cursos WHERE id = :id AND docente_id = :docente_id');
$stmt->execute(['id' => $cursoId, 'docente_id' => $docenteId]);
$curso = $stmt->fetch();
if (!$curso) {
    setFlash('danger', 'Curso no encontrado o no tiene permisos sobre él.');
    redirect('/docente/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_examen') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $peso = (string) ($_POST['peso'] ?? '1');
        $fechaLimite = (string) ($_POST['fecha_limite'] ?? '');

        if ($titulo === '' || $fechaLimite === '') {
            setFlash('danger', 'Complete el título y la fecha límite del examen.');
        } elseif (!is_numeric($peso) || (float) $peso <= 0) {
            setFlash('danger', 'El peso debe ser un número mayor a cero.');
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO examenes (curso_id, titulo, descripcion, peso, fecha_limite)
                 VALUES (:curso_id, :titulo, :descripcion, :peso, :fecha_limite)'
            );
            $ins->execute([
                'curso_id' => $cursoId, 'titulo' => $titulo, 'descripcion' => $descripcion,
                'peso' => (float) $peso, 'fecha_limite' => $fechaLimite,
            ]);
            setFlash('success', 'Examen creado. Ahora agregue las preguntas.');
            redirect('/docente/examen_preguntas.php?examen_id=' . $pdo->lastInsertId());
        }
    } elseif ($action === 'delete_examen') {
        $examenId = (int) ($_POST['examen_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM examenes WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $examenId, 'curso_id' => $cursoId]);
        setFlash('success', 'Examen eliminado.');
    }
    redirect('/docente/examenes.php?curso_id=' . $cursoId);
}

$examenes = $pdo->prepare(
    "SELECT e.*, (SELECT COUNT(*) FROM examen_preguntas p WHERE p.examen_id = e.id) AS total_preguntas,
            (SELECT COUNT(*) FROM examen_intentos i WHERE i.examen_id = e.id AND i.fecha_envio IS NOT NULL) AS total_rendidos
     FROM examenes e WHERE e.curso_id = :curso_id ORDER BY e.fecha_limite"
);
$examenes->execute(['curso_id' => $cursoId]);
$examenes = $examenes->fetchAll();

$pageTitle = 'Exámenes - ' . $curso['nombre'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2>Exámenes: <?= e($curso['nombre']) ?></h2>
    <a href="/docente/curso.php?id=<?= $cursoId ?>" class="av-btn av-btn--outline">&larr; Volver al curso</a>
</div>

<div class="av-card" style="margin-bottom:18px">
    <h3>+ Nuevo examen</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_examen">
        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
        <div class="av-form-grid">
            <div class="av-fg"><label>Título</label><input type="text" name="titulo" required></div>
            <div class="av-fg"><label>Peso en el promedio</label><input type="number" name="peso" value="1" min="0.1" step="0.1" required></div>
            <div class="av-fg"><label>Fecha límite</label><input type="datetime-local" name="fecha_limite" required></div>
        </div>
        <div class="av-fg"><label>Descripción / indicaciones</label><textarea name="descripcion" rows="2"></textarea></div>
        <button class="av-btn av-btn--primary" type="submit">Crear examen y agregar preguntas</button>
    </form>
</div>

<div class="av-table-wrap">
    <table class="av-table">
        <thead><tr><th>Título</th><th>Preguntas</th><th>Fecha límite</th><th>Rendido por</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($examenes as $ex): ?>
            <tr>
                <td>
                    <strong><?= e($ex['titulo']) ?></strong>
                    <?php if (isPastDue($ex['fecha_limite'])): ?><span class="av-badge av-badge--red">Vencido</span><?php endif; ?>
                </td>
                <td><?= (int) $ex['total_preguntas'] ?></td>
                <td><?= formatDateEs($ex['fecha_limite']) ?></td>
                <td><?= (int) $ex['total_rendidos'] ?> estudiante(s)</td>
                <td class="av-td-actions">
                    <a href="/docente/examen_preguntas.php?examen_id=<?= (int) $ex['id'] ?>" class="av-btn av-btn--secondary av-btn--sm">Preguntas</a>
                    <a href="/docente/examen_resultados.php?examen_id=<?= (int) $ex['id'] ?>" class="av-btn av-btn--secondary av-btn--sm">Resultados</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar este examen y todos sus intentos?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_examen">
                        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                        <input type="hidden" name="examen_id" value="<?= (int) $ex['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$examenes): ?>
            <tr><td colspan="5" class="av-empty">No hay exámenes creados.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

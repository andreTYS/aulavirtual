<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];
$examenId = (int) ($_GET['examen_id'] ?? $_POST['examen_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT ex.*, c.nombre AS curso_nombre, c.id AS curso_id FROM examenes ex
     JOIN cursos c ON c.id = ex.curso_id
     WHERE ex.id = :id AND c.docente_id = :docente_id'
);
$stmt->execute(['id' => $examenId, 'docente_id' => $docenteId]);
$examen = $stmt->fetch();
if (!$examen) {
    setFlash('danger', 'Examen no encontrado o no tiene permisos sobre él.');
    redirect('/docente/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create_pregunta') {
        $enunciado = trim((string) ($_POST['enunciado'] ?? ''));
        $opciones = array_map('trim', (array) ($_POST['opciones'] ?? []));
        $opciones = array_values(array_filter($opciones, fn($o) => $o !== ''));
        $correcta = (int) ($_POST['correcta'] ?? -1);

        if ($enunciado === '') {
            setFlash('danger', 'Escriba el enunciado de la pregunta.');
        } elseif (count($opciones) < 2) {
            setFlash('danger', 'Agregue al menos 2 opciones.');
        } elseif ($correcta < 0 || $correcta >= count($opciones)) {
            setFlash('danger', 'Marque cuál opción es la correcta.');
        } else {
            $pdo->beginTransaction();
            try {
                $maxOrdenStmt = $pdo->prepare('SELECT COALESCE(MAX(orden), 0) FROM examen_preguntas WHERE examen_id = :examen_id');
                $maxOrdenStmt->execute(['examen_id' => $examenId]);
                $maxOrden = (int) $maxOrdenStmt->fetchColumn();
                $ins = $pdo->prepare('INSERT INTO examen_preguntas (examen_id, enunciado, orden) VALUES (:examen_id, :enunciado, :orden)');
                $ins->execute(['examen_id' => $examenId, 'enunciado' => $enunciado, 'orden' => $maxOrden + 1]);
                $preguntaId = (int) $pdo->lastInsertId();

                $insOpcion = $pdo->prepare('INSERT INTO examen_opciones (pregunta_id, texto, es_correcta, orden) VALUES (:pregunta_id, :texto, :es_correcta, :orden)');
                foreach ($opciones as $idx => $texto) {
                    $insOpcion->execute([
                        'pregunta_id' => $preguntaId, 'texto' => $texto,
                        'es_correcta' => $idx === $correcta ? 1 : 0, 'orden' => $idx,
                    ]);
                }
                $pdo->commit();
                setFlash('success', 'Pregunta agregada.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                error_log('examen_preguntas.php: ' . $e->getMessage());
                setFlash('danger', 'No se pudo guardar la pregunta.');
            }
        }
    } elseif ($action === 'delete_pregunta') {
        $preguntaId = (int) ($_POST['pregunta_id'] ?? 0);
        $del = $pdo->prepare(
            'DELETE p FROM examen_preguntas p JOIN examenes e ON e.id = p.examen_id
             WHERE p.id = :id AND p.examen_id = :examen_id'
        );
        $del->execute(['id' => $preguntaId, 'examen_id' => $examenId]);
        setFlash('success', 'Pregunta eliminada.');
    }
    redirect('/docente/examen_preguntas.php?examen_id=' . $examenId);
}

$preguntas = $pdo->prepare('SELECT * FROM examen_preguntas WHERE examen_id = :examen_id ORDER BY orden');
$preguntas->execute(['examen_id' => $examenId]);
$preguntas = $preguntas->fetchAll();

$opcionesStmt = $pdo->prepare(
    'SELECT o.* FROM examen_opciones o JOIN examen_preguntas p ON p.id = o.pregunta_id
     WHERE p.examen_id = :examen_id ORDER BY o.pregunta_id, o.orden'
);
$opcionesStmt->execute(['examen_id' => $examenId]);
$opcionesPorPregunta = [];
foreach ($opcionesStmt->fetchAll() as $o) {
    $opcionesPorPregunta[(int) $o['pregunta_id']][] = $o;
}

$pageTitle = 'Preguntas - ' . $examen['titulo'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2><?= e($examen['titulo']) ?></h2>
    <a href="/docente/examenes.php?curso_id=<?= (int) $examen['curso_id'] ?>" class="av-btn av-btn--outline">&larr; <?= e($examen['curso_nombre']) ?></a>
</div>

<div class="av-card" style="margin-bottom:18px">
    <h3>+ Nueva pregunta</h3>
    <form method="post" id="form-pregunta">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_pregunta">
        <input type="hidden" name="examen_id" value="<?= $examenId ?>">
        <div class="av-fg"><label>Enunciado</label><textarea name="enunciado" rows="2" required></textarea></div>
        <div class="av-fg">
            <label>Opciones (marque la correcta)</label>
            <div id="opciones-wrap"></div>
            <div style="display:flex;gap:8px;margin-top:8px">
                <button type="button" class="av-btn av-btn--outline av-btn--sm" onclick="agregarOpcion()">+ Agregar opción</button>
                <button type="button" class="av-btn av-btn--outline av-btn--sm" onclick="prellenarVF()">Verdadero / Falso</button>
            </div>
        </div>
        <button class="av-btn av-btn--primary" type="submit" style="margin-top:10px">Guardar pregunta</button>
    </form>
</div>

<?php foreach ($preguntas as $idx => $p): ?>
    <div class="av-card" style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
            <div>
                <strong><?= $idx + 1 ?>. <?= e($p['enunciado']) ?></strong>
                <ul style="margin:10px 0 0 18px">
                    <?php foreach ($opcionesPorPregunta[(int) $p['id']] ?? [] as $o): ?>
                        <li style="<?= $o['es_correcta'] ? 'color:var(--success);font-weight:700' : '' ?>">
                            <?= e($o['texto']) ?><?= $o['es_correcta'] ? ' ✓' : '' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <form method="post" onsubmit="return confirm('¿Eliminar esta pregunta?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_pregunta">
                <input type="hidden" name="examen_id" value="<?= $examenId ?>">
                <input type="hidden" name="pregunta_id" value="<?= (int) $p['id'] ?>">
                <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
<?php if (!$preguntas): ?>
    <div class="av-empty">Aún no hay preguntas en este examen.</div>
<?php endif; ?>

<script>
let contadorOpciones = 0;
function agregarOpcion(texto) {
    const wrap = document.getElementById('opciones-wrap');
    const idx = contadorOpciones++;
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px';
    row.innerHTML = `
        <input type="radio" name="correcta" value="${idx}" style="width:auto" required>
        <input type="text" name="opciones[]" placeholder="Opción ${idx + 1}" style="flex:1;padding:8px 10px;border:1.5px solid var(--n200);border-radius:6px" value="${texto || ''}">
    `;
    wrap.appendChild(row);
}
function prellenarVF() {
    document.getElementById('opciones-wrap').innerHTML = '';
    contadorOpciones = 0;
    agregarOpcion('Verdadero');
    agregarOpcion('Falso');
}
agregarOpcion(); agregarOpcion();
document.getElementById('form-pregunta').addEventListener('submit', function () {
    contadorOpciones = 0;
});
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

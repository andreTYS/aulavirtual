<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('estudiante');

$estudianteId = (int) $_SESSION['user_id'];
$examenId = (int) ($_GET['id'] ?? $_POST['examen_id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT ex.*, c.nombre AS curso_nombre, c.id AS curso_id
     FROM examenes ex
     JOIN cursos c ON c.id = ex.curso_id
     JOIN matriculas m ON m.curso_id = c.id
     WHERE ex.id = :id AND m.estudiante_id = :estudiante_id AND m.estado = "activo"'
);
$stmt->execute(['id' => $examenId, 'estudiante_id' => $estudianteId]);
$examen = $stmt->fetch();
if (!$examen) {
    setFlash('danger', 'Examen no encontrado o no tiene acceso a él.');
    redirect('/estudiante/index.php');
}

$intentoStmt = $pdo->prepare('SELECT * FROM examen_intentos WHERE examen_id = :examen_id AND estudiante_id = :estudiante_id');
$intentoStmt->execute(['examen_id' => $examenId, 'estudiante_id' => $estudianteId]);
$intento = $intentoStmt->fetch();

$vencido = isPastDue($examen['fecha_limite']);
$yaRendido = $intento && $intento['fecha_envio'] !== null;

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

$respuestasPrevias = [];
if ($intento) {
    $respStmt = $pdo->prepare('SELECT pregunta_id, opcion_id FROM examen_respuestas WHERE intento_id = :intento_id');
    $respStmt->execute(['intento_id' => $intento['id']]);
    foreach ($respStmt->fetchAll() as $r) {
        $respuestasPrevias[(int) $r['pregunta_id']] = (int) $r['opcion_id'];
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$yaRendido) {
    requireValidCsrf();

    if ($vencido) {
        $errors[] = 'El plazo para rendir este examen venció.';
    } elseif (!$preguntas) {
        $errors[] = 'Este examen todavía no tiene preguntas.';
    } else {
        $pdo->beginTransaction();
        try {
            if (!$intento) {
                $pdo->prepare('INSERT INTO examen_intentos (examen_id, estudiante_id) VALUES (:examen_id, :estudiante_id)')
                    ->execute(['examen_id' => $examenId, 'estudiante_id' => $estudianteId]);
                $intentoId = (int) $pdo->lastInsertId();
            } else {
                $intentoId = (int) $intento['id'];
            }

            $correctas = 0;
            $insRespuesta = $pdo->prepare(
                'INSERT INTO examen_respuestas (intento_id, pregunta_id, opcion_id) VALUES (:intento_id, :pregunta_id, :opcion_id)
                 ON DUPLICATE KEY UPDATE opcion_id = VALUES(opcion_id)'
            );
            foreach ($preguntas as $p) {
                $opcionId = (int) ($_POST['respuesta'][$p['id']] ?? 0);
                $insRespuesta->execute(['intento_id' => $intentoId, 'pregunta_id' => $p['id'], 'opcion_id' => $opcionId ?: null]);
                foreach ($opcionesPorPregunta[(int) $p['id']] ?? [] as $o) {
                    if ((int) $o['id'] === $opcionId && (int) $o['es_correcta'] === 1) {
                        $correctas++;
                    }
                }
            }

            $puntaje = round(($correctas / count($preguntas)) * 20, 2);
            $pdo->prepare('UPDATE examen_intentos SET puntaje = :puntaje, fecha_envio = NOW() WHERE id = :id')
                ->execute(['puntaje' => $puntaje, 'id' => $intentoId]);

            $pdo->commit();
            setFlash('success', 'Examen enviado. Obtuvo ' . number_format($puntaje, 2) . ' / 20.');
            redirect('/estudiante/examen.php?id=' . $examenId);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('estudiante/examen.php: ' . $e->getMessage());
            $errors[] = 'No se pudo enviar el examen. Intente nuevamente.';
        }
    }
}

$pageTitle = $examen['titulo'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2><?= e($examen['titulo']) ?></h2>
    <a href="/estudiante/curso.php?id=<?= (int) $examen['curso_id'] ?>" class="av-btn av-btn--outline">&larr; <?= e($examen['curso_nombre']) ?></a>
</div>

<div class="av-card" style="margin-bottom:18px">
    <p><strong>Fecha límite:</strong> <?= formatDateEs($examen['fecha_limite']) ?>
        <?php if ($vencido): ?><span class="av-badge av-badge--red">Vencido</span><?php endif; ?>
    </p>
    <p style="margin-top:10px"><?= nl2br(e($examen['descripcion'] ?? '')) ?></p>
</div>

<?php if ($errors): ?>
    <div class="av-alert av-alert--danger">
        <span><?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?></span>
    </div>
<?php endif; ?>

<?php if ($yaRendido): ?>
    <div class="av-grade-box">
        <strong>Ya rindió este examen. Puntaje: <?= number_format((float) $intento['puntaje'], 2) ?> / 20</strong>
    </div>
<?php elseif ($vencido): ?>
    <div class="av-alert av-alert--danger"><span>El plazo para rendir este examen venció. Ya no se puede rendir.</span></div>
<?php elseif (!$preguntas): ?>
    <div class="av-empty">Este examen todavía no tiene preguntas publicadas.</div>
<?php else: ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="examen_id" value="<?= $examenId ?>">
        <?php foreach ($preguntas as $idx => $p): ?>
            <div class="av-card" style="margin-bottom:14px">
                <strong><?= $idx + 1 ?>. <?= e($p['enunciado']) ?></strong>
                <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px">
                    <?php foreach ($opcionesPorPregunta[(int) $p['id']] ?? [] as $o): ?>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="radio" name="respuesta[<?= (int) $p['id'] ?>]" value="<?= (int) $o['id'] ?>"
                                   <?= ($respuestasPrevias[(int) $p['id']] ?? null) === (int) $o['id'] ? 'checked' : '' ?> required>
                            <?= e($o['texto']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <button type="submit" class="av-btn av-btn--primary" onclick="return confirm('¿Enviar el examen? No podrá modificar sus respuestas después.');">Enviar examen</button>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>

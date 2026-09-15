<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$user = currentUser();
$userId = (int) $user['id'];
$contactos = mensajesContactos($pdo, $user);
$contactosPorId = [];
foreach ($contactos as $c) {
    $contactosPorId[(int) $c['id']] = $c;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $destinatarioId = (int) ($_POST['destinatario_id'] ?? 0);
    $cuerpo = trim((string) ($_POST['cuerpo'] ?? ''));

    if (!isset($contactosPorId[$destinatarioId])) {
        setFlash('danger', 'No puede enviar mensajes a ese destinatario.');
    } elseif ($cuerpo === '') {
        setFlash('danger', 'Escriba un mensaje antes de enviarlo.');
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO mensajes (remitente_id, destinatario_id, cuerpo) VALUES (:remitente, :destinatario, :cuerpo)'
        );
        $stmt->execute(['remitente' => $userId, 'destinatario' => $destinatarioId, 'cuerpo' => $cuerpo]);
    }
    redirect('/mensajes.php?with=' . $destinatarioId);
}

$withId = (int) ($_GET['with'] ?? 0);
$contactoActivo = $contactosPorId[$withId] ?? null;

if ($contactoActivo) {
    $pdo->prepare('UPDATE mensajes SET leido = 1 WHERE destinatario_id = :yo AND remitente_id = :otro AND leido = 0')
        ->execute(['yo' => $userId, 'otro' => $withId]);
}

$hilos = $pdo->prepare(
    "SELECT m.*,
            IF(m.remitente_id = :yo1, m.destinatario_id, m.remitente_id) AS contacto_id,
            (SELECT COUNT(*) FROM mensajes m2 WHERE m2.remitente_id = IF(m.remitente_id = :yo2, m.destinatario_id, m.remitente_id)
                AND m2.destinatario_id = :yo3 AND m2.leido = 0) AS no_leidos
     FROM mensajes m
     WHERE m.remitente_id = :yo4 OR m.destinatario_id = :yo5
     ORDER BY m.created_at DESC"
);
$hilos->execute(['yo1' => $userId, 'yo2' => $userId, 'yo3' => $userId, 'yo4' => $userId, 'yo5' => $userId]);
$hilos = $hilos->fetchAll();

$resumenPorContacto = [];
foreach ($hilos as $h) {
    $cid = (int) $h['contacto_id'];
    if (!isset($resumenPorContacto[$cid])) {
        $resumenPorContacto[$cid] = ['ultimo' => $h, 'no_leidos' => (int) $h['no_leidos']];
    }
}

$mensajesHilo = [];
if ($contactoActivo) {
    $stmt = $pdo->prepare(
        'SELECT * FROM mensajes
         WHERE (remitente_id = :yo AND destinatario_id = :otro) OR (remitente_id = :otro2 AND destinatario_id = :yo2)
         ORDER BY created_at ASC'
    );
    $stmt->execute(['yo' => $userId, 'otro' => $withId, 'otro2' => $withId, 'yo2' => $userId]);
    $mensajesHilo = $stmt->fetchAll();
}

$pageTitle = 'Mensajes';
require __DIR__ . '/includes/header.php';
?>
<div class="av-page-header"><h2>Mensajes</h2></div>

<div class="av-msg-layout">
    <div class="av-msg-contacts">
        <?php foreach ($contactos as $c): ?>
            <?php
                $cid = (int) $c['id'];
                $noLeidos = $resumenPorContacto[$cid]['no_leidos'] ?? 0;
            ?>
            <a href="/mensajes.php?with=<?= $cid ?>" class="av-msg-contact<?= $withId === $cid ? ' active' : '' ?>">
                <span class="av-msg-contact__name"><?= e($c['nombre'] . ' ' . $c['apellidos']) ?></span>
                <span class="av-badge av-badge--gray" style="text-transform:capitalize"><?= e($c['rol']) ?></span>
                <?php if ($noLeidos > 0): ?><span class="av-msg-dot"><?= $noLeidos ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (!$contactos): ?>
            <div class="av-empty">No tiene contactos disponibles todavía.</div>
        <?php endif; ?>
    </div>

    <div class="av-msg-thread">
        <?php if (!$contactoActivo): ?>
            <div class="av-empty">Seleccione un contacto para ver la conversación.</div>
        <?php else: ?>
            <div class="av-msg-thread__head">
                <strong><?= e($contactoActivo['nombre'] . ' ' . $contactoActivo['apellidos']) ?></strong>
                <span class="av-badge av-badge--gray" style="text-transform:capitalize"><?= e($contactoActivo['rol']) ?></span>
            </div>
            <div class="av-msg-thread__body">
                <?php foreach ($mensajesHilo as $m): ?>
                    <div class="av-msg-bubble<?= (int) $m['remitente_id'] === $userId ? ' own' : '' ?>">
                        <p><?= nl2br(e($m['cuerpo'])) ?></p>
                        <span><?= formatDateEs($m['created_at']) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if (!$mensajesHilo): ?>
                    <div class="av-empty">Aún no hay mensajes. Escriba el primero.</div>
                <?php endif; ?>
            </div>
            <form method="post" class="av-msg-thread__form">
                <?= csrfField() ?>
                <input type="hidden" name="destinatario_id" value="<?= $withId ?>">
                <textarea name="cuerpo" placeholder="Escriba un mensaje..." required></textarea>
                <button type="submit" class="av-btn av-btn--primary">Enviar</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

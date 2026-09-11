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
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $contenido = trim((string) ($_POST['contenido'] ?? ''));
        if ($titulo === '' || $contenido === '') {
            setFlash('danger', 'Complete el título y el contenido del aviso.');
        } else {
            $ins = $pdo->prepare('INSERT INTO avisos (autor_id, curso_id, titulo, contenido) VALUES (:autor_id, NULL, :titulo, :contenido)');
            $ins->execute(['autor_id' => $_SESSION['user_id'], 'titulo' => $titulo, 'contenido' => $contenido]);
            setFlash('success', 'Aviso publicado para todo el instituto.');
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM avisos WHERE id = :id')->execute(['id' => $id]);
        setFlash('success', 'Aviso eliminado.');
    }
    redirect('/admin/avisos.php');
}

$avisos = $pdo->query(
    "SELECT a.*, c.nombre AS curso_nombre, CONCAT(u.nombre, ' ', u.apellidos) AS autor_nombre
     FROM avisos a
     LEFT JOIN cursos c ON c.id = a.curso_id
     LEFT JOIN usuarios u ON u.id = a.autor_id
     ORDER BY a.created_at DESC"
)->fetchAll();

$pageTitle = 'Avisos';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Avisos</h2></div>

<div class="av-card" style="margin-bottom:20px;max-width:760px">
    <h3>Nuevo aviso general (visible para todo el instituto)</h3>
    <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="av-fg"><label>Título</label><input type="text" name="titulo" required></div>
        <div class="av-fg"><label>Contenido</label><textarea name="contenido" rows="3" required></textarea></div>
        <button type="submit" class="av-btn av-btn--primary"><?= avIcon('megaphone') ?> Publicar aviso</button>
    </form>
</div>

<div class="av-list">
    <?php foreach ($avisos as $a): ?>
        <div class="av-list-item">
            <div>
                <div class="content-title">
                    <?= e($a['titulo']) ?>
                    <?php if ($a['curso_nombre']): ?><span class="av-badge av-badge--blue"><?= e($a['curso_nombre']) ?></span>
                    <?php else: ?><span class="av-badge av-badge--amber">General</span><?php endif; ?>
                </div>
                <div class="content-desc"><?= nl2br(e($a['contenido'])) ?></div>
                <div class="content-desc" style="margin-top:4px">
                    <?= e($a['autor_nombre'] ?? 'Sistema') ?> &middot; <?= formatDateEs($a['created_at']) ?>
                </div>
            </div>
            <form method="post" onsubmit="return confirm('¿Eliminar este aviso?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
            </form>
        </div>
    <?php endforeach; ?>
    <?php if (!$avisos): ?>
        <div class="av-empty">No hay avisos publicados todavía.</div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

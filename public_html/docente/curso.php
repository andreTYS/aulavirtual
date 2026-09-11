<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('docente');

$docenteId = (int) $_SESSION['user_id'];
$cursoId = (int) ($_GET['id'] ?? $_POST['curso_id'] ?? 0);

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

    if ($action === 'create_unidad') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $orden = (int) ($_POST['orden'] ?? 1);
        if ($nombre === '') {
            setFlash('danger', 'El nombre de la unidad es obligatorio.');
        } else {
            $ins = $pdo->prepare('INSERT INTO unidades (curso_id, nombre, orden) VALUES (:curso_id, :nombre, :orden)');
            $ins->execute(['curso_id' => $cursoId, 'nombre' => $nombre, 'orden' => $orden]);
            setFlash('success', 'Unidad creada.');
        }
    } elseif ($action === 'delete_unidad') {
        $unidadId = (int) ($_POST['unidad_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM unidades WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $unidadId, 'curso_id' => $cursoId]);
        setFlash('success', 'Unidad eliminada junto con su contenido.');
    } elseif ($action === 'create_contenido') {
        $unidadId = (int) ($_POST['unidad_id'] ?? 0);
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $tipo = (string) ($_POST['tipo'] ?? '');
        $url = trim((string) ($_POST['url'] ?? ''));

        $checkUnidad = $pdo->prepare('SELECT id FROM unidades WHERE id = :id AND curso_id = :curso_id');
        $checkUnidad->execute(['id' => $unidadId, 'curso_id' => $cursoId]);

        if (!$checkUnidad->fetch()) {
            setFlash('danger', 'Unidad inválida.');
        } elseif ($titulo === '' || !in_array($tipo, ['pdf', 'video', 'enlace'], true)) {
            setFlash('danger', 'Complete el título y el tipo de contenido.');
        } else {
            try {
                $archivoPath = null;
                if ($tipo === 'enlace') {
                    if (!filter_var($url, FILTER_VALIDATE_URL)) {
                        throw new RuntimeException('Ingrese una URL válida para el enlace.');
                    }
                } else {
                    $archivoPath = handleUpload('archivo', 'contenidos/' . $cursoId, ALLOWED_CONTENIDO_EXT);
                    if (!$archivoPath) {
                        throw new RuntimeException('Debe adjuntar un archivo para este tipo de contenido.');
                    }
                    $url = null;
                }
                $ins = $pdo->prepare(
                    'INSERT INTO contenidos (unidad_id, curso_id, titulo, descripcion, tipo, archivo_path, url)
                     VALUES (:unidad_id, :curso_id, :titulo, :descripcion, :tipo, :archivo_path, :url)'
                );
                $ins->execute([
                    'unidad_id' => $unidadId, 'curso_id' => $cursoId, 'titulo' => $titulo,
                    'descripcion' => $descripcion, 'tipo' => $tipo, 'archivo_path' => $archivoPath, 'url' => $url,
                ]);
                setFlash('success', 'Contenido publicado.');
            } catch (RuntimeException $e) {
                setFlash('danger', $e->getMessage());
            }
        }
    } elseif ($action === 'delete_contenido') {
        $contenidoId = (int) ($_POST['contenido_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM contenidos WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $contenidoId, 'curso_id' => $cursoId]);
        setFlash('success', 'Contenido eliminado.');
    } elseif ($action === 'create_tarea') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $fechaLimite = (string) ($_POST['fecha_limite'] ?? '');
        $unidadIdRaw = (string) ($_POST['unidad_id'] ?? '');
        $unidadId = $unidadIdRaw === '' ? null : (int) $unidadIdRaw;

        if ($titulo === '' || $fechaLimite === '') {
            setFlash('danger', 'Complete el título y la fecha límite de la tarea.');
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO tareas (curso_id, unidad_id, titulo, descripcion, fecha_limite)
                 VALUES (:curso_id, :unidad_id, :titulo, :descripcion, :fecha_limite)'
            );
            $ins->execute([
                'curso_id' => $cursoId, 'unidad_id' => $unidadId, 'titulo' => $titulo,
                'descripcion' => $descripcion, 'fecha_limite' => $fechaLimite,
            ]);
            setFlash('success', 'Tarea creada.');
        }
    } elseif ($action === 'delete_tarea') {
        $tareaId = (int) ($_POST['tarea_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM tareas WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $tareaId, 'curso_id' => $cursoId]);
        setFlash('success', 'Tarea eliminada.');
    }

    redirect('/docente/curso.php?id=' . $cursoId);
}

$unidades = $pdo->prepare('SELECT * FROM unidades WHERE curso_id = :curso_id ORDER BY orden, id');
$unidades->execute(['curso_id' => $cursoId]);
$unidades = $unidades->fetchAll();

$contenidosStmt = $pdo->prepare('SELECT * FROM contenidos WHERE curso_id = :curso_id ORDER BY fecha_publicacion');
$contenidosStmt->execute(['curso_id' => $cursoId]);
$contenidosPorUnidad = [];
foreach ($contenidosStmt->fetchAll() as $cont) {
    $contenidosPorUnidad[(int) $cont['unidad_id']][] = $cont;
}

$tareasStmt = $pdo->prepare(
    'SELECT t.*, (SELECT COUNT(*) FROM entregas e WHERE e.tarea_id = t.id) AS total_entregas,
            (SELECT COUNT(*) FROM entregas e WHERE e.tarea_id = t.id AND e.calificacion IS NOT NULL) AS total_calificadas
     FROM tareas t WHERE t.curso_id = :curso_id ORDER BY t.fecha_limite'
);
$tareasStmt->execute(['curso_id' => $cursoId]);
$tareas = $tareasStmt->fetchAll();

$pageTitle = $curso['nombre'];
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header">
    <h2><?= e($curso['nombre']) ?></h2>
    <a href="/docente/index.php" class="av-btn av-btn--outline">&larr; Mis cursos</a>
    <p><?= nl2br(e($curso['descripcion'] ?? '')) ?></p>
</div>

<div class="av-tabs-wrap">
    <div class="av-tabs">
        <button type="button" class="av-tab active" data-tab-target="tab-contenido">Contenido</button>
        <button type="button" class="av-tab" data-tab-target="tab-tareas">Tareas</button>
    </div>

    <div class="av-tabpanel active" id="tab-contenido">
        <div class="av-card" style="margin-bottom:16px">
            <h3>+ Nueva unidad / semana</h3>
            <form method="post" class="av-inline-form">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_unidad">
                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                <div class="av-fg" style="flex:3"><input type="text" name="nombre" placeholder="Ej. Unidad 1 - Introducción" required></div>
                <div class="av-fg" style="flex:1"><input type="number" name="orden" placeholder="Orden" value="<?= count($unidades) + 1 ?>" min="1"></div>
                <button class="av-btn av-btn--primary" type="submit">Agregar</button>
            </form>
        </div>

        <?php foreach ($unidades as $idx => $u): ?>
            <?php $uid = (int) $u['id']; $items = $contenidosPorUnidad[$uid] ?? []; ?>
            <div class="av-accordion-item<?= $idx === 0 ? ' open' : '' ?>">
                <button type="button" class="av-accordion-header">
                    <span><?= e($u['nombre']) ?></span>
                    <span class="av-badge av-badge--gray"><?= count($items) ?> contenido(s)</span>
                    <span class="chev">&#9660;</span>
                </button>
                <div class="av-accordion-body">
                    <div class="av-list" style="margin-bottom:16px">
                        <?php foreach ($items as $item): ?>
                            <div class="av-list-item">
                                <div>
                                    <span class="av-badge av-badge--blue"><?= e($item['tipo']) ?></span>
                                    <span class="content-title"><?= e($item['titulo']) ?></span>
                                    <?php if ($item['descripcion']): ?><div class="content-desc"><?= e($item['descripcion']) ?></div><?php endif; ?>
                                    <div style="margin-top:4px">
                                        <?php if ($item['tipo'] === 'enlace'): ?>
                                            <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener" style="color:var(--ab600);font-weight:600;font-size:.82rem">Abrir enlace</a>
                                        <?php else: ?>
                                            <a href="/download.php?type=contenido&id=<?= (int) $item['id'] ?>" style="color:var(--ab600);font-weight:600;font-size:.82rem">Descargar archivo</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <form method="post" onsubmit="return confirm('¿Eliminar este contenido?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete_contenido">
                                    <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                                    <input type="hidden" name="contenido_id" value="<?= (int) $item['id'] ?>">
                                    <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$items): ?>
                            <div class="av-empty">Sin contenido publicado en esta unidad.</div>
                        <?php endif; ?>
                    </div>

                    <form method="post" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="create_contenido">
                        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                        <input type="hidden" name="unidad_id" value="<?= $uid ?>">
                        <div class="av-form-grid">
                            <div class="av-fg"><label>Título</label><input type="text" name="titulo" required></div>
                            <div class="av-fg">
                                <label>Tipo</label>
                                <select name="tipo" class="tipo-select" required onchange="toggleTipoInputs(this)">
                                    <option value="">Seleccionar</option>
                                    <option value="pdf">PDF</option>
                                    <option value="video">Video</option>
                                    <option value="enlace">Enlace</option>
                                </select>
                            </div>
                        </div>
                        <div class="av-fg">
                            <label>Archivo / enlace</label>
                            <input type="file" name="archivo" class="campo-archivo">
                            <input type="url" name="url" class="campo-url d-none" placeholder="https://..." style="display:none;margin-top:6px">
                        </div>
                        <div class="av-fg"><label>Descripción (opcional)</label><input type="text" name="descripcion"></div>
                        <button class="av-btn av-btn--primary av-btn--sm" type="submit">Publicar contenido</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$unidades): ?>
            <div class="av-alert av-alert--danger"><span>Cree primero una unidad para poder publicar contenido.</span></div>
        <?php endif; ?>
    </div>

    <div class="av-tabpanel" id="tab-tareas">
        <div class="av-card" style="margin-bottom:16px">
            <h3>+ Nueva tarea</h3>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_tarea">
                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                <div class="av-form-grid">
                    <div class="av-fg"><label>Título</label><input type="text" name="titulo" required></div>
                    <div class="av-fg">
                        <label>Unidad</label>
                        <select name="unidad_id">
                            <option value="">Sin unidad específica</option>
                            <?php foreach ($unidades as $u): ?>
                                <option value="<?= (int) $u['id'] ?>"><?= e($u['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="av-fg"><label>Fecha límite</label><input type="datetime-local" name="fecha_limite" required></div>
                </div>
                <div class="av-fg"><label>Descripción / indicaciones</label><textarea name="descripcion" rows="2"></textarea></div>
                <button class="av-btn av-btn--primary" type="submit">Crear tarea</button>
            </form>
        </div>

        <div class="av-table-wrap">
            <table class="av-table">
                <thead><tr><th>Título</th><th>Fecha límite</th><th>Entregas</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($tareas as $t): ?>
                    <tr>
                        <td>
                            <strong><?= e($t['titulo']) ?></strong>
                            <?php if (isPastDue($t['fecha_limite'])): ?><span class="av-badge av-badge--red">Vencida</span><?php endif; ?>
                        </td>
                        <td><?= formatDateEs($t['fecha_limite']) ?></td>
                        <td><?= (int) $t['total_calificadas'] ?> / <?= (int) $t['total_entregas'] ?> calificadas</td>
                        <td class="av-td-actions">
                            <a href="/docente/tarea_entregas.php?tarea_id=<?= (int) $t['id'] ?>" class="av-btn av-btn--secondary av-btn--sm">Ver entregas</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar esta tarea y sus entregas?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_tarea">
                                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                                <input type="hidden" name="tarea_id" value="<?= (int) $t['id'] ?>">
                                <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tareas): ?>
                    <tr><td colspan="4" class="av-empty">No hay tareas creadas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleTipoInputs(select) {
    const form = select.closest('form');
    const archivo = form.querySelector('.campo-archivo');
    const url = form.querySelector('.campo-url');
    if (select.value === 'enlace') {
        archivo.style.display = 'none'; archivo.required = false; archivo.value = '';
        url.style.display = 'block'; url.required = true;
    } else {
        url.style.display = 'none'; url.required = false; url.value = '';
        archivo.style.display = 'block'; archivo.required = true;
    }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

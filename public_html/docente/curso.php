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
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2><?= e($curso['nombre']) ?></h2>
    <a href="/docente/index.php" class="btn btn-outline-secondary">&larr; Mis cursos</a>
</div>
<p class="text-muted"><?= nl2br(e($curso['descripcion'] ?? '')) ?></p>

<ul class="nav nav-tabs mb-3" id="cursoTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-contenido">Contenido</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tareas">Tareas</button></li>
</ul>

<div class="tab-content">
<div class="tab-pane fade show active" id="tab-contenido">

    <div class="card mb-3">
        <div class="card-header">+ Nueva unidad / semana</div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_unidad">
                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                <div class="col-md-7"><input type="text" name="nombre" class="form-control" placeholder="Ej. Unidad 1 - Introducción" required></div>
                <div class="col-md-2"><input type="number" name="orden" class="form-control" placeholder="Orden" value="<?= count($unidades) + 1 ?>" min="1"></div>
                <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Agregar</button></div>
            </form>
        </div>
    </div>

    <div class="accordion" id="unidadesAccordion">
        <?php foreach ($unidades as $idx => $u): ?>
            <?php $uid = (int) $u['id']; $items = $contenidosPorUnidad[$uid] ?? []; ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button <?= $idx > 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#unidad-<?= $uid ?>">
                        <?= e($u['nombre']) ?> <span class="badge bg-secondary ms-2"><?= count($items) ?> contenido(s)</span>
                    </button>
                </h2>
                <div id="unidad-<?= $uid ?>" class="accordion-collapse collapse <?= $idx === 0 ? 'show' : '' ?>" data-bs-parent="#unidadesAccordion">
                    <div class="accordion-body">
                        <ul class="list-group mb-3">
                            <?php foreach ($items as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-info text-dark text-uppercase"><?= e($item['tipo']) ?></span>
                                        <strong><?= e($item['titulo']) ?></strong>
                                        <?php if ($item['descripcion']): ?><div class="small text-muted"><?= e($item['descripcion']) ?></div><?php endif; ?>
                                        <?php if ($item['tipo'] === 'enlace'): ?>
                                            <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener">Abrir enlace</a>
                                        <?php else: ?>
                                            <a href="/download.php?type=contenido&id=<?= (int) $item['id'] ?>">Descargar archivo</a>
                                        <?php endif; ?>
                                    </div>
                                    <form method="post" onsubmit="return confirm('¿Eliminar este contenido?');">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete_contenido">
                                        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                                        <input type="hidden" name="contenido_id" value="<?= (int) $item['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                            <?php if (!$items): ?>
                                <li class="list-group-item text-muted">Sin contenido publicado en esta unidad.</li>
                            <?php endif; ?>
                        </ul>

                        <form method="post" enctype="multipart/form-data" class="border rounded p-3 bg-light">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="create_contenido">
                            <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                            <input type="hidden" name="unidad_id" value="<?= $uid ?>">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="text" name="titulo" class="form-control" placeholder="Título" required>
                                </div>
                                <div class="col-md-3">
                                    <select name="tipo" class="form-select tipo-select" required onchange="toggleTipoInputs(this)">
                                        <option value="">Tipo</option>
                                        <option value="pdf">PDF</option>
                                        <option value="video">Video</option>
                                        <option value="enlace">Enlace</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <input type="file" name="archivo" class="form-control campo-archivo">
                                    <input type="url" name="url" class="form-control campo-url d-none" placeholder="https://...">
                                </div>
                                <div class="col-12">
                                    <input type="text" name="descripcion" class="form-control" placeholder="Descripción (opcional)">
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary btn-sm" type="submit">Publicar contenido</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$unidades): ?>
            <div class="alert alert-info">Cree primero una unidad para poder publicar contenido.</div>
        <?php endif; ?>
    </div>
</div>

<div class="tab-pane fade" id="tab-tareas">
    <div class="card mb-3">
        <div class="card-header">+ Nueva tarea</div>
        <div class="card-body">
            <form method="post" class="row g-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_tarea">
                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                <div class="col-md-4"><input type="text" name="titulo" class="form-control" placeholder="Título" required></div>
                <div class="col-md-3">
                    <select name="unidad_id" class="form-select">
                        <option value="">Sin unidad específica</option>
                        <?php foreach ($unidades as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"><?= e($u['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><input type="datetime-local" name="fecha_limite" class="form-control" required></div>
                <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Crear</button></div>
                <div class="col-12"><textarea name="descripcion" class="form-control" rows="2" placeholder="Descripción / indicaciones"></textarea></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Título</th><th>Fecha límite</th><th>Entregas</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($tareas as $t): ?>
                    <tr>
                        <td>
                            <?= e($t['titulo']) ?>
                            <?php if (isPastDue($t['fecha_limite'])): ?><span class="badge bg-danger ms-1">Vencida</span><?php endif; ?>
                        </td>
                        <td><?= formatDateEs($t['fecha_limite']) ?></td>
                        <td><?= (int) $t['total_calificadas'] ?> / <?= (int) $t['total_entregas'] ?> calificadas</td>
                        <td class="text-end">
                            <a href="/docente/tarea_entregas.php?tarea_id=<?= (int) $t['id'] ?>" class="btn btn-sm btn-outline-primary">Ver entregas</a>
                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta tarea y sus entregas?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_tarea">
                                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                                <input type="hidden" name="tarea_id" value="<?= (int) $t['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$tareas): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No hay tareas creadas.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<script>
function toggleTipoInputs(select) {
    const form = select.closest('form');
    const archivo = form.querySelector('.campo-archivo');
    const url = form.querySelector('.campo-url');
    if (select.value === 'enlace') {
        archivo.classList.add('d-none'); archivo.required = false; archivo.value = '';
        url.classList.remove('d-none'); url.required = true;
    } else {
        url.classList.add('d-none'); url.required = false; url.value = '';
        archivo.classList.remove('d-none'); archivo.required = true;
    }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>

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

    if ($action === 'create_sesion') {
        $fecha = (string) ($_POST['fecha'] ?? '');
        $hora = (string) ($_POST['hora_inicio'] ?? '');
        $duracion = (int) ($_POST['duracion_min'] ?? 90);
        $tema = trim((string) ($_POST['tema'] ?? ''));
        $linkZoom = trim((string) ($_POST['link_zoom'] ?? ''));

        if ($fecha === '' || $hora === '' || $tema === '') {
            setFlash('danger', 'Complete fecha, hora y tema de la sesión.');
        } elseif ($linkZoom !== '' && !filter_var($linkZoom, FILTER_VALIDATE_URL)) {
            setFlash('danger', 'El link de Zoom no es una URL válida.');
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO sesiones (curso_id, fecha, hora_inicio, duracion_min, tema, link_zoom)
                 VALUES (:curso_id, :fecha, :hora_inicio, :duracion_min, :tema, :link_zoom)'
            );
            $ins->execute([
                'curso_id' => $cursoId, 'fecha' => $fecha, 'hora_inicio' => $hora,
                'duracion_min' => $duracion > 0 ? $duracion : 90, 'tema' => $tema,
                'link_zoom' => $linkZoom !== '' ? $linkZoom : null,
            ]);
            setFlash('success', 'Sesión programada.');
        }
    } elseif ($action === 'update_sesion') {
        $sesionId = (int) ($_POST['sesion_id'] ?? 0);
        $fecha = (string) ($_POST['fecha'] ?? '');
        $hora = (string) ($_POST['hora_inicio'] ?? '');
        $duracion = (int) ($_POST['duracion_min'] ?? 90);
        $tema = trim((string) ($_POST['tema'] ?? ''));
        $linkZoom = trim((string) ($_POST['link_zoom'] ?? ''));
        $linkGrabacion = trim((string) ($_POST['link_grabacion'] ?? ''));

        if ($fecha === '' || $hora === '' || $tema === '') {
            setFlash('danger', 'Complete fecha, hora y tema de la sesión.');
        } elseif ($linkZoom !== '' && !filter_var($linkZoom, FILTER_VALIDATE_URL)) {
            setFlash('danger', 'El link de Zoom no es una URL válida.');
        } elseif ($linkGrabacion !== '' && !filter_var($linkGrabacion, FILTER_VALIDATE_URL)) {
            setFlash('danger', 'El link de grabación no es una URL válida.');
        } else {
            $upd = $pdo->prepare(
                'UPDATE sesiones SET fecha=:fecha, hora_inicio=:hora_inicio, duracion_min=:duracion_min,
                 tema=:tema, link_zoom=:link_zoom, link_grabacion=:link_grabacion
                 WHERE id=:id AND curso_id=:curso_id'
            );
            $upd->execute([
                'fecha' => $fecha, 'hora_inicio' => $hora, 'duracion_min' => $duracion > 0 ? $duracion : 90,
                'tema' => $tema, 'link_zoom' => $linkZoom !== '' ? $linkZoom : null,
                'link_grabacion' => $linkGrabacion !== '' ? $linkGrabacion : null,
                'id' => $sesionId, 'curso_id' => $cursoId,
            ]);
            setFlash('success', 'Sesión actualizada.');
        }
    } elseif ($action === 'cancelar_sesion') {
        $sesionId = (int) ($_POST['sesion_id'] ?? 0);
        $upd = $pdo->prepare("UPDATE sesiones SET estado = 'cancelada' WHERE id = :id AND curso_id = :curso_id");
        $upd->execute(['id' => $sesionId, 'curso_id' => $cursoId]);
        setFlash('success', 'Sesión cancelada. Los estudiantes verán el cambio en su panel.');
    } elseif ($action === 'reactivar_sesion') {
        $sesionId = (int) ($_POST['sesion_id'] ?? 0);
        $upd = $pdo->prepare("UPDATE sesiones SET estado = 'programada' WHERE id = :id AND curso_id = :curso_id");
        $upd->execute(['id' => $sesionId, 'curso_id' => $cursoId]);
        setFlash('success', 'Sesión reactivada.');
    } elseif ($action === 'create_contenido') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $tipo = (string) ($_POST['tipo'] ?? '');
        $url = trim((string) ($_POST['url'] ?? ''));
        $sesionIdRaw = (string) ($_POST['sesion_id'] ?? '');
        $sesionId = $sesionIdRaw === '' ? null : (int) $sesionIdRaw;

        if ($titulo === '' || !in_array($tipo, ['pdf', 'video', 'enlace'], true)) {
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
                    'INSERT INTO contenidos (curso_id, sesion_id, titulo, descripcion, tipo, archivo_path, url)
                     VALUES (:curso_id, :sesion_id, :titulo, :descripcion, :tipo, :archivo_path, :url)'
                );
                $ins->execute([
                    'curso_id' => $cursoId, 'sesion_id' => $sesionId, 'titulo' => $titulo,
                    'descripcion' => $descripcion, 'tipo' => $tipo, 'archivo_path' => $archivoPath, 'url' => $url,
                ]);
                setFlash('success', 'Material publicado.');
            } catch (RuntimeException $e) {
                setFlash('danger', $e->getMessage());
            }
        }
    } elseif ($action === 'delete_contenido') {
        $contenidoId = (int) ($_POST['contenido_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM contenidos WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $contenidoId, 'curso_id' => $cursoId]);
        setFlash('success', 'Material eliminado.');
    } elseif ($action === 'create_tarea') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $fechaLimite = (string) ($_POST['fecha_limite'] ?? '');
        $sesionIdRaw = (string) ($_POST['sesion_id'] ?? '');
        $sesionId = $sesionIdRaw === '' ? null : (int) $sesionIdRaw;

        if ($titulo === '' || $fechaLimite === '') {
            setFlash('danger', 'Complete el título y la fecha límite de la tarea.');
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO tareas (curso_id, sesion_id, titulo, descripcion, fecha_limite)
                 VALUES (:curso_id, :sesion_id, :titulo, :descripcion, :fecha_limite)'
            );
            $ins->execute([
                'curso_id' => $cursoId, 'sesion_id' => $sesionId, 'titulo' => $titulo,
                'descripcion' => $descripcion, 'fecha_limite' => $fechaLimite,
            ]);
            setFlash('success', 'Tarea creada.');
        }
    } elseif ($action === 'delete_tarea') {
        $tareaId = (int) ($_POST['tarea_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM tareas WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $tareaId, 'curso_id' => $cursoId]);
        setFlash('success', 'Tarea eliminada.');
    } elseif ($action === 'create_aviso') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $contenido = trim((string) ($_POST['contenido'] ?? ''));
        if ($titulo === '' || $contenido === '') {
            setFlash('danger', 'Complete el título y el contenido del aviso.');
        } else {
            $ins = $pdo->prepare('INSERT INTO avisos (autor_id, curso_id, titulo, contenido) VALUES (:autor_id, :curso_id, :titulo, :contenido)');
            $ins->execute(['autor_id' => $docenteId, 'curso_id' => $cursoId, 'titulo' => $titulo, 'contenido' => $contenido]);
            setFlash('success', 'Aviso publicado para los estudiantes del curso.');
        }
    } elseif ($action === 'delete_aviso') {
        $avisoId = (int) ($_POST['aviso_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM avisos WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $avisoId, 'curso_id' => $cursoId]);
        setFlash('success', 'Aviso eliminado.');
    } elseif ($action === 'create_comentario') {
        $contenido = trim((string) ($_POST['contenido'] ?? ''));
        if ($contenido === '') {
            setFlash('danger', 'Escriba un comentario.');
        } else {
            $ins = $pdo->prepare('INSERT INTO comentarios (curso_id, autor_id, contenido) VALUES (:curso_id, :autor_id, :contenido)');
            $ins->execute(['curso_id' => $cursoId, 'autor_id' => $docenteId, 'contenido' => $contenido]);
            setFlash('success', 'Comentario publicado.');
        }
    } elseif ($action === 'delete_comentario') {
        $comentarioId = (int) ($_POST['comentario_id'] ?? 0);
        $del = $pdo->prepare('DELETE FROM comentarios WHERE id = :id AND curso_id = :curso_id');
        $del->execute(['id' => $comentarioId, 'curso_id' => $cursoId]);
        setFlash('success', 'Comentario eliminado.');
    }

    redirect('/docente/curso.php?id=' . $cursoId);
}

$sesiones = $pdo->prepare('SELECT * FROM sesiones WHERE curso_id = :curso_id ORDER BY fecha DESC, hora_inicio DESC');
$sesiones->execute(['curso_id' => $cursoId]);
$sesiones = $sesiones->fetchAll();

$contenidos = $pdo->prepare(
    'SELECT c.*, s.tema AS sesion_tema, s.fecha AS sesion_fecha FROM contenidos c
     LEFT JOIN sesiones s ON s.id = c.sesion_id
     WHERE c.curso_id = :curso_id ORDER BY c.fecha_publicacion DESC'
);
$contenidos->execute(['curso_id' => $cursoId]);
$contenidos = $contenidos->fetchAll();

$tareas = $pdo->prepare(
    'SELECT t.*, (SELECT COUNT(*) FROM entregas e WHERE e.tarea_id = t.id) AS total_entregas,
            (SELECT COUNT(*) FROM entregas e WHERE e.tarea_id = t.id AND e.calificacion IS NOT NULL) AS total_calificadas
     FROM tareas t WHERE t.curso_id = :curso_id ORDER BY t.fecha_limite'
);
$tareas->execute(['curso_id' => $cursoId]);
$tareas = $tareas->fetchAll();

$asistenciaResumen = $pdo->prepare(
    "SELECT u.id, u.nombre, u.apellidos,
            COUNT(a.id) AS sesiones_registradas,
            SUM(a.estado = 'presente') AS presentes,
            SUM(a.estado = 'tarde') AS tardes,
            SUM(a.estado = 'falta') AS faltas,
            SUM(a.estado = 'justificado') AS justificados
     FROM matriculas m
     JOIN usuarios u ON u.id = m.estudiante_id
     LEFT JOIN sesiones s ON s.curso_id = m.curso_id
     LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.estudiante_id = u.id
     WHERE m.curso_id = :curso_id AND m.estado = 'activo'
     GROUP BY u.id, u.nombre, u.apellidos
     ORDER BY u.apellidos, u.nombre"
);
$asistenciaResumen->execute(['curso_id' => $cursoId]);
$asistenciaResumen = $asistenciaResumen->fetchAll();

$avisos = $pdo->prepare('SELECT * FROM avisos WHERE curso_id = :curso_id ORDER BY created_at DESC');
$avisos->execute(['curso_id' => $cursoId]);
$avisos = $avisos->fetchAll();

$comentarios = $pdo->prepare(
    "SELECT co.*, CONCAT(u.nombre, ' ', u.apellidos) AS autor_nombre, u.rol AS autor_rol
     FROM comentarios co LEFT JOIN usuarios u ON u.id = co.autor_id
     WHERE co.curso_id = :curso_id ORDER BY co.created_at ASC"
);
$comentarios->execute(['curso_id' => $cursoId]);
$comentarios = $comentarios->fetchAll();

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
        <button type="button" class="av-tab active" data-tab-target="tab-sesiones">Sesiones</button>
        <button type="button" class="av-tab" data-tab-target="tab-materiales">Materiales</button>
        <button type="button" class="av-tab" data-tab-target="tab-tareas">Tareas</button>
        <button type="button" class="av-tab" data-tab-target="tab-asistencia">Asistencia</button>
        <button type="button" class="av-tab" data-tab-target="tab-avisos">Avisos</button>
        <button type="button" class="av-tab" data-tab-target="tab-comentarios">Comentarios</button>
    </div>

    <!-- SESIONES -->
    <div class="av-tabpanel active" id="tab-sesiones">
        <div class="av-card" style="margin-bottom:16px">
            <h3>+ Nueva sesión</h3>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_sesion">
                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                <div class="av-form-grid">
                    <div class="av-fg"><label>Fecha</label><input type="date" name="fecha" required></div>
                    <div class="av-fg"><label>Hora de inicio</label><input type="time" name="hora_inicio" required></div>
                    <div class="av-fg"><label>Duración (min)</label><input type="number" name="duracion_min" value="90" min="15" step="5"></div>
                    <div class="av-fg"><label>Tema</label><input type="text" name="tema" required></div>
                </div>
                <div class="av-fg"><label>Link de Zoom (opcional)</label><input type="url" name="link_zoom" placeholder="https://zoom.us/j/..."></div>
                <button class="av-btn av-btn--primary" type="submit">Programar sesión</button>
            </form>
        </div>

        <?php foreach ($sesiones as $idx => $s): ?>
            <?php
                $sid = (int) $s['id'];
                $esHoy = isSessionToday($s['fecha']);
                $esPasada = isSessionPast($s['fecha']);
            ?>
            <div class="av-accordion-item<?= $idx === 0 ? ' open' : '' ?>">
                <button type="button" class="av-accordion-header">
                    <span><?= formatFechaEs($s['fecha']) ?> <?= formatHoraEs($s['hora_inicio']) ?> &middot; <?= e($s['tema']) ?></span>
                    <?php if ($s['estado'] === 'cancelada'): ?><span class="av-badge av-badge--red">Cancelada</span>
                    <?php elseif ($esHoy): ?><span class="av-badge av-badge--green">Hoy</span>
                    <?php elseif ($esPasada): ?><span class="av-badge av-badge--gray">Realizada</span>
                    <?php else: ?><span class="av-badge av-badge--blue">Programada</span><?php endif; ?>
                    <span class="chev">&#9660;</span>
                </button>
                <div class="av-accordion-body">
                    <form method="post" class="av-form-grid" style="margin-bottom:12px">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_sesion">
                        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                        <input type="hidden" name="sesion_id" value="<?= $sid ?>">
                        <div class="av-fg"><label>Fecha</label><input type="date" name="fecha" value="<?= e($s['fecha']) ?>" required></div>
                        <div class="av-fg"><label>Hora de inicio</label><input type="time" name="hora_inicio" value="<?= e(substr($s['hora_inicio'], 0, 5)) ?>" required></div>
                        <div class="av-fg"><label>Duración (min)</label><input type="number" name="duracion_min" value="<?= (int) $s['duracion_min'] ?>" min="15" step="5"></div>
                        <div class="av-fg"><label>Tema</label><input type="text" name="tema" value="<?= e($s['tema']) ?>" required></div>
                        <div class="av-fg"><label>Link de Zoom</label><input type="url" name="link_zoom" value="<?= e($s['link_zoom'] ?? '') ?>" placeholder="https://zoom.us/j/..."></div>
                        <div class="av-fg"><label>Link de grabación</label><input type="url" name="link_grabacion" value="<?= e($s['link_grabacion'] ?? '') ?>" placeholder="https://..."></div>
                        <div style="grid-column:1/-1"><button class="av-btn av-btn--secondary av-btn--sm" type="submit">Guardar cambios</button></div>
                    </form>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <a href="/docente/asistencia.php?sesion_id=<?= $sid ?>" class="av-btn av-btn--primary av-btn--sm">Tomar asistencia</a>
                        <?php if ($s['estado'] !== 'cancelada'): ?>
                            <form method="post" onsubmit="return confirm('¿Cancelar esta sesión?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="cancelar_sesion">
                                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                                <input type="hidden" name="sesion_id" value="<?= $sid ?>">
                                <button class="av-btn av-btn--danger av-btn--sm" type="submit">Cancelar sesión</button>
                            </form>
                        <?php else: ?>
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="reactivar_sesion">
                                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                                <input type="hidden" name="sesion_id" value="<?= $sid ?>">
                                <button class="av-btn av-btn--outline av-btn--sm" type="submit">Reactivar sesión</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$sesiones): ?>
            <div class="av-empty">Aún no ha programado sesiones para este curso.</div>
        <?php endif; ?>
    </div>

    <!-- MATERIALES -->
    <div class="av-tabpanel" id="tab-materiales">
        <div class="av-card" style="margin-bottom:16px">
            <h3>+ Nuevo material</h3>
            <form method="post" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_contenido">
                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                <div class="av-form-grid">
                    <div class="av-fg"><label>Título</label><input type="text" name="titulo" required></div>
                    <div class="av-fg">
                        <label>Tipo</label>
                        <select name="tipo" required onchange="toggleTipoInputs(this)">
                            <option value="">Seleccionar</option>
                            <option value="pdf">PDF</option>
                            <option value="video">Video</option>
                            <option value="enlace">Enlace</option>
                        </select>
                    </div>
                    <div class="av-fg">
                        <label>Sesión (opcional)</label>
                        <select name="sesion_id">
                            <option value="">General del curso</option>
                            <?php foreach ($sesiones as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= formatFechaEs($s['fecha']) ?> - <?= e($s['tema']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="av-fg">
                    <label>Archivo / enlace</label>
                    <input type="file" name="archivo" class="campo-archivo">
                    <input type="url" name="url" class="campo-url" placeholder="https://..." style="display:none;margin-top:6px">
                </div>
                <div class="av-fg"><label>Descripción (opcional)</label><input type="text" name="descripcion"></div>
                <button class="av-btn av-btn--primary" type="submit">Publicar material</button>
            </form>
        </div>

        <div class="av-list">
            <?php foreach ($contenidos as $item): ?>
                <div class="av-list-item">
                    <div>
                        <span class="av-badge av-badge--blue"><?= e($item['tipo']) ?></span>
                        <span class="content-title"><?= e($item['titulo']) ?></span>
                        <?php if ($item['sesion_tema']): ?><span class="av-badge av-badge--gray"><?= formatFechaEs($item['sesion_fecha']) ?> - <?= e($item['sesion_tema']) ?></span><?php endif; ?>
                        <?php if ($item['descripcion']): ?><div class="content-desc"><?= e($item['descripcion']) ?></div><?php endif; ?>
                        <div style="margin-top:4px">
                            <?php if ($item['tipo'] === 'enlace'): ?>
                                <a href="<?= e($item['url']) ?>" target="_blank" rel="noopener" style="color:var(--ab600);font-weight:600;font-size:.82rem">Abrir enlace</a>
                            <?php else: ?>
                                <a href="/download.php?type=contenido&id=<?= (int) $item['id'] ?>" style="color:var(--ab600);font-weight:600;font-size:.82rem">Descargar archivo</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="post" onsubmit="return confirm('¿Eliminar este material?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_contenido">
                        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                        <input type="hidden" name="contenido_id" value="<?= (int) $item['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$contenidos): ?>
                <div class="av-empty">Sin materiales publicados en este curso.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAREAS -->
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
                        <label>Sesión (opcional)</label>
                        <select name="sesion_id">
                            <option value="">General del curso</option>
                            <?php foreach ($sesiones as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"><?= formatFechaEs($s['fecha']) ?> - <?= e($s['tema']) ?></option>
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

    <!-- ASISTENCIA -->
    <div class="av-tabpanel" id="tab-asistencia">
        <div style="display:flex;justify-content:flex-end;margin-bottom:12px">
            <a href="/docente/export_asistencia.php?curso_id=<?= $cursoId ?>" class="av-btn av-btn--outline av-btn--sm"><?= avIcon('download') ?> Exportar CSV</a>
        </div>
        <div class="av-table-wrap">
            <table class="av-table">
                <thead><tr><th>Estudiante</th><th>Sesiones registradas</th><th>Presente</th><th>Tarde</th><th>Falta</th><th>Justificado</th><th>% Asistencia</th></tr></thead>
                <tbody>
                <?php foreach ($asistenciaResumen as $r): ?>
                    <?php
                        $reg = (int) $r['sesiones_registradas'];
                        $pct = $reg > 0 ? round((((int) $r['presentes'] + (int) $r['tardes']) / $reg) * 100) : null;
                    ?>
                    <tr>
                        <td><strong><?= e($r['nombre'] . ' ' . $r['apellidos']) ?></strong></td>
                        <td><?= $reg ?></td>
                        <td><?= (int) $r['presentes'] ?></td>
                        <td><?= (int) $r['tardes'] ?></td>
                        <td><?= (int) $r['faltas'] ?></td>
                        <td><?= (int) $r['justificados'] ?></td>
                        <td>
                            <?php if ($pct === null): ?>
                                <span class="av-text-muted">Sin registros</span>
                            <?php else: ?>
                                <span class="av-badge av-badge--<?= $pct >= 70 ? 'green' : 'red' ?>"><?= $pct ?>%</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$asistenciaResumen): ?>
                    <tr><td colspan="7" class="av-empty">No hay estudiantes matriculados en este curso.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- AVISOS -->
    <div class="av-tabpanel" id="tab-avisos">
        <div class="av-card" style="margin-bottom:16px">
            <h3>+ Nuevo aviso para este curso</h3>
            <form method="post">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create_aviso">
                <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                <div class="av-fg"><label>Título</label><input type="text" name="titulo" required></div>
                <div class="av-fg"><label>Contenido</label><textarea name="contenido" rows="3" required></textarea></div>
                <button class="av-btn av-btn--primary" type="submit"><?= avIcon('megaphone') ?> Publicar aviso</button>
            </form>
        </div>
        <div class="av-list">
            <?php foreach ($avisos as $a): ?>
                <div class="av-list-item">
                    <div>
                        <div class="content-title"><?= e($a['titulo']) ?></div>
                        <div class="content-desc"><?= nl2br(e($a['contenido'])) ?></div>
                        <div class="content-desc" style="margin-top:4px"><?= formatDateEs($a['created_at']) ?></div>
                    </div>
                    <form method="post" onsubmit="return confirm('¿Eliminar este aviso?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_aviso">
                        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                        <input type="hidden" name="aviso_id" value="<?= (int) $a['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$avisos): ?>
                <div class="av-empty">Sin avisos publicados en este curso.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- COMENTARIOS -->
    <div class="av-tabpanel" id="tab-comentarios">
        <div class="av-comments">
            <?php foreach ($comentarios as $c): ?>
                <div class="av-comment">
                    <div class="av-comment__body">
                        <div class="av-comment__meta">
                            <strong><?= e($c['autor_nombre'] ?? 'Usuario eliminado') ?></strong>
                            <?php if ($c['autor_rol']): ?><span class="av-badge av-badge--gray"><?= e($c['autor_rol']) ?></span><?php endif; ?>
                            <span class="av-text-muted"><?= formatDateEs($c['created_at']) ?></span>
                        </div>
                        <p><?= nl2br(e($c['contenido'])) ?></p>
                    </div>
                    <form method="post" onsubmit="return confirm('¿Eliminar este comentario?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_comentario">
                        <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
                        <input type="hidden" name="comentario_id" value="<?= (int) $c['id'] ?>">
                        <button class="av-btn av-btn--danger av-btn--sm" type="submit">Eliminar</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!$comentarios): ?>
                <div class="av-empty">Sin comentarios todavía. Sé el primero en escribir.</div>
            <?php endif; ?>
        </div>
        <form method="post" class="av-card" style="margin-top:16px">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_comentario">
            <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
            <div class="av-fg"><textarea name="contenido" rows="3" placeholder="Escribe un comentario para el curso..." required></textarea></div>
            <button class="av-btn av-btn--primary" type="submit">Comentar</button>
        </form>
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

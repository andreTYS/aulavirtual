<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

$id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : 0);
$editing = $id > 0;

$curso = ['nombre' => '', 'descripcion' => '', 'carrera_id' => '', 'docente_id' => '', 'activo' => 1];

if ($editing) {
    $stmt = $pdo->prepare('SELECT * FROM cursos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) {
        setFlash('danger', 'Curso no encontrado.');
        redirect('/admin/cursos.php');
    }
    $curso = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    $curso['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $curso['descripcion'] = trim((string) ($_POST['descripcion'] ?? ''));
    $curso['carrera_id'] = (int) ($_POST['carrera_id'] ?? 0);
    $docenteRaw = (string) ($_POST['docente_id'] ?? '');
    $curso['docente_id'] = $docenteRaw === '' ? null : (int) $docenteRaw;
    $curso['activo'] = isset($_POST['activo']) ? 1 : 0;

    if ($curso['nombre'] === '') $errors[] = 'El nombre del curso es obligatorio.';
    if ($curso['carrera_id'] <= 0) $errors[] = 'Debe seleccionar una carrera.';

    if (!$errors) {
        if ($editing) {
            $stmt = $pdo->prepare(
                'UPDATE cursos SET nombre=:nombre, descripcion=:descripcion, carrera_id=:carrera_id,
                 docente_id=:docente_id, activo=:activo WHERE id=:id'
            );
            $stmt->execute([
                'nombre' => $curso['nombre'], 'descripcion' => $curso['descripcion'],
                'carrera_id' => $curso['carrera_id'], 'docente_id' => $curso['docente_id'],
                'activo' => $curso['activo'], 'id' => $id,
            ]);
            setFlash('success', 'Curso actualizado.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO cursos (nombre, descripcion, carrera_id, docente_id, activo)
                 VALUES (:nombre, :descripcion, :carrera_id, :docente_id, :activo)'
            );
            $stmt->execute([
                'nombre' => $curso['nombre'], 'descripcion' => $curso['descripcion'],
                'carrera_id' => $curso['carrera_id'], 'docente_id' => $curso['docente_id'],
                'activo' => $curso['activo'],
            ]);
            setFlash('success', 'Curso creado.');
        }
        redirect('/admin/cursos.php');
    }
}

$carreras = $pdo->query('SELECT id, nombre FROM carreras ORDER BY nombre')->fetchAll();
$docentes = $pdo->query("SELECT id, nombre, apellidos FROM usuarios WHERE rol = 'docente' AND activo = 1 ORDER BY apellidos")->fetchAll();

$pageTitle = $editing ? 'Editar curso' : 'Nuevo curso';
require __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-4"><?= e($pageTitle) ?></h2>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" novalidate>
            <?= csrfField() ?>
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Nombre del curso</label>
                    <input type="text" name="nombre" class="form-control" value="<?= e($curso['nombre']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?= e($curso['descripcion']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Carrera</label>
                    <select name="carrera_id" class="form-select" required>
                        <option value="">-- Seleccionar --</option>
                        <?php foreach ($carreras as $car): ?>
                            <option value="<?= (int) $car['id'] ?>" <?= (int) $curso['carrera_id'] === (int) $car['id'] ? 'selected' : '' ?>>
                                <?= e($car['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Docente asignado</label>
                    <select name="docente_id" class="form-select">
                        <option value="">Sin asignar</option>
                        <?php foreach ($docentes as $doc): ?>
                            <option value="<?= (int) $doc['id'] ?>" <?= (int) ($curso['docente_id'] ?? 0) === (int) $doc['id'] ? 'selected' : '' ?>>
                                <?= e($doc['nombre'] . ' ' . $doc['apellidos']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $curso['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Curso activo</label>
                    </div>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Guardar</button>
                <a href="/admin/cursos.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>

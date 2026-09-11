<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

const DEMO_CURSO_PREFIX = '[DEMO] ';
const DEMO_USERNAME_PREFIX = 'demo_';
const DEMO_PERIODO_NOMBRE = '[DEMO] Periodo de práctica';

function demoCleanup(PDO $pdo): void
{
    // Borra archivos en disco de materiales/entregas de cursos demo antes de borrar las filas.
    $paths = $pdo->query(
        "SELECT archivo_path FROM contenidos WHERE curso_id IN (SELECT id FROM cursos WHERE nombre LIKE '" . DEMO_CURSO_PREFIX . "%') AND archivo_path IS NOT NULL
         UNION
         SELECT e.archivo_path FROM entregas e JOIN tareas t ON t.id = e.tarea_id
         WHERE t.curso_id IN (SELECT id FROM cursos WHERE nombre LIKE '" . DEMO_CURSO_PREFIX . "%')"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($paths as $relative) {
        $full = UPLOADS_PATH . '/' . $relative;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    // Cascada: al borrar los cursos demo se van sesiones, contenidos, tareas,
    // entregas, avisos y comentarios asociados.
    $pdo->exec("DELETE FROM cursos WHERE nombre LIKE '" . DEMO_CURSO_PREFIX . "%'");
    $pdo->exec("DELETE FROM usuarios WHERE username LIKE '" . DEMO_USERNAME_PREFIX . "%'");
    $pdo->exec("DELETE FROM avisos WHERE titulo LIKE '" . DEMO_CURSO_PREFIX . "%'");
}

function writeDemoFile(string $subdir, string $filename, string $content): string
{
    $dir = UPLOADS_PATH . '/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $safeName = bin2hex(random_bytes(6)) . '_' . $filename;
    file_put_contents($dir . '/' . $safeName, $content);
    return trim($subdir, '/') . '/' . $safeName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'seed') {
        demoCleanup($pdo);

        $carreras = $pdo->query('SELECT id, nombre FROM carreras ORDER BY id')->fetchAll();
        if (!$carreras) {
            setFlash('danger', 'Primero crea al menos una carrera antes de generar datos de demostración.');
            redirect('/admin/demo_data.php');
        }

        // Periodo dedicado a demo (no toca el periodo activo real del instituto).
        $stmt = $pdo->prepare('SELECT id FROM periodos_academicos WHERE nombre = :nombre');
        $stmt->execute(['nombre' => DEMO_PERIODO_NOMBRE]);
        $periodoId = $stmt->fetchColumn();
        if (!$periodoId) {
            $pdo->prepare('INSERT INTO periodos_academicos (nombre, fecha_inicio, fecha_fin, activo) VALUES (:nombre, :inicio, :fin, 0)')
                ->execute(['nombre' => DEMO_PERIODO_NOMBRE, 'inicio' => date('Y-m-d', strtotime('-2 months')), 'fin' => date('Y-m-d', strtotime('+3 months'))]);
            $periodoId = (int) $pdo->lastInsertId();
        }

        $hash = password_hash('Demo1234!', PASSWORD_BCRYPT);

        $docentesInfo = [
            ['María', 'Quispe Huamán', 'Ingeniería de Software'],
            ['Carlos', 'Ramírez Salas', 'Redes y Telecomunicaciones'],
            ['Lucía', 'Torres Bejarano', 'Contabilidad y Finanzas'],
            ['Jorge', 'Mamani Apaza', 'Electrónica Industrial'],
        ];
        $docenteIds = [];
        foreach ($docentesInfo as $i => [$nombre, $apellidos, $especialidad]) {
            $username = DEMO_USERNAME_PREFIX . 'docente' . ($i + 1);
            $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellidos, username, email, password_hash, rol, activo, especialidad)
                 VALUES (:nombre, :apellidos, :username, :email, :hash, "docente", 1, :especialidad)'
            )->execute([
                'nombre' => $nombre, 'apellidos' => $apellidos, 'username' => $username,
                'email' => $username . '@demo.iestpbf.edu.pe', 'hash' => $hash, 'especialidad' => $especialidad,
            ]);
            $docenteIds[] = (int) $pdo->lastInsertId();
        }

        $nombresEst = ['Juan Pérez Condori', 'Ana Flores Mamani', 'Luis Mendoza Choque', 'Rosa Quispe Ttito',
            'Miguel Apaza Cutipa', 'Carla Huanca Zapata', 'Diego Salas Vilca', 'Fiorella Chura Yucra',
            'Renato Cáceres Paco', 'Milagros Condori Larico', 'Kevin Yucra Machaca', 'Yesenia Pinto Calisaya'];
        $estudianteIds = [];
        foreach ($nombresEst as $i => $nombreCompleto) {
            [$nombre, $apellidos] = [strtok($nombreCompleto, ' '), substr($nombreCompleto, strpos($nombreCompleto, ' ') + 1)];
            $username = DEMO_USERNAME_PREFIX . 'estudiante' . ($i + 1);
            $carrera = $carreras[$i % count($carreras)];
            $pdo->prepare(
                'INSERT INTO usuarios (nombre, apellidos, username, email, password_hash, rol, activo, dni, telefono, carrera_id, apoderado_nombre, apoderado_telefono)
                 VALUES (:nombre, :apellidos, :username, :email, :hash, "estudiante", 1, :dni, :telefono, :carrera_id, :apo_nombre, :apo_tel)'
            )->execute([
                'nombre' => $nombre, 'apellidos' => $apellidos, 'username' => $username,
                'email' => $username . '@demo.iestpbf.edu.pe', 'hash' => $hash,
                'dni' => (string) (70000000 + $i), 'telefono' => '9510' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'carrera_id' => $carrera['id'], 'apo_nombre' => 'Apoderado de ' . $nombre, 'apo_tel' => '9520' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
            ]);
            $estudianteIds[] = (int) $pdo->lastInsertId();
        }

        $cursosInfo = [
            ['Programación Web I', 3],
            ['Redes de Computadoras', 4],
            ['Contabilidad General', 2],
            ['Electrónica Digital', 3],
            ['Base de Datos Aplicadas', 5],
        ];
        $totalSesiones = 0;
        $totalMateriales = 0;
        $totalTareas = 0;
        $totalEntregas = 0;
        $totalAsistencias = 0;

        foreach ($cursosInfo as $i => [$nombreCurso, $ciclo]) {
            $carrera = $carreras[$i % count($carreras)];
            $docenteId = $docenteIds[$i % count($docenteIds)];

            $pdo->prepare(
                'INSERT INTO cursos (nombre, descripcion, carrera_id, docente_id, ciclo, periodo_academico_id, activo)
                 VALUES (:nombre, :descripcion, :carrera_id, :docente_id, :ciclo, :periodo_id, 1)'
            )->execute([
                'nombre' => DEMO_CURSO_PREFIX . $nombreCurso,
                'descripcion' => 'Curso de demostración generado automáticamente para mostrar el funcionamiento del sistema.',
                'carrera_id' => $carrera['id'], 'docente_id' => $docenteId, 'ciclo' => $ciclo, 'periodo_id' => $periodoId,
            ]);
            $cursoId = (int) $pdo->lastInsertId();

            // Matricula entre 5 y 7 estudiantes del pool, rotando el punto de inicio.
            $inscritos = [];
            for ($k = 0; $k < 6; $k++) {
                $estId = $estudianteIds[($i * 3 + $k) % count($estudianteIds)];
                if (in_array($estId, $inscritos, true)) {
                    continue;
                }
                $inscritos[] = $estId;
                $pdo->prepare('INSERT IGNORE INTO matriculas (curso_id, estudiante_id) VALUES (:curso_id, :estudiante_id)')
                    ->execute(['curso_id' => $cursoId, 'estudiante_id' => $estId]);
            }

            // Sesiones: 2 pasadas, 1 hoy, 1 futura.
            $sesionesInfo = [
                ['dias' => -7, 'tema' => 'Sesión 1: Introducción', 'estado' => 'realizada'],
                ['dias' => -3, 'tema' => 'Sesión 2: Desarrollo de contenidos', 'estado' => 'realizada'],
                ['dias' => 0, 'tema' => 'Sesión 3: Práctica guiada', 'estado' => 'programada'],
                ['dias' => 4, 'tema' => 'Sesión 4: Repaso y evaluación', 'estado' => 'programada'],
            ];
            $sesionIds = [];
            foreach ($sesionesInfo as $s) {
                $fecha = date('Y-m-d', strtotime($s['dias'] . ' days'));
                $pdo->prepare(
                    'INSERT INTO sesiones (curso_id, fecha, hora_inicio, duracion_min, tema, link_zoom, link_grabacion, estado)
                     VALUES (:curso_id, :fecha, :hora_inicio, 90, :tema, :link_zoom, :link_grabacion, :estado)'
                )->execute([
                    'curso_id' => $cursoId, 'fecha' => $fecha, 'hora_inicio' => '18:00:00', 'tema' => $s['tema'],
                    'link_zoom' => 'https://zoom.us/j/demo' . $cursoId . abs($s['dias']),
                    'link_grabacion' => $s['estado'] === 'realizada' ? 'https://zoom.us/rec/demo' . $cursoId . abs($s['dias']) : null,
                    'estado' => $s['estado'],
                ]);
                $sesionIds[] = (int) $pdo->lastInsertId();
                $totalSesiones++;
            }

            // Materiales: un PDF real (placeholder) + un enlace.
            $pdfPath = writeDemoFile('contenidos/' . $cursoId, 'material_demo.pdf', "%PDF-1.4\nMaterial de demostración para " . $nombreCurso);
            $pdo->prepare(
                'INSERT INTO contenidos (curso_id, sesion_id, titulo, descripcion, tipo, archivo_path)
                 VALUES (:curso_id, :sesion_id, :titulo, :descripcion, "pdf", :archivo_path)'
            )->execute([
                'curso_id' => $cursoId, 'sesion_id' => $sesionIds[0], 'titulo' => 'Guía de la unidad 1',
                'descripcion' => 'Material introductorio de demostración.', 'archivo_path' => $pdfPath,
            ]);
            $pdo->prepare(
                'INSERT INTO contenidos (curso_id, sesion_id, titulo, descripcion, tipo, url)
                 VALUES (:curso_id, :sesion_id, :titulo, :descripcion, "enlace", :url)'
            )->execute([
                'curso_id' => $cursoId, 'sesion_id' => $sesionIds[1], 'titulo' => 'Lectura complementaria',
                'descripcion' => 'Recurso externo de referencia.', 'url' => 'https://es.wikipedia.org/wiki/' . urlencode($nombreCurso),
            ]);
            $totalMateriales += 2;

            // Tareas: una vencida con entregas calificadas, otra pendiente.
            $pdo->prepare(
                'INSERT INTO tareas (curso_id, sesion_id, titulo, descripcion, fecha_limite)
                 VALUES (:curso_id, :sesion_id, :titulo, :descripcion, :fecha_limite)'
            )->execute([
                'curso_id' => $cursoId, 'sesion_id' => $sesionIds[0], 'titulo' => 'Tarea 1: Práctica inicial',
                'descripcion' => 'Entrega de demostración ya calificada.', 'fecha_limite' => date('Y-m-d H:i:s', strtotime('-1 day')),
            ]);
            $tarea1Id = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO tareas (curso_id, sesion_id, titulo, descripcion, fecha_limite)
                 VALUES (:curso_id, :sesion_id, :titulo, :descripcion, :fecha_limite)'
            )->execute([
                'curso_id' => $cursoId, 'sesion_id' => $sesionIds[3], 'titulo' => 'Tarea 2: Trabajo final',
                'descripcion' => 'Tarea de demostración aún pendiente.', 'fecha_limite' => date('Y-m-d H:i:s', strtotime('+6 days')),
            ]);
            $totalTareas += 2;

            $notas = [12, 14, 15, 16, 17, 18, 19, 20];
            foreach ($inscritos as $idx => $estId) {
                if ($idx % 4 === 3) {
                    continue; // uno de cada cuatro no entrega, para que se vea variado
                }
                $entregaPath = writeDemoFile('entregas/' . $cursoId . '/' . $tarea1Id, 'entrega_demo.pdf', 'Entrega de demostración del estudiante ' . $estId);
                $calificado = $idx % 3 !== 2;
                $pdo->prepare(
                    'INSERT INTO entregas (tarea_id, estudiante_id, archivo_path, calificacion, comentario, fecha_calificacion)
                     VALUES (:tarea_id, :estudiante_id, :archivo_path, :calificacion, :comentario, :fecha_calificacion)'
                )->execute([
                    'tarea_id' => $tarea1Id, 'estudiante_id' => $estId, 'archivo_path' => $entregaPath,
                    'calificacion' => $calificado ? $notas[array_rand($notas)] : null,
                    'comentario' => $calificado ? 'Buen trabajo (comentario de demostración).' : null,
                    'fecha_calificacion' => $calificado ? date('Y-m-d H:i:s') : null,
                ]);
                $totalEntregas++;
            }

            // Asistencia de las 2 sesiones pasadas.
            $estados = ['presente', 'presente', 'presente', 'tarde', 'falta', 'justificado'];
            foreach ([$sesionIds[0], $sesionIds[1]] as $sid) {
                foreach ($inscritos as $idx => $estId) {
                    $pdo->prepare(
                        'INSERT INTO asistencias (sesion_id, estudiante_id, estado) VALUES (:sesion_id, :estudiante_id, :estado)'
                    )->execute(['sesion_id' => $sid, 'estudiante_id' => $estId, 'estado' => $estados[$idx % count($estados)]]);
                    $totalAsistencias++;
                }
            }

            // Aviso y comentarios del curso.
            $pdo->prepare('INSERT INTO avisos (autor_id, curso_id, titulo, contenido) VALUES (:autor_id, :curso_id, :titulo, :contenido)')
                ->execute([
                    'autor_id' => $docenteId, 'curso_id' => $cursoId,
                    'titulo' => DEMO_CURSO_PREFIX . 'Bienvenida al curso',
                    'contenido' => 'Este es un aviso de demostración para el curso ' . $nombreCurso . '.',
                ]);
            $pdo->prepare('INSERT INTO comentarios (curso_id, autor_id, contenido) VALUES (:curso_id, :autor_id, :contenido)')
                ->execute(['curso_id' => $cursoId, 'autor_id' => $docenteId, 'contenido' => 'Bienvenidos al curso, cualquier duda la resolvemos aquí.']);
            if ($inscritos) {
                $pdo->prepare('INSERT INTO comentarios (curso_id, autor_id, contenido) VALUES (:curso_id, :autor_id, :contenido)')
                    ->execute(['curso_id' => $cursoId, 'autor_id' => $inscritos[0], 'contenido' => 'Gracias profesor(a), quedo pendiente de las próximas sesiones.']);
            }
        }

        // Aviso general del instituto (uno solo, identificado con el prefijo demo).
        $pdo->prepare('INSERT INTO avisos (autor_id, curso_id, titulo, contenido) VALUES (:autor_id, NULL, :titulo, :contenido)')
            ->execute([
                'autor_id' => $_SESSION['user_id'],
                'titulo' => DEMO_CURSO_PREFIX . 'Bienvenida institucional',
                'contenido' => 'Aviso general de demostración: inicio de clases del periodo de práctica.',
            ]);

        setFlash('success', sprintf(
            'Datos de demostración generados: %d docentes, %d estudiantes, %d cursos, %d sesiones, %d materiales, %d tareas, %d entregas, %d registros de asistencia. Contraseña de todas las cuentas demo: Demo1234!',
            count($docenteIds), count($estudianteIds), count($cursosInfo),
            $totalSesiones, $totalMateriales, $totalTareas, $totalEntregas, $totalAsistencias
        ));
    } elseif ($action === 'cleanup') {
        demoCleanup($pdo);
        setFlash('success', 'Datos de demostración eliminados.');
    }
    redirect('/admin/demo_data.php');
}

$hayDemo = (int) $pdo->query("SELECT COUNT(*) FROM cursos WHERE nombre LIKE '" . DEMO_CURSO_PREFIX . "%'")->fetchColumn() > 0;

$pageTitle = 'Datos de demostración';
require __DIR__ . '/../includes/header.php';
?>
<div class="av-page-header"><h2>Datos de demostración</h2></div>

<div class="av-card" style="max-width:720px">
    <h3>¿Qué hace esto?</h3>
    <p style="font-size:.88rem;color:var(--n700);line-height:1.7">
        Crea 4 docentes, 12 estudiantes, 5 cursos (con sesiones de Zoom pasadas/hoy/futuras,
        materiales, tareas calificadas, asistencia y avisos) para poder mostrar el sistema
        funcionando de punta a punta. Todo queda identificado con el prefijo
        <strong><?= e(DEMO_CURSO_PREFIX) ?></strong> en cursos/avisos y
        <strong>demo_</strong> en nombres de usuario, para poder identificarlo y borrarlo
        fácilmente. <strong>No modifica ni borra tus datos reales.</strong>
    </p>
    <p style="font-size:.88rem;color:var(--n700)">Contraseña de todas las cuentas de demostración: <strong>Demo1234!</strong> (usuarios: <code>demo_docente1</code>, <code>demo_estudiante1</code>, etc.)</p>

    <div style="display:flex;gap:10px;margin-top:16px">
        <form method="post" onsubmit="return confirm('¿Generar datos de demostración? Si ya existen, se reemplazan por unos nuevos.');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="seed">
            <button type="submit" class="av-btn av-btn--primary"><?= avIcon('star') ?> <?= $hayDemo ? 'Regenerar' : 'Generar' ?> datos de demostración</button>
        </form>
        <?php if ($hayDemo): ?>
            <form method="post" onsubmit="return confirm('¿Eliminar todos los datos de demostración?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="cleanup">
                <button type="submit" class="av-btn av-btn--danger">Eliminar datos de demostración</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>

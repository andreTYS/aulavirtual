<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('administrador');

$rolFiltro = $_GET['rol'] ?? '';
$sql = 'SELECT nombre, apellidos, username, email, rol, activo, dni, telefono, especialidad FROM usuarios';
$params = [];
if (in_array($rolFiltro, ['administrador', 'docente', 'estudiante'], true)) {
    $sql .= ' WHERE rol = :rol';
    $params['rol'] = $rolFiltro;
}
$sql .= ' ORDER BY rol, apellidos, nombre';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="usuarios.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Nombre', 'Apellidos', 'Usuario', 'Correo', 'Rol', 'Estado', 'DNI', 'Teléfono', 'Especialidad']);

foreach ($usuarios as $u) {
    fputcsv($out, [
        $u['nombre'], $u['apellidos'], $u['username'], $u['email'], $u['rol'],
        $u['activo'] ? 'Activo' : 'Inactivo', $u['dni'] ?? '', $u['telefono'] ?? '', $u['especialidad'] ?? '',
    ]);
}
fclose($out);
exit;

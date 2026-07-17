<?php
/**
 * Conexion PDO a MariaDB.
 * Las credenciales coinciden con las variables de entorno definidas
 * en el servicio "db" de docker-compose.yml.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

define('DB_HOST', getenv('DB_HOST') ?: 'aulavirtual-db');
define('DB_NAME', getenv('DB_NAME') ?: 'aulavirtual');
define('DB_USER', getenv('DB_USER') ?: 'aulavirtual');
define('DB_PASS', getenv('DB_PASS') ?: 'qjWiBhv8vNi28334sNdG94Ei');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    error_log('Error de conexion a BD: ' . $e->getMessage());
    http_response_code(500);
    die('No se pudo conectar a la base de datos. Intente mas tarde.');
}

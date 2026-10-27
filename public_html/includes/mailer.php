<?php
/**
 * Cliente SMTP minimo, sin dependencias externas (sin PHPMailer/Composer),
 * para las notificaciones por correo del sistema. Soporta STARTTLS (587) o
 * TLS implicito (465), y autenticacion AUTH LOGIN.
 *
 * Si SMTP_HOST no esta configurado, smtpSend() no hace nada y devuelve
 * false de inmediato: el sistema sigue funcionando sin notificaciones por
 * correo hasta que el administrador configure un servidor real.
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function smtpIsConfigured(): bool
{
    return SMTP_HOST !== '' && SMTP_USER !== '' && SMTP_PASS !== '';
}

/**
 * Envia un correo HTML simple. Nunca lanza excepciones: registra el error
 * en el log de PHP y devuelve false si algo falla, para no romper el flujo
 * principal (guardar una nota, publicar un aviso, etc.) por un problema de
 * correo.
 */
function smtpSend(string $to, string $toName, string $subject, string $bodyHtml): bool
{
    if (!smtpIsConfigured()) {
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    try {
        $socket = smtpConnect();
        smtpHandshakeAndSend($socket, $to, $toName, $subject, $bodyHtml);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('smtpSend: ' . $e->getMessage());
        return false;
    }
}

/**
 * @return resource
 */
function smtpConnect()
{
    $host = SMTP_SECURE === 'ssl' ? 'ssl://' . SMTP_HOST : SMTP_HOST;
    $socket = @stream_socket_client($host . ':' . SMTP_PORT, $errno, $errstr, 10);
    if (!$socket) {
        throw new RuntimeException('No se pudo conectar al servidor SMTP: ' . $errstr);
    }
    stream_set_timeout($socket, 10);
    smtpReadResponse($socket, 220);
    return $socket;
}

/**
 * @param resource $socket
 */
function smtpHandshakeAndSend($socket, string $to, string $toName, string $subject, string $bodyHtml): void
{
    smtpCommand($socket, 'EHLO ' . parse_url('http://' . SMTP_HOST, PHP_URL_HOST), 250);

    if (SMTP_SECURE === 'tls') {
        smtpCommand($socket, 'STARTTLS', 220);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('No se pudo iniciar TLS con el servidor SMTP.');
        }
        smtpCommand($socket, 'EHLO ' . parse_url('http://' . SMTP_HOST, PHP_URL_HOST), 250);
    }

    smtpCommand($socket, 'AUTH LOGIN', 334);
    smtpCommand($socket, base64_encode(SMTP_USER), 334);
    smtpCommand($socket, base64_encode(SMTP_PASS), 235);

    smtpCommand($socket, 'MAIL FROM:<' . SMTP_FROM . '>', 250);
    smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
    smtpCommand($socket, 'DATA', 354);

    $headers = smtpEncodeHeader('From', SMTP_FROM_NAME, SMTP_FROM)
        . smtpEncodeHeader('To', $toName, $to)
        . 'Subject: ' . smtpEncodeWord($subject) . "\r\n"
        . 'MIME-Version: 1.0' . "\r\n"
        . 'Content-Type: text/html; charset=UTF-8' . "\r\n"
        . 'Content-Transfer-Encoding: 8bit' . "\r\n"
        . 'Date: ' . date('r') . "\r\n";

    // Escapa lineas que empiezan con "." (terminador del cuerpo SMTP).
    $body = preg_replace('/^\./m', '..', $bodyHtml);

    smtpWrite($socket, $headers . "\r\n" . $body . "\r\n.");
    smtpReadResponse($socket, 250);
    smtpCommand($socket, 'QUIT', 221);
}

function smtpEncodeHeader(string $field, string $name, string $email): string
{
    $name = trim(preg_replace('/[\r\n]+/', ' ', $name));
    return $field . ': ' . ($name !== '' ? smtpEncodeWord($name) . ' ' : '') . '<' . $email . '>' . "\r\n";
}

function smtpEncodeWord(string $text): string
{
    $text = trim(preg_replace('/[\r\n]+/', ' ', $text));
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

/**
 * @param resource $socket
 */
function smtpCommand($socket, string $command, int|array $expectedCodes): string
{
    smtpWrite($socket, $command);
    $expected = is_array($expectedCodes) ? $expectedCodes : [$expectedCodes];
    return smtpReadResponse($socket, ...$expected);
}

/**
 * @param resource $socket
 */
function smtpWrite($socket, string $data): void
{
    fwrite($socket, $data . "\r\n");
}

/**
 * @param resource $socket
 */
/**
 * Notifica por correo un aviso recién publicado. Si $cursoId es null, se
 * notifica a todos los docentes y estudiantes activos del instituto; si
 * no, solo a los estudiantes matriculados activos de ese curso. No
 * interrumpe la publicación del aviso si el correo falla o no está
 * configurado: cada envío se intenta por separado.
 */
function notificarAviso(PDO $pdo, string $titulo, string $contenido, ?int $cursoId): void
{
    if (!smtpIsConfigured()) {
        return;
    }

    if ($cursoId === null) {
        $stmt = $pdo->query("SELECT nombre, apellidos, email FROM usuarios WHERE activo = 1 AND rol IN ('docente','estudiante')");
        $destinatarios = $stmt->fetchAll();
        $ambito = 'para todo el instituto';
    } else {
        $stmt = $pdo->prepare(
            "SELECT u.nombre, u.apellidos, u.email FROM usuarios u
             JOIN matriculas m ON m.estudiante_id = u.id
             WHERE m.curso_id = :curso_id AND m.estado = 'activo'"
        );
        $stmt->execute(['curso_id' => $cursoId]);
        $destinatarios = $stmt->fetchAll();
        $ambito = 'en tu curso';
    }

    if (!$destinatarios) {
        return;
    }

    // Publicar un aviso general puede notificar a todo el instituto; se
    // amplia el limite de ejecucion para no cortar el envio a mitad de
    // camino (cada correo abre su propia conexion SMTP).
    set_time_limit(max(120, count($destinatarios) * 2));

    foreach ($destinatarios as $d) {
        smtpSend(
            $d['email'], $d['nombre'] . ' ' . $d['apellidos'],
            'Nuevo aviso ' . $ambito . ': ' . $titulo,
            '<p>Hola ' . e($d['nombre']) . ',</p>'
            . '<p>Se publicó un nuevo aviso ' . $ambito . ':</p>'
            . '<p><strong>' . e($titulo) . '</strong></p>'
            . '<p>' . nl2br(e($contenido)) . '</p>'
            . '<p>Ingresa al Aula Virtual para más detalles.</p>'
        );
    }
}

function smtpReadResponse($socket, int ...$expectedCodes): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        // Las lineas intermedias de una respuesta multilinea usan "250-";
        // la ultima linea usa "250 " (espacio), ahi termina la respuesta.
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }
    if ($response === '') {
        throw new RuntimeException('El servidor SMTP cerró la conexión inesperadamente.');
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('Respuesta SMTP inesperada: ' . trim($response));
    }
    return $response;
}

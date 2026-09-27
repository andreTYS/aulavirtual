<?php
/**
 * Integracion con Google Calendar (OAuth2) para crear videollamadas de
 * Google Meet automaticamente al programar una sesion, usando la cuenta
 * educativa del docente. Sin dependencias externas: solo cURL + JSON.
 */

declare(strict_types=1);

const GOOGLE_OAUTH_SCOPE = 'https://www.googleapis.com/auth/calendar.events';
const GOOGLE_AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
const GOOGLE_CALENDAR_EVENTS_ENDPOINT = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

function googleIsConfigured(): bool
{
    return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
}

function googleAuthUrl(string $state): string
{
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => GOOGLE_OAUTH_SCOPE,
        'access_type' => 'offline',
        'prompt' => 'consent',
        'state' => $state,
    ];
    return GOOGLE_AUTH_ENDPOINT . '?' . http_build_query($params);
}

/**
 * Llamada HTTP generica a la API de Google. Devuelve el cuerpo decodificado
 * como arreglo asociativo, o null si la respuesta no fue exitosa (2xx).
 */
function googleHttpRequest(string $method, string $url, array $headers = [], ?string $body = null): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('google_calendar: error de conexion (' . $method . ' ' . $url . '): ' . $error);
        return null;
    }

    $decoded = json_decode($response, true);
    if ($status < 200 || $status >= 300) {
        error_log('google_calendar: respuesta ' . $status . ' de ' . $url . ': ' . substr($response, 0, 500));
        return null;
    }
    return is_array($decoded) ? $decoded : [];
}

function googleExchangeCodeForTokens(string $code): ?array
{
    $body = http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code',
    ]);
    return googleHttpRequest('POST', GOOGLE_TOKEN_ENDPOINT, ['Content-Type: application/x-www-form-urlencoded'], $body);
}

function googleRefreshAccessToken(string $refreshToken): ?array
{
    $body = http_build_query([
        'refresh_token' => $refreshToken,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'grant_type' => 'refresh_token',
    ]);
    return googleHttpRequest('POST', GOOGLE_TOKEN_ENDPOINT, ['Content-Type: application/x-www-form-urlencoded'], $body);
}

function googleSaveTokens(PDO $pdo, int $usuarioId, array $tokens, ?string $existingRefreshToken = null): void
{
    $refreshToken = $tokens['refresh_token'] ?? $existingRefreshToken;
    if (!$refreshToken) {
        // Google solo entrega refresh_token la primera vez que se autoriza
        // con prompt=consent; si no llega y tampoco habia uno guardado, no
        // se puede mantener la conexion activa.
        error_log('google_calendar: no se recibio refresh_token para usuario ' . $usuarioId);
        return;
    }
    $expiraEn = date('Y-m-d H:i:s', time() + (int) ($tokens['expires_in'] ?? 3600) - 60);
    $stmt = $pdo->prepare(
        'INSERT INTO google_tokens (usuario_id, access_token, refresh_token, expira_en, scope)
         VALUES (:usuario_id, :access_token, :refresh_token, :expira_en, :scope)
         ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token),
             expira_en = VALUES(expira_en), scope = VALUES(scope)'
    );
    $stmt->execute([
        'usuario_id' => $usuarioId,
        'access_token' => (string) ($tokens['access_token'] ?? ''),
        'refresh_token' => $refreshToken,
        'expira_en' => $expiraEn,
        'scope' => (string) ($tokens['scope'] ?? GOOGLE_OAUTH_SCOPE),
    ]);
}

function googleIsConnected(PDO $pdo, int $usuarioId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM google_tokens WHERE usuario_id = :id');
    $stmt->execute(['id' => $usuarioId]);
    return (bool) $stmt->fetchColumn();
}

function googleDisconnect(PDO $pdo, int $usuarioId): void
{
    $pdo->prepare('DELETE FROM google_tokens WHERE usuario_id = :id')->execute(['id' => $usuarioId]);
}

/**
 * Devuelve un access_token vigente para el usuario, renovandolo con el
 * refresh_token si ya vencio. Si la renovacion falla (token revocado por
 * el docente desde su cuenta de Google), se elimina la conexion guardada
 * para que el usuario deba reconectar.
 */
function googleGetValidAccessToken(PDO $pdo, int $usuarioId): ?string
{
    $stmt = $pdo->prepare('SELECT access_token, refresh_token, expira_en FROM google_tokens WHERE usuario_id = :id');
    $stmt->execute(['id' => $usuarioId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if (strtotime($row['expira_en']) > time()) {
        return $row['access_token'];
    }

    $tokens = googleRefreshAccessToken($row['refresh_token']);
    if (!$tokens || empty($tokens['access_token'])) {
        googleDisconnect($pdo, $usuarioId);
        return null;
    }

    googleSaveTokens($pdo, $usuarioId, $tokens, $row['refresh_token']);
    return $tokens['access_token'];
}

function googleDateTimeLima(string $fecha, string $hora, int $sumarMinutos = 0): string
{
    $ts = strtotime($fecha . ' ' . $hora) + ($sumarMinutos * 60);
    return date('Y-m-d\TH:i:s', $ts) . '-05:00';
}

/**
 * Crea un evento en el Google Calendar del docente con una videollamada
 * de Google Meet. Devuelve ['event_id' => ..., 'meet_link' => ...] o null
 * si la creacion fallo (token invalido, cuota, etc.).
 */
function googleCreateMeetEvent(PDO $pdo, int $docenteId, string $cursoNombre, array $sesion): ?array
{
    $accessToken = googleGetValidAccessToken($pdo, $docenteId);
    if (!$accessToken) {
        return null;
    }

    $payload = [
        'summary' => $cursoNombre . ' - ' . $sesion['tema'],
        'description' => 'Sesión generada automáticamente desde Aula Virtual IESTP Benjamín Franklin.',
        'start' => ['dateTime' => googleDateTimeLima($sesion['fecha'], $sesion['hora_inicio']), 'timeZone' => 'America/Lima'],
        'end' => ['dateTime' => googleDateTimeLima($sesion['fecha'], $sesion['hora_inicio'], (int) $sesion['duracion_min']), 'timeZone' => 'America/Lima'],
        'conferenceData' => [
            'createRequest' => [
                'requestId' => bin2hex(random_bytes(8)),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ],
        ],
    ];

    $result = googleHttpRequest(
        'POST',
        GOOGLE_CALENDAR_EVENTS_ENDPOINT . '?conferenceDataVersion=1',
        ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken],
        json_encode($payload)
    );

    if (!$result || empty($result['id'])) {
        return null;
    }

    return [
        'event_id' => $result['id'],
        'meet_link' => $result['hangoutLink'] ?? ($result['conferenceData']['entryPoints'][0]['uri'] ?? null),
    ];
}

/**
 * Actualiza fecha/hora/tema de un evento ya creado (por ejemplo, si el
 * docente reprograma la sesion). Devuelve true si se actualizo, false si
 * no se pudo (el evento seguira mostrando los datos anteriores).
 */
function googleUpdateMeetEvent(PDO $pdo, int $docenteId, string $eventId, string $cursoNombre, array $sesion): bool
{
    $accessToken = googleGetValidAccessToken($pdo, $docenteId);
    if (!$accessToken) {
        return false;
    }

    $payload = [
        'summary' => $cursoNombre . ' - ' . $sesion['tema'],
        'start' => ['dateTime' => googleDateTimeLima($sesion['fecha'], $sesion['hora_inicio']), 'timeZone' => 'America/Lima'],
        'end' => ['dateTime' => googleDateTimeLima($sesion['fecha'], $sesion['hora_inicio'], (int) $sesion['duracion_min']), 'timeZone' => 'America/Lima'],
    ];

    $result = googleHttpRequest(
        'PATCH',
        GOOGLE_CALENDAR_EVENTS_ENDPOINT . '/' . rawurlencode($eventId),
        ['Content-Type: application/json', 'Authorization: Bearer ' . $accessToken],
        json_encode($payload)
    );

    return $result !== null;
}

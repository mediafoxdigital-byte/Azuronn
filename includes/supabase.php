<?php

declare(strict_types=1);

function app_runtime_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $path = dirname(__DIR__) . '/data/runtime-config.php';
    if (!is_file($path)) {
        $config = [];
        return $config;
    }

    $loaded = require $path;
    $config = is_array($loaded) ? $loaded : [];
    return $config;
}

function app_runtime_config_value(string $key, string $default = ''): string
{
    $config = app_runtime_config();
    $value = $config[$key] ?? $default;
    return is_scalar($value) ? trim((string) $value) : $default;
}

function supabase_project_url(): string
{
    $url = trim((string) (
        app_runtime_config_value('supabase_url')
        ?: app_runtime_config_value('SUPABASE_URL')
        ?: 
        getenv('SUPABASE_URL')
        ?: getenv('SUPABASE_PROJECT_URL')
        ?: 'https://hlqaqoqqvbozxdmieaii.supabase.co'
    ));

    return rtrim($url, '/');
}

function supabase_publishable_key(): string
{
    return trim((string) (
        app_runtime_config_value('supabase_publishable_key')
        ?: app_runtime_config_value('SUPABASE_PUBLISHABLE_KEY')
        ?:
        getenv('SUPABASE_PUBLISHABLE_KEY')
        ?: getenv('SUPABASE_ANON_KEY')
        ?: 'sb_publishable_OvT73J4c3K_Ow472mUfLRQ_Upjidfdp'
    ));
}

function supabase_service_role_key(): string
{
    return trim((string) (
        app_runtime_config_value('supabase_service_role_key')
        ?: app_runtime_config_value('SUPABASE_SERVICE_ROLE_KEY')
        ?:
        getenv('SUPABASE_SERVICE_ROLE_KEY')
        ?: getenv('SUPABASE_SECRET_KEY')
        ?: ''
    ));
}

function supabase_key_for_request(bool $write = false): string
{
    $serviceKey = supabase_service_role_key();
    if ($write && $serviceKey !== '') {
        return $serviceKey;
    }

    return $serviceKey !== '' ? $serviceKey : supabase_publishable_key();
}

function supabase_enabled(): bool
{
    return supabase_project_url() !== '' && supabase_key_for_request() !== '';
}

function supabase_private_write_enabled(): bool
{
    return supabase_service_role_key() !== '';
}

function supabase_rest_headers(bool $write = false, bool $returnRepresentation = false, bool $mergeDuplicates = false): array
{
    $key = supabase_key_for_request($write);
    $headers = [
        'apikey: ' . $key,
        'Authorization: Bearer ' . $key,
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    if ($write) {
        $preferParts = [$returnRepresentation ? 'return=representation' : 'return=minimal'];
        if ($mergeDuplicates) {
            $preferParts[] = 'resolution=merge-duplicates';
        }
        $headers[] = 'Prefer: ' . implode(',', $preferParts);
    }

    return $headers;
}

function supabase_http_status(array $headers): int
{
    foreach ($headers as $header) {
        if (preg_match('~^HTTP/\S+\s+(\d{3})~i', $header, $matches)) {
            return (int) $matches[1];
        }
    }

    return 0;
}

function supabase_http_request(string $method, string $path, array $query = [], ?array $payload = null, bool $write = false, bool $returnRepresentation = false, bool $mergeDuplicates = false): array
{
    if (!supabase_enabled()) {
        return ['ok' => false, 'status' => 0, 'error' => 'Supabase is not configured.'];
    }

    $url = supabase_project_url() . $path;
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $options = [
        'http' => [
            'method' => strtoupper($method),
            'ignore_errors' => true,
            'timeout' => 20,
            'header' => implode("\r\n", supabase_rest_headers($write, $returnRepresentation, $mergeDuplicates)),
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ];

    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return ['ok' => false, 'status' => 0, 'error' => 'Unable to encode payload.'];
        }
        $options['http']['content'] = $json;
    }

    $context = stream_context_create($options);
    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = supabase_http_status($headers);
    $decoded = null;

    if (is_string($body) && trim($body) !== '') {
        $decoded = json_decode($body, true);
    }

    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'headers' => $headers,
        'body' => is_string($body) ? $body : '',
        'json' => is_array($decoded) ? $decoded : null,
        'error' => ($status >= 200 && $status < 300) ? '' : ((is_array($decoded) ? ($decoded['message'] ?? $decoded['hint'] ?? '') : '') ?: 'Supabase request failed.'),
    ];
}

function supabase_filter_query(array $filters): array
{
    $query = [];
    foreach ($filters as $column => $value) {
        if ($value === null) {
            $query[$column] = 'is.null';
            continue;
        }

        $query[$column] = 'eq.' . (string) $value;
    }

    return $query;
}

function supabase_select_rows(string $table, array $filters = [], string $columns = '*', array $extraQuery = []): array
{
    $query = array_merge(['select' => $columns], supabase_filter_query($filters), $extraQuery);
    $result = supabase_http_request('GET', '/rest/v1/' . rawurlencode($table), $query);
    if (!($result['ok'] ?? false) || !is_array($result['json'] ?? null)) {
        return [];
    }

    return array_values(array_filter($result['json'], 'is_array'));
}

function supabase_select_first(string $table, array $filters = [], string $columns = '*', array $extraQuery = []): ?array
{
    $rows = supabase_select_rows($table, $filters, $columns, array_merge($extraQuery, ['limit' => 1]));
    return $rows[0] ?? null;
}

function supabase_upsert_rows(string $table, array $rows, string $onConflict = ''): bool
{
    if ($rows === []) {
        return true;
    }

    $query = [];
    if ($onConflict !== '') {
        $query['on_conflict'] = $onConflict;
    }

    $result = supabase_http_request('POST', '/rest/v1/' . rawurlencode($table), $query, $rows, true, false, $onConflict !== '');
    return (bool) ($result['ok'] ?? false);
}

function supabase_delete_rows(string $table, array $filters): bool
{
    if ($filters === []) {
        return false;
    }

    $result = supabase_http_request('DELETE', '/rest/v1/' . rawurlencode($table), supabase_filter_query($filters), null, true);
    return (bool) ($result['ok'] ?? false);
}

function supabase_state_key(string $name): string
{
    return preg_replace('/[^a-z0-9_\-]/i', '_', trim($name)) ?: 'state';
}

function supabase_read_state(string $key): ?array
{
    $row = supabase_select_first('app_state', ['key' => supabase_state_key($key)], 'key,payload,updated_at');
    if ($row === null || !is_array($row['payload'] ?? null)) {
        return null;
    }

    return $row['payload'];
}

function supabase_write_state(string $key, array $payload): bool
{
    return supabase_upsert_rows('app_state', [[
        'key' => supabase_state_key($key),
        'payload' => $payload,
        'updated_at' => gmdate('c'),
    ]], 'key');
}

function supabase_register_media_asset(array $asset): bool
{
    $publicUrl = trim((string) ($asset['public_url'] ?? ''));
    if ($publicUrl === '') {
        return false;
    }

    return supabase_upsert_rows('media_assets', [[
        'public_url' => $publicUrl,
        'file_path' => trim((string) ($asset['file_path'] ?? '')),
        'file_name' => trim((string) ($asset['file_name'] ?? '')),
        'mime_type' => trim((string) ($asset['mime_type'] ?? '')),
        'media_type' => trim((string) ($asset['media_type'] ?? 'file')),
        'file_size' => max(0, (int) ($asset['file_size'] ?? 0)),
        'source' => trim((string) ($asset['source'] ?? 'hosting')),
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ]], 'public_url');
}

function supabase_health_check(): array
{
    $checks = [];

    $checks[] = [
        'name' => 'Supabase URL configured',
        'ok' => supabase_project_url() !== '',
        'detail' => supabase_project_url() !== '' ? supabase_project_url() : 'Missing SUPABASE_URL',
    ];

    $checks[] = [
        'name' => 'Publishable key configured',
        'ok' => supabase_publishable_key() !== '',
        'detail' => supabase_publishable_key() !== '' ? 'Present' : 'Missing SUPABASE_PUBLISHABLE_KEY',
    ];

    $checks[] = [
        'name' => 'Service role key configured',
        'ok' => supabase_private_write_enabled(),
        'detail' => supabase_private_write_enabled() ? 'Present' : 'Missing SUPABASE_SERVICE_ROLE_KEY',
    ];

    if (!supabase_enabled()) {
        return [
            'ok' => false,
            'checks' => $checks,
            'message' => 'Supabase is not configured.',
        ];
    }

    $stateRead = supabase_http_request('GET', '/rest/v1/app_state', ['select' => 'key', 'limit' => 1]);
    $checks[] = [
        'name' => 'app_state table reachable',
        'ok' => (bool) ($stateRead['ok'] ?? false),
        'detail' => (bool) ($stateRead['ok'] ?? false) ? 'Reachable' : ((string) ($stateRead['error'] ?? 'Request failed')),
    ];

    $cartRead = supabase_http_request('GET', '/rest/v1/cart_sessions', ['select' => 'session_key', 'limit' => 1]);
    $checks[] = [
        'name' => 'cart_sessions table reachable',
        'ok' => (bool) ($cartRead['ok'] ?? false),
        'detail' => (bool) ($cartRead['ok'] ?? false) ? 'Reachable' : ((string) ($cartRead['error'] ?? 'Request failed')),
    ];

    $mediaRead = supabase_http_request('GET', '/rest/v1/media_assets', ['select' => 'id', 'limit' => 1]);
    $checks[] = [
        'name' => 'media_assets table reachable',
        'ok' => (bool) ($mediaRead['ok'] ?? false),
        'detail' => (bool) ($mediaRead['ok'] ?? false) ? 'Reachable' : ((string) ($mediaRead['error'] ?? 'Request failed')),
    ];

    if (supabase_private_write_enabled()) {
        $tempStateKey = 'healthcheck_' . bin2hex(random_bytes(6));
        $stateWrite = supabase_write_state($tempStateKey, ['checked_at' => gmdate('c')]);
        $checks[] = [
            'name' => 'app_state write',
            'ok' => $stateWrite,
            'detail' => $stateWrite ? 'Write succeeded' : 'Write failed',
        ];

        $stateCleanup = supabase_delete_rows('app_state', ['key' => $tempStateKey]);
        $checks[] = [
            'name' => 'app_state cleanup',
            'ok' => $stateCleanup,
            'detail' => $stateCleanup ? 'Delete succeeded' : 'Delete failed',
        ];

        $tempCartKey = 'healthcheck_cart_' . bin2hex(random_bytes(6));
        $cartWrite = supabase_upsert_rows('cart_sessions', [[
            'session_key' => $tempCartKey,
            'customer_id' => null,
            'payload' => ['items' => [], 'coupon_code' => ''],
            'updated_at' => gmdate('c'),
        ]], 'session_key');
        $checks[] = [
            'name' => 'cart_sessions write',
            'ok' => $cartWrite,
            'detail' => $cartWrite ? 'Write succeeded' : 'Write failed',
        ];

        $cartCleanup = supabase_delete_rows('cart_sessions', ['session_key' => $tempCartKey]);
        $checks[] = [
            'name' => 'cart_sessions cleanup',
            'ok' => $cartCleanup,
            'detail' => $cartCleanup ? 'Delete succeeded' : 'Delete failed',
        ];
    }

    $allOk = count(array_filter($checks, static fn (array $check): bool => !($check['ok'] ?? false))) === 0;

    return [
        'ok' => $allOk,
        'checks' => $checks,
        'message' => $allOk ? 'Supabase health check passed.' : 'Supabase health check found issues.',
    ];
}

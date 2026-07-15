<?php

declare(strict_types=1);

function fs_config(?string $key = null): mixed
{
    static $config;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config/freshsplash.php';
    }

    if ($key === null) {
        return $config;
    }

    if (!array_key_exists($key, $config)) {
        throw new InvalidArgumentException("Unknown configuration key: {$key}");
    }

    return $config[$key];
}

function fs_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('freshsplash');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => fs_base_path() ?: '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function fs_base_path(): string
{
    $basePath = (string) fs_config('base_path');
    return $basePath === '/' ? '' : rtrim($basePath, '/');
}

function fs_url(string $path = '', bool $absolute = false): string
{
    $path = '/' . ltrim($path, '/');
    $relative = fs_base_path() . ($path === '/' ? '/' : $path);

    if (!$absolute) {
        return $relative;
    }

    $configured = (string) fs_config('base_url');
    if ($configured !== '') {
        return $configured . ($path === '/' ? '/' : $path);
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . $relative;
}

function fs_escape(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fs_json(mixed $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function fs_security_headers(): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
}

function fs_csrf_token(): string
{
    fs_start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['csrf'];
}

function fs_require_csrf(?string $token): void
{
    $expected = fs_csrf_token();
    if ($token === null || !hash_equals($expected, $token)) {
        throw new RuntimeException('The form expired. Refresh the page and try again.');
    }
}

function fs_token_from_request(): string
{
    $header = $_SERVER['HTTP_X_FRESHSPLASH_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    return is_string($_POST['token'] ?? null) ? (string) $_POST['token'] : '';
}

function fs_require_upload_access(): void
{
    $expected = (string) fs_config('upload_token');
    if ($expected === '') {
        return;
    }

    $provided = fs_token_from_request();
    if ($provided === '' || !hash_equals($expected, $provided)) {
        throw new RuntimeException('The upload token is missing or incorrect.');
    }
}

function fs_admin_authenticated(): bool
{
    fs_start_session();
    $expected = (string) fs_config('admin_token');
    if ($expected === '' || empty($_SESSION['freshsplash_admin'])) {
        return false;
    }

    return hash_equals(hash('sha256', $expected), (string) $_SESSION['freshsplash_admin']);
}

function fs_text_limit(mixed $value, int $max): string
{
    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max, 'UTF-8');
    }
    return substr($value, 0, $max);
}

function fs_flash(string $type, string $message): void
{
    fs_start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function fs_take_flashes(): array
{
    fs_start_session();
    $flashes = is_array($_SESSION['flash'] ?? null) ? $_SESSION['flash'] : [];
    unset($_SESSION['flash']);
    return $flashes;
}

function fs_human_bytes(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / 1024 / 1024, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }
    return $bytes . ' B';
}

<?php

declare(strict_types=1);

$env = static function (string $name, mixed $default = null): mixed {
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
};

$root = dirname(__DIR__);
$maxUploadMb = max(1, (int) $env('FRESHSPLASH_MAX_UPLOAD_MB', 15));

$config = [
    'name' => (string) $env('FRESHSPLASH_NAME', 'FreshSplash'),
    'tagline' => (string) $env('FRESHSPLASH_TAGLINE', 'A tiny wallpaper community for friends.'),
    'base_url' => rtrim((string) $env('FRESHSPLASH_BASE_URL', ''), '/'),
    'base_path' => '/' . trim((string) $env('FRESHSPLASH_BASE_PATH', ''), '/'),
    'upload_token' => (string) $env('FRESHSPLASH_UPLOAD_TOKEN', ''),
    'admin_token' => (string) $env('FRESHSPLASH_ADMIN_TOKEN', ''),
    'storage_driver' => strtolower((string) $env('FRESHSPLASH_STORAGE_DRIVER', 'auto')),
    'max_upload_bytes' => $maxUploadMb * 1024 * 1024,
    'min_width' => max(1, (int) $env('FRESHSPLASH_MIN_WIDTH', 1280)),
    'min_height' => max(1, (int) $env('FRESHSPLASH_MIN_HEIGHT', 720)),
    'max_pixels' => max(1, (int) $env('FRESHSPLASH_MAX_PIXELS', 40000000)),
    'jpeg_quality' => min(100, max(60, (int) $env('FRESHSPLASH_JPEG_QUALITY', 90))),
    'webp_quality' => min(100, max(60, (int) $env('FRESHSPLASH_WEBP_QUALITY', 88))),
    'root_path' => $root,
    'public_path' => $root . '/public',
    'upload_path' => $root . '/public/uploads',
    'var_path' => $root . '/var',
];

$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    $overrides = require $localConfig;
    if (!is_array($overrides)) {
        throw new RuntimeException('config/local.php must return an array.');
    }
    $config = array_replace($config, $overrides);
}

return $config;

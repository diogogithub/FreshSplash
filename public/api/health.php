<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

try {
    $repository = fs_repository();
    fs_json([
        'status' => 'ok',
        'name' => fs_config('name'),
        'wallpapers' => $repository->count(),
        'storage' => $repository->driver(),
        'gd' => extension_loaded('gd'),
        'time' => gmdate('c'),
    ]);
} catch (Throwable $throwable) {
    fs_json(['status' => 'error', 'error' => $throwable->getMessage()], 503);
}

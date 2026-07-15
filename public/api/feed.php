<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$limit = max(1, min(100, (int) ($_GET['limit'] ?? 30)));
$items = array_map('fs_wallpaper_payload', fs_repository()->latest($limit));
fs_json([
    'name' => fs_config('name'),
    'count' => fs_repository()->count(),
    'wallpapers' => $items,
]);

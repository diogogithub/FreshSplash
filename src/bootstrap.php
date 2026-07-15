<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/WallpaperRepository.php';
require_once __DIR__ . '/ImageStore.php';

fs_security_headers();

function fs_repository(): WallpaperRepository
{
    static $repository;
    return $repository ??= new WallpaperRepository();
}

function fs_image_store(): ImageStore
{
    static $store;
    return $store ??= new ImageStore(fs_repository());
}

function fs_wallpaper_payload(array $wallpaper): array
{
    return [
        'id' => $wallpaper['id'],
        'caption' => $wallpaper['caption'],
        'author' => $wallpaper['author'],
        'width' => (int) $wallpaper['width'],
        'height' => (int) $wallpaper['height'],
        'mime_type' => $wallpaper['mime_type'],
        'size_bytes' => (int) $wallpaper['size_bytes'],
        'sha256' => $wallpaper['sha256'],
        'created_at' => $wallpaper['created_at'],
        'downloads' => (int) ($wallpaper['downloads'] ?? 0),
        'image_url' => fs_url('api/wallpaper.php?id=' . rawurlencode((string) $wallpaper['id']) . '&format=image', true),
    ];
}

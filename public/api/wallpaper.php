<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$repository = fs_repository();
$id = is_string($_GET['id'] ?? null) ? (string) $_GET['id'] : '';
$mode = is_string($_GET['mode'] ?? null) ? (string) $_GET['mode'] : 'random';
$format = is_string($_GET['format'] ?? null) ? strtolower((string) $_GET['format']) : 'image';
$wallpaper = $id !== '' ? $repository->findById($id) : $repository->select($mode);

if ($wallpaper === null) {
    fs_json(['error' => 'No wallpapers are available yet.'], 404);
}

if ($format === 'json') {
    fs_json(['wallpaper' => fs_wallpaper_payload($wallpaper)]);
}

if ($format !== 'image') {
    fs_json(['error' => 'format must be image or json.'], 400);
}

$absolute = realpath((string) fs_config('public_path') . '/' . $wallpaper['filename']);
$uploadRoot = realpath((string) fs_config('upload_path'));
if ($absolute === false || $uploadRoot === false || !str_starts_with($absolute, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($absolute)) {
    fs_json(['error' => 'The wallpaper file is missing.'], 410);
}

$etag = '"' . $wallpaper['sha256'] . '"';
if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

$repository->incrementDownloads((string) $wallpaper['id']);
header_remove('Content-Security-Policy');
header('Content-Type: ' . $wallpaper['mime_type']);
header('Content-Length: ' . (string) filesize($absolute));
header('Content-Disposition: inline; filename="freshsplash-' . $wallpaper['id'] . '.' . pathinfo($absolute, PATHINFO_EXTENSION) . '"');
header('Cache-Control: public, max-age=3600');
header('ETag: ' . $etag);
readfile($absolute);

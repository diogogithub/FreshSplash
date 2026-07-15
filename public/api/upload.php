<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fs_json(['error' => 'POST a multipart image field to this endpoint.'], 405);
}

try {
    fs_require_upload_access();
    $result = fs_image_store()->accept(
        is_array($_FILES['image'] ?? null) ? $_FILES['image'] : [],
        (string) ($_POST['caption'] ?? ''),
        (string) ($_POST['author'] ?? '')
    );
    fs_json([
        'created' => $result['created'],
        'wallpaper' => fs_wallpaper_payload($result['record']),
    ], $result['created'] ? 201 : 200);
} catch (Throwable $throwable) {
    fs_json(['error' => $throwable->getMessage()], 422);
}

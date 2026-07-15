<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
fs_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . fs_url());
    exit;
}

try {
    fs_require_csrf(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null);
    if ((string) ($_POST['website'] ?? '') !== '') {
        throw new RuntimeException('The upload was rejected.');
    }
    fs_require_upload_access();
    $result = fs_image_store()->accept(
        is_array($_FILES['image'] ?? null) ? $_FILES['image'] : [],
        (string) ($_POST['caption'] ?? ''),
        (string) ($_POST['author'] ?? '')
    );
    fs_flash('success', $result['created'] ? 'Wallpaper added to the splash pool.' : 'That exact wallpaper is already in the pool.');
} catch (Throwable $throwable) {
    fs_flash('error', $throwable->getMessage());
}

header('Location: ' . fs_url() . '#share');
exit;

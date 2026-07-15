<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
fs_start_session();

$enabled = (string) fs_config('admin_token') !== '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        fs_require_csrf(is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : null);
        $action = (string) ($_POST['action'] ?? 'login');
        if ($action === 'login') {
            $provided = (string) ($_POST['admin_token'] ?? '');
            $expected = (string) fs_config('admin_token');
            if ($expected === '' || !hash_equals($expected, $provided)) {
                throw new RuntimeException('The admin token is incorrect.');
            }
            session_regenerate_id(true);
            $_SESSION['freshsplash_admin'] = hash('sha256', $expected);
            header('Location: ' . fs_url('admin.php'));
            exit;
        }
        if ($action === 'logout') {
            unset($_SESSION['freshsplash_admin']);
            header('Location: ' . fs_url('admin.php'));
            exit;
        }
        if ($action === 'delete') {
            if (!fs_admin_authenticated()) {
                throw new RuntimeException('Admin authentication is required.');
            }
            $id = (string) ($_POST['id'] ?? '');
            $record = fs_repository()->delete($id);
            if ($record !== null) {
                fs_image_store()->removeFiles($record);
            }
            fs_flash('success', $record === null ? 'Wallpaper not found.' : 'Wallpaper removed.');
            header('Location: ' . fs_url('admin.php'));
            exit;
        }
    } catch (Throwable $throwable) {
        $error = $throwable->getMessage();
    }
}

$authenticated = fs_admin_authenticated();
$wallpapers = $authenticated ? fs_repository()->latest(100) : [];
$flashes = fs_take_flashes();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin · <?= fs_escape((string) fs_config('name')) ?></title>
  <link rel="icon" href="<?= fs_escape(fs_url('assets/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= fs_escape(fs_url('assets/app.css')) ?>">
  <script src="<?= fs_escape(fs_url('assets/app.js')) ?>" defer></script>
</head>
<body>
<header class="shell site-header"><a class="brand" href="<?= fs_escape(fs_url()) ?>"><img src="<?= fs_escape(fs_url('assets/logo.svg')) ?>" alt=""><span><?= fs_escape((string) fs_config('name')) ?></span></a><nav class="nav"><a href="<?= fs_escape(fs_url()) ?>">Back to gallery</a></nav></header>
<main class="shell">
  <?php foreach ($flashes as $flash): ?><div class="flash <?= fs_escape((string) $flash['type']) ?>"><?= fs_escape((string) $flash['message']) ?></div><?php endforeach; ?>
  <?php if ($error !== ''): ?><div class="flash error"><?= fs_escape($error) ?></div><?php endif; ?>
  <section class="panel">
    <h1 class="admin-title">Server admin</h1>
    <?php if (!$enabled): ?>
      <p>Admin access is disabled. Set <code>FRESHSPLASH_ADMIN_TOKEN</code> to enable moderation.</p>
    <?php elseif (!$authenticated): ?>
      <form method="post" class="upload-box admin-login">
        <input type="hidden" name="csrf" value="<?= fs_escape(fs_csrf_token()) ?>"><input type="hidden" name="action" value="login">
        <div class="field"><label for="admin-token">Admin token</label><input id="admin-token" name="admin_token" type="password" required autofocus></div>
        <button class="button" type="submit">Sign in</button>
      </form>
    <?php else: ?>
      <form method="post" class="admin-logout"><input type="hidden" name="csrf" value="<?= fs_escape(fs_csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button class="button secondary small">Sign out</button></form>
      <div class="admin-grid">
        <?php foreach ($wallpapers as $wallpaper): $preview = $wallpaper['thumbnail'] ?: $wallpaper['filename']; ?>
          <article class="admin-card"><img src="<?= fs_escape(fs_url((string) $preview)) ?>" alt=""><div><strong><?= fs_escape((string) ($wallpaper['caption'] ?: 'Untitled splash')) ?></strong><p class="help"><?= fs_escape((string) ($wallpaper['author'] ?: 'anonymous')) ?> · <?= (int) $wallpaper['width'] ?>×<?= (int) $wallpaper['height'] ?> · <?= (int) ($wallpaper['downloads'] ?? 0) ?> selections</p><form method="post" data-confirm="Remove this wallpaper?"><input type="hidden" name="csrf" value="<?= fs_escape(fs_csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= fs_escape((string) $wallpaper['id']) ?>"><button class="button danger small" type="submit">Remove</button></form></div></article>
        <?php endforeach; ?>
        <?php if ($wallpapers === []): ?><div class="empty">Nothing to moderate.</div><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>

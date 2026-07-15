<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';
fs_start_session();

$repository = fs_repository();
$wallpapers = $repository->latest(24);
$count = $repository->count();
$downloads = array_sum(array_map(static fn (array $item): int => (int) ($item['downloads'] ?? 0), $wallpapers));
$uploadOpen = (string) fs_config('upload_token') === '';
$flashes = fs_take_flashes();
$apiUrl = fs_url('api/wallpaper.php?format=image&mode=random', true);
$clientCommand = 'freshsplash --server ' . escapeshellarg(rtrim(fs_url('', true), '/')) . ' --set auto';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= fs_escape((string) fs_config('tagline')) ?>">
  <title><?= fs_escape((string) fs_config('name')) ?></title>
  <link rel="icon" href="<?= fs_escape(fs_url('assets/logo.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= fs_escape(fs_url('assets/app.css')) ?>">
  <script src="<?= fs_escape(fs_url('assets/app.js')) ?>" defer></script>
</head>
<body>
<header class="shell site-header">
  <a class="brand" href="<?= fs_escape(fs_url()) ?>"><img src="<?= fs_escape(fs_url('assets/logo.svg')) ?>" alt=""><span><?= fs_escape((string) fs_config('name')) ?></span></a>
  <nav class="nav" aria-label="Primary">
    <a href="#share">Share</a>
    <a href="#gallery">Gallery</a>
    <a href="<?= fs_escape(fs_url('api/feed.php')) ?>">JSON feed</a>
    <?php if ((string) fs_config('admin_token') !== ''): ?><a href="<?= fs_escape(fs_url('admin.php')) ?>">Admin</a><?php endif; ?>
  </nav>
</header>

<main class="shell">
  <?php foreach ($flashes as $flash): ?>
    <div class="flash <?= fs_escape((string) ($flash['type'] ?? '')) ?>" role="status"><?= fs_escape((string) ($flash['message'] ?? '')) ?></div>
  <?php endforeach; ?>

  <section class="hero">
    <p class="eyebrow">Fresh art · friendly surprises · your desktop</p>
    <h1>A wallpaper feed made by your people.</h1>
    <p class="lede"><?= fs_escape((string) fs_config('tagline')) ?> Share landscapes, photos, drawings, or gloriously questionable edits—then let FreshSplash surprise everyone’s desktop.</p>
    <div class="hero-actions">
      <a class="button" href="#share">Add a wallpaper</a>
      <a class="button secondary" href="<?= fs_escape($apiUrl) ?>">Get a random splash</a>
    </div>
  </section>

  <section class="stats" aria-label="Server statistics">
    <div class="stat"><strong><?= $count ?></strong><span>shared wallpapers</span></div>
    <div class="stat"><strong><?= $downloads ?></strong><span>recent selections</span></div>
    <div class="stat"><strong><?= fs_escape(strtoupper($repository->driver())) ?></strong><span>metadata storage</span></div>
  </section>

  <section id="share" class="layout">
    <div class="panel">
      <h2>Share the next surprise</h2>
      <p>JPEG, PNG, or WebP. Landscape only, at least <?= (int) fs_config('min_width') ?> × <?= (int) fs_config('min_height') ?>.</p>
      <form class="upload-box" action="<?= fs_escape(fs_url('upload.php')) ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= fs_escape(fs_csrf_token()) ?>">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" hidden aria-hidden="true">
        <label class="dropzone" data-dropzone>
          <span>
            <img class="preview" data-preview alt="Selected wallpaper preview" hidden>
            <strong>Drop an image here, or choose one</strong>
            <span data-file-label>Up to <?= fs_escape(fs_human_bytes((int) fs_config('max_upload_bytes'))) ?>. Metadata is stripped when GD is available.</span>
          </span>
          <input data-image-input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
        </label>
        <div class="form-grid">
          <div class="field"><label for="author">Your name <span class="help">(optional)</span></label><input id="author" name="author" maxlength="80" autocomplete="name"></div>
          <?php if (!$uploadOpen): ?><div class="field"><label for="token">Upload token</label><input id="token" name="token" type="password" required autocomplete="off"></div><?php endif; ?>
          <div class="field full"><label for="caption">Caption <span class="help">(optional)</span></label><textarea id="caption" name="caption" maxlength="160" placeholder="A tiny bit of context, or a very bad joke."></textarea></div>
        </div>
        <button class="button" type="submit">Splash it</button>
      </form>
    </div>

    <aside class="panel">
      <h2>Connect a desktop</h2>
      <p>The image endpoint returns a fresh wallpaper directly, so it works with the included client, curl, timers, or your own scripts.</p>
      <div class="codebox"><code id="api-url"><?= fs_escape($apiUrl) ?></code><button class="copy" type="button" data-copy="#api-url">Copy</button></div>
      <p class="help">Install the client from this repository, then run:</p>
      <div class="codebox"><code id="client-command"><?= fs_escape($clientCommand) ?></code><button class="copy" type="button" data-copy="#client-command">Copy</button></div>
      <p class="help">The client writes atomically and can update GNOME, Cinnamon, MATE, XFCE, or KDE Plasma automatically.</p>
    </aside>
  </section>

  <div id="gallery" class="section-head"><div><p class="eyebrow">The current pool</p><h2>Recent splashes</h2></div><p><?= $count === 0 ? 'Be the first one.' : 'Random means random.' ?></p></div>
  <section class="gallery" aria-label="Wallpaper gallery">
    <?php if ($wallpapers === []): ?>
      <div class="empty"><strong>No wallpapers yet.</strong><br>Upload the first surprise to bring the server to life.</div>
    <?php endif; ?>
    <?php foreach ($wallpapers as $wallpaper):
      $preview = $wallpaper['thumbnail'] ?: $wallpaper['filename'];
      $title = $wallpaper['caption'] !== '' ? $wallpaper['caption'] : 'Untitled splash';
    ?>
      <article class="card">
        <a href="<?= fs_escape(fs_url('api/wallpaper.php?id=' . rawurlencode((string) $wallpaper['id']) . '&format=image')) ?>"><img loading="lazy" src="<?= fs_escape(fs_url((string) $preview)) ?>" alt="<?= fs_escape((string) $title) ?>"></a>
        <div class="card-body">
          <p class="card-title"><?= fs_escape((string) $title) ?></p>
          <div class="card-meta"><span><?= $wallpaper['author'] !== '' ? 'by ' . fs_escape((string) $wallpaper['author']) : 'anonymous' ?></span><span><?= (int) $wallpaper['width'] ?>×<?= (int) $wallpaper['height'] ?></span></div>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</main>

<footer class="site-footer"><div class="shell"><span>FreshSplash is small on purpose.</span><span>MIT-licensed code · uploaded images keep their own rights.</span></div></footer>
</body>
</html>

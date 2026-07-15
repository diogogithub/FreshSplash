# FreshSplash

FreshSplash is a tiny, self-hosted wallpaper community. Friends can upload landscape images to a shared pool, and desktops periodically pick a random one. The result is part photo feed, part inside joke, and part very low-stakes chaos.

This refresh replaces the original proof of concept with a deployable PHP application and a robust GNU/Linux client.

## What is included

- responsive upload dashboard and gallery;
- JPEG, PNG, and WebP validation using file contents rather than filenames;
- minimum resolution, landscape, file-size, and pixel-count limits;
- duplicate detection through SHA-256;
- metadata stripping, EXIF orientation, normalization, and thumbnails when PHP GD is available;
- SQLite metadata storage with an automatic locked-JSON fallback;
- optional shared upload token and separate moderation token;
- random, latest, and oldest wallpaper modes;
- direct-image API, JSON metadata API, upload API, feed, and health endpoint;
- an atomic POSIX-shell desktop client;
- optional GNOME, Cinnamon, MATE, XFCE, and KDE Plasma wallpaper setters;
- a systemd user timer;
- Docker Compose deployment and shared-hosting instructions.

## Fastest deployment: Docker Compose

Requirements: Docker with the Compose plugin.

```sh
cp .env.example .env
# Edit .env and replace both example tokens.
docker compose up -d --build
```

Open `http://localhost:8080` by default. The Compose file keeps images and metadata in named volumes and restarts the service automatically.

Generate suitable tokens with:

```sh
openssl rand -hex 32
```

Set `FRESHSPLASH_UPLOAD_TOKEN` to an empty value only when intentionally allowing public uploads. Read access remains public because the desktop clients need to retrieve wallpapers without an account.

## Traditional PHP deployment

Requirements:

- PHP 8.2 or newer;
- `fileinfo`;
- write access to `var/` and `public/uploads/`;
- PHP `upload_max_filesize` and `post_max_size` values large enough for the configured limit;
- recommended: `pdo_sqlite`, `gd`, and `exif`.

Point the web server document root at `public/`. Copy `config/local.php.example` to `config/local.php` and edit it, or set the environment variables shown in `.env.example`.

Without `pdo_sqlite`, FreshSplash automatically uses a locked JSON metadata file. Without GD, uploads still work, but images are retained as uploaded and the original file is used in the gallery; metadata stripping and thumbnail generation require GD.

Example Nginx location:

```nginx
server {
    listen 80;
    server_name wallpapers.example.com;
    root /srv/FreshSplash/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

For a subdirectory deployment, set both `base_url` and `base_path`, for example `https://example.com/freshsplash` and `/freshsplash`.

## Desktop client

The simplest use keeps the desktop configured to read one stable file:

```sh
./bin/freshsplash \
  --server https://wallpapers.example.com \
  --output "$HOME/.local/share/backgrounds/freshsplash.jpg"
```

FreshSplash downloads to a temporary file, rejects HTTP errors and empty responses, validates the MIME type when `file` is installed, and then atomically replaces the destination.

To also update the running desktop:

```sh
./bin/freshsplash --server https://wallpapers.example.com --set auto
```

Install the client and user-level systemd timer:

```sh
./bin/install-client
$EDITOR "$HOME/.config/freshsplash/client.env"
systemctl --user enable --now freshsplash.timer
```

The timer refreshes after login and then roughly every six hours. Edit `systemd/freshsplash.timer` before installation to use another cadence.

## API

Return a random image directly:

```text
GET /api/wallpaper.php?format=image&mode=random
```

Return JSON metadata:

```text
GET /api/wallpaper.php?format=json&mode=latest
```

Fetch a specific image:

```text
GET /api/wallpaper.php?id=IMAGE_ID&format=image
```

List recent images:

```text
GET /api/feed.php?limit=30
```

Upload programmatically:

```sh
curl --fail \
  -H "X-FreshSplash-Token: YOUR_UPLOAD_TOKEN" \
  -F "image=@wallpaper.jpg" \
  -F "author=Diogo" \
  -F "caption=Definitely a serious desktop background" \
  https://wallpapers.example.com/api/upload.php
```

Health check:

```text
GET /api/health.php
```

## Security and operations

Use long, unrelated values for the upload and admin tokens. Keep the upload token among the intended contributors; a public upload endpoint can be abused. Put the service behind HTTPS, retain backups of the `var` and `public/uploads` volumes, and set reverse-proxy request limits that agree with `FRESHSPLASH_MAX_UPLOAD_MB`.

Uploads are checked by MIME signature and image decoder, constrained by file size and pixel count, assigned random server-side names, and never executed. The included Docker image re-encodes uploads, which also strips embedded metadata.

The application intentionally has no accounts, comments, likes, notifications, tracking, or third-party assets.

## Development checks

```sh
find . -name '*.php' -print0 | xargs -0 -n1 php -l
sh -n bin/freshsplash bin/install-client docker/entrypoint.sh
php tests/run.php
```

## History

The original FreshSplash experiment imagined an “indie Spotlight”: a server shared with friends so their photos and artwork could unexpectedly appear in day-to-day desktop life. This version keeps that playful idea while making the server safe enough to deploy and the client dependable enough to leave running.

The separate `spotlight-wallpaper` project remains the Microsoft Spotlight downloader. FreshSplash is the community-hosted counterpart.

## Licence

MIT. See `LICENSE`. Uploaded images are not relicensed by FreshSplash and remain subject to their respective rights.

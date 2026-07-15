<?php

declare(strict_types=1);

final class WallpaperRepository
{
    private ?PDO $pdo = null;
    private string $jsonPath;
    private string $driver;

    public function __construct()
    {
        $varPath = (string) fs_config('var_path');
        if (!is_dir($varPath) && !mkdir($varPath, 0775, true) && !is_dir($varPath)) {
            throw new RuntimeException('Unable to create the data directory.');
        }

        $requested = (string) fs_config('storage_driver');
        $sqliteAvailable = extension_loaded('pdo_sqlite');
        $this->driver = match ($requested) {
            'sqlite' => $sqliteAvailable ? 'sqlite' : throw new RuntimeException('pdo_sqlite is required by the configured storage driver.'),
            'json' => 'json',
            'auto' => $sqliteAvailable ? 'sqlite' : 'json',
            default => throw new RuntimeException('FRESHSPLASH_STORAGE_DRIVER must be auto, sqlite, or json.'),
        };

        $this->jsonPath = $varPath . '/wallpapers.json';

        if ($this->driver === 'sqlite') {
            $this->pdo = new PDO('sqlite:' . $varPath . '/freshsplash.sqlite', null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->pdo->exec('PRAGMA journal_mode = WAL');
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS wallpapers (
                    id TEXT PRIMARY KEY,
                    filename TEXT NOT NULL,
                    thumbnail TEXT,
                    original_name TEXT NOT NULL,
                    mime_type TEXT NOT NULL,
                    width INTEGER NOT NULL,
                    height INTEGER NOT NULL,
                    size_bytes INTEGER NOT NULL,
                    sha256 TEXT NOT NULL UNIQUE,
                    caption TEXT NOT NULL DEFAULT "",
                    author TEXT NOT NULL DEFAULT "",
                    created_at TEXT NOT NULL,
                    downloads INTEGER NOT NULL DEFAULT 0
                )'
            );
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS wallpapers_created_at ON wallpapers(created_at DESC)');
        } elseif (!is_file($this->jsonPath)) {
            file_put_contents($this->jsonPath, "[]\n", LOCK_EX);
        }
    }

    public function driver(): string
    {
        return $this->driver;
    }

    public function count(): int
    {
        if ($this->pdo !== null) {
            return (int) $this->pdo->query('SELECT COUNT(*) FROM wallpapers')->fetchColumn();
        }
        return count($this->readJson());
    }

    public function latest(int $limit = 24): array
    {
        $limit = max(1, min(100, $limit));
        if ($this->pdo !== null) {
            $statement = $this->pdo->prepare('SELECT * FROM wallpapers ORDER BY created_at DESC LIMIT :limit');
            $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
            $statement->execute();
            return $statement->fetchAll();
        }

        $items = $this->readJson();
        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
        return array_slice($items, 0, $limit);
    }

    public function findById(string $id): ?array
    {
        if ($this->pdo !== null) {
            $statement = $this->pdo->prepare('SELECT * FROM wallpapers WHERE id = :id LIMIT 1');
            $statement->execute(['id' => $id]);
            $result = $statement->fetch();
            return $result === false ? null : $result;
        }

        foreach ($this->readJson() as $item) {
            if (($item['id'] ?? null) === $id) {
                return $item;
            }
        }
        return null;
    }

    public function findByHash(string $sha256): ?array
    {
        if ($this->pdo !== null) {
            $statement = $this->pdo->prepare('SELECT * FROM wallpapers WHERE sha256 = :sha256 LIMIT 1');
            $statement->execute(['sha256' => $sha256]);
            $result = $statement->fetch();
            return $result === false ? null : $result;
        }

        foreach ($this->readJson() as $item) {
            if (($item['sha256'] ?? null) === $sha256) {
                return $item;
            }
        }
        return null;
    }

    public function select(string $mode): ?array
    {
        $mode = in_array($mode, ['random', 'latest', 'oldest'], true) ? $mode : 'random';

        if ($this->pdo !== null) {
            $order = match ($mode) {
                'latest' => 'created_at DESC',
                'oldest' => 'created_at ASC',
                default => 'RANDOM()',
            };
            $result = $this->pdo->query("SELECT * FROM wallpapers ORDER BY {$order} LIMIT 1")->fetch();
            return $result === false ? null : $result;
        }

        $items = $this->readJson();
        if ($items === []) {
            return null;
        }
        if ($mode === 'random') {
            return $items[random_int(0, count($items) - 1)];
        }
        usort($items, static fn (array $a, array $b): int => strcmp((string) $a['created_at'], (string) $b['created_at']));
        return $mode === 'latest' ? $items[array_key_last($items)] : $items[0];
    }

    /** @return array{created: bool, record: array} */
    public function insert(array $record): array
    {
        if ($this->pdo !== null) {
            try {
                $statement = $this->pdo->prepare(
                    'INSERT INTO wallpapers
                    (id, filename, thumbnail, original_name, mime_type, width, height, size_bytes, sha256, caption, author, created_at, downloads)
                    VALUES
                    (:id, :filename, :thumbnail, :original_name, :mime_type, :width, :height, :size_bytes, :sha256, :caption, :author, :created_at, 0)'
                );
                $statement->execute($record);
                $record['downloads'] = 0;
                return ['created' => true, 'record' => $record];
            } catch (PDOException $exception) {
                if ((string) $exception->getCode() !== '23000') {
                    throw $exception;
                }
                $existing = $this->findByHash((string) $record['sha256']);
                if ($existing === null) {
                    throw $exception;
                }
                return ['created' => false, 'record' => $existing];
            }
        }

        return $this->mutateJson(static function (array &$items) use ($record): array {
            foreach ($items as $item) {
                if (($item['sha256'] ?? null) === $record['sha256']) {
                    return ['created' => false, 'record' => $item];
                }
            }
            $record['downloads'] = 0;
            $items[] = $record;
            return ['created' => true, 'record' => $record];
        });
    }

    public function incrementDownloads(string $id): void
    {
        if ($this->pdo !== null) {
            $statement = $this->pdo->prepare('UPDATE wallpapers SET downloads = downloads + 1 WHERE id = :id');
            $statement->execute(['id' => $id]);
            return;
        }

        $this->mutateJson(static function (array &$items) use ($id): null {
            foreach ($items as &$item) {
                if (($item['id'] ?? null) === $id) {
                    $item['downloads'] = (int) ($item['downloads'] ?? 0) + 1;
                    break;
                }
            }
            unset($item);
            return null;
        });
    }

    public function delete(string $id): ?array
    {
        if ($this->pdo !== null) {
            $record = $this->findById($id);
            if ($record === null) {
                return null;
            }
            $statement = $this->pdo->prepare('DELETE FROM wallpapers WHERE id = :id');
            $statement->execute(['id' => $id]);
            return $record;
        }

        return $this->mutateJson(static function (array &$items) use ($id): ?array {
            foreach ($items as $index => $item) {
                if (($item['id'] ?? null) === $id) {
                    array_splice($items, $index, 1);
                    return $item;
                }
            }
            return null;
        });
    }

    private function readJson(): array
    {
        $handle = fopen($this->jsonPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the JSON data file.');
        }
        try {
            if (!flock($handle, LOCK_SH)) {
                throw new RuntimeException('Unable to lock the JSON data file.');
            }
            rewind($handle);
            $contents = stream_get_contents($handle);
            $items = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            return is_array($items) ? $items : [];
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function mutateJson(callable $callback): mixed
    {
        $handle = fopen($this->jsonPath, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open the JSON data file.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock the JSON data file.');
            }
            rewind($handle);
            $contents = stream_get_contents($handle);
            $items = $contents === false || trim($contents) === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($items)) {
                $items = [];
            }
            $result = $callback($items);
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
            fflush($handle);
            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}

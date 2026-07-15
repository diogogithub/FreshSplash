<?php

declare(strict_types=1);

putenv('FRESHSPLASH_STORAGE_DRIVER=json');
putenv('FRESHSPLASH_BASE_URL=https://fresh.example');
require dirname(__DIR__) . '/src/bootstrap.php';

$tests = 0;
$assert = static function (bool $condition, string $message) use (&$tests): void {
    $tests++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assert(fs_url('api/health.php', true) === 'https://fresh.example/api/health.php', 'absolute URL generation failed');
$assert(fs_text_limit('abcdef', 3) === 'abc', 'text limiting failed');
$assert(fs_human_bytes(1048576) === '1.0 MB', 'byte formatting failed');
$assert(fs_escape('<script>') === '&lt;script&gt;', 'HTML escaping failed');
$assert(in_array(fs_repository()->driver(), ['json', 'sqlite'], true), 'repository driver invalid');

printf("%d tests passed\n", $tests);

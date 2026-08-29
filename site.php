<?php
declare(strict_types=1);

function site_env(string $key): string
{
    if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    $g = getenv($key);
    if (is_string($g) && $g !== '') {
        return $g;
    }
    return '';
}

function load_site_env(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $file = __DIR__ . '/.env';
    if (!is_readable($file)) {
        return;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        $val = trim($val, "\"'");
        if ($key !== '' && !isset($_ENV[$key])) {
            $_ENV[$key] = $val;
        }
    }
}

function public_origin(): string
{
    $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = true;
    if ($fwd !== '') {
        $https = explode(',', $fwd)[0] === 'https';
    } elseif (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off') {
        $https = true;
    } elseif ((string) ($_SERVER['SERVER_PORT'] ?? '') === '80') {
        $https = false;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https://' : 'http://') . $host;
}

function public_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = dirname($script);
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        return '';
    }
    return rtrim($dir, '/');
}

function public_base_url(): string
{
    load_site_env();
    $forced = rtrim(site_env('SITE_URL'), '/');
    if ($forced !== '') {
        return $forced;
    }
    return public_origin() . public_base_path();
}

function public_url(string $path = ''): string
{
    $base = public_base_url();
    $path = ltrim($path, '/');
    if ($path === '') {
        return $base . '/';
    }
    return $base . '/' . $path;
}

function site_json(mixed $data): string
{
    return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

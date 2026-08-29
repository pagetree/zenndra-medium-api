<?php
declare(strict_types=1);

function load_env(): void
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
        if ($key !== '') {
            $_ENV[$key] = $val;
        }
    }
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    load_env();
    $url = '';
    foreach (['DB', 'DATABASE_URL'] as $key) {
        if (isset($_ENV[$key]) && is_string($_ENV[$key]) && $_ENV[$key] !== '') {
            $url = $_ENV[$key];
            break;
        }
        $fromEnv = getenv($key);
        if (is_string($fromEnv) && $fromEnv !== '') {
            $url = $fromEnv;
            break;
        }
    }
    if ($url === '') {
        fail('database is not configured', 500);
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host']) || empty($parts['path'])) {
        fail('database is not configured', 500);
    }

    $host = (string) $parts['host'];
    $port = isset($parts['port']) ? (int) $parts['port'] : 5432;
    $name = ltrim((string) $parts['path'], '/');
    $user = (string) ($parts['user'] ?? '');
    $pass = (string) ($parts['pass'] ?? '');

    $dsn = 'pgsql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';sslmode=require';

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        fail('database unavailable', 503);
    }

    return $pdo;
}

function db_missing_table(PDOException $e): bool
{
    return $e->getCode() === '42P01' || str_contains($e->getMessage(), 'undefined table');
}

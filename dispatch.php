<?php
declare(strict_types=1);

function dispatch_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(dirname($script), '/');
    if ($dir !== '' && $dir !== '/' && $dir !== '.' && str_starts_with($uri, $dir)) {
        $uri = substr($uri, strlen($dir)) ?: '/';
    }
    return trim($uri, '/');
}

$path = dispatch_path();

if ($path === '' || $path === 'index.php') {
    return;
}

$pages = [
    'ref' => 'record.php',
    'feedback' => 'feedback.php',
    'openapi.json' => 'openapi.php',
];

$docs = [
    'robots.txt' => 'robots',
    'sitemap.xml' => 'sitemap',
    'llms-full.txt' => 'llms-full',
    'ai.txt' => 'ai',
    'agents.json' => 'agents',
    '.well-known/tdmrep.json' => 'tdmrep',
];

if (isset($pages[$path])) {
    require __DIR__ . '/' . $pages[$path];
    exit;
}

if (isset($docs[$path])) {
    $_GET['doc'] = $docs[$path];
    require __DIR__ . '/discover.php';
    exit;
}

if ($path === '.well-known/llms.txt') {
    header('Content-Type: text/plain; charset=utf-8');
    readfile(__DIR__ . '/llms.txt');
    exit;
}

if ($path === 'api' || str_starts_with($path, 'api/')) {
    require __DIR__ . '/api.php';
    exit;
}

<?php
declare(strict_types=1);

require __DIR__ . '/ref.php';

$spec = json_decode((string) file_get_contents(__DIR__ . '/openapi.json'), true);
if (!is_array($spec)) {
    $spec = ['openapi' => '3.0.3', 'info' => new stdClass(), 'paths' => new stdClass()];
}
if (!isset($spec['info']) || !is_array($spec['info'])) {
    $spec['info'] = [];
}
$spec['info']['version'] = project_ref();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

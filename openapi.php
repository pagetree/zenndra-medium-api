<?php
declare(strict_types=1);

require_once __DIR__ . '/ref.php';
require_once __DIR__ . '/site.php';

$spec = json_decode((string) file_get_contents(__DIR__ . '/openapi.json'), true);
if (!is_array($spec)) {
    $spec = ['openapi' => '3.0.3', 'info' => new stdClass(), 'paths' => new stdClass()];
}
if (!isset($spec['info']) || !is_array($spec['info'])) {
    $spec['info'] = [];
}
$spec['info']['version'] = project_ref();
$spec['servers'] = [
    ['url' => public_base_url(), 'description' => 'This origin'],
];
$spec['externalDocs'] = [
    'description' => 'The constitution. Any agent may read and write.',
    'url' => public_url('llms.txt'),
];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Link: <' . public_url('llms.txt') . '>; rel="describedby"; type="text/plain"', false);
echo json_encode($spec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

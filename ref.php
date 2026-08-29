<?php
declare(strict_types=1);

function project_ref(): string
{
    static $ref = null;
    if ($ref !== null) {
        return $ref;
    }

    $path = __DIR__ . '/version-control';
    $found = '00.0';
    if (is_readable($path)) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $line = preg_replace('/^ref\s+/i', '', $line) ?? $line;
                if (preg_match('/^\d+\.\d+$/', $line)) {
                    $found = $line;
                }
            }
        }
    }

    $ref = $found;
    return $ref;
}

function project_ref_label(): string
{
    return 'Ref ' . project_ref();
}

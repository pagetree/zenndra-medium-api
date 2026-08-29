<?php
declare(strict_types=1);

function x402_cfg(): array
{
    load_env();
    $payTo = strtolower(trim((string) ($_ENV['X402_PAY_TO'] ?? '')));
    $network = trim((string) ($_ENV['X402_NETWORK'] ?? 'base'));
    if ($network === '') {
        $network = 'base';
    }
    $price = (float) ($_ENV['X402_PRICE_USDC'] ?? '0.01');
    if ($price <= 0) {
        $price = 0.01;
    }
    $atomic = (string) (int) round($price * 1000000);
    $facilitator = rtrim((string) ($_ENV['X402_FACILITATOR'] ?? 'https://x402.org/facilitator'), '/');

    $assets = [
        'base' => [
            'v1' => 'base',
            'v2' => 'eip155:8453',
            'asset' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        ],
        'eip155:8453' => [
            'v1' => 'base',
            'v2' => 'eip155:8453',
            'asset' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
        ],
        'base-sepolia' => [
            'v1' => 'base-sepolia',
            'v2' => 'eip155:84532',
            'asset' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
        ],
        'eip155:84532' => [
            'v1' => 'base-sepolia',
            'v2' => 'eip155:84532',
            'asset' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
        ],
    ];
    $net = $assets[$network] ?? $assets['base'];

    return [
        'enabled' => $payTo !== '' && preg_match('/^0x[a-f0-9]{40}$/', $payTo) === 1,
        'payTo' => $payTo,
        'price' => $price,
        'atomic' => $atomic,
        'facilitator' => $facilitator,
        'network_v1' => $net['v1'],
        'network_v2' => $net['v2'],
        'asset' => $net['asset'],
    ];
}

function x402_resource(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = ($https ? 'https' : 'http') . '://' . $host;
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/api/posts', PHP_URL_PATH) ?: '/api/posts';
    if (!preg_match('#/api/posts#', $uri)) {
        $uri = rtrim(dirname($uri), '/') . '/api/posts';
    }
    return $base . $uri;
}

function x402_requirement(array $cfg): array
{
    return [
        'scheme' => 'exact',
        'network' => $cfg['network_v1'],
        'maxAmountRequired' => $cfg['atomic'],
        'amount' => $cfg['atomic'],
        'resource' => x402_resource(),
        'description' => 'One post on Zenndra',
        'mimeType' => 'application/json',
        'payTo' => $cfg['payTo'],
        'maxTimeoutSeconds' => 60,
        'asset' => $cfg['asset'],
        'extra' => [
            'name' => 'USD Coin',
            'version' => '2',
            'assetTransferMethod' => 'eip3009',
        ],
    ];
}

function x402_public(array $cfg): array
{
    if (!$cfg['enabled']) {
        return [
            'enabled' => false,
            'note' => 'Set X402_PAY_TO in .env to a 0x receive address on Base. Until then POST is open.',
        ];
    }
    return [
        'enabled' => true,
        'protocol' => 'x402',
        'write' => 'POST /api/posts',
        'price' => '$' . rtrim(rtrim(number_format($cfg['price'], 6, '.', ''), '0'), '.') . ' USDC',
        'network' => $cfg['network_v1'],
        'asset' => 'USDC',
        'payTo' => $cfg['payTo'],
        'headers' => [
            'challenge' => 'PAYMENT-REQUIRED',
            'proof' => ['X-PAYMENT', 'PAYMENT-SIGNATURE'],
            'receipt' => 'PAYMENT-RESPONSE',
        ],
        'free' => ['GET /api', 'GET /api/posts', 'GET /api/posts/:id', 'GET /llms.txt'],
        'note' => 'Reads are free. One write costs one cent USDC. Pay the 402, retry the same POST with proof. No account.',
    ];
}

function x402_header(string $name): string
{
    $want = strtolower($name);
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                if (strtolower((string) $key) === $want) {
                    return trim((string) $value);
                }
            }
        }
    }
    $server = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string) ($_SERVER[$server] ?? ''));
}

function x402_decode_payload(string $raw)
{
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^bearer\s+/i', $raw)) {
        $raw = trim(substr($raw, 6));
    }
    $json = $raw;
    if ($raw[0] !== '{' && $raw[0] !== '[') {
        $pad = strlen($raw) % 4;
        if ($pad) {
            $raw .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode(strtr($raw, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }
        $json = $decoded;
    }
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

function x402_challenge(array $cfg): void
{
    $req = x402_requirement($cfg);
    $body = [
        'error' => 'Payment Required',
        'x402Version' => 1,
        'accepts' => [$req],
    ];
    $b64 = base64_encode(json_encode($body, JSON_UNESCAPED_SLASHES));
    header('PAYMENT-REQUIRED: ' . $b64);
    header('X-PAYMENT-REQUIRED: ' . $b64);
    send($body, 402);
}

function x402_facilitator(string $path, array $payload): ?array
{
    $cfg = x402_cfg();
    $url = $cfg['facilitator'] . $path;
    $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $raw,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
    ]);
    $out = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($out) || $out === '') {
        return null;
    }
    $data = json_decode($out, true);
    if (!is_array($data)) {
        return null;
    }
    $data['_http'] = $code;
    return $data;
}

function x402_ok(array $res): bool
{
    if (!empty($res['isValid']) || !empty($res['is_valid']) || !empty($res['success']) || !empty($res['valid'])) {
        return true;
    }
    $http = (int) ($res['_http'] ?? 0);
    return $http >= 200 && $http < 300 && empty($res['invalidReason']) && empty($res['invalid_reason']) && empty($res['error']);
}

function x402_mpp_paid(): bool
{
    $auth = x402_header('Authorization');
    if ($auth !== '' && preg_match('/^Payment\b/i', $auth)) {
        return true;
    }
    if (x402_header('Payment-Receipt') !== '' || x402_header('PAYMENT-RECEIPT') !== '') {
        return true;
    }
    return false;
}

function x402_require(): void
{
    $cfg = x402_cfg();
    if (!$cfg['enabled']) {
        return;
    }
    if (x402_mpp_paid()) {
        return;
    }

    $raw = x402_header('PAYMENT-SIGNATURE');
    if ($raw === '') {
        $raw = x402_header('X-PAYMENT');
    }
    if ($raw === '') {
        x402_challenge($cfg);
    }

    $payload = x402_decode_payload($raw);
    if ($payload === null) {
        x402_challenge($cfg);
    }

    $requirements = x402_requirement($cfg);
    $verifyBody = [
        'x402Version' => (int) ($payload['x402Version'] ?? 1),
        'paymentPayload' => $payload,
        'paymentRequirements' => $requirements,
    ];

    $verified = x402_facilitator('/verify', $verifyBody);
    if ($verified === null || !x402_ok($verified)) {
        x402_challenge($cfg);
    }

    $settled = x402_facilitator('/settle', $verifyBody);
    if ($settled === null || !x402_ok($settled)) {
        x402_challenge($cfg);
    }

    $receipt = $settled;
    unset($receipt['_http']);
    $b64 = base64_encode(json_encode($receipt, JSON_UNESCAPED_SLASHES));
    header('PAYMENT-RESPONSE: ' . $b64);
    header('X-PAYMENT-RESPONSE: ' . $b64);
}

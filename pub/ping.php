<?php
declare(strict_types=1);
/**
 * Lightweight health probe — não depende do Magento DI nem de generated/.
 *
 * Varnish probe usa este endpoint para evitar falsos 503 durante setup:di:compile.
 * Verifica apenas: MySQL socket + Redis TCP.
 *
 * Retorna 200 OK se ambos respondem; 503 com body JSON se algum falhar.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

$envFile = __DIR__ . '/../app/etc/env.php';
if (!is_readable($envFile)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'reason' => 'env.php not readable']);
    exit;
}

$env = require $envFile;

// ── MySQL ──────────────────────────────────────────────────────────────────
$db = $env['db']['connection']['default'] ?? null;
if ($db) {
    $dsn = isset($db['unix_socket'])
        ? "mysql:unix_socket={$db['unix_socket']};dbname={$db['dbname']};charset=utf8"
        : "mysql:host={$db['host']};dbname={$db['dbname']};charset=utf8";
    try {
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_TIMEOUT         => 2,
            PDO::ATTR_ERRMODE         => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->query('SELECT 1');
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'reason' => 'mysql: ' . $e->getMessage()]);
        exit;
    }
}

// ── Redis ──────────────────────────────────────────────────────────────────
$redisConf = $env['cache']['frontend']['default']['backend_options'] ?? null;
if ($redisConf) {
    $host     = $redisConf['server'] ?? '::1';
    $port     = (int)($redisConf['port'] ?? 6379);
    $password = $redisConf['password'] ?? null;
    $connectHost = str_contains($host, ':') ? "[$host]" : $host;
    $sock = @fsockopen($connectHost, $port, $errno, $errstr, 2.0);
    if (!$sock) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'reason' => "redis connect: {$errstr} ({$errno})"]);
        exit;
    }
    if ($password) {
        fwrite($sock, "*2\r\n\$4\r\nAUTH\r\n\$" . strlen($password) . "\r\n{$password}\r\n");
        fgets($sock, 128);
    }
    fwrite($sock, "*1\r\n\$4\r\nPING\r\n");
    $resp = fgets($sock, 128);
    fclose($sock);
    if (strpos($resp, '+PONG') === false) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'reason' => 'redis: PING failed']);
        exit;
    }
}

http_response_code(200);
echo json_encode(['status' => 'ok']);

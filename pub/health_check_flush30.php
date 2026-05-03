<?php
/**
 * Temporary full cache purge (Varnish + Redis FPC). Delete after use.
 * Token: ak7x9q2w
 */
if (($_GET['token'] ?? '') !== 'ak7x9q2w2026') {
    http_response_code(403); echo 'Forbidden'; exit;
}

$results = [];

// Purge Varnish
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'http://127.0.0.1:6081/',
    CURLOPT_CUSTOMREQUEST => 'PURGE',
    CURLOPT_HTTPHEADER => ['X-Magento-Tags-Pattern: .*'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
]);
$varnishResp = curl_exec($ch);
$varnishCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$results['varnish'] = ['code' => $varnishCode, 'resp' => substr((string)$varnishResp, 0, 100)];

// Flush Redis FPC (DB2)
$redis = new \Redis();
$redis->connect('::1', 6379);
$redis->auth('Aw4R3d1s2026Sec');
$redis->select(2);
$redis->flushDB();
$results['redis_fpc'] = 'flushed';

// Also flush Redis layout cache (DB1) using Magento
define('BP', dirname(__DIR__));
require BP . '/app/bootstrap.php';
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$om = $bootstrap->getObjectManager();
$om->get(\Magento\Framework\App\Cache\Manager::class)->clean(['layout','block_html','full_page','config']);
$results['magento_cache'] = 'cleaned';

echo json_encode($results, JSON_PRETTY_PRINT);

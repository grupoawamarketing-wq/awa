<?php
// One-time use script — delete after running
$file = '/home/user/htdocs/srv1113343.hstgr.cloud/app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_Themeoption/templates/html/header.phtml';
if (function_exists('opcache_invalidate')) {
    $result = opcache_invalidate($file, true);
    echo "opcache_invalidate($file): " . ($result ? 'OK' : 'FAILED') . PHP_EOL;
} else {
    echo "OPcache not available" . PHP_EOL;
}
unlink(__FILE__); // Self-delete
echo "Done";

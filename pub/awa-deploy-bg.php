<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo "This endpoint has been retired.\n";
echo "Use Magento CLI / controlled deployment tasks instead.\n";

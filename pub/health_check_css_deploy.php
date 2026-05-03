<?php
$src = dirname(__DIR__) . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/awa-visual-bugfix-2026-04-30.css';
$dst = __DIR__ . '/static/frontend/AWA_Custom/ayo_home5_child/pt_BR/css/awa-visual-bugfix-2026-04-30.css';
if (!file_exists($src)) { die("SRC NOT FOUND"); }
if (!copy($src, $dst)) { die("COPY FAILED"); }
echo "OK src=" . filesize($src) . " dst=" . filesize($dst);

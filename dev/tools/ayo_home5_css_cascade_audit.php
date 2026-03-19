#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Auditoria de CSS da Home5 (Ayo/Rokanthemes + customizações AWA).
 *
 * Objetivo:
 * - listar CSS efetivamente carregados na home
 * - detectar 404/duplicidade/legado
 * - inspecionar ordem de cascata dos CSS AWA
 * - mapear sobreposição de seletores chave em arquivos CSS carregados
 */

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function warn(string $message): void
{
    fwrite(STDOUT, '[WARN] ' . $message . PHP_EOL);
}

function fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
}

/**
 * @param array<int,string> $argv
 * @return array{url:string, timeout:int, insecure:bool, check-http:bool, strict:bool, demo-pure:bool}
 */
function parseArgs(array $argv): array
{
    $opts = [
        'url' => 'https://awamotos.com/',
        'timeout' => 20,
        'insecure' => false,
        'check-http' => true,
        'strict' => false,
        'demo-pure' => false,
    ];

    for ($i = 1, $max = count($argv); $i < $max; $i++) {
        $arg = (string) $argv[$i];

        if ($arg === '--insecure') {
            $opts['insecure'] = true;
            continue;
        }
        if ($arg === '--no-check-http') {
            $opts['check-http'] = false;
            continue;
        }
        if ($arg === '--strict') {
            $opts['strict'] = true;
            continue;
        }
        if ($arg === '--demo-pure') {
            $opts['demo-pure'] = true;
            continue;
        }

        if (!str_starts_with($arg, '--')) {
            throw new InvalidArgumentException('Opção inválida: ' . $arg);
        }

        $key = substr($arg, 2);
        if (!array_key_exists($key, $opts)) {
            throw new InvalidArgumentException('Opção não suportada: --' . $key);
        }

        $value = $argv[$i + 1] ?? null;
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException('Valor ausente para --' . $key);
        }

        if ($key === 'timeout') {
            $opts[$key] = max(1, (int) $value);
        } else {
            $opts[$key] = $value;
        }
        $i++;
    }

    $opts['url'] = rtrim((string) $opts['url'], '/') . '/';

    return [
        'url' => (string) $opts['url'],
        'timeout' => (int) $opts['timeout'],
        'insecure' => (bool) $opts['insecure'],
        'check-http' => (bool) $opts['check-http'],
        'strict' => (bool) $opts['strict'],
        'demo-pure' => (bool) $opts['demo-pure'],
    ];
}

function rootDir(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return array{code:int, body:string}
 */
function fetchHtml(string $url, int $timeout, bool $insecure): array
{
    $ch = curl_init();
    if ($ch === false) {
        throw new RuntimeException('Falha ao iniciar cURL');
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AYOHome5CssCascadeAudit/1.0)',
        CURLOPT_HEADER => false,
    ]);

    if ($insecure) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $body = curl_exec($ch);
    if (!is_string($body)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Falha ao baixar HTML: ' . $error);
    }

    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return ['code' => $code, 'body' => $body];
}

/**
 * @return list<string>
 */
function extractCssLinks(string $html): array
{
    $links = [];
    if (preg_match_all('/<link\b[^>]*href="([^"]+\.css[^"]*)"[^>]*>/i', $html, $matches) >= 1) {
        foreach (($matches[1] ?? []) as $href) {
            $href = html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5);
            if ($href !== '') {
                $links[] = $href;
            }
        }
    }

    return $links;
}

/**
 * @return array<int,array{url:string,code:int}>
 */
function checkCssHttpStatuses(array $links, int $timeout, bool $insecure): array
{
    $results = [];
    foreach ($links as $url) {
        $ch = curl_init();
        if ($ch === false) {
            $results[] = ['url' => $url, 'code' => 0];
            continue;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AYOHome5CssCascadeAudit/1.0)',
            CURLOPT_HEADER => false,
        ]);

        if ($insecure) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        $ok = curl_exec($ch);
        $code = $ok === false ? 0 : (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $results[] = ['url' => $url, 'code' => $code];
    }

    return $results;
}

/**
 * @return array<string,list<int>>
 */
function duplicateIndexesByValue(array $values): array
{
    $positions = [];
    foreach (array_values($values) as $idx => $value) {
        $positions[(string) $value][] = $idx;
    }

    $duplicates = [];
    foreach ($positions as $value => $indexes) {
        if (count($indexes) > 1) {
            $duplicates[$value] = $indexes;
        }
    }

    return $duplicates;
}

/**
 * @return string
 */
function basenameFromCssUrl(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return basename($url);
    }

    return basename($path);
}

/**
 * @return ?string
 */
function mapCssUrlToLocalFile(string $url): ?string
{
    $path = (string) parse_url($url, PHP_URL_PATH);
    if ($path === '') {
        return null;
    }

    if (!str_contains($path, '/frontend/AWA_Custom/ayo_home5_child/')) {
        return null;
    }

    $basename = basename($path);
    if ($basename === '' || !str_ends_with($basename, '.css')) {
        return null;
    }

    $root = rootDir();
    $candidates = [
        $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/web/css/' . $basename,
        $root . '/app/design/frontend/ayo/ayo_home5/web/css/' . $basename,
        $root . '/app/design/frontend/ayo/ayo_default/web/css/' . $basename,
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * @return array<int,array{line:int,text:string}>
 */
function findSelectorMentions(string $file, string $needle): array
{
    $content = file_get_contents($file);
    if (!is_string($content) || $content === '') {
        return [];
    }

    $lines = preg_split('/\R/', $content);
    if (!is_array($lines)) {
        return [];
    }

    $hits = [];
    foreach ($lines as $i => $line) {
        if (str_contains($line, $needle)) {
            $hits[] = [
                'line' => $i + 1,
                'text' => trim($line),
            ];
        }
    }

    return $hits;
}

/**
 * @return string
 */
function shorten(string $text, int $max = 110): string
{
    if (strlen($text) <= $max) {
        return $text;
    }

    return substr($text, 0, max(0, $max - 3)) . '...';
}

try {
    $args = parseArgs($argv);

    out('=== AYO HOME5 CSS CASCADE AUDIT ===');
    out('URL: ' . $args['url']);
    $isDemoPureMode = $args['demo-pure']
        || str_contains($args['url'], '/homepage_ayo_home5_demo_stage/')
        || str_contains($args['url'], '/homepage_ayo_home5_stage/');
    out('Mode: ' . ($isDemoPureMode ? 'demo-pure' : 'awa-home'));

    $resp = fetchHtml($args['url'], $args['timeout'], $args['insecure']);
    if ($resp['code'] === 403) {
        warn('Home retornou 403 (WAF/anti-bot). Auditoria CSS pulada sem falha.');
        exit(0);
    }
    if ($resp['code'] !== 200) {
        fail('Home não retornou HTTP 200. Código: ' . $resp['code']);
        exit(1);
    }

    $html = $resp['body'];
    $cssLinks = extractCssLinks($html);
    if ($cssLinks === []) {
        fail('Nenhum <link href=\"*.css\"> encontrado no HTML da home');
        exit(1);
    }

    $errors = [];
    $warnings = [];

    out('');
    out('=== RESUMO DE LINKS CSS ===');
    out('Total CSS links: ' . count($cssLinks));

    $duplicateUrls = duplicateIndexesByValue($cssLinks);
    $basenames = array_map('basenameFromCssUrl', $cssLinks);
    $duplicateBasenames = duplicateIndexesByValue($basenames);

    out('Duplicidades (URL exata): ' . count($duplicateUrls));
    out('Duplicidades (basename): ' . count($duplicateBasenames));

    if ($duplicateUrls !== []) {
        foreach ($duplicateUrls as $url => $idxs) {
            $warnings[] = sprintf('CSS duplicado (URL exata) em posições [%s]: %s', implode(', ', $idxs), $url);
        }
    }

    // Duplicate basenames often indicate same asset added twice from different static versions/paths.
    foreach ($duplicateBasenames as $basename => $idxs) {
        if (in_array($basename, ['styles-m.css', 'styles-l.css'], true)) {
            continue;
        }
        $urlsForBase = [];
        foreach ($idxs as $idx) {
            $urlsForBase[] = $cssLinks[$idx] ?? '';
        }
        $warnings[] = sprintf(
            'Mesmo basename CSS carregado múltiplas vezes [%s]: %s',
            implode(', ', $idxs),
            $basename . ' => ' . implode(' | ', array_filter($urlsForBase))
        );
    }

    out('');
    out('=== CHECKS AYO/AWA ===');
    $mustContain = [
        'frontend/AWA_Custom/ayo_home5_child/' => 'Home não está servindo CSS do child theme AWA_Custom/ayo_home5_child',
    ];
    if (!$isDemoPureMode) {
        $mustContain['css/awa-consistency-home5.css'] = 'CSS homepage-only awa-consistency-home5.css ausente';
        $mustContain['css/awa-fixes.css'] = 'awa-fixes.css ausente';
    }
    foreach ($mustContain as $needle => $message) {
        $found = false;
        foreach ($cssLinks as $url) {
            if (str_contains($url, $needle)) {
                $found = true;
                break;
            }
        }
        out(sprintf('%s: %s', $needle, $found ? 'SIM' : 'NÃO'));
        if (!$found) {
            $errors[] = $message;
        }
    }

    foreach ($cssLinks as $url) {
        if (preg_match('~/css/awa-round[0-9][^/]*\.css~', $url) === 1) {
            $errors[] = 'CSS legado awa-round* ainda presente no HTML final: ' . $url;
        }
        if ($isDemoPureMode && preg_match('~/css/awa-(custom|core|layout|components|consistency|fixes|grid)~', $url) === 1) {
            $errors[] = 'CSS AWA ainda presente em modo demo-pure: ' . $url;
        }
    }

    out('');
    out('=== ORDEM DE CASCATA (AWA CSS) ===');
    $orderTargets = [
        'themes5.css',
        'awa-core.css',
        'awa-layout.css',
        'awa-components.css',
        'awa-consistency.css',
        'awa-consistency-ui.css',
        'awa-consistency-home5.css',
        'awa-fixes.css',
        'awa-grid-unified.css',
        'awa-custom-brand-bridge.css',
        'awa-custom-header-minicart.css',
        'awa-custom-footer-trust.css',
        'awa-custom-global-brand.css',
        'awa-custom-ux-refine.css',
        'awa-custom-b2b-gate-refine.css',
        'awa-custom-auth-refine.css',
        'awa-custom-plp-search-cart-refine.css',
        'awa-custom-home-owl-tabs.css',
        'awa-04-components.css',
        'awa-05-pages.css',
    ];
    $orderIndexByBasename = [];
    foreach ($cssLinks as $idx => $url) {
        $orderIndexByBasename[basenameFromCssUrl($url)][] = $idx;
    }

    foreach ($orderTargets as $basename) {
        $idxs = $orderIndexByBasename[$basename] ?? [];
        if ($idxs === []) {
            out(sprintf('%-34s | ausente', $basename));
            continue;
        }
        out(sprintf('%-34s | pos %s', $basename, implode(',', $idxs)));
    }

    $orderChecks = [
        ['awa-consistency-home5.css', 'awa-fixes.css', 'awa-consistency-home5.css deveria carregar após awa-fixes.css para overrides de homepage terem precedência'],
        ['awa-custom-home-owl-tabs.css', 'awa-consistency-home5.css', 'awa-custom-home-owl-tabs.css deveria carregar após awa-consistency-home5.css'],
    ];

    foreach ($orderChecks as [$a, $b, $message]) {
        $aIdx = $orderIndexByBasename[$a][0] ?? null;
        $bIdx = $orderIndexByBasename[$b][0] ?? null;
        if (!is_int($aIdx) || !is_int($bIdx)) {
            continue;
        }
        if ($aIdx < $bIdx) {
            $warnings[] = $message . sprintf(' (ordem atual: %s=%d, %s=%d)', $a, $aIdx, $b, $bIdx);
        }
    }

    if ($args['check-http']) {
        out('');
        out('=== STATUS HTTP DOS CSS ===');
        $statuses = checkCssHttpStatuses($cssLinks, min(10, $args['timeout']), $args['insecure']);
        $non200 = 0;
        foreach ($statuses as $row) {
            if ($row['code'] !== 200) {
                $non200++;
                $errors[] = sprintf('CSS não retornou 200: [%d] %s', $row['code'], $row['url']);
                out(sprintf('[%d] %s', $row['code'], $row['url']));
            }
        }
        if ($non200 === 0) {
            out('[OK] Todos os CSS renderizados responderam HTTP 200');
        }
    }

    out('');
    out('=== SOBREPOSIÇÃO DE SELETORES (arquivos CSS carregados) ===');
    $selectorNeedles = [
        '.ayo-home5-wrapper',
        '.ayo-home5-section',
        '.ayo-home5-heading',
        '.ayo-home5-label',
        '.ayo-home5-divider',
        '.top-home-content--trust-and-offers',
        '.top-home-content--fitment-search',
        '.ayo-home5-section--fitment',
    ];

    $loadedLocalCss = [];
    foreach ($cssLinks as $idx => $url) {
        $local = mapCssUrlToLocalFile($url);
        if ($local === null) {
            continue;
        }
        $loadedLocalCss[] = [
            'index' => $idx,
            'url' => $url,
            'basename' => basenameFromCssUrl($url),
            'file' => $local,
        ];
    }

    if ($loadedLocalCss === []) {
        warn('Não foi possível mapear CSS renderizados para arquivos locais (static fallback/externos).');
    } else {
        foreach ($selectorNeedles as $needle) {
            $hitsPerFile = [];
            foreach ($loadedLocalCss as $item) {
                $hits = findSelectorMentions((string) $item['file'], $needle);
                if ($hits === []) {
                    continue;
                }
                $hitsPerFile[] = [
                    'index' => (int) $item['index'],
                    'basename' => (string) $item['basename'],
                    'file' => (string) $item['file'],
                    'hits' => $hits,
                ];
            }

            if ($hitsPerFile === []) {
                out(sprintf('%-36s | sem ocorrências nos CSS locais carregados', $needle));
                continue;
            }

            out(sprintf('%-36s | %d arquivo(s)', $needle, count($hitsPerFile)));
            foreach ($hitsPerFile as $item) {
                /** @var array<int,array{line:int,text:string}> $hits */
                $hits = $item['hits'];
                $sample = $hits[0];
                out(sprintf(
                    '  - pos=%d file=%s line=%d :: %s',
                    (int) $item['index'],
                    (string) $item['basename'],
                    (int) $sample['line'],
                    shorten((string) $sample['text'])
                ));
            }
        }
    }

    out('');
    out('=== ACHADOS ===');
    if ($warnings === [] && $errors === []) {
        out('[OK] Nenhum erro/risco de CSS detectado pelos checks automatizados.');
        exit(0);
    }

    foreach ($warnings as $warning) {
        warn($warning);
    }
    foreach ($errors as $error) {
        fail($error);
    }

    if ($args['strict'] && ($warnings !== [] || $errors !== [])) {
        exit(1);
    }

    exit($errors === [] ? 0 : 1);
} catch (Throwable $e) {
    fail($e->getMessage());
    exit(1);
}

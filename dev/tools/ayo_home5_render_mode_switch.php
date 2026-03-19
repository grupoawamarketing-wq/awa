#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Switch seguro do modo de renderização da Home5 (Ayo) no child theme.
 *
 * Modos:
 * - template: mantém top-home.phtml (parent ayo_default) e remove cms_page_content
 * - cms: remove content-top-home/top_home e restaura cms_page_content
 *
 * Opera apenas no arquivo de layout do child theme:
 * app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/layout/cms_index_index.xml
 */

/**
 * @param string $message
 */
function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

/**
 * @param string $message
 */
function warn(string $message): void
{
    fwrite(STDOUT, '[WARN] ' . $message . PHP_EOL);
}

/**
 * @param string $message
 */
function fail(string $message): void
{
    fwrite(STDERR, '[FAIL] ' . $message . PHP_EOL);
}

/**
 * @return string
 */
function rootDir(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @param array<int,string> $argv
 * @return array{command:string,file:string,backup-dir:string}
 */
function parseArgs(array $argv): array
{
    $command = $argv[1] ?? 'status';
    $allowed = ['status', 'template', 'cms'];
    if (!in_array($command, $allowed, true)) {
        throw new InvalidArgumentException(
            'Uso: ayo_home5_render_mode_switch.php [status|template|cms] [--file <path>] [--backup-dir <path>]'
        );
    }

    $opts = [
        'file' => rootDir() . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Cms/layout/cms_index_index.xml',
        'backup-dir' => rootDir() . '/var/tmp/ayo-home5-render-mode-backups',
    ];

    for ($i = 2, $max = count($argv); $i < $max; $i++) {
        $arg = (string) $argv[$i];
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

        $opts[$key] = rtrim($value, '/');
        $i++;
    }

    return [
        'command' => $command,
        'file' => (string) $opts['file'],
        'backup-dir' => (string) $opts['backup-dir'],
    ];
}

/**
 * @return string
 */
function readFileStrict(string $path): string
{
    if (!is_file($path)) {
        throw new RuntimeException('Arquivo não encontrado: ' . $path);
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        throw new RuntimeException('Falha ao ler arquivo: ' . $path);
    }

    return $content;
}

/**
 * @return array{cms_page_content:?bool,content_top_home:?bool,mode:string,demo_suppressions:string}
 */
function detectMode(string $xml): array
{
    $cmsPageContentRemove = null;
    $contentTopHomeRemove = null;

    if (preg_match('/<referenceBlock\s+name="cms_page_content"\s+remove="(true|false)"\s*\/>/i', $xml, $m) === 1) {
        $cmsPageContentRemove = strtolower((string) $m[1]) === 'true';
    }

    if (preg_match('/<referenceContainer\s+name="content-top-home"\s+remove="(true|false)"\s*\/>/i', $xml, $m) === 1) {
        $contentTopHomeRemove = strtolower((string) $m[1]) === 'true';
    }

    $mode = 'unknown';
    if ($cmsPageContentRemove === true && $contentTopHomeRemove === false) {
        $mode = 'template';
    } elseif ($cmsPageContentRemove === false && $contentTopHomeRemove === true) {
        $mode = 'cms';
    } elseif ($cmsPageContentRemove !== null || $contentTopHomeRemove !== null) {
        $mode = 'mixed';
    }

    $demoSuppressions = detectManagedRegionState($xml, 'AWA_HOME5_DEMO_SUPPRESSIONS_HEAD_START', 'AWA_HOME5_DEMO_SUPPRESSIONS_HEAD_END');
    $demoSuppressionsBody = detectManagedRegionState($xml, 'AWA_HOME5_DEMO_SUPPRESSIONS_BODY_START', 'AWA_HOME5_DEMO_SUPPRESSIONS_BODY_END');
    if ($demoSuppressions === 'unknown' || $demoSuppressionsBody === 'unknown') {
        $demoSuppressionsState = 'unknown';
    } elseif ($demoSuppressions === $demoSuppressionsBody) {
        $demoSuppressionsState = $demoSuppressions;
    } else {
        $demoSuppressionsState = 'mixed';
    }

    return [
        'cms_page_content' => $cmsPageContentRemove,
        'content_top_home' => $contentTopHomeRemove,
        'mode' => $mode,
        'demo_suppressions' => $demoSuppressionsState,
    ];
}

/**
 * @return array{xml:string,replacements:int}
 */
function patchMode(string $xml, string $targetMode): array
{
    $targetCmsPageContentRemove = $targetMode === 'template' ? 'true' : 'false';
    $targetContentTopHomeRemove = $targetMode === 'template' ? 'false' : 'true';

    $replacements = 0;

    $xml = preg_replace_callback(
        '/<referenceBlock\s+name="cms_page_content"\s+remove="(true|false)"\s*\/>/i',
        static function (array $match) use ($targetCmsPageContentRemove, &$replacements): string {
            $replacements++;
            return '<referenceBlock name="cms_page_content" remove="' . $targetCmsPageContentRemove . '"/>';
        },
        $xml,
        1
    );
    if (!is_string($xml)) {
        throw new RuntimeException('Falha ao aplicar patch em cms_page_content');
    }

    $xml = preg_replace_callback(
        '/<referenceContainer\s+name="content-top-home"\s+remove="(true|false)"\s*\/>/i',
        static function (array $match) use ($targetContentTopHomeRemove, &$replacements): string {
            $replacements++;
            return '<referenceContainer name="content-top-home" remove="' . $targetContentTopHomeRemove . '"/>';
        },
        $xml,
        1
    );
    if (!is_string($xml)) {
        throw new RuntimeException('Falha ao aplicar patch em content-top-home');
    }

    $demoHeadSnippet = $targetMode === 'cms' ? demoSuppressionsHeadSnippet() : '';
    $demoBodySnippet = $targetMode === 'cms' ? demoSuppressionsBodySnippet() : '';

    $xml = replaceManagedRegion(
        $xml,
        'AWA_HOME5_DEMO_SUPPRESSIONS_HEAD_START',
        'AWA_HOME5_DEMO_SUPPRESSIONS_HEAD_END',
        $demoHeadSnippet,
        $replacements
    );

    $xml = replaceManagedRegion(
        $xml,
        'AWA_HOME5_DEMO_SUPPRESSIONS_BODY_START',
        'AWA_HOME5_DEMO_SUPPRESSIONS_BODY_END',
        $demoBodySnippet,
        $replacements
    );

    if ($replacements !== 4) {
        throw new RuntimeException(
            'Não foi possível localizar os trechos gerenciados do layout (modo + suppressions demo).'
        );
    }

    return [
        'xml' => $xml,
        'replacements' => $replacements,
    ];
}

/**
 * @return string
 */
function detectManagedRegionState(string $xml, string $startMarker, string $endMarker): string
{
    $pattern = sprintf(
        '/<!--\s*%s\s*-->(.*?)<!--\s*%s\s*-->/s',
        preg_quote($startMarker, '/'),
        preg_quote($endMarker, '/')
    );

    if (preg_match($pattern, $xml, $m) !== 1) {
        return 'unknown';
    }

    $content = trim((string) ($m[1] ?? ''));
    if ($content === '') {
        return 'disabled';
    }

    return 'enabled';
}

/**
 * @param int $replacements
 * @return string
 */
function replaceManagedRegion(
    string $xml,
    string $startMarker,
    string $endMarker,
    string $innerXml,
    int &$replacements
): string {
    $pattern = sprintf(
        '/(^[ \t]*<!--\s*%s\s*-->\R)(.*?)(^[ \t]*<!--\s*%s\s*-->\R?)/ms',
        preg_quote($startMarker, '/'),
        preg_quote($endMarker, '/')
    );

    $result = preg_replace_callback(
        $pattern,
        static function (array $m) use ($innerXml, &$replacements): string {
            $replacements++;
            $start = (string) $m[1];
            $end = (string) $m[3];
            $replacement = $start;
            if ($innerXml !== '') {
                $replacement .= rtrim($innerXml, "\n") . "\n";
            }
            $replacement .= $end;
            return $replacement;
        },
        $xml,
        1
    );

    if (!is_string($result)) {
        throw new RuntimeException('Falha ao substituir região gerenciada: ' . $startMarker);
    }

    return $result;
}

/**
 * @return string
 */
function demoSuppressionsHeadSnippet(): string
{
    return <<<'XML'
        <remove src="css/awa-institutional.css"/>
        <remove src="css/awa-core.css"/>
        <remove src="css/awa-layout.css"/>
        <remove src="css/awa-components.css"/>
        <remove src="css/awa-consistency.css"/>
        <remove src="css/awa-consistency-ui.css"/>
        <remove src="css/awa-consistency-home5.css"/>
        <remove src="css/awa-fixes.css"/>
        <remove src="css/awa-grid-unified.css"/>
        <remove src="css/awa-custom-brand-bridge.css"/>
        <remove src="css/awa-custom-header-minicart.css"/>
        <remove src="css/awa-custom-footer-trust.css"/>
        <remove src="css/awa-custom-global-brand.css"/>
        <remove src="css/awa-custom-ux-refine.css"/>
        <remove src="css/awa-custom-b2b-gate-refine.css"/>
        <remove src="css/awa-custom-auth-refine.css"/>
        <remove src="css/awa-custom-plp-search-cart-refine.css"/>
        <remove src="css/awa-custom-home-owl-tabs.css"/>
        <remove src="css/awa-custom-post-themeoption-overrides.css"/>
        <remove src="css/awa-custom-footer-light-perfect.css"/>
        <remove src="css/awa-custom-components-b2b-foundation.css"/>
        <remove src="css/awa-custom-compat-b2b-nav-plp-cart-checkout.css"/>
        <remove src="css/awa-custom-page-b2b-cart-checkout-premium.css"/>
        <remove src="css/awa-custom-page-home-category-premium.css"/>
        <remove src="Rokanthemes_SearchSuiteAutocomplete::css/awa-autocomplete.css"/>
        <remove src="GrupoAwamotos_B2B::css/b2b-header.css"/>
        <remove src="GrupoAwamotos_B2B::css/header-status-panel.css"/>
        <remove src="GrupoAwamotos_B2B::css/login-to-cart.css"/>
        <!-- Consolidated CSS (Phase 2) -->
        <remove src="css/awa-04-components.css"/>
        <remove src="css/awa-05-pages.css"/>
    XML;
}

/**
 * @return string
 */
function demoSuppressionsBodySnippet(): string
{
    return <<<'XML'
        <referenceBlock name="awa.post.themeoption.css">
            <arguments>
                <argument name="disable_awa_post_themeoption_css" xsi:type="boolean">true</argument>
            </arguments>
        </referenceBlock>
        <referenceBlock name="awa.seo.head" remove="true"/>
        <referenceBlock name="awa.seo.h1" remove="true"/>
        <referenceBlock name="awa.master.fix.js" remove="true"/>
        <referenceBlock name="awa.custom.header.minicart.js" remove="true"/>
        <referenceBlock name="awa.custom.home.owl.tabs.js" remove="true"/>
        <referenceBlock name="newsletter_popup" remove="true"/>
        <referenceBlock name="fixed_right" remove="true"/>
    XML;
}

function validateXmlString(string $xml): void
{
    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $ok = $doc->loadXML($xml);
    $errors = libxml_get_errors();
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($ok !== true) {
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = trim((string) $error->message);
        }
        throw new RuntimeException('XML inválido após alteração: ' . implode(' | ', $messages));
    }
}

/**
 * @return string
 */
function writeBackup(string $backupDir, string $filePath, string $originalContent): string
{
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        throw new RuntimeException('Não foi possível criar backup dir: ' . $backupDir);
    }

    $base = basename($filePath);
    $filename = sprintf('%s/%s.%s.bak', $backupDir, $base, gmdate('Ymd_His'));
    $written = file_put_contents($filename, $originalContent);
    if ($written === false) {
        throw new RuntimeException('Falha ao gravar backup: ' . $filename);
    }

    return $filename;
}

try {
    $args = parseArgs($argv);
    $file = $args['file'];
    $command = $args['command'];

    $originalXml = readFileStrict($file);
    $current = detectMode($originalXml);

    out('=== AYO HOME5 RENDER MODE SWITCH ===');
    out('File: ' . $file);
    out('Current mode: ' . $current['mode']);
    out(
        'Managed flags: cms_page_content.remove='
        . (($current['cms_page_content'] === null) ? 'N/A' : ($current['cms_page_content'] ? 'true' : 'false'))
        . ' | content-top-home.remove='
        . (($current['content_top_home'] === null) ? 'N/A' : ($current['content_top_home'] ? 'true' : 'false'))
    );
    out('Demo suppressions: ' . $current['demo_suppressions']);

    if ($command === 'status') {
        if ($current['mode'] === 'unknown') {
            warn('Trecho gerenciado não encontrado. Verifique cms_index_index.xml do child theme.');
            exit(1);
        }
        if ($current['mode'] === 'mixed') {
            warn('Layout em estado misto; use "template" ou "cms" para normalizar.');
            exit(1);
        }
        exit(0);
    }

    $targetMode = $command;
    out('Target mode: ' . $targetMode);

    if ($current['mode'] === $targetMode) {
        out('Nenhuma alteração necessária (layout já está no modo alvo).');
        out('Lembrete: se acabou de trocar por fora, limpe cache layout/block_html/full_page.');
        exit(0);
    }

    $patched = patchMode($originalXml, $targetMode);
    $newXml = $patched['xml'];

    if ($newXml === $originalXml) {
        out('Nenhuma alteração de conteúdo foi gerada.');
        exit(0);
    }

    validateXmlString($newXml);
    $backupPath = writeBackup($args['backup-dir'], $file, $originalXml);

    $written = file_put_contents($file, $newXml);
    if ($written === false) {
        throw new RuntimeException('Falha ao gravar arquivo: ' . $file);
    }

    $after = detectMode($newXml);
    out('Backup: ' . $backupPath);
    out('Bytes gravados: ' . $written);
    out('Novo modo: ' . $after['mode']);
    out('Próximo passo: php bin/magento cache:clean layout block_html full_page');
} catch (Throwable $e) {
    fail($e->getMessage());
    exit(1);
}

#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Auditoria de paridade do child theme AWA_Custom/ayo_home5_child.
 *
 * Read-only: valida registro do tema, config atual, blocos CMS críticos,
 * paridade de overrides críticos e referências de assets (child ou parent).
 */

const EXIT_OK = 0;
const EXIT_FAIL = 1;

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

function projectRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * @return array<string, mixed>
 */
function loadPhpArray(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("Arquivo não encontrado: {$path}");
    }

    $data = include $path;
    if (!is_array($data)) {
        throw new RuntimeException("Arquivo não retornou array: {$path}");
    }

    return $data;
}

/**
 * @return array{host:string,dbname:string,username:string,password:string}
 */
function loadDbConfig(string $root): array
{
    $env = loadPhpArray($root . '/app/etc/env.php');
    $db = $env['db']['connection']['default'] ?? null;
    if (!is_array($db)) {
        throw new RuntimeException('Config de banco inválida em app/etc/env.php');
    }

    $host = (string) ($db['host'] ?? '127.0.0.1');
    $dbname = (string) ($db['dbname'] ?? '');
    $username = (string) ($db['username'] ?? '');
    $password = (string) ($db['password'] ?? '');

    if ($dbname === '' || $username === '') {
        throw new RuntimeException('Credenciais de banco incompletas em app/etc/env.php');
    }

    return [
        'host' => $host,
        'dbname' => $dbname,
        'username' => $username,
        'password' => $password,
    ];
}

function connectPdo(array $db): PDO
{
    $dsnCandidates = [];
    if (str_starts_with($db['host'], '/')) {
        $dsnCandidates[] = sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
            $db['host'],
            $db['dbname']
        );
        $dsnCandidates[] = sprintf('mysql:host=127.0.0.1;port=3306;dbname=%s;charset=utf8mb4', $db['dbname']);
    } else {
        $dsnCandidates[] = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['dbname']);
        if ($db['host'] !== '127.0.0.1') {
            $dsnCandidates[] = sprintf('mysql:host=127.0.0.1;port=3306;dbname=%s;charset=utf8mb4', $db['dbname']);
        }
    }

    $lastError = 'erro desconhecido';
    foreach ($dsnCandidates as $dsn) {
        try {
            return new PDO(
                $dsn,
                $db['username'],
                $db['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (Throwable $e) {
            $lastError = $e->getMessage();
        }
    }

    throw new RuntimeException('Falha ao conectar no MySQL: ' . $lastError);
}

/**
 * @return array<string, mixed>|null
 */
function findTheme(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare(
        'SELECT theme_id, parent_id, area, theme_path, code, theme_title
         FROM theme
         WHERE area = :area AND code = :code
         LIMIT 1'
    );
    $stmt->execute([
        ':area' => 'frontend',
        ':code' => $code,
    ]);

    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function findStoreId(PDO $pdo, string $storeCode): ?int
{
    $stmt = $pdo->prepare('SELECT store_id FROM store WHERE code = :code LIMIT 1');
    $stmt->execute([':code' => $storeCode]);
    $row = $stmt->fetch();
    if (!is_array($row) || !isset($row['store_id'])) {
        return null;
    }

    return (int) $row['store_id'];
}

function getStoreThemeRaw(PDO $pdo, int $storeId): ?string
{
    $stmt = $pdo->prepare(
        'SELECT value
         FROM core_config_data
         WHERE scope = :scope AND scope_id = :scope_id AND path = :path
         ORDER BY config_id DESC
         LIMIT 1'
    );
    $stmt->execute([
        ':scope' => 'stores',
        ':scope_id' => $storeId,
        ':path' => 'design/theme/theme_id',
    ]);
    $row = $stmt->fetch();
    if (is_array($row) && isset($row['value'])) {
        return (string) $row['value'];
    }

    return null;
}

function getDefaultThemeFromConfigDump(string $root): ?string
{
    $config = loadPhpArray($root . '/app/etc/config.php');
    $value = $config['system']['default']['theme']['theme_id'] ?? null;
    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * @param list<string> $identifiers
 * @return list<string>
 */
function missingCmsBlocks(PDO $pdo, array $identifiers): array
{
    $placeholders = implode(',', array_fill(0, count($identifiers), '?'));
    $stmt = $pdo->prepare("SELECT identifier FROM cms_block WHERE identifier IN ({$placeholders})");
    $stmt->execute($identifiers);
    $rows = $stmt->fetchAll();

    $found = [];
    foreach ($rows as $row) {
        if (is_array($row) && isset($row['identifier'])) {
            $found[(string) $row['identifier']] = true;
        }
    }

    $missing = [];
    foreach ($identifiers as $identifier) {
        if (!isset($found[$identifier])) {
            $missing[] = $identifier;
        }
    }

    return $missing;
}

function homepageExists(PDO $pdo, string $identifier): bool
{
    $stmt = $pdo->prepare('SELECT page_id FROM cms_page WHERE identifier = :identifier LIMIT 1');
    $stmt->execute([':identifier' => $identifier]);
    $row = $stmt->fetch();
    return is_array($row) && isset($row['page_id']);
}

function sha1File(string $path): string
{
    $hash = sha1_file($path);
    if ($hash === false) {
        throw new RuntimeException("Falha ao calcular hash: {$path}");
    }
    return $hash;
}

/**
 * @param list<string> $files
 * @return list<string>
 */
function auditSamePathParity(string $root, array $files): array
{
    $issues = [];
    foreach ($files as $relative) {
        $child = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/' . $relative;
        $base = $root . '/app/design/frontend/ayo/ayo_home5/' . $relative;

        if (!is_file($child)) {
            $issues[] = "Arquivo ausente no child: {$relative}";
            continue;
        }
        if (!is_file($base)) {
            $issues[] = "Arquivo ausente no base: {$relative}";
            continue;
        }

        if (sha1File($child) !== sha1File($base)) {
            $issues[] = "Paridade divergente (esperado igual): {$relative}";
        }
    }

    return $issues;
}

/**
 * @param array<string, string> $pairs childRelative => baseRelative
 * @return list<string>
 */
function auditMappedParity(string $root, array $pairs): array
{
    $issues = [];
    foreach ($pairs as $childRel => $baseRel) {
        $child = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/' . $childRel;
        $base = $root . '/app/design/frontend/ayo/ayo_home5/' . $baseRel;

        if (!is_file($child)) {
            $issues[] = "Asset/arquivo mapeado ausente no child: {$childRel}";
            continue;
        }
        if (!is_file($base)) {
            $issues[] = "Asset/arquivo mapeado ausente no base: {$baseRel}";
            continue;
        }

        if (sha1File($child) !== sha1File($base)) {
            $issues[] = "Conteúdo divergente no mapeamento: {$childRel} != {$baseRel}";
        }
    }

    return $issues;
}

/**
 * @return list<string>
 */
function auditAssetReferences(string $root): array
{
    $childThemeRoot = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child';
    $childWebRoot = $childThemeRoot . '/web';
    $baseWebRoot = $root . '/app/design/frontend/ayo/ayo_home5/web';

    $issues = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($childThemeRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        if (!preg_match('/\.(xml|phtml)$/', $path)) {
            continue;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            $issues[] = "Falha ao ler arquivo para auditoria de assets: {$path}";
            continue;
        }

        $patterns = [
            '/<css\s+src="([^"]+)"/',
            '/<script\s+src="([^"]+)"/',
            "/getViewFileUrl\\('([^']+)'\\)/",
        ];

        foreach ($patterns as $pattern) {
            if (!preg_match_all($pattern, $content, $matches)) {
                continue;
            }

            foreach ($matches[1] as $ref) {
                if (!is_string($ref) || !preg_match('#^(css|js)/#', $ref)) {
                    continue;
                }

                $candidates = [
                    $childWebRoot . '/' . $ref,
                    $baseWebRoot . '/' . $ref,
                ];

                if (str_starts_with($ref, 'css/') && str_ends_with($ref, '.css')) {
                    $lessRef = substr($ref, 0, -4) . '.less';
                    $candidates[] = $childWebRoot . '/' . $lessRef;
                    $candidates[] = $baseWebRoot . '/' . $lessRef;
                }

                $exists = false;
                foreach ($candidates as $candidate) {
                    if (is_file($candidate)) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $issues[] = "Referência sem arquivo (child/base): {$ref} em {$path}";
                }
            }
        }
    }

    return $issues;
}

function readFileIfExists(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException('Falha ao ler arquivo: ' . $path);
    }

    return $content;
}

try {
    $root = projectRoot();
    $pdo = connectPdo(loadDbConfig($root));

    out('=== AYO CHILD THEME AUDIT ===');
    out('Root: ' . $root);

    $baseTheme = findTheme($pdo, 'ayo/ayo_home5');
    $childTheme = findTheme($pdo, 'AWA_Custom/ayo_home5_child');

    $failures = [];

    if ($baseTheme === null) {
        $failures[] = 'Tema base não registrado: ayo/ayo_home5';
    } else {
        out(sprintf('[OK] Tema base registrado: ayo/ayo_home5 (ID %s)', (string) $baseTheme['theme_id']));
    }

    if ($childTheme === null) {
        $failures[] = 'Child theme não registrado: AWA_Custom/ayo_home5_child';
    } else {
        out(sprintf('[OK] Child theme registrado: AWA_Custom/ayo_home5_child (ID %s)', (string) $childTheme['theme_id']));
    }

    $storeCode = 'default';
    $storeId = findStoreId($pdo, $storeCode);
    if ($storeId === null) {
        $failures[] = 'Store não encontrado: default';
    } else {
        out(sprintf('[OK] Store "%s" encontrado (ID %d)', $storeCode, $storeId));
        $storeThemeRaw = getStoreThemeRaw($pdo, $storeId);
        if ($storeThemeRaw !== null) {
            out(sprintf('[INFO] core_config_data stores[%d] design/theme/theme_id = %s', $storeId, $storeThemeRaw));
        } else {
            warn('Nenhum override stores/default para design/theme/theme_id em core_config_data');
        }
        $dumpDefaultTheme = getDefaultThemeFromConfigDump($root);
        if ($dumpDefaultTheme !== null) {
            out('[INFO] app/etc/config.php system/default/theme/theme_id = ' . $dumpDefaultTheme);
        }
    }

    $requiredBlocks = [
        'block_top',
        'banner_mid_home5',
        'notification_home5',
        'category1_home5',
        'category2_home5',
        'footer_static5',
        'footer_payment',
        'fixed_right',
        'top-contact',
        'top-left-static',
        'hotline_header',
        'social_block',
        'footer-tags',
        'rokanthemes_custom_menu',
        'rokanthemes_vertical_menu',
    ];
    $missingBlocks = missingCmsBlocks($pdo, $requiredBlocks);
    if ($missingBlocks !== []) {
        $failures[] = 'Blocos CMS críticos ausentes: ' . implode(', ', $missingBlocks);
    } else {
        out('[OK] Blocos CMS críticos presentes (' . count($requiredBlocks) . ')');
    }

    if (!homepageExists($pdo, 'homepage_ayo_home5')) {
        $failures[] = 'Página CMS homepage_ayo_home5 não encontrada';
    } else {
        out('[OK] Página CMS homepage_ayo_home5 encontrada');
    }

    $mustMatchSamePath = [
        'GrupoAwamotos_B2B/layout/default.xml',
        'GrupoAwamotos_B2B/templates/product/customer-price-info.phtml',
        'Magento_Theme/templates/html/b2b-mode-badge.phtml',
        'Rokanthemes_AjaxSuite/templates/popup_wrapper.phtml',
        'Rokanthemes_CustomMenu/templates/topmenu.phtml',
        'Rokanthemes_Themeoption/layout/default.xml',
        'Rokanthemes_Themeoption/templates/html/footer.phtml',
        'Rokanthemes_Themeoption/templates/html/header.phtml',
        'Rokanthemes_VerticalMenu/templates/sidemenu.phtml',
    ];
    foreach (auditSamePathParity($root, $mustMatchSamePath) as $issue) {
        $failures[] = $issue;
    }
    if ($failures === []) {
        out('[OK] Paridade dos overrides críticos de mesmo caminho está consistente');
    }

    $expectedCustomOverrides = [
        'composer.json',
        'requirejs-config.js',
        'Magento_Catalog/layout/catalog_product_view.xml',
        'Magento_Cms/layout/cms_index_index.xml',
        'Magento_Theme/layout/default_head_blocks.xml',
        'Magento_Theme/templates/html/awa-custom-js-loader.phtml',
        'Magento_Theme/templates/html/awa-post-themeoption-head-css.phtml',
        'Rokanthemes_SearchSuiteAutocomplete/templates/autocomplete.phtml',
        'web/js/awa-search-autocomplete-compat.js',
        'web/js/awa-search-category-chosen.js',
        'web/js/awa-custom-b2b-cart-checkout-compat.js',
        'web/js/awa-custom-home-category-compat.js',
        'web/js/awa-custom-compat-bootstrap.js',
        'web/css/awa-01-tokens.css',
        'web/css/awa-02-base.css',
        'web/css/awa-03-layout.css',
        'web/css/awa-04-components.css',
        'web/css/awa-05-pages.css',
        'web/css/awa-06-responsive.css',
    ];
    foreach ($expectedCustomOverrides as $relative) {
        $childPath = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/' . $relative;
        if (!is_file($childPath)) {
            $failures[] = 'Override custom esperado ausente no child: ' . $relative;
        }
    }
    if ($failures === []) {
        out('[OK] Overrides custom esperados do child estão presentes');
    }

    $mappedPairs = [
        'web/css/awa-custom-brand-bridge.css' => 'web/css/awa-round2-brand-bridge.css',
        'web/css/awa-custom-header-minicart.css' => 'web/css/awa-round2-header-minicart.css',
        'web/css/awa-custom-home-owl-tabs.css' => 'web/css/awa-round2-home-owl-tabs.css',
        'web/css/awa-custom-footer-trust.css' => 'web/css/awa-round3-footer-trust.css',
        'web/css/awa-custom-pdp-conversion.css' => 'web/css/awa-round3-pdp-conversion.css',
        'web/css/awa-custom-global-brand.css' => 'web/css/awa-round4-global-brand.css',
        'web/css/awa-custom-ux-refine.css' => 'web/css/awa-round5-ux-refine.css',
        'web/css/awa-custom-b2b-gate-refine.css' => 'web/css/awa-round6-b2b-gate-refine.css',
        'web/css/awa-custom-auth-refine.css' => 'web/css/awa-round7-auth-refine.css',
        'web/css/awa-custom-plp-search-cart-refine.css' => 'web/css/awa-round8-plp-search-cart-refine.css',
        'web/css/awa-custom-post-themeoption-overrides.css' => 'web/css/awa-round9-post-themeoption-overrides.css',
        'web/css/awa-custom-footer-light-perfect.css' => 'web/css/awa-round10-footer-light-perfect.css',
        'web/css/awa-custom-components-b2b-foundation.css' => 'web/css/awa-components-b2b-foundation.css',
        'web/css/awa-custom-compat-b2b-nav-plp-cart-checkout.css' => 'web/css/awa-compat-b2b-nav-plp-cart-checkout.css',
        'web/css/awa-custom-page-b2b-cart-checkout-premium.css' => 'web/css/awa-page-b2b-cart-checkout-premium.css',
        'web/css/awa-custom-page-home-category-premium.css' => 'web/css/awa-page-home-category-premium.css',
        'web/js/awa-header-minicart-ui.js' => 'web/js/awa-round2-header-minicart-ui.js',
        'web/js/awa-home-owl-tabs-ui.js' => 'web/js/awa-round2-home-owl-tabs-ui.js',
        'web/js/awa-footer-ux.js' => 'web/js/awa-round3-footer-ux.js',
        'web/js/awa-pdp-sticky-cta.js' => 'web/js/awa-round3-pdp-sticky-cta.js',
        'web/js/awa-custom-menu-compat.js' => 'web/js/awa-custom-menu-compat.js',
        'web/js/awa-vertical-menu-compat.js' => 'web/js/awa-vertical-menu-compat.js',
    ];
    $mappedParityIssues = auditMappedParity($root, $mappedPairs);
    foreach ($mappedParityIssues as $issue) {
        $failures[] = $issue;
    }
    if ($mappedParityIssues === []) {
        out('[OK] Mapeamento de assets/overrides child->base consistente');
    }

    $themeXmlPath = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/theme.xml';
    $themeXmlContent = readFileIfExists($themeXmlPath);
    if ($themeXmlContent === null) {
        $failures[] = 'theme.xml do child theme ausente';
    } else {
        if (!str_contains($themeXmlContent, '<parent>ayo/ayo_home5</parent>')) {
            $failures[] = 'theme.xml do child não define parent ayo/ayo_home5';
        }
        if (!str_contains($themeXmlContent, '<title>AWA Custom - Ayo Home5 Child</title>')) {
            $failures[] = 'theme.xml do child sem título esperado (AWA Custom - Ayo Home5 Child)';
        }
    }

    $registrationPhpPath = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/registration.php';
    $registrationPhpContent = readFileIfExists($registrationPhpPath);
    if ($registrationPhpContent === null) {
        $failures[] = 'registration.php do child theme ausente';
    } else {
        if (!str_contains($registrationPhpContent, 'ComponentRegistrar::THEME')) {
            $failures[] = 'registration.php do child não registra ComponentRegistrar::THEME';
        }
        if (!str_contains($registrationPhpContent, 'frontend/AWA_Custom/ayo_home5_child')) {
            $failures[] = 'registration.php do child sem código frontend/AWA_Custom/ayo_home5_child';
        }
    }

    $composerJsonPath = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/composer.json';
    $composerJsonContent = readFileIfExists($composerJsonPath);
    if ($composerJsonContent === null) {
        $failures[] = 'composer.json do child theme ausente';
    } else {
        try {
            /** @var array<string, mixed> $composerData */
            $composerData = json_decode($composerJsonContent, true, 512, JSON_THROW_ON_ERROR);
            if (($composerData['type'] ?? null) !== 'magento2-theme') {
                $failures[] = 'composer.json do child com type diferente de magento2-theme';
            }
            if (($composerData['autoload']['files'][0] ?? null) !== 'registration.php') {
                $failures[] = 'composer.json do child sem autoload.files -> registration.php';
            }
            if (($composerData['name'] ?? '') === '') {
                $failures[] = 'composer.json do child sem campo name';
            }
        } catch (Throwable $e) {
            $failures[] = 'composer.json do child inválido: ' . $e->getMessage();
        }
    }

    $requireJsConfigPath = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/requirejs-config.js';
    $requireJsConfigContent = readFileIfExists($requireJsConfigPath);
    if ($requireJsConfigContent === null) {
        $failures[] = 'requirejs-config.js do child theme ausente';
    } else {
        if (!str_contains($requireJsConfigContent, 'awaCustomCompatBootstrap')) {
            $failures[] = 'requirejs-config.js do child sem alias awaCustomCompatBootstrap';
        }
        if (!str_contains($requireJsConfigContent, 'js/awa-custom-compat-bootstrap')) {
            $failures[] = 'requirejs-config.js do child sem path js/awa-custom-compat-bootstrap';
        }
    }

    $autocompleteTemplate = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/Rokanthemes_SearchSuiteAutocomplete/templates/autocomplete.phtml';
    if (is_file($autocompleteTemplate)) {
        $autocompleteContent = file_get_contents($autocompleteTemplate);
        if ($autocompleteContent === false) {
            $failures[] = 'Falha ao ler template de autocomplete do child';
        } else {
            if (str_contains($autocompleteContent, '"js/awa-search-autocomplete-compat"')) {
                $failures[] = 'Template de autocomplete ainda inicializa js/awa-search-autocomplete-compat localmente (duplicado)';
            }
            if (!str_contains($autocompleteContent, '"js/awa-search-category-chosen"')) {
                $failures[] = 'Template de autocomplete não inicializa js/awa-search-category-chosen via x-magento-init';
            }
            if (preg_match("~require\\s*\\(\\s*\\[\\s*'jquery'\\s*,\\s*'rokanthemes/choose'~", $autocompleteContent) === 1) {
                $failures[] = 'Template de autocomplete ainda contém require() inline para rokanthemes/choose';
            }
        }
    }

    $customLoaderTemplate = $root . '/app/design/frontend/AWA_Custom/ayo_home5_child/Magento_Theme/templates/html/awa-custom-js-loader.phtml';
    if (is_file($customLoaderTemplate)) {
        $customLoaderContent = file_get_contents($customLoaderTemplate);
        if ($customLoaderContent === false) {
            $failures[] = 'Falha ao ler loader JS custom do child';
        } else {
            if (!str_contains($customLoaderContent, 'text/x-magento-init')) {
                $failures[] = 'Loader JS custom do child sem x-magento-init para bootstrap compat';
            }
            if (str_contains($customLoaderContent, "require(['jquery', 'js/awa-search-autocomplete-compat']")) {
                $failures[] = 'Loader JS custom do child ainda contém require() inline do search compat';
            }
        }
    }

    $assetRefIssues = auditAssetReferences($root);
    foreach ($assetRefIssues as $issue) {
        $failures[] = $issue;
    }
    if ($assetRefIssues === []) {
        out('[OK] Referências de assets do child resolvem em child ou parent');
    }

    if ($failures !== []) {
        out('');
        out('=== FALHAS ===');
        foreach ($failures as $failure) {
            fail($failure);
        }
        exit(EXIT_FAIL);
    }

    out('');
    out('[OK] Auditoria do child theme concluída sem falhas');
    exit(EXIT_OK);
} catch (Throwable $e) {
    fail($e->getMessage());
    exit(EXIT_FAIL);
}

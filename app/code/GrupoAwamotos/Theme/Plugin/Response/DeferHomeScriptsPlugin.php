<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Response;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as ResponseHttp;
use Magento\Framework\View\Asset\Repository as AssetRepository;

/**
 * Home PSI — adia require config + merged bundle até interação (TBT/Lighthouse).
 */
class DeferHomeScriptsPlugin
{
    private const HOME_ACTION = 'cms_index_index';

    public function __construct(
        private readonly HttpRequest $request,
        private readonly AssetRepository $assetRepository,
    ) {
    }

    public function beforeSendResponse(ResponseHttp $subject): void
    {
        if ($this->request->getFullActionName() !== self::HOME_ACTION) {
            return;
        }

        $contentType = $subject->getHeader('Content-Type');
        if ($contentType && stripos($contentType->getFieldValue(), 'text/html') === false) {
            return;
        }

        $html = (string) $subject->getBody();
        if ($html === '' || str_contains($html, 'awa-home-bootstrap-defer.js')) {
            return;
        }

        $pattern = '#<script(?![^>]*type=["\']text/plain["\'])[^>]*>\s*(var LOCALE\s*=.*?require\s*=\s*\{.*?\};)\s*</script>\s*'
            . '<script(?![^>]*defer)[^>]*src="([^"]*_cache/merged/[^"]+\.min\.js)"[^>]*>\s*</script>#s';

        if (!preg_match($pattern, $html, $matches)) {
            return;
        }

        $inlineJs = $matches[1];
        $mergedSrc = $matches[2];

        try {
            $deferSrc = $this->assetRepository->getUrl('js/awa-home-bootstrap-defer.js');
        } catch (\Throwable) {
            return;
        }

        $replacement = '<script type="text/plain" id="awa-home-bootstrap-inline">'
            . $inlineJs
            . '</script><script type="text/plain" id="awa-home-bootstrap-merged" data-src="'
            . htmlspecialchars($mergedSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '"></script><script src="'
            . htmlspecialchars($deferSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" defer></script>';

        $subject->setBody((string) preg_replace($pattern, $replacement, $html, 1));
    }
}

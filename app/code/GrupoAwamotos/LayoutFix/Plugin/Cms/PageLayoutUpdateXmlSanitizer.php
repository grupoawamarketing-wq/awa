<?php

declare(strict_types=1);

namespace GrupoAwamotos\LayoutFix\Plugin\Cms;

use Magento\Cms\Model\Page;
use Psr\Log\LoggerInterface;

/**
 * Sanitiza o XML salvo no CMS (Layout Update XML / Custom Layout Update XML).
 *
 * Problemas reais observados em produção:
 * - tags inválidas em minúsculas (referenceblock/referencecontainer)
 * - inclusão indevida de wrappers <page> / <body>, gerando XML mesclado inválido
 */
final class PageLayoutUpdateXmlSanitizer
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param mixed $result
     */
    public function afterGetLayoutUpdateXml(Page $subject, $result): string
    {
        return $this->sanitize((string)$result, $subject, 'layout_update_xml');
    }

    /**
     * @param mixed $result
     */
    public function afterGetCustomLayoutUpdateXml(Page $subject, $result): string
    {
        return $this->sanitize((string)$result, $subject, 'custom_layout_update_xml');
    }

    private function sanitize(string $xml, Page $page, string $field): string
    {
        $original = $xml;
        $xml = trim($xml);
        if ($xml === '') {
            return $xml;
        }

        // Remove wrappers indevidos que às vezes são colados no Admin.
        $xml = (string)preg_replace('~<\?xml[^>]*\?>\s*~i', '', $xml);
        $xml = (string)preg_replace('~<\s*page\b[^>]*>\s*~i', '', $xml);
        $xml = (string)preg_replace('~\s*</\s*page\s*>\s*~i', '', $xml);
        $xml = (string)preg_replace('~<\s*/?\s*body\b[^>]*>\s*~i', '', $xml);

        // Normaliza tags de referência para o casing esperado pelo schema.
        $xml = (string)preg_replace('~<\s*(/?)\s*referenceblock\b~i', '<$1referenceBlock', $xml);
        $xml = (string)preg_replace('~<\s*(/?)\s*referencecontainer\b~i', '<$1referenceContainer', $xml);

        $xml = trim($xml);

        if ($xml !== $original) {
            $this->logger->warning('[LayoutFix] Sanitized CMS layout update XML', [
                'page_id' => (int)$page->getId(),
                'identifier' => (string)$page->getIdentifier(),
                'field' => $field,
            ]);
        }

        return $xml;
    }
}

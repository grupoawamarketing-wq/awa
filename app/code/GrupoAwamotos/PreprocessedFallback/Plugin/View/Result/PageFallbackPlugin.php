<?php
declare(strict_types=1);

namespace GrupoAwamotos\PreprocessedFallback\Plugin\View\Result;

use GrupoAwamotos\PreprocessedFallback\Model\PreprocessedTemplateHealer;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\View\Result\Page;
use Throwable;

final class PageFallbackPlugin
{
    private PreprocessedTemplateHealer $templateHealer;

    public function __construct(
        PreprocessedTemplateHealer $templateHealer
    ) {
        $this->templateHealer = $templateHealer;
    }

    /**
     * @throws Throwable
     */
    public function aroundRenderResult(
        Page $subject,
        callable $proceed,
        ResponseInterface $httpResponse
    ): Page {
        try {
            return $proceed($httpResponse);
        } catch (Throwable $exception) {
            $missingPath = $this->templateHealer->extractMissingPathFromMessage($exception->getMessage());
            if ($missingPath === null || !$this->templateHealer->isPreprocessedPath($missingPath)) {
                throw $exception;
            }

            if (!$this->templateHealer->ensurePreprocessedExists($missingPath)) {
                throw $exception;
            }

            return $proceed($httpResponse);
        }
    }
}

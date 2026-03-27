<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Model;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Psr\Log\LoggerInterface;

class CmsDirectiveSanitizer
{
    /**
     * Normaliza diretivas CMS com aspas duplas ou duplas escapadas.
     *
     * Exemplos convertidos:
     * - {{store url="customer-service"}}
     * - {{store url=\"customer-service\"}}
     */
    private const DIRECTIVE_PATTERN = '/\{\{(store url|media url|config path)=\\\\?"([^"]+)\\\\?"\}\}/';

    public function __construct(
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly BlockFactory $blockFactory,
        private readonly PageFactory $pageFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{
     *     blocks:int,
     *     pages:int,
     *     failed_blocks:string[],
     *     failed_pages:string[]
     * }
     */
    public function sanitizeAll(): array
    {
        $blocks = $this->sanitizeBlocks();
        $pages = $this->sanitizePages();

        return [
            'blocks' => $blocks['updated'],
            'pages' => $pages['updated'],
            'failed_blocks' => $blocks['failed'],
            'failed_pages' => $pages['failed'],
        ];
    }

    /**
     * @return array{updated:int,failed:string[]}
     */
    public function sanitizeBlocks(): array
    {
        $updatedCount = 0;
        $failedIdentifiers = [];
        $collection = $this->blockCollectionFactory->create();

        foreach ($collection as $block) {
            $content = (string) $block->getContent();
            $sanitizedContent = $this->sanitizeContent($content);

            if ($sanitizedContent === $content) {
                continue;
            }

            try {
                $sanitizableBlock = $this->blockFactory->create();
                $sanitizableBlock->load((int) $block->getId());
                $sanitizableBlock->setContent($sanitizedContent);
                $sanitizableBlock->save();
                $updatedCount++;
            } catch (\Throwable $exception) {
                $identifier = (string) $block->getIdentifier();
                $failedIdentifiers[] = $identifier;
                $this->logger->error(
                    sprintf(
                        '[CmsDirectiveSanitizer] Erro ao sanitizar bloco "%s": %s',
                        $identifier,
                        $exception->getMessage()
                    )
                );
            }
        }

        return [
            'updated' => $updatedCount,
            'failed' => $failedIdentifiers,
        ];
    }

    /**
     * @return array{updated:int,failed:string[]}
     */
    public function sanitizePages(): array
    {
        $updatedCount = 0;
        $failedIdentifiers = [];
        $collection = $this->pageCollectionFactory->create();

        foreach ($collection as $page) {
            $content = (string) $page->getContent();
            $sanitizedContent = $this->sanitizeContent($content);

            if ($sanitizedContent === $content) {
                continue;
            }

            try {
                $sanitizablePage = $this->pageFactory->create();
                $sanitizablePage->load((int) $page->getId());
                $sanitizablePage->setContent($sanitizedContent);
                $sanitizablePage->save();
                $updatedCount++;
            } catch (\Throwable $exception) {
                $identifier = (string) $page->getIdentifier();
                $failedIdentifiers[] = $identifier;
                $this->logger->error(
                    sprintf(
                        '[CmsDirectiveSanitizer] Erro ao sanitizar página "%s": %s',
                        $identifier,
                        $exception->getMessage()
                    )
                );
            }
        }

        return [
            'updated' => $updatedCount,
            'failed' => $failedIdentifiers,
        ];
    }

    public function sanitizeContent(string $content): string
    {
        return (string) preg_replace(
            self::DIRECTIVE_PATTERN,
            '{{$1=\'$2\'}}',
            $content
        );
    }
}

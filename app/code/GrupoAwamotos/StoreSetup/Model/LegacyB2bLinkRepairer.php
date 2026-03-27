<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Model;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Psr\Log\LoggerInterface;
use Rokanthemes\Blog\Model\PostFactory;
use Rokanthemes\Blog\Model\ResourceModel\Post as PostResource;
use Rokanthemes\Blog\Model\ResourceModel\Post\CollectionFactory as PostCollectionFactory;

class LegacyB2bLinkRepairer
{
    /**
     * @var array<string, string>
     */
    private const LINK_REPLACEMENTS = [
        'b2b/account/register' => 'b2b/register',
        'b2b/quote/request' => 'b2b/quote/index',
    ];

    public function __construct(
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly PostCollectionFactory $postCollectionFactory,
        private readonly BlockFactory $blockFactory,
        private readonly PageFactory $pageFactory,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{
     *     blocks:int,
     *     pages:int,
     *     posts:int,
     *     replaced:int,
     *     failed_blocks:string[],
     *     failed_pages:string[],
     *     failed_posts:string[]
     * }
     */
    public function repairAll(): array
    {
        $blocks = $this->repairBlocks();
        $pages = $this->repairPages();
        $posts = $this->repairPosts();

        return [
            'blocks' => $blocks['updated'],
            'pages' => $pages['updated'],
            'posts' => $posts['updated'],
            'replaced' => $blocks['replaced'] + $pages['replaced'] + $posts['replaced'],
            'failed_blocks' => $blocks['failed'],
            'failed_pages' => $pages['failed'],
            'failed_posts' => $posts['failed'],
        ];
    }

    /**
     * @return array{updated:int,replaced:int,failed:string[]}
     */
    public function repairBlocks(): array
    {
        $updatedCount = 0;
        $replacedCount = 0;
        $failedIdentifiers = [];
        $collection = $this->blockCollectionFactory->create();

        foreach ($collection as $block) {
            $content = (string) $block->getContent();
            $repairResult = $this->repairContent($content);

            if ($repairResult['content'] === $content) {
                continue;
            }

            $identifier = (string) $block->getIdentifier();

            try {
                $repairableBlock = $this->blockFactory->create();
                $repairableBlock->load((int) $block->getId());
                $repairableBlock->setContent($repairResult['content']);
                $repairableBlock->save();
                $updatedCount++;
                $replacedCount += $repairResult['replaced'];
            } catch (\Throwable $exception) {
                $failedIdentifiers[] = $identifier;
                $this->logger->error(
                    sprintf(
                        '[LegacyB2bLinkRepairer] Erro ao reparar bloco "%s": %s',
                        $identifier,
                        $exception->getMessage()
                    )
                );
            }
        }

        return [
            'updated' => $updatedCount,
            'replaced' => $replacedCount,
            'failed' => $failedIdentifiers,
        ];
    }

    /**
     * @return array{updated:int,replaced:int,failed:string[]}
     */
    public function repairPages(): array
    {
        $updatedCount = 0;
        $replacedCount = 0;
        $failedIdentifiers = [];
        $collection = $this->pageCollectionFactory->create();

        foreach ($collection as $page) {
            $content = (string) $page->getContent();
            $repairResult = $this->repairContent($content);

            if ($repairResult['content'] === $content) {
                continue;
            }

            $identifier = (string) $page->getIdentifier();

            try {
                $repairablePage = $this->pageFactory->create();
                $repairablePage->load((int) $page->getId());
                $repairablePage->setContent($repairResult['content']);
                $repairablePage->save();
                $updatedCount++;
                $replacedCount += $repairResult['replaced'];
            } catch (\Throwable $exception) {
                $failedIdentifiers[] = $identifier;
                $this->logger->error(
                    sprintf(
                        '[LegacyB2bLinkRepairer] Erro ao reparar página "%s": %s',
                        $identifier,
                        $exception->getMessage()
                    )
                );
            }
        }

        return [
            'updated' => $updatedCount,
            'replaced' => $replacedCount,
            'failed' => $failedIdentifiers,
        ];
    }

    /**
     * @return array{updated:int,replaced:int,failed:string[]}
     */
    public function repairPosts(): array
    {
        $updatedCount = 0;
        $replacedCount = 0;
        $failedIdentifiers = [];
        $collection = $this->postCollectionFactory->create();

        foreach ($collection as $post) {
            $content = (string) $post->getContent();
            $repairResult = $this->repairContent($content);

            if ($repairResult['content'] === $content) {
                continue;
            }

            $identifier = (string) $post->getIdentifier();

            try {
                $repairablePost = $this->postFactory->create();
                $this->postResource->load($repairablePost, (int) $post->getId());
                $repairablePost->setContent($repairResult['content']);
                $this->postResource->save($repairablePost);
                $updatedCount++;
                $replacedCount += $repairResult['replaced'];
            } catch (\Throwable $exception) {
                $failedIdentifiers[] = $identifier;
                $this->logger->error(
                    sprintf(
                        '[LegacyB2bLinkRepairer] Erro ao reparar post "%s": %s',
                        $identifier,
                        $exception->getMessage()
                    )
                );
            }
        }

        return [
            'updated' => $updatedCount,
            'replaced' => $replacedCount,
            'failed' => $failedIdentifiers,
        ];
    }

    /**
     * @return array{content:string,replaced:int}
     */
    public function repairContent(string $content): array
    {
        $replacedCount = 0;
        $repairedContent = str_replace(
            array_keys(self::LINK_REPLACEMENTS),
            array_values(self::LINK_REPLACEMENTS),
            $content,
            $replacedCount
        );

        return [
            'content' => $repairedContent,
            'replaced' => $replacedCount,
        ];
    }
}

<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Cron;

use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Cron: publica automaticamente em redes sociais via N8N webhook.
 *
 * Detecta:
 * - Novos produtos (criados nas últimas 24h)
 * - Produtos em promoção (special_price ativado nas últimas 24h)
 *
 * Envia payload com dados do produto para webhook N8N, que distribui
 * para Instagram (via Meta Graph API) e Facebook (page post).
 *
 * Roda diariamente às 08:00 (horário comercial, melhor engajamento).
 */
class SocialPostPublisher
{
    private const MAX_POSTS_PER_RUN = 5;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isSocialPostEnabled()) {
            return;
        }

        $webhookUrl = $this->config->getSocialPostWebhookUrl();
        if (empty($webhookUrl)) {
            $this->logger->warning('[SocialPost] N8N webhook URL not configured');
            return;
        }

        try {
            $newProducts = $this->getNewProducts();
            $onSaleProducts = $this->getNewOnSaleProducts();

            $posted = 0;
            $failed = 0;

            foreach ($newProducts as $product) {
                if ($posted >= self::MAX_POSTS_PER_RUN) {
                    break;
                }
                try {
                    $this->publishPost($product, 'new_product', $webhookUrl);
                    $this->logPost((int) $product['entity_id'], 'new_product');
                    $posted++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->debug('[SocialPost] Failed new_product ' . $product['sku']);
                }
            }

            foreach ($onSaleProducts as $product) {
                if ($posted >= self::MAX_POSTS_PER_RUN) {
                    break;
                }
                try {
                    $this->publishPost($product, 'on_sale', $webhookUrl);
                    $this->logPost((int) $product['entity_id'], 'on_sale');
                    $posted++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->debug('[SocialPost] Failed on_sale ' . $product['sku']);
                }
            }

            if ($posted > 0 || $failed > 0) {
                $this->logger->info('[SocialPost] Completed', [
                    'posted' => $posted,
                    'failed' => $failed,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('[SocialPost] Cron error: ' . $e->getMessage());
        }
    }

    /**
     * Produtos criados nas últimas 24h que ainda não foram postados.
     *
     * @return array<int, array{entity_id: int, sku: string, name: string, price: float, url_key: string, image: string|null}>
     */
    private function getNewProducts(): array
    {
        $connection = $this->resource->getConnection();
        $yesterday = date('Y-m-d H:i:s', strtotime('-24 hours'));

        $nameAttrId = $this->getAttributeId($connection, 'name');
        $priceAttrId = $this->getAttributeId($connection, 'price');
        $urlKeyAttrId = $this->getAttributeId($connection, 'url_key');
        $imageAttrId = $this->getAttributeId($connection, 'image');

        $select = $connection->select()
            ->from(
                ['cpe' => $this->resource->getTableName('catalog_product_entity')],
                ['entity_id', 'sku']
            )
            ->join(
                ['pname' => $this->resource->getTableName('catalog_product_entity_varchar')],
                'pname.entity_id = cpe.entity_id AND pname.attribute_id = ' . $nameAttrId . ' AND pname.store_id = 0',
                ['name' => 'pname.value']
            )
            ->joinLeft(
                ['pprice' => $this->resource->getTableName('catalog_product_entity_decimal')],
                'pprice.entity_id = cpe.entity_id AND pprice.attribute_id = ' . $priceAttrId . ' AND pprice.store_id = 0',
                ['price' => 'pprice.value']
            )
            ->joinLeft(
                ['purl' => $this->resource->getTableName('catalog_product_entity_varchar')],
                'purl.entity_id = cpe.entity_id AND purl.attribute_id = ' . $urlKeyAttrId . ' AND purl.store_id = 0',
                ['url_key' => 'purl.value']
            )
            ->joinLeft(
                ['pimg' => $this->resource->getTableName('catalog_product_entity_varchar')],
                'pimg.entity_id = cpe.entity_id AND pimg.attribute_id = ' . $imageAttrId . ' AND pimg.store_id = 0',
                ['image' => 'pimg.value']
            )
            ->where('cpe.created_at >= ?', $yesterday)
            ->where('cpe.type_id = ?', 'simple')
            // Not already posted
            ->where('NOT EXISTS (?)', new \Zend_Db_Expr(
                $connection->select()
                    ->from(
                        ['sp' => $this->resource->getTableName('awa_whatsapp_social_post_log')],
                        [new \Zend_Db_Expr('1')]
                    )
                    ->where('sp.product_id = cpe.entity_id')
                    ->where('sp.post_type = ?', 'new_product')
                    ->limit(1)
            ))
            ->limit(self::MAX_POSTS_PER_RUN);

        return $connection->fetchAll($select);
    }

    /**
     * Produtos com special_price ativado nas últimas 24h que ainda não foram postados.
     *
     * @return array<int, array{entity_id: int, sku: string, name: string, price: float, special_price: float, url_key: string, image: string|null}>
     */
    private function getNewOnSaleProducts(): array
    {
        $connection = $this->resource->getConnection();
        $today = date('Y-m-d');

        $nameAttrId = $this->getAttributeId($connection, 'name');
        $priceAttrId = $this->getAttributeId($connection, 'price');
        $specialPriceAttrId = $this->getAttributeId($connection, 'special_price');
        $specialFromAttrId = $this->getAttributeId($connection, 'special_from_date');
        $urlKeyAttrId = $this->getAttributeId($connection, 'url_key');
        $imageAttrId = $this->getAttributeId($connection, 'image');

        if ($specialPriceAttrId === 0 || $specialFromAttrId === 0) {
            return [];
        }

        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $select = $connection->select()
            ->from(
                ['cpe' => $this->resource->getTableName('catalog_product_entity')],
                ['entity_id', 'sku']
            )
            ->join(
                ['pname' => $this->resource->getTableName('catalog_product_entity_varchar')],
                'pname.entity_id = cpe.entity_id AND pname.attribute_id = ' . $nameAttrId . ' AND pname.store_id = 0',
                ['name' => 'pname.value']
            )
            ->join(
                ['pprice' => $this->resource->getTableName('catalog_product_entity_decimal')],
                'pprice.entity_id = cpe.entity_id AND pprice.attribute_id = ' . $priceAttrId . ' AND pprice.store_id = 0',
                ['price' => 'pprice.value']
            )
            ->join(
                ['sprice' => $this->resource->getTableName('catalog_product_entity_decimal')],
                'sprice.entity_id = cpe.entity_id AND sprice.attribute_id = ' . $specialPriceAttrId . ' AND sprice.store_id = 0',
                ['special_price' => 'sprice.value']
            )
            ->join(
                ['sfrom' => $this->resource->getTableName('catalog_product_entity_datetime')],
                'sfrom.entity_id = cpe.entity_id AND sfrom.attribute_id = ' . $specialFromAttrId . ' AND sfrom.store_id = 0',
                []
            )
            ->joinLeft(
                ['purl' => $this->resource->getTableName('catalog_product_entity_varchar')],
                'purl.entity_id = cpe.entity_id AND purl.attribute_id = ' . $urlKeyAttrId . ' AND purl.store_id = 0',
                ['url_key' => 'purl.value']
            )
            ->joinLeft(
                ['pimg' => $this->resource->getTableName('catalog_product_entity_varchar')],
                'pimg.entity_id = cpe.entity_id AND pimg.attribute_id = ' . $imageAttrId . ' AND pimg.store_id = 0',
                ['image' => 'pimg.value']
            )
            ->where('sfrom.value >= ?', $yesterday)
            ->where('sfrom.value <= ?', $today . ' 23:59:59')
            ->where('sprice.value IS NOT NULL')
            ->where('sprice.value > 0')
            ->where('cpe.type_id = ?', 'simple')
            // Not already posted
            ->where('NOT EXISTS (?)', new \Zend_Db_Expr(
                $connection->select()
                    ->from(
                        ['sp' => $this->resource->getTableName('awa_whatsapp_social_post_log')],
                        [new \Zend_Db_Expr('1')]
                    )
                    ->where('sp.product_id = cpe.entity_id')
                    ->where('sp.post_type = ?', 'on_sale')
                    ->limit(1)
            ))
            ->limit(self::MAX_POSTS_PER_RUN);

        return $connection->fetchAll($select);
    }

    /**
     * Publica o post via webhook N8N.
     */
    private function publishPost(array $product, string $postType, string $webhookUrl): void
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);

        $productUrl = rtrim($baseUrl, '/') . '/' . ($product['url_key'] ?? $product['sku']) . '.html';
        $imageUrl = !empty($product['image']) ? $mediaUrl . 'catalog/product' . $product['image'] : '';

        $discount = '';
        if ($postType === 'on_sale' && !empty($product['special_price']) && !empty($product['price'])) {
            $pct = round((1 - (float) $product['special_price'] / (float) $product['price']) * 100);
            $discount = $pct . '% OFF';
        }

        $payload = [
            'type' => $postType,
            'product' => [
                'sku' => $product['sku'],
                'name' => $product['name'],
                'price' => (float) ($product['price'] ?? 0),
                'special_price' => (float) ($product['special_price'] ?? 0),
                'discount' => $discount,
                'url' => $productUrl,
                'image_url' => $imageUrl,
            ],
            'source' => 'magento_whatsapp_commerce',
            'timestamp' => date('c'),
        ];

        $this->curl->setHeaders(['Content-Type' => 'application/json']);
        $this->curl->setTimeout(15);
        $this->curl->post($webhookUrl, json_encode($payload));

        $status = $this->curl->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('N8N webhook returned HTTP ' . $status);
        }
    }

    private function logPost(int $productId, string $postType): void
    {
        try {
            $connection = $this->resource->getConnection();
            $connection->insert(
                $this->resource->getTableName('awa_whatsapp_social_post_log'),
                [
                    'product_id' => $productId,
                    'post_type' => $postType,
                    'posted_at' => date('Y-m-d H:i:s'),
                ]
            );
        } catch (\Exception $e) {
            $this->logger->debug('[SocialPost] Failed to log post: ' . $e->getMessage());
        }
    }

    private function getAttributeId(\Magento\Framework\DB\Adapter\AdapterInterface $connection, string $code): int
    {
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', $code)
                ->where('entity_type_id = ?', 4) // catalog_product
                ->limit(1)
        );
    }
}

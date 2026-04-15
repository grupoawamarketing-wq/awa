<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Cron;

use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

/**
 * Cron: gera meta descriptions com IA (Groq Llama) para produtos sem meta_description.
 *
 * - Processa até 20 produtos por execução (rate limit Groq free tier)
 * - Usa Groq API com Llama 3.3 70B (mesma IA do chat WhatsApp)
 * - Meta description otimizada para SEO: 150-160 chars, com nome do produto e keywords
 * - Salva diretamente no produto, status pending = requer aprovação no admin
 *
 * Roda diariamente às 03:00 (baixo tráfego).
 */
class MetaDescriptionGenerator
{
    private const MAX_PRODUCTS_PER_RUN = 20;
    private const META_MAX_LENGTH = 160;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ResourceConnection $resource,
        private readonly Curl $curl,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isMetaGenerationEnabled()) {
            return;
        }

        $apiKey = $this->config->getGroqApiKey();
        if (empty($apiKey)) {
            $this->logger->warning('[MetaGenerator] Groq API key not configured');
            return;
        }

        try {
            $products = $this->getProductsWithoutMeta();

            if (empty($products)) {
                $this->logger->debug('[MetaGenerator] All products have meta descriptions');
                return;
            }

            $generated = 0;
            $failed = 0;

            foreach ($products as $product) {
                try {
                    $meta = $this->generateMetaDescription($product, $apiKey);

                    if (!empty($meta)) {
                        $this->saveMetaDescription((int) $product['entity_id'], $meta);
                        $generated++;
                        $this->logger->debug('[MetaGenerator] Generated for SKU ' . $product['sku']);
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->warning('[MetaGenerator] Failed for SKU ' . $product['sku'], [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->info('[MetaGenerator] Completed', [
                'processed' => count($products),
                'generated' => $generated,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[MetaGenerator] Cron error: ' . $e->getMessage());
        }
    }

    /**
     * Run for a specific number of products (used by CLI)
     */
    public function generateBatch(int $limit = self::MAX_PRODUCTS_PER_RUN): array
    {
        $apiKey = $this->config->getGroqApiKey();
        if (empty($apiKey)) {
            return ['success' => false, 'message' => 'Groq API key not configured'];
        }

        $products = $this->getProductsWithoutMeta($limit);
        if (empty($products)) {
            return ['success' => true, 'generated' => 0, 'message' => 'All products have meta descriptions'];
        }

        $generated = 0;
        $failed = 0;
        $results = [];

        foreach ($products as $product) {
            try {
                $meta = $this->generateMetaDescription($product, $apiKey);
                if (!empty($meta)) {
                    $this->saveMetaDescription((int) $product['entity_id'], $meta);
                    $generated++;
                    $results[] = ['sku' => $product['sku'], 'meta' => $meta];
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return [
            'success' => true,
            'generated' => $generated,
            'failed' => $failed,
            'total' => count($products),
            'results' => $results,
        ];
    }

    /**
     * @return array<int, array{entity_id: int, sku: string, name: string, short_description: string|null}>
     */
    private function getProductsWithoutMeta(int $limit = self::MAX_PRODUCTS_PER_RUN): array
    {
        $connection = $this->resource->getConnection();

        $metaAttrId = $this->getAttributeId($connection, 'meta_description');
        $nameAttrId = $this->getAttributeId($connection, 'name');
        $shortDescAttrId = $this->getAttributeId($connection, 'short_description');

        if ($metaAttrId === 0 || $nameAttrId === 0) {
            return [];
        }

        $select = $connection->select()
            ->from(
                ['cpe' => $this->resource->getTableName('catalog_product_entity')],
                ['entity_id', 'sku']
            )
            ->joinLeft(
                ['meta' => $this->resource->getTableName('catalog_product_entity_text')],
                'meta.entity_id = cpe.entity_id AND meta.attribute_id = ' . $metaAttrId . ' AND meta.store_id = 0',
                []
            )
            ->join(
                ['pname' => $this->resource->getTableName('catalog_product_entity_varchar')],
                'pname.entity_id = cpe.entity_id AND pname.attribute_id = ' . $nameAttrId . ' AND pname.store_id = 0',
                ['name' => 'pname.value']
            )
            ->joinLeft(
                ['sdesc' => $this->resource->getTableName('catalog_product_entity_text')],
                'sdesc.entity_id = cpe.entity_id AND sdesc.attribute_id = ' . $shortDescAttrId . ' AND sdesc.store_id = 0',
                ['short_description' => 'sdesc.value']
            )
            ->where('meta.value IS NULL OR meta.value = ?', '')
            ->where('cpe.type_id = ?', 'simple')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    private function generateMetaDescription(array $product, string $apiKey): string
    {
        $name = $product['name'] ?? '';
        $shortDesc = strip_tags($product['short_description'] ?? '');

        $prompt = "Voce e um especialista em SEO para e-commerce de pecas de motos no Brasil.\n"
            . "Gere uma meta description otimizada para o produto abaixo.\n\n"
            . "Regras:\n"
            . "- Maximo 155 caracteres\n"
            . "- Em portugues brasileiro\n"
            . "- Inclua o nome do produto\n"
            . "- Use palavras-chave relevantes (pecas de moto, acessorios)\n"
            . "- Call to action sutil (compre, confira, aproveite)\n"
            . "- NAO use aspas nem emojis\n"
            . "- Responda APENAS com a meta description, sem explicacao\n\n"
            . "Produto: {$name}\n";

        if (!empty($shortDesc)) {
            $prompt .= "Descricao curta: " . mb_substr($shortDesc, 0, 200) . "\n";
        }

        $payload = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.7,
            'max_tokens' => 100,
        ];

        $this->curl->setHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
        ]);
        $this->curl->setTimeout(15);
        $this->curl->post('https://api.groq.com/openai/v1/chat/completions', json_encode($payload));

        $response = json_decode($this->curl->getBody(), true);

        $meta = trim($response['choices'][0]['message']['content'] ?? '');

        // Clean up
        $meta = str_replace(['"', "'", "\n", "\r"], ['', '', ' ', ''], $meta);
        $meta = trim($meta, '.');

        if (mb_strlen($meta) > self::META_MAX_LENGTH) {
            $meta = mb_substr($meta, 0, self::META_MAX_LENGTH - 3) . '...';
        }

        return $meta;
    }

    private function saveMetaDescription(int $productId, string $meta): void
    {
        $connection = $this->resource->getConnection();
        $attrId = $this->getAttributeId($connection, 'meta_description');

        if ($attrId === 0) {
            return;
        }

        $table = $this->resource->getTableName('catalog_product_entity_text');

        $connection->insertOnDuplicate($table, [
            'attribute_id' => $attrId,
            'store_id' => 0,
            'entity_id' => $productId,
            'value' => $meta,
        ], ['value']);
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

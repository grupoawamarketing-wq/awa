<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Model\Chat;

use GrupoAwamotos\Fitment\Model\ResourceModel\Application\CollectionFactory as ApplicationCollectionFactory;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;

class PageContextBuilder
{
    private const MAX_VALUE_LENGTH = 500;
    private const MAX_FITMENT_BRANDS = 3;
    private const MAX_FITMENT_MODELS_PER_BRAND = 3;

    private RequestInterface $request;

    private Registry $registry;

    private StoreManagerInterface $storeManager;

    private ApplicationCollectionFactory $applicationCollectionFactory;

    public function __construct(
        RequestInterface $request,
        Registry $registry,
        StoreManagerInterface $storeManager,
        ApplicationCollectionFactory $applicationCollectionFactory
    ) {
        $this->request = $request;
        $this->registry = $registry;
        $this->storeManager = $storeManager;
        $this->applicationCollectionFactory = $applicationCollectionFactory;
    }

    /**
     * Build contextual page variables for LiveChat.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function build(): array
    {
        $variables = [];

        $this->appendVariable($variables, 'Tipo de pagina', $this->resolvePageType());
        $this->appendVariable($variables, 'Store view', $this->storeManager->getStore()->getCode());

        $product = $this->getCurrentProduct();
        if ($product !== null) {
            $this->appendProductVariables($variables, $product);
        }

        $category = $this->getCurrentCategory();
        if ($category !== null) {
            $this->appendVariable($variables, 'Categoria atual', (string) $category->getName());
        }

        $searchQuery = trim((string) $this->request->getParam('q', ''));
        if ($searchQuery !== '') {
            $this->appendVariable($variables, 'Busca', $searchQuery);
        }

        return $variables;
    }

    /**
     * @param array<int, array{name: string, value: string}> $variables
     */
    private function appendProductVariables(array &$variables, ProductInterface $product): void
    {
        $this->appendVariable($variables, 'Produto', (string) $product->getName());
        $this->appendVariable($variables, 'SKU do produto', (string) $product->getSku());
        $this->appendVariable($variables, 'Marca do produto', $this->getProductBrand($product));
        $this->appendVariable($variables, 'Compatibilidade', $this->buildFitmentSummary((int) $product->getId()));
    }

    private function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    private function getCurrentCategory(): ?CategoryInterface
    {
        $category = $this->registry->registry('current_category');
        return $category instanceof CategoryInterface ? $category : null;
    }

    private function getProductBrand(ProductInterface $product): ?string
    {
        $manufacturer = $product->getAttributeText('manufacturer');

        if (is_array($manufacturer)) {
            $manufacturer = implode(', ', array_filter($manufacturer));
        }

        $manufacturer = is_string($manufacturer) ? $manufacturer : '';
        if ($manufacturer !== '') {
            return $manufacturer;
        }

        $groupedApplications = $this->getGroupedApplications((int) $product->getId());

        if (!empty($groupedApplications[0]['brand_name'])) {
            return (string) $groupedApplications[0]['brand_name'];
        }

        return null;
    }

    private function buildFitmentSummary(int $productId): ?string
    {
        $groupedApplications = $this->getGroupedApplications($productId);
        if ($groupedApplications === []) {
            return null;
        }

        $chunks = [];
        $brandCount = 0;

        foreach ($groupedApplications as $brandGroup) {
            if ($brandCount >= self::MAX_FITMENT_BRANDS) {
                break;
            }

            $brandName = isset($brandGroup['brand_name']) ? (string) $brandGroup['brand_name'] : '';
            $models = is_array($brandGroup['models'] ?? null) ? $brandGroup['models'] : [];
            if ($brandName === '' || $models === []) {
                continue;
            }

            $modelLabels = [];
            foreach (array_slice($models, 0, self::MAX_FITMENT_MODELS_PER_BRAND) as $model) {
                if (!is_array($model)) {
                    continue;
                }

                $label = trim($this->buildMotorcycleLabel($model));
                if ($label !== '') {
                    $modelLabels[] = $label;
                }
            }

            if ($modelLabels === []) {
                continue;
            }

            $chunk = $brandName . ': ' . implode(', ', $modelLabels);
            $extraModels = count($models) - count($modelLabels);
            if ($extraModels > 0) {
                $chunk .= sprintf(' +%d modelos', $extraModels);
            }

            $chunks[] = $chunk;
            $brandCount++;
        }

        if ($chunks === []) {
            return null;
        }

        $extraBrands = count($groupedApplications) - $brandCount;
        if ($extraBrands > 0) {
            $chunks[] = sprintf('+%d marcas', $extraBrands);
        }

        return $this->truncateValue(implode(' | ', $chunks));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getGroupedApplications(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        $collection = $this->applicationCollectionFactory->create();
        $collection->addProductFilter($productId);

        return $collection->getGroupedByBrand();
    }

    /**
     * @param array<string, mixed> $model
     */
    private function buildMotorcycleLabel(array $model): string
    {
        $parts = [];

        $modelName = trim((string) ($model['model_name'] ?? ''));
        if ($modelName !== '') {
            $parts[] = $modelName;
        }

        $years = trim((string) ($model['years'] ?? ''));
        if ($years !== '') {
            $parts[] = '(' . $years . ')';
        }

        $engineCc = trim((string) ($model['engine_cc'] ?? ''));
        if ($engineCc !== '') {
            $parts[] = $engineCc;
        }

        return implode(' ', $parts);
    }

    /**
     * @param array<int, array{name: string, value: string}> $variables
     */
    private function appendVariable(array &$variables, string $name, ?string $value): void
    {
        $normalizedValue = $this->normalizeValue($value);
        if ($normalizedValue === null) {
            return;
        }

        $variables[] = [
            'name' => $name,
            'value' => $normalizedValue,
        ];
    }

    private function normalizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: '';
        if ($value === '') {
            return null;
        }

        return $this->truncateValue($value);
    }

    private function truncateValue(string $value): string
    {
        if (mb_strlen($value) <= self::MAX_VALUE_LENGTH) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, self::MAX_VALUE_LENGTH - 1)) . '…';
    }

    private function resolvePageType(): string
    {
        return match ($this->request->getFullActionName()) {
            'cms_index_index' => 'Home',
            'catalog_product_view' => 'Produto',
            'catalog_category_view' => 'Categoria',
            'catalogsearch_result_index' => 'Busca',
            'checkout_cart_index' => 'Carrinho',
            'checkout_index_index' => 'Checkout',
            'customer_account_login' => 'Login',
            'customer_account_create' => 'Cadastro',
            default => 'Pagina interna',
        };
    }
}

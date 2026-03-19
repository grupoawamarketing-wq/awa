<?php
declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Block;

use GrupoAwamotos\Fitment\Model\ResourceModel\Application\CollectionFactory as ApplicationCollectionFactory;
use LiveChat\LiveChat\Helper\Data as LiveChatDataHelper;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;

class SnippetBlock extends \LiveChat\LiveChat\Block\SnippetBlock
{
    private const MAX_VALUE_LENGTH = 500;
    private const MAX_FITMENT_ITEMS = 6;

    private Registry $registry;
    private ApplicationCollectionFactory $applicationCollectionFactory;

    /**
     * @var array<int, array{name: string, value: string}>|null
     */
    private ?array $additionalCustomVariables = null;

    public function __construct(
        Context $context,
        LiveChatDataHelper $dataHelper,
        Registry $registry,
        ApplicationCollectionFactory $applicationCollectionFactory,
        array $data = []
    ) {
        parent::__construct($context, $dataHelper, $data);
        $this->registry = $registry;
        $this->applicationCollectionFactory = $applicationCollectionFactory;
    }

    public function hasAdditionalCustomVariables(): bool
    {
        return $this->getAdditionalCustomVariables() !== [];
    }

    public function getAdditionalCustomVariablesJson(): string
    {
        $json = json_encode(
            $this->getAdditionalCustomVariables(),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );

        return $json !== false ? $json : '[]';
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    private function getAdditionalCustomVariables(): array
    {
        if ($this->additionalCustomVariables !== null) {
            return $this->additionalCustomVariables;
        }

        $variables = [];

        foreach ($this->getPageContextVariables() as $variable) {
            $variables[] = $variable;
        }

        $this->additionalCustomVariables = $variables;

        return $this->additionalCustomVariables;
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    /**
     * @return array<int, array{name: string, value: string}>
     */
    private function getPageContextVariables(): array
    {
        $variables = [];
        $fullActionName = $this->getRequest()->getFullActionName();

        $this->appendVariable($variables, 'Tipo de pagina', $this->getPageTypeLabel($fullActionName));

        $product = $this->registry->registry('current_product');
        if ($product instanceof ProductInterface) {
            $this->appendVariable($variables, 'Produto', (string) $product->getName());
            $this->appendVariable($variables, 'SKU do produto', (string) $product->getSku());
            $this->appendVariable($variables, 'Marca do produto', $this->getProductAttributeText($product, ['manufacturer', 'brand']));
            $this->appendVariable($variables, 'Compatibilidade', $this->getProductFitmentSummary($product));

            return $variables;
        }

        $category = $this->registry->registry('current_category');
        if ($category !== null && method_exists($category, 'getName')) {
            $this->appendVariable($variables, 'Categoria atual', (string) $category->getName());
        }

        $searchQuery = trim((string) $this->getRequest()->getParam('q', ''));
        if ($searchQuery !== '') {
            $this->appendVariable($variables, 'Busca atual', $searchQuery);
        }

        return $variables;
    }

    private function getPageTypeLabel(string $fullActionName): string
    {
        $labels = [
            'cms_index_index' => 'Home',
            'catalog_product_view' => 'Produto',
            'catalog_category_view' => 'Categoria',
            'catalogsearch_result_index' => 'Busca',
            'checkout_cart_index' => 'Carrinho',
            'checkout_index_index' => 'Checkout',
            'customer_account_login' => 'Login',
        ];

        return $labels[$fullActionName] ?? $fullActionName;
    }

    /**
     * @param ProductInterface&\Magento\Catalog\Model\Product $product
     */
    private function getProductFitmentSummary(ProductInterface $product): ?string
    {
        $items = [];
        $collection = $this->applicationCollectionFactory->create()
            ->addProductFilter((int) $product->getId());

        foreach ($collection->getGroupedByBrand() as $brandGroup) {
            foreach ($brandGroup['models'] as $model) {
                $label = trim($brandGroup['brand_name'] . ' ' . $this->formatFitmentModelLabel($model));
                if ($label !== '') {
                    $items[] = $label;
                }

                if (count($items) >= self::MAX_FITMENT_ITEMS) {
                    break 2;
                }
            }
        }

        if ($items === []) {
            $fallback = array_filter([
                $this->getProductAttributeText($product, ['marca_moto']),
                $this->getProductAttributeText($product, ['modelo_moto']),
                $this->getProductAttributeText($product, ['ano_moto']),
            ]);

            if ($fallback === []) {
                return null;
            }

            return $this->truncateValue(implode(' / ', $fallback));
        }

        return $this->truncateValue(implode('; ', $items));
    }

    /**
     * @param array<string, mixed> $model
     */
    private function formatFitmentModelLabel(array $model): string
    {
        $parts = [];

        if (!empty($model['model_name'])) {
            $parts[] = (string) $model['model_name'];
        }

        if (!empty($model['years'])) {
            $parts[] = '(' . (string) $model['years'] . ')';
        }

        if (!empty($model['engine_cc'])) {
            $parts[] = (string) $model['engine_cc'];
        }

        return trim(implode(' ', $parts));
    }

    /**
     * @param ProductInterface&\Magento\Catalog\Model\Product $product
     * @param array<int, string> $attributeCodes
     */
    private function getProductAttributeText(ProductInterface $product, array $attributeCodes): ?string
    {
        foreach ($attributeCodes as $attributeCode) {
            $text = $product->getAttributeText($attributeCode);

            if (is_array($text)) {
                $text = implode(', ', array_filter($text));
            }

            if (is_scalar($text)) {
                $text = trim((string) $text);
                if ($text !== '') {
                    return $this->truncateValue($text);
                }
            }

            $rawValue = $product->getData($attributeCode);
            if (is_scalar($rawValue)) {
                $rawValue = trim((string) $rawValue);
                if ($rawValue !== '') {
                    return $this->truncateValue($rawValue);
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, array{name: string, value: string}> $variables
     */
    private function appendVariable(array &$variables, string $name, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $value = $this->truncateValue($value);
        if ($value === '') {
            return;
        }

        $variables[] = [
            'name' => $name,
            'value' => $value,
        ];
    }

    private function truncateValue(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) <= self::MAX_VALUE_LENGTH) {
            return $value;
        }

        return mb_substr($value, 0, self::MAX_VALUE_LENGTH - 3) . '...';
    }
}

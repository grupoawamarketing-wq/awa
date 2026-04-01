<?php

declare(strict_types=1);

namespace GrupoAwamotos\SchemaOrg\Test\Unit\Block;

use GrupoAwamotos\SchemaOrg\Block\ProductSchema;
use Magento\Catalog\Api\Data\ProductExtensionInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template\Context;
use Magento\Review\Model\Review\Summary as ReviewSummary;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GrupoAwamotos\SchemaOrg\Block\ProductSchema
 */
class ProductSchemaTest extends TestCase
{
    private ProductSchema $block;
    private Registry&MockObject $registry;
    private StoreManagerInterface&MockObject $storeManager;
    private Store&MockObject $store;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $this->registry = $this->createMock(Registry::class);
        $reviewFactory = $this->createMock(ReviewFactory::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);

        $this->store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBaseUrl'])
            ->getMock();

        $this->store->method('getBaseUrl')
            ->willReturnCallback(static function ($type = null): string {
                return $type === \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                    ? 'https://awamotos.com.br/media/'
                    : 'https://awamotos.com.br/';
            });

        $this->storeManager->method('getStore')->willReturn($this->store);

        $this->block = new ProductSchema(
            $context,
            $this->registry,
            $reviewFactory,
            $this->storeManager
        );
    }

    /**
     * Cria um mock de Product com valores configuráveis por override.
     *
     * Em PHPUnit 10, a primeira configuração de um método via willReturn() prevalece.
     * Para evitar conflito, os overrides são mesclados antes de configurar cada método
     * — cada método é configurado uma única vez.
     *
     * PHPUnit 10+:
     *   - onlyMethods() → métodos declarados em Product.php / AbstractModel
     *   - addMethods()  → métodos @method (magic via DataObject::__call)
     *
     * @param array<string, mixed> $overrides Valores a sobrescrever nos defaults
     * @return Product&MockObject
     */
    private function makeProduct(array $overrides = []): MockObject
    {
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getId', 'getName', 'getSku', 'getProductUrl', 'getImage',
                'getFinalPrice', 'getPrice', 'getSpecialPrice',
                'getAttributeText', 'getData', 'getExtensionAttributes',
            ])
            ->addMethods(['getRatingSummary', 'getShortDescription', 'getDescription'])
            ->getMock();

        $defaults = [
            'getId'             => 1,
            'getName'           => 'Bagageiro Honda CG 160',
            'getSku'            => 'BAG-CG160',
            'getProductUrl'     => 'https://awamotos.com.br/bagageiro-cg-160.html',
            'getShortDescription' => 'Bagageiro específico para CG 160',
            'getDescription'    => null,
            'getImage'          => '/b/a/bagageiro.jpg',
            'getFinalPrice'     => 199.90,
            'getPrice'          => 199.90,
            'getSpecialPrice'   => null,
            'getAttributeText'  => null,
            'getData'           => null,
            'getRatingSummary'  => null,
        ];

        $config = array_merge($defaults, $overrides);

        foreach ($config as $method => $value) {
            $product->method($method)->willReturn($value);
        }

        // Stock item padrão — pode ser sobrescrito passando 'getExtensionAttributes'
        if (!array_key_exists('getExtensionAttributes', $overrides)) { // phpcs:ignore Squiz.Operators.ComparisonOperatorUsage
            $stockItem = $this->createMock(StockItemInterface::class);
            $stockItem->method('getIsInStock')->willReturn(true);
            $extensionAttributes = $this->createMock(ProductExtensionInterface::class);
            $extensionAttributes->method('getStockItem')->willReturn($stockItem);
            $product->method('getExtensionAttributes')->willReturn($extensionAttributes);
        }

        return $product;
    }

    // Helpers para criar extensionAttributes com is_in_stock customizado
    private function makeExtensionAttributes(bool $inStock): ProductExtensionInterface&MockObject
    {
        $stockItem = $this->createMock(StockItemInterface::class);
        $stockItem->method('getIsInStock')->willReturn($inStock);
        $extensionAttributes = $this->createMock(ProductExtensionInterface::class);
        $extensionAttributes->method('getStockItem')->willReturn($stockItem);
        return $extensionAttributes;
    }

    // ====================================================================
    // getProduct
    // ====================================================================

    public function testGetProductReturnsProductFromRegistry(): void
    {
        $product = $this->makeProduct();
        $this->registry->method('registry')
            ->with('current_product')
            ->willReturn($product);

        $this->assertSame($product, $this->block->getProduct());
    }

    public function testGetProductReturnsNullWhenRegistryIsEmpty(): void
    {
        $this->registry->method('registry')
            ->with('current_product')
            ->willReturn(null);

        $this->assertNull($this->block->getProduct());
    }

    // ====================================================================
    // getProductSchemaData — caso sem produto
    // ====================================================================

    public function testGetProductSchemaDataReturnsEmptyWhenNoProduct(): void
    {
        $this->registry->method('registry')->willReturn(null);

        $this->assertSame([], $this->block->getProductSchemaData());
    }

    // ====================================================================
    // getProductSchemaData — campos obrigatórios
    // ====================================================================

    public function testGetProductSchemaDataContainsRequiredFields(): void
    {
        $product = $this->makeProduct();
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Bagageiro Honda CG 160', $data['name']);
        $this->assertSame('BAG-CG160', $data['sku']);
        $this->assertSame('https://awamotos.com.br/bagageiro-cg-160.html', $data['url']);
        $this->assertStringContainsString('/b/a/bagageiro.jpg', $data['image']);
    }

    // ====================================================================
    // getProductSchemaData — descrição: prefere shortDescription
    // ====================================================================

    public function testDescriptionUsesShortDescriptionFirst(): void
    {
        $product = $this->makeProduct(['getShortDescription' => '<p>Bagageiro resistente</p>']);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertSame('Bagageiro resistente', $data['description']);
    }

    public function testDescriptionFallsBackToDescription(): void
    {
        $product = $this->makeProduct([
            'getShortDescription' => '',
            'getDescription' => '<b>Descrição completa</b>',
        ]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertSame('Descrição completa', $data['description']);
    }

    // ====================================================================
    // getProductSchemaData — disponibilidade de estoque
    // ====================================================================

    public function testOffersShowInStockWhenProductIsAvailable(): void
    {
        $product = $this->makeProduct();
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
    }

    public function testOffersShowOutOfStockWhenNotAvailable(): void
    {
        $product = $this->makeProduct(['getExtensionAttributes' => $this->makeExtensionAttributes(false)]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertSame('https://schema.org/OutOfStock', $data['offers']['availability']);
    }

    public function testOffersShowOutOfStockWhenNoExtensionAttributes(): void
    {
        $product = $this->makeProduct(['getExtensionAttributes' => null]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertSame('https://schema.org/OutOfStock', $data['offers']['availability']);
    }

    // ====================================================================
    // getProductSchemaData — preço
    // ====================================================================

    public function testOffersPriceIncludedWhenFinalPriceIsPositive(): void
    {
        $product = $this->makeProduct(['getFinalPrice' => 249.90]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayHasKey('price', $data['offers']);
        $this->assertSame('249.90', $data['offers']['price']);
    }

    public function testOffersPriceOmittedWhenFinalPriceIsZeroB2B(): void
    {
        $product = $this->makeProduct(['getFinalPrice' => 0.0]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayNotHasKey('price', $data['offers']);
    }

    // ====================================================================
    // getProductSchemaData — marca
    // ====================================================================

    public function testBrandIncludedWhenManufacturerAttributeIsSet(): void
    {
        // getAttributeText('manufacturer') deve retornar 'Honda'
        $product = $this->makeProduct(['getAttributeText' => 'Honda']);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayHasKey('brand', $data);
        $this->assertSame('Brand', $data['brand']['@type']);
        $this->assertSame('Honda', $data['brand']['name']);
    }

    public function testBrandOmittedWhenManufacturerAttributeIsEmpty(): void
    {
        $product = $this->makeProduct(); // getAttributeText já é null por padrão
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayNotHasKey('brand', $data);
    }

    // ====================================================================
    // getProductSchemaData — priceSpecification (promoção)
    // ====================================================================

    public function testPriceSpecificationIncludedWhenSpecialPriceLowerThanRegular(): void
    {
        $product = $this->makeProduct([
            'getFinalPrice' => 149.90,
            'getPrice' => 199.90,
            'getSpecialPrice' => 149.90,
        ]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayHasKey('priceSpecification', $data['offers']);
        $this->assertSame('https://schema.org/SalePrice', $data['offers']['priceSpecification']['priceType']);
        $this->assertSame('149.90', $data['offers']['priceSpecification']['price']);
    }

    public function testPriceSpecificationOmittedWhenNoSpecialPrice(): void
    {
        $product = $this->makeProduct(); // getSpecialPrice já é null por padrão
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayNotHasKey('priceSpecification', $data['offers']);
    }

    // ====================================================================
    // getProductSchemaData — aggregateRating
    // ====================================================================

    public function testAggregateRatingAbsentWhenNoReviews(): void
    {
        $product = $this->makeProduct(); // getRatingSummary já é null por padrão
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayNotHasKey('aggregateRating', $data);
    }

    public function testAggregateRatingAbsentWhenReviewCountIsZero(): void
    {
        $summary = $this->createMock(ReviewSummary::class);
        $summary->method('getReviewsCount')->willReturn(0);
        $summary->method('getRatingSummary')->willReturn(0.0);

        $product = $this->makeProduct(['getRatingSummary' => $summary]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayNotHasKey('aggregateRating', $data);
    }

    public function testAggregateRatingContainsRealDataWhenReviewsExist(): void
    {
        $summary = $this->createMock(ReviewSummary::class);
        $summary->method('getReviewsCount')->willReturn(8);
        $summary->method('getRatingSummary')->willReturn(80.0); // 80% = 4.0/5

        $product = $this->makeProduct(['getRatingSummary' => $summary]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertArrayHasKey('aggregateRating', $data);
        $this->assertSame('AggregateRating', $data['aggregateRating']['@type']);
        $this->assertSame('4.0', $data['aggregateRating']['ratingValue']);
        $this->assertSame(8, $data['aggregateRating']['reviewCount']);
        $this->assertSame('5', $data['aggregateRating']['bestRating']);
        $this->assertSame('1', $data['aggregateRating']['worstRating']);
    }

    public function testAggregateRatingConverts100PlusScaleTo5Scale(): void
    {
        // 60% rating_summary → 3.0/5
        $summary = $this->createMock(ReviewSummary::class);
        $summary->method('getReviewsCount')->willReturn(3);
        $summary->method('getRatingSummary')->willReturn(60.0);

        $product = $this->makeProduct(['getRatingSummary' => $summary]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertSame('3.0', $data['aggregateRating']['ratingValue']);
    }

    public function testAggregateRatingRoundsTenthsCorrectly(): void
    {
        // 85% → 4.25 → arredonda para 4.3
        $summary = $this->createMock(ReviewSummary::class);
        $summary->method('getReviewsCount')->willReturn(2);
        $summary->method('getRatingSummary')->willReturn(85.0);

        $product = $this->makeProduct(['getRatingSummary' => $summary]);
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $data = $this->block->getProductSchemaData();

        $this->assertSame('4.3', $data['aggregateRating']['ratingValue']);
    }

    // ====================================================================
    // getSchemaJson
    // ====================================================================

    public function testGetSchemaJsonReturnsValidJsonWhenProductExists(): void
    {
        $product = $this->makeProduct();
        $this->registry->method('registry')->with('current_product')->willReturn($product);

        $json = $this->block->getSchemaJson();

        $this->assertNotEmpty($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('https://schema.org', $decoded['@context']);
    }

    public function testGetSchemaJsonReturnsEmptyStringWhenNoProduct(): void
    {
        $this->registry->method('registry')->willReturn(null);

        $this->assertSame('', $this->block->getSchemaJson());
    }
}

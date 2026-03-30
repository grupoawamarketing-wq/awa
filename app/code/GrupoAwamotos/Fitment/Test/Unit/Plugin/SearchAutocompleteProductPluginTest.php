<?php
declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Test\Unit\Plugin;

use GrupoAwamotos\Fitment\Plugin\SearchAutocompleteProductPlugin;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Mirasvit\Search\Index\Magento\Catalog\Product\InstantProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SearchAutocompleteProductPluginTest extends TestCase
{
    private CollectionFactory&MockObject $collectionFactory;
    private LoggerInterface&MockObject $logger;
    private InstantProvider&MockObject $instantProvider;
    private SearchAutocompleteProductPlugin $plugin;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->instantProvider = $this->createMock(InstantProvider::class);
        $this->plugin = new SearchAutocompleteProductPlugin(
            $this->collectionFactory,
            $this->logger
        );
    }

    public function testAfterMapUsesStoreIdFromVariadicArguments(): void
    {
        $product = $this->createProduct([
            'entity_id' => 10,
            'marca_moto' => 'Honda',
            'modelo_moto' => 'CG 160',
            'ano_moto' => '2024',
        ]);

        $collection = new SearchAutocompleteProductPluginTestCollection([$product]);

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $this->logger->expects($this->never())->method('error');

        $result = $this->plugin->afterMap(
            $this->instantProvider,
            [
                10 => ['_instant' => ['name' => 'Produto']],
            ],
            [10 => ['name' => 'Produto']],
            3
        );

        $this->assertSame('Honda · CG 160 · 2024', $result[10]['_instant']['fitment']);
        $this->assertSame(3, $collection->getStoreId());
        $this->assertSame(['entity_id', ['in' => [10]]], $collection->getAttributeFilters()[0]);
    }

    public function testAfterGetItemsInjectsFitmentBySku(): void
    {
        $product = $this->getMockBuilder(\Magento\Catalog\Model\Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSku', 'getData'])
            ->getMock();
        $product->method('getSku')->willReturn('ABC123');
        $product->method('getData')->willReturnMap([
            ['marca_moto', 'Yamaha'],
            ['modelo_moto', 'Fazer 250'],
            ['ano_moto', '2023'],
        ]);

        $collection = new SearchAutocompleteProductPluginTestCollection([$product]);

        $this->collectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($collection);

        $result = $this->plugin->afterGetItems(
            $this->instantProvider,
            [
                ['sku' => 'ABC123', 'name' => 'Produto 1'],
                ['sku' => 'SEM-FITMENT', 'name' => 'Produto 2'],
            ],
            5,
            10,
            1
        );

        $this->assertArrayHasKey('fitment', $result[0]);
        $this->assertSame('', $result[1]['fitment']);
        $this->assertSame(5, $collection->getStoreId());
        $this->assertSame(['sku', ['in' => ['ABC123', 'SEM-FITMENT']]], $collection->getAttributeFilters()[0]);
    }

    private function createProduct(array $data): \Magento\Catalog\Model\Product
    {
        $reflection = new \ReflectionClass(\Magento\Catalog\Model\Product::class);
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $reflection->newInstanceWithoutConstructor();
        $product->setData($data);

        return $product;
    }
}

class SearchAutocompleteProductPluginTestCollection implements \IteratorAggregate
{
    private array $items;
    private array $attributeFilters = [];
    private array $selectedAttributes = [];
    private ?int $storeId = null;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function addAttributeToFilter(string $attribute, array $condition): self
    {
        $this->attributeFilters[] = [$attribute, $condition];

        return $this;
    }

    public function addAttributeToSelect(array $attributes): self
    {
        $this->selectedAttributes[] = $attributes;

        return $this;
    }

    public function addStoreFilter(int $storeId): self
    {
        $this->storeId = $storeId;

        return $this;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    public function getAttributeFilters(): array
    {
        return $this->attributeFilters;
    }
}

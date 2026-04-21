<?php

declare(strict_types=1);

namespace GrupoAwamotos\LiveChat\Test\Unit\Model\Chat;

use GrupoAwamotos\Fitment\Model\ResourceModel\Application\Collection;
use GrupoAwamotos\Fitment\Model\ResourceModel\Application\CollectionFactory;
use GrupoAwamotos\LiveChat\Model\Chat\PageContextBuilder;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Registry;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PageContextBuilderTest extends TestCase
{
    public function testBuildCachesGroupedApplicationsWithinRequest(): void
    {
        $request = $this->getMockBuilder(Http::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getFullActionName', 'getParam'])
            ->getMock();
        $registry = $this->createMock(Registry::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $store = $this->getMockBuilder(Store::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCode'])
            ->getMock();
        $collectionFactory = $this->createMock(CollectionFactory::class);
        $collection = $this->createMock(Collection::class);
        $product = $this->getMockBuilder(Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getName', 'getSku', 'getAttributeText'])
            ->getMock();

        $request->method('getFullActionName')->willReturn('catalog_product_view');
        $request->method('getParam')->with('q', '')->willReturn('');
        $storeManager->method('getStore')->willReturn($store);
        $store->method('getCode')->willReturn('default');

        $product->method('getId')->willReturn(10);
        $product->method('getName')->willReturn('Retrovisor');
        $product->method('getSku')->willReturn('RET-10');
        $product->method('getAttributeText')->with('manufacturer')->willReturn('');

        $registry->method('registry')
            ->willReturnCallback(static fn (string $key) => $key === 'current_product' ? $product : null);

        $collectionFactory->expects($this->once())->method('create')->willReturn($collection);
        $collection->expects($this->once())->method('addProductFilter')->with(10)->willReturnSelf();
        $collection->expects($this->once())->method('getGroupedByBrand')->willReturn([
            [
                'brand_name' => 'Honda',
                'models' => [
                    [
                        'model_name' => 'CB 300',
                        'years' => '2010-2012',
                        'engine_cc' => '300cc',
                    ],
                ],
            ],
        ]);

        $builder = new PageContextBuilder($request, $registry, $storeManager, $collectionFactory);

        $variables = $builder->build();
        $variablesAgain = $builder->build();

        $this->assertSame($variables, $variablesAgain);
        $this->assertSame('Honda', $this->findVariableValue($variables, 'Marca do produto'));
        $this->assertSame('Honda: CB 300 (2010-2012) 300cc', $this->findVariableValue($variables, 'Compatibilidade'));
    }

    /**
     * @param array<int, array{name: string, value: string}> $variables
     */
    private function findVariableValue(array $variables, string $name): ?string
    {
        foreach ($variables as $variable) {
            if (($variable['name'] ?? '') === $name) {
                return $variable['value'] ?? null;
            }
        }

        return null;
    }
}

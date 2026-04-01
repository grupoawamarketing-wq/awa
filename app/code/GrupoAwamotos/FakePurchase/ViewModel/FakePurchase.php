<?php

declare(strict_types=1);

namespace GrupoAwamotos\FakePurchase\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Helper\Image as ImageHelper;

class FakePurchase implements ArgumentInterface
{
    private ScopeConfigInterface $scopeConfig;
    private CollectionFactory $productCollectionFactory;
    private ImageHelper $imageHelper;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CollectionFactory $productCollectionFactory,
        ImageHelper $imageHelper
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->imageHelper = $imageHelper;
    }

    public function isEnabled(): bool
    {
        // Permanently disabled - fake purchase notifications deactivated
        return false;
    }

    public function getDisplayTime(): int
    {
        return (int) $this->scopeConfig->getValue('grupoawamotos_fakepurchase/general/display_time', ScopeInterface::SCOPE_STORE) ?: 5000;
    }

    public function getDelayTime(): int
    {
        return (int) $this->scopeConfig->getValue('grupoawamotos_fakepurchase/general/delay_time', ScopeInterface::SCOPE_STORE) ?: 8000;
    }

    public function getMaxNotifications(): int
    {
        return (int) $this->scopeConfig->getValue('grupoawamotos_fakepurchase/general/max_notifications', ScopeInterface::SCOPE_STORE) ?: 10;
    }

    public function getPosition(): string
    {
        return $this->scopeConfig->getValue('grupoawamotos_fakepurchase/general/position', ScopeInterface::SCOPE_STORE) ?: 'bottom-left';
    }

    public function getProductsJson(): string
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect(['name', 'thumbnail', 'url_key', 'price'])
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', ['in' => [2, 3, 4]])
            ->setPageSize(30);
        $collection->getSelect()->orderRand();
        $products = [];
        foreach ($collection as $product) {
            $imageUrl = $this->imageHelper->init($product, 'product_thumbnail_image')
                ->setImageFile($product->getThumbnail())
                ->resize(80, 80)
                ->getUrl();
            $products[] = [
                'name' => $product->getName(),
                'url' => $product->getProductUrl(),
                'image' => $imageUrl,
                'price' => number_format((float)$product->getPrice(), 2, ',', '.')
            ];
        }
        return json_encode($products);
    }

    public function getCitiesJson(): string
    {
        $cities = $this->scopeConfig->getValue('grupoawamotos_fakepurchase/fake_data/cities', ScopeInterface::SCOPE_STORE);
        return json_encode(array_filter(array_map('trim', explode(',', $cities ?: ''))));
    }

    public function getNamesJson(): string
    {
        $names = $this->scopeConfig->getValue('grupoawamotos_fakepurchase/fake_data/first_names', ScopeInterface::SCOPE_STORE);
        return json_encode(array_filter(array_map('trim', explode(',', $names ?: ''))));
    }

    public function getConfigJson(): string
    {
        return json_encode([
            'displayTime' => $this->getDisplayTime(),
            'delayTime' => $this->getDelayTime(),
            'maxNotifications' => $this->getMaxNotifications(),
            'position' => $this->getPosition()
        ]);
    }
}

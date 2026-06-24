<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\ViewModel;

use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\CustomerData\LastOrderedItems;

class HomeRecentOrders implements ArgumentInterface
{
    public const STATE_GUEST = 'guest';

    public const STATE_LOGGED_EMPTY = 'logged_empty';

    public const STATE_HAS_ITEMS = 'has_items';

    private const WHATSAPP_NUMBER = '5516997367588';

    /** @var array<string, mixed>|null */
    private ?array $sectionData = null;

    public function __construct(
        private readonly HttpContext $httpContext,
        private readonly LastOrderedItems $lastOrderedItems,
        private readonly CategoryCarouselData $categoryCarouselData,
        private readonly \Magento\Framework\UrlInterface $urlBuilder
    ) {
    }

    public function isHomeSectionEnabled(): bool
    {
        return true;
    }

    public function isLoggedIn(): bool
    {
        return (bool) $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);
    }

    public function getState(): string
    {
        if (!$this->isLoggedIn()) {
            return self::STATE_GUEST;
        }

        return $this->getRecentItems() === [] ? self::STATE_LOGGED_EMPTY : self::STATE_HAS_ITEMS;
    }

    /**
     * @return list<array{id: int|string, name: string, url: string|null, is_saleable: bool, product_id: int|string}>
     */
    public function getRecentItems(): array
    {
        if (!$this->isLoggedIn()) {
            return [];
        }

        $items = $this->getSectionData()['items'] ?? [];

        return is_array($items) ? $items : [];
    }

    public function getBestsellersUrl(): string
    {
        return $this->urlBuilder->getUrl('', ['_direct' => 'ofertas.html']);
    }

    public function getLoginUrl(): string
    {
        return $this->urlBuilder->getUrl('b2b/account/login');
    }

    public function getRegisterUrl(): string
    {
        return $this->urlBuilder->getUrl('b2b/register');
    }

    public function getWhatsAppUrl(): string
    {
        $text = rawurlencode((string) __('Olá, quero falar com um vendedor sobre pedidos B2B na AWA Motos.'));

        return 'https://wa.me/' . self::WHATSAPP_NUMBER . '?text=' . $text;
    }

    public function getOrdersUrl(): string
    {
        return $this->urlBuilder->getUrl('sales/order/history');
    }

    /**
     * @return array<string, mixed>
     */
    private function getSectionData(): array
    {
        if ($this->sectionData === null) {
            $this->sectionData = $this->lastOrderedItems->getSectionData();
        }

        return $this->sectionData;
    }
}

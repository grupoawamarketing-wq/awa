<?php

/**
 * Block para formulário de resposta de cotação
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Adminhtml\Quote;

use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class Respond extends Template
{
    /**
     * @var QuoteRequestRepositoryInterface
     */
    private $quoteRequestRepository;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var QuoteRequestInterface|null
     */
    private $quoteRequest = null;

    public function __construct(
        Context $context,
        QuoteRequestRepositoryInterface $quoteRequestRepository,
        Config $config,
        array $data = []
    ) {
        $this->quoteRequestRepository = $quoteRequestRepository;
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Get quote request
     *
     * @return QuoteRequestInterface|null
     */
    public function getQuoteRequest(): ?QuoteRequestInterface
    {
        if ($this->quoteRequest === null) {
            $requestId = (int) $this->getRequest()->getParam('id');
            if ($requestId) {
                try {
                    $this->quoteRequest = $this->quoteRequestRepository->getById($requestId);
                } catch (\Exception $e) {
                    return null;
                }
            }
        }
        return $this->quoteRequest;
    }

    /**
     * Get items from quote request
     *
     * @return array
     */
    public function getItems(): array
    {
        $quote = $this->getQuoteRequest();
        if (!$quote) {
            return [];
        }
        return $quote->getItems();
    }

    /**
     * Get save URL
     *
     * @return string
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save');
    }

    /**
     * Get back URL
     *
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    /**
     * Get default expiry days
     *
     * @return int
     */
    public function getDefaultExpiryDays(): int
    {
        return $this->config->getQuoteExpiryDays() ?: 7;
    }

    /**
     * Format price
     *
     * @param float $price
     * @return string
     */
    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

    /**
     * Get status label
     *
     * @param string $status
     * @return string
     */
    public function getStatusLabel(string $status): string
    {
        $labels = [
            'pending' => __('Aguardando Análise'),
            'processing' => __('Em Análise'),
            'quoted' => __('Orçamento Enviado'),
            'accepted' => __('Aceito'),
            'rejected' => __('Recusado'),
            'expired' => __('Expirado'),
            'converted' => __('Convertido em Pedido'),
        ];

        return (string) ($labels[$status] ?? $status);
    }

    /**
     * Calculate suggested total from items
     *
     * @return float
     */
    public function getSuggestedTotal(): float
    {
        $total = 0.0;
        foreach ($this->getItems() as $item) {
            $price = (float) ($item['price'] ?? 0);
            $qty = (float) ($item['qty'] ?? 1);
            $total += $price * $qty;
        }
        return $total;
    }
}

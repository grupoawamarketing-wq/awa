<?php

/**
 * Block para visualização de cotação no admin
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Adminhtml\Quote;

use GrupoAwamotos\B2B\Api\QuoteRequestRepositoryInterface;
use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;

class View extends Template
{
    private QuoteRequestRepositoryInterface $quoteRequestRepository;
    private CustomerRepositoryInterface $customerRepository;
    private ?QuoteRequestInterface $quoteRequest = null;

    public function __construct(
        Context $context,
        QuoteRequestRepositoryInterface $quoteRequestRepository,
        CustomerRepositoryInterface $customerRepository,
        array $data = []
    ) {
        $this->quoteRequestRepository = $quoteRequestRepository;
        $this->customerRepository = $customerRepository;
        parent::__construct($context, $data);
    }

    public function getQuoteRequest(): ?QuoteRequestInterface
    {
        if ($this->quoteRequest === null) {
            $requestId = (int)$this->getRequest()->getParam('id');
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

    public function getItems(): array
    {
        $quote = $this->getQuoteRequest();
        return $quote ? $quote->getItems() : [];
    }

    public function getCustomerName(): string
    {
        $quote = $this->getQuoteRequest();
        if (!$quote) {
            return '';
        }
        try {
            $customer = $this->customerRepository->getById($quote->getCustomerId());
            return $customer->getFirstname() . ' ' . $customer->getLastname();
        } catch (\Exception $e) {
            return __('Cliente #%1', $quote->getCustomerId())->render();
        }
    }

    public function getRespondUrl(): string
    {
        $quote = $this->getQuoteRequest();
        return $this->getUrl('*/*/respond', ['id' => $quote ? $quote->getRequestId() : 0]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }

    public function formatPrice(float $price): string
    {
        return 'R$ ' . number_format($price, 2, ',', '.');
    }

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
        return (string)($labels[$status] ?? $status);
    }

    public function getStatusCssClass(string $status): string
    {
        $classes = [
            'pending' => 'grid-severity-minor',
            'processing' => 'grid-severity-minor',
            'quoted' => 'grid-severity-notice',
            'accepted' => 'grid-severity-notice',
            'rejected' => 'grid-severity-critical',
            'expired' => 'grid-severity-critical',
            'converted' => 'grid-severity-notice',
        ];
        return $classes[$status] ?? 'grid-severity-minor';
    }
}

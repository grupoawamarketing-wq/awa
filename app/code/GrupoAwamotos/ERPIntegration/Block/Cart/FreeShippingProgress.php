<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Cart;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\ScopeInterface;

/**
 * Barra de progresso de frete grátis na página do carrinho.
 * Lê o threshold do carrier freeshipping do Magento e o subtotal atual da sessão.
 */
class FreeShippingProgress extends Template
{
    private const XML_PATH_ACTIVE    = 'carriers/freeshipping/active';
    private const XML_PATH_THRESHOLD = 'carriers/freeshipping/free_shipping_subtotal';

    private ScopeConfigInterface $scopeConfig;
    private PriceCurrencyInterface $priceCurrency;
    private \Magento\Checkout\Model\Session $checkoutSession;
    private Json $json;

    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        PriceCurrencyInterface $priceCurrency,
        \Magento\Checkout\Model\Session $checkoutSession,
        Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->scopeConfig      = $scopeConfig;
        $this->priceCurrency    = $priceCurrency;
        $this->checkoutSession  = $checkoutSession;
        $this->json             = $json;
    }

    public function isMinicartContext(): bool
    {
        return (bool) $this->getData('minicart_mode');
    }

    /**
     * Config JSON para atualização dinâmica via customer-data (minicart).
     *
     * @return string
     */
    public function isCarrierEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE);
    }

    public function hasQuoteItems(): bool
    {
        $quote = $this->checkoutSession->getQuote();
        return $quote !== null && $quote->getItemsCount() > 0;
    }

    public function getClientConfigJson(): string
    {
        return $this->json->serialize([
            'active'    => $this->shouldDisplay(),
            'threshold' => $this->getThreshold(),
            'subtotal'  => $this->getSubtotal(),
            'percent'   => $this->getProgressPercent(),
            'reached'   => $this->hasReachedThreshold(),
            'remaining' => $this->formatCurrency($this->getRemainingAmount()),
            'thresholdFormatted' => $this->formatCurrency($this->getThreshold()),
            'successMessage' => (string) __('Parabéns! Você ganhou frete grátis.'),
            'progressMessage' => (string) __('Faltam %1 para frete grátis!'),
        ]);
    }

    /**
     * Retorna false se o carrier de frete grátis estiver desabilitado ou sem itens.
     */
    public function shouldDisplay(): bool
    {
        return $this->isCarrierEnabled() && $this->hasQuoteItems();
    }

    /**
     * Valor mínimo configurado para frete grátis (em moeda base).
     */
    public function getThreshold(): float
    {
        return (float) $this->scopeConfig->getValue(
            self::XML_PATH_THRESHOLD,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Subtotal atual do carrinho (somente produtos, sem frete).
     */
    public function getSubtotal(): float
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote) {
            return 0.0;
        }
        return (float) $quote->getSubtotal();
    }

    /**
     * Quanto falta para atingir o frete grátis (0 se já atingiu).
     */
    public function getRemainingAmount(): float
    {
        return max(0.0, $this->getThreshold() - $this->getSubtotal());
    }

    /**
     * Percentual já percorrido em direção ao frete grátis (0–100).
     */
    public function getProgressPercent(): int
    {
        $threshold = $this->getThreshold();
        if ($threshold <= 0) {
            return 100;
        }
        return min(100, (int) round(($this->getSubtotal() / $threshold) * 100));
    }

    /**
     * Formata um valor float na moeda da loja (ex: R$299,00).
     */
    public function formatCurrency(float $amount): string
    {
        return $this->priceCurrency->format($amount, false);
    }

    /**
     * Retorna true quando o carrinho já qualifica para frete grátis.
     */
    public function hasReachedThreshold(): bool
    {
        return $this->getSubtotal() >= $this->getThreshold();
    }
}

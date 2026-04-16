<?php

/**
 * Plugin to hide prices for non-logged users
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use Magento\Catalog\Block\Product\AbstractProduct;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class HidePricePlugin
{
    private PriceVisibilityInterface $priceVisibility;
    private LoggerInterface $logger;

    public function __construct(
        PriceVisibilityInterface $priceVisibility,
        ?LoggerInterface $logger = null
    ) {
        $this->priceVisibility = $priceVisibility;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * After getProductPrice - return message instead of price if not allowed
     */
    public function afterGetProductPrice(AbstractProduct $subject, string $result): string
    {
        try {
            if (!$this->priceVisibility->canViewPrices()) {
                return '<div class="b2b-login-to-see-price">'
                    . $this->priceVisibility->getPriceReplacementMessage()
                    . '</div>';
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->error('[B2B HidePricePlugin] Falha ao aplicar regra de visibilidade de preço.', [
                'exception' => $exception->getMessage(),
            ]);

            // Fail-open para evitar esconder preço indevidamente por erro transitório.
            return $result;
        }
    }
}

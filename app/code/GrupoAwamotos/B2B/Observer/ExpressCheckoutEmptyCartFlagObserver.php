<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Model\Cart\ExpressEmptyCartFlagManager;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Grava flag quando o OPC é acessado com carrinho vazio (redirect para cart ou login B2B).
 */
class ExpressCheckoutEmptyCartFlagObserver implements ObserverInterface
{
    private const EXPRESS_FULL_ACTION = 'onepagecheckout_index_index';

    public function __construct(
        private readonly ExpressEmptyCartFlagManager $expressEmptyCartFlagManager,
        private readonly HttpRequest $request
    ) {
    }

    public function execute(Observer $observer): void
    {
        if ($this->request->getFullActionName() !== self::EXPRESS_FULL_ACTION) {
            return;
        }

        $this->expressEmptyCartFlagManager->markWhenQuoteEmpty();
    }
}

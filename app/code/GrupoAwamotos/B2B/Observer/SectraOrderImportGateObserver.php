<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Model\Sectra\OrderImportGate;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies Sectra import gate when a B2B order is placed.
 */
class SectraOrderImportGateObserver implements ObserverInterface
{
    public function __construct(
        private readonly OrderImportGate $orderImportGate,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        if (!$order instanceof OrderInterface || !$order->getEntityId()) {
            return;
        }

        try {
            $this->orderImportGate->applyOnOrderPlace($order);
        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                '[B2B-Sectra] SectraOrderImportGateObserver pedido #%s: %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}

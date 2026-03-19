<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Block to display B2B checkout metadata in admin order view
 * P0-1: Purchase Order Number
 * P2-4.2: Order Notes
 */
class PoNumber extends Template
{
    /**
     * @var Registry
     */
    private Registry $registry;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        array $data = []
    ) {
        $this->registry = $registry;
        parent::__construct($context, $data);
    }

    /**
     * Get current order
     *
     * @return OrderInterface|null
     */
    public function getOrder(): ?OrderInterface
    {
        return $this->registry->registry('current_order');
    }

    /**
     * Get PO Number from order
     *
     * @return string|null
     */
    public function getPoNumber(): ?string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return null;
        }

        return $order->getData('b2b_po_number') ?: null;
    }

    /**
     * Check if PO Number exists
     *
     * @return bool
     */
    public function hasPoNumber(): bool
    {
        $poNumber = $this->getPoNumber();
        return $poNumber !== null && trim($poNumber) !== '';
    }

    /**
     * Get Order Notes from order
     *
     * @return string|null
     */
    public function getOrderNotes(): ?string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return null;
        }

        return $order->getData('b2b_order_notes') ?: null;
    }

    /**
     * Check if Order Notes exists
     *
     * @return bool
     */
    public function hasOrderNotes(): bool
    {
        $orderNotes = $this->getOrderNotes();
        return $orderNotes !== null && trim($orderNotes) !== '';
    }

    /**
     * Check if there is any B2B checkout metadata in current order
     *
     * @return bool
     */
    public function hasCheckoutMetadata(): bool
    {
        return $this->hasPoNumber() || $this->hasOrderNotes();
    }

    /**
     * Get block title
     *
     * @return string
     */
    public function getTitle(): string
    {
        return (string) __('Metadados B2B do Checkout');
    }
}

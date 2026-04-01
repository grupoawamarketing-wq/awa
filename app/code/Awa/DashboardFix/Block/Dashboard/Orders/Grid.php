<?php

/**
 * Fix: "Call to a member function getName() on null" when getBillingAddress() returns null
 * for orders in the admin dashboard "Last Orders" grid.
 */

declare(strict_types=1);

namespace Awa\DashboardFix\Block\Dashboard\Orders;

class Grid extends \Magento\Backend\Block\Dashboard\Orders\Grid
{
    /**
     * Process collection after loading — with null-safe billing address check
     *
     * @return $this
     */
    protected function _afterLoadCollection()
    {
        foreach ($this->getCollection() as $item) {
            if (!$item->getCustomer()) {
                $billingAddress = $item->getBillingAddress();
                $name = $billingAddress ? $billingAddress->getName() : __('Guest');
                $item->setCustomer($name);
            }
        }
        return $this;
    }
}

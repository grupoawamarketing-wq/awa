<?php

/**
 * Shipping Rate Result Plugin
 * Adds B2B carrier information to shipping rates in checkout
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use Magento\Shipping\Model\Rate\Result;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\B2B\Model\CarrierService;
use GrupoAwamotos\B2B\Model\ResourceModel\Carrier\CollectionFactory;

class ShippingRateResultPlugin
{
    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CarrierService
     */
    private $carrierService;

    /**
     * @var CollectionFactory
     */
    private $carrierCollectionFactory;

    /**
     * @param CustomerSession $customerSession
     * @param CarrierService $carrierService
     * @param CollectionFactory $carrierCollectionFactory
     */
    public function __construct(
        CustomerSession $customerSession,
        CarrierService $carrierService,
        CollectionFactory $carrierCollectionFactory
    ) {
        $this->customerSession = $customerSession;
        $this->carrierService = $carrierService;
        $this->carrierCollectionFactory = $carrierCollectionFactory;
    }

    /**
     * After get all rates - enhance B2B customer shipping options
     *
     * @param Result $subject
     * @param array $result
     * @return array
     */
    public function afterGetAllRates(Result $subject, $result): array
    {
        if (!$this->isB2BCustomer()) {
            return $result;
        }

        // Get B2B carrier info to enhance descriptions
        $b2bCarriers = $this->getB2BCarriersMap();

        foreach ($result as $rate) {
            $carrierCode = $rate->getCarrier();

            // Check if we have enhanced info for this carrier
            if (isset($b2bCarriers[$carrierCode])) {
                $carrierInfo = $b2bCarriers[$carrierCode];

                // Add delivery time if not already present
                if ($carrierInfo['delivery_time']) {
                    $currentMethod = $rate->getMethodTitle();
                    if (strpos($currentMethod, $carrierInfo['delivery_time']) === false) {
                        $rate->setMethodTitle($currentMethod . ' (' . $carrierInfo['delivery_time'] . ')');
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Check if current customer is B2B
     *
     * @return bool
     */
    private function isB2BCustomer(): bool
    {
        return $this->carrierService->isB2BCustomer();
    }

    /**
     * Get B2B carriers as map
     *
     * @return array
     */
    private function getB2BCarriersMap(): array
    {
        $carriers = $this->carrierCollectionFactory->create()
            ->addActiveFilter();

        $map = [];
        foreach ($carriers as $carrier) {
            $map[$carrier->getCode()] = [
                'name' => $carrier->getName(),
                'delivery_time' => $carrier->getDeliveryTime(),
                'description' => $carrier->getDescription()
            ];
        }

        return $map;
    }
}

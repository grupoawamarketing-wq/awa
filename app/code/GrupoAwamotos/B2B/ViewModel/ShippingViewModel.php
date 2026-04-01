<?php

/**
 * B2B Shipping Selector ViewModel
 * Provides carrier data for checkout
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\B2B\Model\CarrierService;
use Magento\Framework\Serialize\Serializer\Json;

class ShippingViewModel implements ArgumentInterface
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
     * @var Json
     */
    private $json;

    /**
     * @param CustomerSession $customerSession
     * @param CarrierService $carrierService
     * @param Json $json
     */
    public function __construct(
        CustomerSession $customerSession,
        CarrierService $carrierService,
        Json $json
    ) {
        $this->customerSession = $customerSession;
        $this->carrierService = $carrierService;
        $this->json = $json;
    }

    /**
     * Check if customer is B2B (delegates to CarrierService)
     *
     * @return bool
     */
    public function isB2BCustomer(): bool
    {
        return $this->carrierService->isB2BCustomer();
    }

    /**
     * Get B2B carriers as JSON
     *
     * @return string
     */
    public function getCarriersJson(): string
    {
        $carriers = $this->carrierService->getAvailableCarriers();
        $data = [];

        foreach ($carriers as $carrier) {
            $data[] = [
                'code' => $carrier->getCode(),
                'name' => $carrier->getName(),
                'logo' => $carrier->getLogo(),
                'phone' => $carrier->getPhone(),
                'website' => $carrier->getWebsite(),
                'description' => $carrier->getDescription(),
                'delivery_time' => $carrier->getDeliveryTime()
            ];
        }

        return $this->json->serialize($data);
    }

    /**
     * Get carriers collection
     *
     * @return \GrupoAwamotos\B2B\Model\ResourceModel\Carrier\Collection
     */
    public function getCarriers()
    {
        return $this->carrierService->getAvailableCarriers();
    }
}

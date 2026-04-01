<?php

/**
 * Model da Transportadora
 */

declare(strict_types=1);

namespace GrupoAwamotos\CarrierSelect\Model;

use Magento\Framework\Model\AbstractModel;
use GrupoAwamotos\CarrierSelect\Model\ResourceModel\Carrier as CarrierResource;

class Carrier extends AbstractModel
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(CarrierResource::class);
    }

    /**
     * Get carrier ID
     *
     * @return int|null
     */
    public function getCarrierId(): ?int
    {
        return $this->getData('carrier_id') ? (int) $this->getData('carrier_id') : null;
    }

    /**
     * Get carrier name
     *
     * @return string
     */
    public function getName(): string
    {
        return (string) $this->getData('name');
    }

    /**
     * Get carrier code
     *
     * @return string
     */
    public function getCode(): string
    {
        return (string) $this->getData('code');
    }

    /**
     * Get contact phone
     *
     * @return string|null
     */
    public function getContactPhone(): ?string
    {
        return $this->getData('contact_phone');
    }

    /**
     * Get contact email
     *
     * @return string|null
     */
    public function getContactEmail(): ?string
    {
        return $this->getData('contact_email');
    }

    /**
     * Get website
     *
     * @return string|null
     */
    public function getWebsite(): ?string
    {
        return $this->getData('website');
    }

    /**
     * Get regions as array
     *
     * @return array
     */
    public function getRegions(): array
    {
        $regions = $this->getData('regions');
        if ($regions) {
            return json_decode($regions, true) ?: [];
        }
        return [];
    }

    /**
     * Get notes
     *
     * @return string|null
     */
    public function getNotes(): ?string
    {
        return $this->getData('notes');
    }

    /**
     * Check if carrier is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->getData('is_active');
    }

    /**
     * Get sort order
     *
     * @return int
     */
    public function getSortOrder(): int
    {
        return (int) $this->getData('sort_order');
    }
}

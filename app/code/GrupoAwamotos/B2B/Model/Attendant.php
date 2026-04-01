<?php

/**
 * B2B Attendant Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class Attendant extends AbstractModel
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\Attendant::class);
    }

    public function getName(): string
    {
        return (string)$this->getData('name');
    }

    public function getEmail(): string
    {
        return (string)$this->getData('email');
    }

    public function getPhone(): ?string
    {
        return $this->getData('phone');
    }

    public function getWhatsapp(): ?string
    {
        return $this->getData('whatsapp');
    }

    public function getDepartment(): string
    {
        return (string)$this->getData('department');
    }

    public function getIsActive(): bool
    {
        return (bool)$this->getData('is_active');
    }

    public function getCustomerCount(): int
    {
        return (int)$this->getData('customer_count');
    }

    public function getMaxCustomers(): int
    {
        return (int)$this->getData('max_customers');
    }
}

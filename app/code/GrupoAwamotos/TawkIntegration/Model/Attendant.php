<?php

declare(strict_types=1);

namespace GrupoAwamotos\TawkIntegration\Model;

use Magento\Framework\Model\AbstractModel;

class Attendant extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Attendant::class);
    }

    public function getCustomerId(): int
    {
        return (int) $this->getData('customer_id');
    }

    public function setCustomerId(int $id): self
    {
        return $this->setData('customer_id', $id);
    }

    public function getAttendantCode(): string
    {
        return (string) $this->getData('attendant_code');
    }

    public function setAttendantCode(string $code): self
    {
        return $this->setData('attendant_code', $code);
    }

    public function isAutoAssigned(): bool
    {
        return (bool) $this->getData('auto_assigned');
    }

    public function setAutoAssigned(bool $auto): self
    {
        return $this->setData('auto_assigned', (int) $auto);
    }
}

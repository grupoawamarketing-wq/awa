<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model;

use Magento\Framework\Model\AbstractModel;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\SuggestionHistory as SuggestionHistoryResource;

/**
 * Suggestion History Model
 */
class SuggestionHistory extends AbstractModel
{
    /**
     * Status constants
     */
    public const STATUS_GENERATED = 'generated';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ = 'read';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_FAILED = 'send_failed';

    /**
     * Channel constants
     */
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_MANUAL = 'manual';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(SuggestionHistoryResource::class);
    }

    /**
     * Get customer ID
     */
    public function getCustomerId(): int
    {
        return (int) $this->getData('customer_id');
    }

    /**
     * Set customer ID
     */
    public function setCustomerId(int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * Get customer name
     */
    public function getCustomerName(): string
    {
        return (string) $this->getData('customer_name');
    }

    /**
     * Set customer name
     */
    public function setCustomerName(string $name): self
    {
        return $this->setData('customer_name', $name);
    }

    /**
     * Get suggestion data as array
     */
    public function getSuggestionData(): array
    {
        $data = $this->getData('suggestion_data');
        if (is_string($data)) {
            return json_decode($data, true) ?? [];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Set suggestion data
     */
    public function setSuggestionData(array $data): self
    {
        return $this->setData('suggestion_data', json_encode($data));
    }

    /**
     * Get total value
     */
    public function getTotalValue(): float
    {
        return (float) $this->getData('total_value');
    }

    /**
     * Set total value
     */
    public function setTotalValue(float $value): self
    {
        return $this->setData('total_value', $value);
    }

    /**
     * Get status
     */
    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    /**
     * Set status
     */
    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    /**
     * Get channel
     */
    public function getChannel(): ?string
    {
        return $this->getData('channel');
    }

    /**
     * Set channel
     */
    public function setChannel(string $channel): self
    {
        return $this->setData('channel', $channel);
    }

    /**
     * Check if converted
     */
    public function isConverted(): bool
    {
        return $this->getStatus() === self::STATUS_CONVERTED;
    }

    /**
     * Mark as converted
     */
    public function markAsConverted(int $orderId, float $orderValue): self
    {
        $this->setStatus(self::STATUS_CONVERTED);
        $this->setData('converted_order_id', $orderId);
        $this->setData('conversion_value', $orderValue);
        $this->setData('converted_at', date('Y-m-d H:i:s'));
        return $this;
    }

    /**
     * Get available statuses
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_GENERATED => __('Gerada'),
            self::STATUS_SENT => __('Enviada'),
            self::STATUS_DELIVERED => __('Entregue'),
            self::STATUS_READ => __('Lida'),
            self::STATUS_CONVERTED => __('Convertida'),
            self::STATUS_EXPIRED => __('Expirada'),
            self::STATUS_FAILED => __('Falha no Envio')
        ];
    }
}

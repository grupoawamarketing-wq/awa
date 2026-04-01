<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model;

use Magento\Framework\Model\AbstractModel;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\WhatsappQueue as WhatsappQueueResource;

/**
 * WhatsApp Queue Model
 *
 * Manages queued WhatsApp messages for batch processing
 */
class WhatsappQueue extends AbstractModel
{
    /**
     * Status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Priority constants
     */
    public const PRIORITY_LOW = 0;
    public const PRIORITY_NORMAL = 5;
    public const PRIORITY_HIGH = 10;
    public const PRIORITY_URGENT = 15;

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(WhatsappQueueResource::class);
    }

    /**
     * Get phone number
     */
    public function getPhoneNumber(): string
    {
        return (string) $this->getData('phone_number');
    }

    /**
     * Set phone number
     */
    public function setPhoneNumber(string $phone): self
    {
        // Normalize phone number (remove non-digits, ensure country code)
        $normalized = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($normalized) === 11 && str_starts_with($normalized, '0')) {
            $normalized = '55' . substr($normalized, 1);
        } elseif (strlen($normalized) === 10 || strlen($normalized) === 11) {
            $normalized = '55' . $normalized;
        }

        return $this->setData('phone_number', $normalized);
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
     * Get message content
     */
    public function getMessageContent(): string
    {
        return (string) $this->getData('message_content');
    }

    /**
     * Set message content
     */
    public function setMessageContent(string $content): self
    {
        return $this->setData('message_content', $content);
    }

    /**
     * Get template name
     */
    public function getTemplateName(): ?string
    {
        return $this->getData('template_name');
    }

    /**
     * Set template name
     */
    public function setTemplateName(?string $template): self
    {
        return $this->setData('template_name', $template);
    }

    /**
     * Get template parameters as array
     */
    public function getTemplateParams(): array
    {
        $params = $this->getData('template_params');
        if (is_string($params)) {
            return json_decode($params, true) ?? [];
        }
        return is_array($params) ? $params : [];
    }

    /**
     * Set template parameters
     */
    public function setTemplateParams(array $params): self
    {
        return $this->setData('template_params', json_encode($params));
    }

    /**
     * Get suggestion history ID
     */
    public function getSuggestionHistoryId(): ?int
    {
        $id = $this->getData('suggestion_history_id');
        return $id ? (int) $id : null;
    }

    /**
     * Set suggestion history ID
     */
    public function setSuggestionHistoryId(?int $historyId): self
    {
        return $this->setData('suggestion_history_id', $historyId);
    }

    /**
     * Get status
     */
    public function getStatus(): string
    {
        return (string) ($this->getData('status') ?? self::STATUS_PENDING);
    }

    /**
     * Set status
     */
    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }

    /**
     * Get priority
     */
    public function getPriority(): int
    {
        return (int) ($this->getData('priority') ?? self::PRIORITY_NORMAL);
    }

    /**
     * Set priority
     */
    public function setPriority(int $priority): self
    {
        return $this->setData('priority', $priority);
    }

    /**
     * Get retry count
     */
    public function getRetryCount(): int
    {
        return (int) ($this->getData('retry_count') ?? 0);
    }

    /**
     * Increment retry count
     */
    public function incrementRetryCount(): self
    {
        return $this->setData('retry_count', $this->getRetryCount() + 1);
    }

    /**
     * Get error message
     */
    public function getErrorMessage(): ?string
    {
        return $this->getData('error_message');
    }

    /**
     * Set error message
     */
    public function setErrorMessage(?string $message): self
    {
        return $this->setData('error_message', $message);
    }

    /**
     * Get scheduled at time
     */
    public function getScheduledAt(): ?string
    {
        return $this->getData('scheduled_at');
    }

    /**
     * Set scheduled at time
     */
    public function setScheduledAt(?string $datetime): self
    {
        return $this->setData('scheduled_at', $datetime);
    }

    /**
     * Get sent at time
     */
    public function getSentAt(): ?string
    {
        return $this->getData('sent_at');
    }

    /**
     * Set sent at time
     */
    public function setSentAt(?string $datetime): self
    {
        return $this->setData('sent_at', $datetime);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(?string $externalId = null): self
    {
        $this->setStatus(self::STATUS_SENT);
        $this->setSentAt(date('Y-m-d H:i:s'));
        if ($externalId) {
            $this->setData('external_id', $externalId);
        }
        return $this;
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): self
    {
        $this->setStatus(self::STATUS_FAILED);
        $this->setErrorMessage($errorMessage);
        $this->incrementRetryCount();
        return $this;
    }

    /**
     * Check if can retry
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->getStatus() === self::STATUS_FAILED
            && $this->getRetryCount() < $maxRetries;
    }

    /**
     * Get available statuses
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING => __('Pendente'),
            self::STATUS_PROCESSING => __('Processando'),
            self::STATUS_SENT => __('Enviado'),
            self::STATUS_DELIVERED => __('Entregue'),
            self::STATUS_FAILED => __('Falhou'),
            self::STATUS_CANCELLED => __('Cancelado')
        ];
    }
}

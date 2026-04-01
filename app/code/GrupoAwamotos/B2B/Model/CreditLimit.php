<?php

/**
 * Credit Limit Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit as ResourceModel;

class CreditLimit extends AbstractModel
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_b2b_credit_limit';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * Get customer ID
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        return $this->getData('customer_id') ? (int)$this->getData('customer_id') : null;
    }

    /**
     * Set customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId(int $customerId): self
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * Get credit limit
     *
     * @return float
     */
    public function getCreditLimit(): float
    {
        return (float)$this->getData('credit_limit');
    }

    /**
     * Set credit limit
     *
     * @param float $limit
     * @return $this
     */
    public function setCreditLimit(float $limit): self
    {
        return $this->setData('credit_limit', $limit);
    }

    /**
     * Get used credit
     *
     * @return float
     */
    public function getUsedCredit(): float
    {
        return (float)$this->getData('used_credit');
    }

    /**
     * Set used credit
     *
     * @param float $used
     * @return $this
     */
    public function setUsedCredit(float $used): self
    {
        return $this->setData('used_credit', $used);
    }

    /**
     * Get available credit
     *
     * @return float
     */
    public function getAvailableCredit(): float
    {
        return $this->getCreditLimit() - $this->getUsedCredit();
    }

    /**
     * Get currency code
     *
     * @return string
     */
    public function getCurrencyCode(): string
    {
        return $this->getData('currency_code') ?? 'BRL';
    }

    /**
     * Set currency code
     *
     * @param string $code
     * @return $this
     */
    public function setCurrencyCode(string $code): self
    {
        return $this->setData('currency_code', $code);
    }

    /**
     * Default payment terms available for all B2B customers
     */
    public const DEFAULT_PAYMENT_TERMS = ['a_vista', '30', '60', '90'];

    /**
     * Human-readable labels for payment terms
     *
     * @return array<string, string>
     */
    public static function getPaymentTermLabels(): array
    {
        return [
            'a_vista' => (string) __('À Vista'),
            '30'      => (string) __('30 dias'),
            '60'      => (string) __('60 dias'),
            '90'      => (string) __('90 dias'),
        ];
    }

    /**
     * Get allowed payment terms for this customer
     *
     * @return string[]
     */
    public function getPaymentTerms(): array
    {
        $raw = $this->getData('payment_terms');
        if (empty($raw)) {
            return self::DEFAULT_PAYMENT_TERMS;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) && count($decoded) > 0 ? $decoded : self::DEFAULT_PAYMENT_TERMS;
    }

    /**
     * Set allowed payment terms
     *
     * @param string[] $terms
     * @return $this
     */
    public function setPaymentTerms(array $terms): self
    {
        return $this->setData('payment_terms', json_encode(array_values($terms)));
    }

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData('created_at');
    }

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData('updated_at');
    }
}

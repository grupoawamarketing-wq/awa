<?php

/**
 * Quote Request Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Api\Data\QuoteRequestInterface;
use Magento\Framework\Model\AbstractModel;

class QuoteRequest extends AbstractModel implements QuoteRequestInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_b2b_quote_request';

    /**
     * Initialize resource model
     */
    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest::class);
    }

    /**
     * @inheritDoc
     */
    public function getRequestId(): ?int
    {
        $id = $this->getData(self::REQUEST_ID);
        return $id ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setRequestId(int $requestId)
    {
        return $this->setData(self::REQUEST_ID, $requestId);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerId(): ?int
    {
        $id = $this->getData(self::CUSTOMER_ID);
        return $id ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId(?int $customerId)
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerEmail(): string
    {
        return (string) $this->getData(self::CUSTOMER_EMAIL);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerEmail(string $email)
    {
        return $this->setData(self::CUSTOMER_EMAIL, $email);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerName(): string
    {
        return (string) $this->getData(self::CUSTOMER_NAME);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerName(string $name)
    {
        return $this->setData(self::CUSTOMER_NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getCompanyName(): ?string
    {
        return $this->getData(self::COMPANY_NAME);
    }

    /**
     * @inheritDoc
     */
    public function setCompanyName(?string $companyName)
    {
        return $this->setData(self::COMPANY_NAME, $companyName);
    }

    /**
     * @inheritDoc
     */
    public function getCnpj(): ?string
    {
        return $this->getData(self::CNPJ);
    }

    /**
     * @inheritDoc
     */
    public function setCnpj(?string $cnpj)
    {
        return $this->setData(self::CNPJ, $cnpj);
    }

    /**
     * @inheritDoc
     */
    public function getPhone(): ?string
    {
        return $this->getData(self::PHONE);
    }

    /**
     * @inheritDoc
     */
    public function setPhone(?string $phone)
    {
        return $this->setData(self::PHONE, $phone);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return (string) ($this->getData(self::STATUS) ?: self::STATUS_PENDING);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status)
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getItemsJson(): string
    {
        return (string) $this->getData(self::ITEMS_JSON);
    }

    /**
     * @inheritDoc
     */
    public function setItemsJson(string $itemsJson)
    {
        return $this->setData(self::ITEMS_JSON, $itemsJson);
    }

    /**
     * @inheritDoc
     */
    public function getItems(): array
    {
        $json = $this->getItemsJson();
        if (empty($json)) {
            return [];
        }

        try {
            return json_decode($json, true) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * @inheritDoc
     */
    public function setItems(array $items)
    {
        return $this->setItemsJson(json_encode($items));
    }

    /**
     * @inheritDoc
     */
    public function getMessage(): ?string
    {
        return $this->getData(self::MESSAGE);
    }

    /**
     * @inheritDoc
     */
    public function setMessage(?string $message)
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * @inheritDoc
     */
    public function getAdminNotes(): ?string
    {
        return $this->getData(self::ADMIN_NOTES);
    }

    /**
     * @inheritDoc
     */
    public function setAdminNotes(?string $adminNotes)
    {
        return $this->setData(self::ADMIN_NOTES, $adminNotes);
    }

    /**
     * @inheritDoc
     */
    public function getQuotedTotal(): ?float
    {
        $total = $this->getData(self::QUOTED_TOTAL);
        return $total !== null ? (float) $total : null;
    }

    /**
     * @inheritDoc
     */
    public function setQuotedTotal(?float $quotedTotal)
    {
        return $this->setData(self::QUOTED_TOTAL, $quotedTotal);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteId(): ?int
    {
        $id = $this->getData(self::QUOTE_ID);
        return $id ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setQuoteId(?int $quoteId)
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): ?int
    {
        $id = $this->getData(self::ORDER_ID);
        return $id ? (int) $id : null;
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(?int $orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getExpiresAt(): ?string
    {
        return $this->getData(self::EXPIRES_AT);
    }

    /**
     * @inheritDoc
     */
    public function setExpiresAt(?string $expiresAt)
    {
        return $this->setData(self::EXPIRES_AT, $expiresAt);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * Check if quote is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getExpiresAt();
        if (empty($expiresAt)) {
            return false;
        }

        return strtotime($expiresAt) < time();
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => __('Aguardando Análise'),
            self::STATUS_PROCESSING => __('Em Análise'),
            self::STATUS_QUOTED => __('Orçamento Enviado'),
            self::STATUS_ACCEPTED => __('Aceito'),
            self::STATUS_REJECTED => __('Recusado'),
            self::STATUS_EXPIRED => __('Expirado'),
            self::STATUS_CONVERTED => __('Convertido em Pedido'),
        ];

        return (string) ($labels[$this->getStatus()] ?? $this->getStatus());
    }
}

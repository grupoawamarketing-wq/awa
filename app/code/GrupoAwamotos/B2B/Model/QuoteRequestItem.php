<?php

/**
 * Quote Request Item Model
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use Magento\Framework\Model\AbstractModel;

class QuoteRequestItem extends AbstractModel
{
    protected $_eventPrefix = 'grupoawamotos_b2b_quote_request_item';

    protected function _construct()
    {
        $this->_init(\GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequestItem::class);
    }

    public function getItemId(): ?int
    {
        $id = $this->getData('item_id');
        return $id ? (int) $id : null;
    }

    public function getRequestId(): int
    {
        return (int) $this->getData('request_id');
    }

    public function setRequestId(int $requestId): self
    {
        return $this->setData('request_id', $requestId);
    }

    public function getProductId(): int
    {
        return (int) $this->getData('product_id');
    }

    public function setProductId(int $productId): self
    {
        return $this->setData('product_id', $productId);
    }

    public function getSku(): string
    {
        return (string) $this->getData('sku');
    }

    public function setSku(string $sku): self
    {
        return $this->setData('sku', $sku);
    }

    public function getName(): string
    {
        return (string) $this->getData('name');
    }

    public function setName(string $name): self
    {
        return $this->setData('name', $name);
    }

    public function getQty(): float
    {
        return (float) $this->getData('qty');
    }

    public function setQty(float $qty): self
    {
        return $this->setData('qty', $qty);
    }

    public function getOriginalPrice(): ?float
    {
        $price = $this->getData('original_price');
        return $price !== null ? (float) $price : null;
    }

    public function setOriginalPrice(?float $price): self
    {
        return $this->setData('original_price', $price);
    }

    public function getQuotedPrice(): ?float
    {
        $price = $this->getData('quoted_price');
        return $price !== null ? (float) $price : null;
    }

    public function setQuotedPrice(?float $price): self
    {
        return $this->setData('quoted_price', $price);
    }

    public function getOptionsJson(): ?string
    {
        return $this->getData('options_json');
    }

    public function getOptions(): array
    {
        $json = $this->getOptionsJson();
        if (empty($json)) {
            return [];
        }
        try {
            return json_decode($json, true) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getNotes(): ?string
    {
        return $this->getData('notes');
    }

    public function setNotes(?string $notes): self
    {
        return $this->setData('notes', $notes);
    }
}

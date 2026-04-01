<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model;

use Magento\Framework\Model\AbstractModel;
use GrupoAwamotos\SmartSuggestions\Model\ResourceModel\RfmCache as RfmCacheResource;

/**
 * RFM Cache Model
 *
 * Stores cached RFM scores for improved performance
 */
class RfmCache extends AbstractModel
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(RfmCacheResource::class);
    }

    /**
     * Get ERP Customer ID
     */
    public function getErpCustomerId(): int
    {
        return (int) $this->getData('erp_customer_id');
    }

    /**
     * Set ERP Customer ID
     */
    public function setErpCustomerId(int $customerId): self
    {
        return $this->setData('erp_customer_id', $customerId);
    }

    /**
     * Get R Score
     */
    public function getRScore(): int
    {
        return (int) $this->getData('r_score');
    }

    /**
     * Set R Score
     */
    public function setRScore(int $score): self
    {
        return $this->setData('r_score', $score);
    }

    /**
     * Get F Score
     */
    public function getFScore(): int
    {
        return (int) $this->getData('f_score');
    }

    /**
     * Set F Score
     */
    public function setFScore(int $score): self
    {
        return $this->setData('f_score', $score);
    }

    /**
     * Get M Score
     */
    public function getMScore(): int
    {
        return (int) $this->getData('m_score');
    }

    /**
     * Set M Score
     */
    public function setMScore(int $score): self
    {
        return $this->setData('m_score', $score);
    }

    /**
     * Get RFM Combined Score
     */
    public function getRfmScore(): string
    {
        return (string) $this->getData('rfm_score');
    }

    /**
     * Set RFM Combined Score
     */
    public function setRfmScore(string $score): self
    {
        return $this->setData('rfm_score', $score);
    }

    /**
     * Get Segment
     */
    public function getSegment(): string
    {
        return (string) $this->getData('segment');
    }

    /**
     * Set Segment
     */
    public function setSegment(string $segment): self
    {
        return $this->setData('segment', $segment);
    }

    /**
     * Get Recency Days
     */
    public function getRecencyDays(): int
    {
        return (int) $this->getData('recency_days');
    }

    /**
     * Set Recency Days
     */
    public function setRecencyDays(int $days): self
    {
        return $this->setData('recency_days', $days);
    }

    /**
     * Get Total Orders (Frequency)
     */
    public function getTotalOrders(): int
    {
        return (int) $this->getData('frequency');
    }

    /**
     * Set Total Orders
     */
    public function setTotalOrders(int $orders): self
    {
        return $this->setData('frequency', $orders);
    }

    /**
     * Get Total Revenue (Monetary)
     */
    public function getTotalRevenue(): float
    {
        return (float) $this->getData('monetary');
    }

    /**
     * Set Total Revenue
     */
    public function setTotalRevenue(float $revenue): self
    {
        return $this->setData('monetary', $revenue);
    }

    /**
     * Get Last Purchase Date
     */
    public function getLastPurchaseDate(): ?string
    {
        return $this->getData('last_order_date');
    }

    /**
     * Set Last Purchase Date
     */
    public function setLastPurchaseDate(?string $date): self
    {
        return $this->setData('last_order_date', $date);
    }

    /**
     * Check if cache is expired (older than 24 hours)
     */
    public function isExpired(int $maxAgeHours = 24): bool
    {
        $updatedAt = $this->getData('calculated_at') ?: $this->getData('updated_at');
        if (!$updatedAt) {
            return true;
        }

        $expirationTime = strtotime($updatedAt) + ($maxAgeHours * 3600);
        return time() > $expirationTime;
    }
}

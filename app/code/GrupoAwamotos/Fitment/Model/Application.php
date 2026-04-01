<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model;

use GrupoAwamotos\Fitment\Api\Data\ApplicationInterface;
use GrupoAwamotos\Fitment\Model\ResourceModel\Application as ResourceModel;
use Magento\Framework\Model\AbstractModel;

/**
 * Application Model - Aplicação de Peças (Peça x Moto)
 */
class Application extends AbstractModel implements ApplicationInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_application';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * @inheritdoc
     */
    public function getApplicationId(): ?int
    {
        $value = $this->getData(self::APPLICATION_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setApplicationId(int $applicationId): ApplicationInterface
    {
        return $this->setData(self::APPLICATION_ID, $applicationId);
    }

    /**
     * @inheritdoc
     */
    public function getProductId(): ?int
    {
        $value = $this->getData(self::PRODUCT_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setProductId(int $productId): ApplicationInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritdoc
     */
    public function getModelId(): ?int
    {
        $value = $this->getData(self::MODEL_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setModelId(int $modelId): ApplicationInterface
    {
        return $this->setData(self::MODEL_ID, $modelId);
    }

    /**
     * @inheritdoc
     */
    public function getYearFrom(): ?int
    {
        $value = $this->getData(self::YEAR_FROM);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setYearFrom(?int $yearFrom): ApplicationInterface
    {
        return $this->setData(self::YEAR_FROM, $yearFrom);
    }

    /**
     * @inheritdoc
     */
    public function getYearTo(): ?int
    {
        $value = $this->getData(self::YEAR_TO);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setYearTo(?int $yearTo): ApplicationInterface
    {
        return $this->setData(self::YEAR_TO, $yearTo);
    }

    /**
     * @inheritdoc
     */
    public function getPosition(): ?string
    {
        return $this->getData(self::POSITION);
    }

    /**
     * @inheritdoc
     */
    public function setPosition(?string $position): ApplicationInterface
    {
        return $this->setData(self::POSITION, $position);
    }

    /**
     * @inheritdoc
     */
    public function getNotes(): ?string
    {
        return $this->getData(self::NOTES);
    }

    /**
     * @inheritdoc
     */
    public function setNotes(?string $notes): ApplicationInterface
    {
        return $this->setData(self::NOTES, $notes);
    }

    /**
     * @inheritdoc
     */
    public function getIsOem(): bool
    {
        return (bool) $this->getData(self::IS_OEM);
    }

    /**
     * @inheritdoc
     */
    public function setIsOem(bool $isOem): ApplicationInterface
    {
        return $this->setData(self::IS_OEM, $isOem ? 1 : 0);
    }

    /**
     * @inheritdoc
     */
    public function getOemCode(): ?string
    {
        return $this->getData(self::OEM_CODE);
    }

    /**
     * @inheritdoc
     */
    public function setOemCode(?string $oemCode): ApplicationInterface
    {
        return $this->setData(self::OEM_CODE, $oemCode);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}

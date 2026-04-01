<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model;

use GrupoAwamotos\Fitment\Api\Data\MotorcycleModelInterface;
use GrupoAwamotos\Fitment\Model\ResourceModel\MotorcycleModel as ResourceModel;
use Magento\Framework\Model\AbstractModel;

/**
 * Motorcycle Model - Modelos de Motos
 *
 * Named "MotorcycleModel" to avoid conflict with Magento's AbstractModel
 */
class MotorcycleModel extends AbstractModel implements MotorcycleModelInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_model';

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
    public function getModelId(): ?int
    {
        $value = $this->getData(self::MODEL_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setModelId(int $modelId): MotorcycleModelInterface
    {
        return $this->setData(self::MODEL_ID, $modelId);
    }

    /**
     * @inheritdoc
     */
    public function getBrandId(): ?int
    {
        $value = $this->getData(self::BRAND_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setBrandId(int $brandId): MotorcycleModelInterface
    {
        return $this->setData(self::BRAND_ID, $brandId);
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritdoc
     */
    public function setName(string $name): MotorcycleModelInterface
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function getCode(): ?string
    {
        return $this->getData(self::CODE);
    }

    /**
     * @inheritdoc
     */
    public function setCode(?string $code): MotorcycleModelInterface
    {
        return $this->setData(self::CODE, $code);
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
    public function setYearFrom(?int $yearFrom): MotorcycleModelInterface
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
    public function setYearTo(?int $yearTo): MotorcycleModelInterface
    {
        return $this->setData(self::YEAR_TO, $yearTo);
    }

    /**
     * @inheritdoc
     */
    public function getEngineCc(): ?string
    {
        return $this->getData(self::ENGINE_CC);
    }

    /**
     * @inheritdoc
     */
    public function setEngineCc(?string $engineCc): MotorcycleModelInterface
    {
        return $this->setData(self::ENGINE_CC, $engineCc);
    }

    /**
     * @inheritdoc
     */
    public function getCategory(): ?string
    {
        return $this->getData(self::CATEGORY);
    }

    /**
     * @inheritdoc
     */
    public function setCategory(?string $category): MotorcycleModelInterface
    {
        return $this->setData(self::CATEGORY, $category);
    }

    /**
     * @inheritdoc
     */
    public function getImage(): ?string
    {
        return $this->getData(self::IMAGE);
    }

    /**
     * @inheritdoc
     */
    public function setImage(?string $image): MotorcycleModelInterface
    {
        return $this->setData(self::IMAGE, $image);
    }

    /**
     * @inheritdoc
     */
    public function getIsActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setIsActive(bool $isActive): MotorcycleModelInterface
    {
        return $this->setData(self::IS_ACTIVE, $isActive ? 1 : 0);
    }

    /**
     * @inheritdoc
     */
    public function getSortOrder(): int
    {
        return (int) $this->getData(self::SORT_ORDER);
    }

    /**
     * @inheritdoc
     */
    public function setSortOrder(int $sortOrder): MotorcycleModelInterface
    {
        return $this->setData(self::SORT_ORDER, $sortOrder);
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

    /**
     * @inheritdoc
     */
    public function getFormattedYears(): string
    {
        $from = $this->getYearFrom();
        $to = $this->getYearTo();

        if ($from === null && $to === null) {
            return 'Todos os anos';
        }

        if ($from !== null && $to === null) {
            return "{$from}-Atual";
        }

        if ($from === null && $to !== null) {
            return "Até {$to}";
        }

        if ($from === $to) {
            return (string) $from;
        }

        return "{$from}-{$to}";
    }
}

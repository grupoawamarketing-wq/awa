<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Model;

use GrupoAwamotos\Fitment\Api\Data\BrandInterface;
use GrupoAwamotos\Fitment\Model\ResourceModel\Brand as ResourceModel;
use Magento\Framework\Model\AbstractModel;

/**
 * Brand Model - Marcas de Motos
 */
class Brand extends AbstractModel implements BrandInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'grupoawamotos_fitment_brand';

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
    public function getBrandId(): ?int
    {
        $value = $this->getData(self::BRAND_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setBrandId(int $brandId): BrandInterface
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
    public function setName(string $name): BrandInterface
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
    public function setCode(?string $code): BrandInterface
    {
        return $this->setData(self::CODE, $code);
    }

    /**
     * @inheritdoc
     */
    public function getLogo(): ?string
    {
        return $this->getData(self::LOGO);
    }

    /**
     * @inheritdoc
     */
    public function setLogo(?string $logo): BrandInterface
    {
        return $this->setData(self::LOGO, $logo);
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
    public function setIsActive(bool $isActive): BrandInterface
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
    public function setSortOrder(int $sortOrder): BrandInterface
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
}

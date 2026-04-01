<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Api\Data;

/**
 * Application Data Interface (Peça x Moto)
 *
 * @api
 */
interface ApplicationInterface
{
    public const APPLICATION_ID = 'application_id';
    public const PRODUCT_ID = 'product_id';
    public const MODEL_ID = 'model_id';
    public const YEAR_FROM = 'year_from';
    public const YEAR_TO = 'year_to';
    public const POSITION = 'position';
    public const NOTES = 'notes';
    public const IS_OEM = 'is_oem';
    public const OEM_CODE = 'oem_code';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get application ID
     */
    public function getApplicationId(): ?int;

    /**
     * Set application ID
     */
    public function setApplicationId(int $applicationId): self;

    /**
     * Get product ID (Magento catalog_product_entity.entity_id)
     */
    public function getProductId(): ?int;

    /**
     * Set product ID
     */
    public function setProductId(int $productId): self;

    /**
     * Get model ID
     */
    public function getModelId(): ?int;

    /**
     * Set model ID
     */
    public function setModelId(int $modelId): self;

    /**
     * Get year from (override do modelo)
     */
    public function getYearFrom(): ?int;

    /**
     * Set year from
     */
    public function setYearFrom(?int $yearFrom): self;

    /**
     * Get year to (override do modelo)
     */
    public function getYearTo(): ?int;

    /**
     * Set year to
     */
    public function setYearTo(?int $yearTo): self;

    /**
     * Get position (Dianteiro, Traseiro, etc.)
     */
    public function getPosition(): ?string;

    /**
     * Set position
     */
    public function setPosition(?string $position): self;

    /**
     * Get notes
     */
    public function getNotes(): ?string;

    /**
     * Set notes
     */
    public function setNotes(?string $notes): self;

    /**
     * Is OEM part?
     */
    public function getIsOem(): bool;

    /**
     * Set is OEM
     */
    public function setIsOem(bool $isOem): self;

    /**
     * Get OEM code
     */
    public function getOemCode(): ?string;

    /**
     * Set OEM code
     */
    public function setOemCode(?string $oemCode): self;

    /**
     * Get created at
     */
    public function getCreatedAt(): ?string;

    /**
     * Get updated at
     */
    public function getUpdatedAt(): ?string;
}

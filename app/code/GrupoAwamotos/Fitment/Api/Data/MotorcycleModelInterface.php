<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Api\Data;

/**
 * Motorcycle Model Data Interface
 *
 * @api
 */
interface MotorcycleModelInterface
{
    public const MODEL_ID = 'model_id';
    public const BRAND_ID = 'brand_id';
    public const NAME = 'name';
    public const CODE = 'code';
    public const YEAR_FROM = 'year_from';
    public const YEAR_TO = 'year_to';
    public const ENGINE_CC = 'engine_cc';
    public const CATEGORY = 'category';
    public const IMAGE = 'image';
    public const IS_ACTIVE = 'is_active';
    public const SORT_ORDER = 'sort_order';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Get model ID
     */
    public function getModelId(): ?int;

    /**
     * Set model ID
     */
    public function setModelId(int $modelId): self;

    /**
     * Get brand ID
     */
    public function getBrandId(): ?int;

    /**
     * Set brand ID
     */
    public function setBrandId(int $brandId): self;

    /**
     * Get name
     */
    public function getName(): ?string;

    /**
     * Set name
     */
    public function setName(string $name): self;

    /**
     * Get code
     */
    public function getCode(): ?string;

    /**
     * Set code
     */
    public function setCode(?string $code): self;

    /**
     * Get year from
     */
    public function getYearFrom(): ?int;

    /**
     * Set year from
     */
    public function setYearFrom(?int $yearFrom): self;

    /**
     * Get year to
     */
    public function getYearTo(): ?int;

    /**
     * Set year to
     */
    public function setYearTo(?int $yearTo): self;

    /**
     * Get engine CC
     */
    public function getEngineCc(): ?string;

    /**
     * Set engine CC
     */
    public function setEngineCc(?string $engineCc): self;

    /**
     * Get category
     */
    public function getCategory(): ?string;

    /**
     * Set category
     */
    public function setCategory(?string $category): self;

    /**
     * Get image
     */
    public function getImage(): ?string;

    /**
     * Set image
     */
    public function setImage(?string $image): self;

    /**
     * Get is active
     */
    public function getIsActive(): bool;

    /**
     * Set is active
     */
    public function setIsActive(bool $isActive): self;

    /**
     * Get sort order
     */
    public function getSortOrder(): int;

    /**
     * Set sort order
     */
    public function setSortOrder(int $sortOrder): self;

    /**
     * Get created at
     */
    public function getCreatedAt(): ?string;

    /**
     * Get updated at
     */
    public function getUpdatedAt(): ?string;

    /**
     * Get formatted years (e.g., "2016-2024" or "2020-Atual")
     */
    public function getFormattedYears(): string;
}

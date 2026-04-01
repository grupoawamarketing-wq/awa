<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Api\Data;

/**
 * Brand Data Interface
 *
 * @api
 */
interface BrandInterface
{
    public const BRAND_ID = 'brand_id';
    public const NAME = 'name';
    public const CODE = 'code';
    public const LOGO = 'logo';
    public const IS_ACTIVE = 'is_active';
    public const SORT_ORDER = 'sort_order';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

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
     * Get logo
     */
    public function getLogo(): ?string;

    /**
     * Set logo
     */
    public function setLogo(?string $logo): self;

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
}

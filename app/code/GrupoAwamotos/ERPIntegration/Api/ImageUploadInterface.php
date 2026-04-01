<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * REST API for receiving product images from external sources (ERP server, scripts, etc.)
 *
 * Endpoints:
 * - POST /V1/erp/images/upload       Upload image for a product
 * - POST /V1/erp/images/upload-batch  Upload images for multiple products
 * - GET  /V1/erp/images/pending       List products missing images
 */
interface ImageUploadInterface
{
    /**
     * Upload a product image via base64-encoded data.
     *
     * @param string $sku Product SKU
     * @param string $imageData Base64-encoded image content
     * @param string $filename Original filename (e.g. "0070.jpg")
     * @param bool $isMain Whether this should be the main product image
     * @param string|null $label Image label/alt text
     * @return string[] Result with status and message
     */
    public function upload(
        string $sku,
        string $imageData,
        string $filename,
        bool $isMain = true,
        ?string $label = null
    ): array;

    /**
     * Upload images for multiple products in a single request.
     *
     * @param mixed[] $images Array of [sku, imageData, filename, isMain, label]
     * @return string[] Results per SKU
     */
    public function uploadBatch(array $images): array;

    /**
     * Get list of products that are missing images.
     *
     * @param int $limit Max number of results (default 100)
     * @return mixed[] List of [sku, name, codinterno]
     */
    public function getPendingProducts(int $limit = 100): array;
}

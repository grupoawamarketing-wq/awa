<?php
declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp Commerce Catalog API
 *
 * Endpoints simplificados para consumo via Typebot/N8N.
 * Retorna dados formatados para exibição no WhatsApp (texto, imagens, botões).
 */
interface CatalogInterface
{
    /**
     * Search products by text query
     *
     * @param string $q Search query
     * @param int $page Page number (default: 1)
     * @return mixed[] Product list with simplified data
     */
    public function search(string $q, int $page = 1): array;

    /**
     * Search products by fitment (brand/model/year)
     *
     * @param string $brand Motorcycle brand name (e.g. "Honda")
     * @param string $model Motorcycle model name (e.g. "CG 160")
     * @param int|null $year Motorcycle year (optional)
     * @return mixed[] Compatible products list
     */
    public function searchByFitment(string $brand, string $model, ?int $year = null): array;

    /**
     * Get top-level categories
     *
     * @return mixed[] Category list
     */
    public function getCategories(): array;

    /**
     * Get products by category
     *
     * @param int $categoryId Category ID
     * @param int $page Page number (default: 1)
     * @return mixed[] Product list
     */
    public function getProductsByCategory(int $categoryId, int $page = 1): array;

    /**
     * Get product details
     *
     * @param string $sku Product SKU
     * @return mixed[] Product data with image, price, stock, fitment info
     */
    public function getProduct(string $sku): array;
}

<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

interface ImageSyncInterface
{
    /**
     * Sincroniza todas as imagens de produtos do ERP
     *
     * @param bool $force Forçar sync mesmo se desabilitado na config
     * @return array Resultado [synced, errors, skipped, total]
     */
    public function syncAll(bool $force = false): array;

    /**
     * Sincroniza imagens de um produto específico
     *
     * @param string $sku SKU do produto
     * @return bool True se sincronizado com sucesso
     */
    public function syncBySku(string $sku): bool;

    /**
     * Obtém URLs/paths de imagens do ERP para um produto
     *
     * @param string $sku SKU do produto
     * @return array Lista de imagens [path, position, type]
     */
    public function getErpImages(string $sku): array;

    /**
     * Remove imagens órfãs (que existem no Magento mas não no ERP)
     *
     * @return array Resultado [removed, errors]
     */
    public function cleanOrphanImages(): array;

    /**
     * Verifica se há imagens pendentes de sincronização
     *
     * @return int Quantidade de imagens pendentes
     */
    public function getPendingCount(): int;
}

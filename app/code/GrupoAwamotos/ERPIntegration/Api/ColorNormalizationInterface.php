<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Api;

/**
 * Contrato de serviço para normalização de cores do ERP → atributo Magento.
 *
 * Separa o mapeamento de abreviações ERP dos módulos de sincronização,
 * permitindo substituição (preference) ou mock em testes.
 */
interface ColorNormalizationInterface
{
    /**
     * Resolve o option_id Magento a partir de um valor bruto do campo COR do ERP.
     *
     * - Normaliza o valor para UPPERCASE sem espaços extras.
     * - Usa dicionário interno de abreviações → label canônico.
     * - Consulta o DB para obter o option_id pelo label (store 0).
     * - Armazena resultado em cache de request.
     * - Nunca cria opções dinamicamente: retorna null + log de warning se não mapeável.
     *
     * @param string|null $erpValue Valor bruto do campo COR vindo do ERP.
     * @return int|null option_id do atributo color, ou null se não mapeável.
     */
    public function resolveOptionId(?string $erpValue): ?int;

    /**
     * Retorna o attribute_id do atributo `color` de catalog_product.
     * Resultado é cacheado em memória por request.
     *
     * @return int|null attribute_id, ou null se o atributo não existir.
     */
    public function getColorAttributeId(): ?int;

    /**
     * Invalida cache em memória.
     * Útil em testes ou no início de jobs de processamento em lote.
     */
    public function clearCache(): void;
}

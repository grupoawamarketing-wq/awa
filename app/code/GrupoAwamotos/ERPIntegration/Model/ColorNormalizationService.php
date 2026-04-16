<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

use GrupoAwamotos\ERPIntegration\Api\ColorNormalizationInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Converte valores de cor brutos do ERP para option_id do atributo `color` do Magento.
 *
 * Regras:
 *  - Normaliza para UPPERCASE e trimado.
 *  - Usa dicionário estático de abreviações ERP → label canônico.
 *  - Consulta o DB via JOIN único para obter o option_id (store 0).
 *  - Armazena resultados em cache de request usando flag booleana para evitar
 *    re-consultas mesmo quando o atributo não é encontrado (null não é sentinela segura).
 *  - NUNCA cria opções dinamicamente — retorna null + warning se não mapeável.
 */
class ColorNormalizationService implements ColorNormalizationInterface
{
    /**
     * Mapeamento ERP abreviação/nome → label canônico Magento.
     *
     * @var array<string, string>
     */
    private const ERP_TO_CANONICAL = [
        'PT'              => 'Preto',
        'PTO'             => 'Preto',
        'PRETO'           => 'Preto',
        'PT BRILHANTE'    => 'Preto Brilhante',
        'PRETO BRILHANTE' => 'Preto Brilhante',
        'PTB'             => 'Preto Brilhante',
        'BC'              => 'Branco',
        'BCO'             => 'Branco',
        'BRANCO'          => 'Branco',
        'AZ'              => 'Azul',
        'AZUL'            => 'Azul',
        'VM'              => 'Vermelho',
        'VERMELHO'        => 'Vermelho',
        'VERM'            => 'Vermelho',
        'AM'              => 'Amarelo',
        'AMARELO'         => 'Amarelo',
        'LR'              => 'Laranja',
        'LAR'             => 'Laranja',
        'LARANJA'         => 'Laranja',
        'RS'              => 'Rosa',
        'ROSA'            => 'Rosa',
        'DR'              => 'Dourado',
        'DOUR'            => 'Dourado',
        'DOURADO'         => 'Dourado',
    ];

    /**
     * Cache dos option_ids já resolvidos, indexado por label canônico.
     * Valores null indicam que o label existe no dicionário mas não tem option_id no DB.
     *
     * @var array<string, int|null>
     */
    private array $optionCache = [];

    /**
     * attribute_id do atributo color, resolvido uma única vez por request.
     */
    private ?int $attrIdCache = null;

    /**
     * Flag: true quando a consulta ao attribute_id já foi realizada.
     * Necessário para distinguir "ainda não consultou" de "consultou e não encontrou".
     */
    private bool $attrIdQueried = false;

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function resolveOptionId(?string $erpValue): ?int
    {
        if ($erpValue === null || trim($erpValue) === '') {
            return null;
        }

        $attrId = $this->getColorAttributeId();
        if ($attrId === null) {
            return null;
        }

        $normalized = strtoupper(trim($erpValue));

        $canonical = self::ERP_TO_CANONICAL[$normalized] ?? null;
        if ($canonical === null) {
            $this->logger->warning(
                sprintf('[ColorNormalizationService] Valor ERP não mapeado: "%s"', $erpValue)
            );
            return null;
        }

        if (array_key_exists($canonical, $this->optionCache)) {
            return $this->optionCache[$canonical];
        }

        $optionId = $this->queryOptionId($canonical, $attrId);
        $this->optionCache[$canonical] = $optionId;

        if ($optionId === null) {
            $this->logger->warning(
                sprintf(
                    '[ColorNormalizationService] Label canônico "%s" não encontrado nas opções color (attr_id=%d)',
                    $canonical,
                    $attrId
                )
            );
        }

        return $optionId;
    }

    /**
     * {@inheritDoc}
     */
    public function getColorAttributeId(): ?int
    {
        if ($this->attrIdQueried) {
            return $this->attrIdCache;
        }

        $this->attrIdQueried = true;

        $connection = $this->resourceConnection->getConnection();

        // JOIN único — evita subquery aninhada e garante índice em attribute_code + entity_type_code
        $select = $connection->select()
            ->from(['a' => $connection->getTableName('eav_attribute')], ['attribute_id'])
            ->join(
                ['t' => $connection->getTableName('eav_entity_type')],
                't.entity_type_id = a.entity_type_id',
                []
            )
            ->where('a.attribute_code = ?', 'color')
            ->where('t.entity_type_code = ?', 'catalog_product')
            ->limit(1);

        $id = $connection->fetchOne($select);

        $this->attrIdCache = ($id !== false && $id !== null) ? (int) $id : null;

        if ($this->attrIdCache === null) {
            $this->logger->error('[ColorNormalizationService] Atributo "color" não encontrado no catálogo.');
        }

        return $this->attrIdCache;
    }

    /**
     * {@inheritDoc}
     */
    public function clearCache(): void
    {
        $this->optionCache   = [];
        $this->attrIdCache   = null;
        $this->attrIdQueried = false;
    }

    /**
     * Busca option_id pelo label canônico (store_id = 0) para o atributo indicado.
     *
     * @param string $label  Label canônico a procurar.
     * @param int    $attrId attribute_id do atributo color.
     * @return int|null option_id encontrado, ou null.
     */
    private function queryOptionId(string $label, int $attrId): ?int
    {
        $connection = $this->resourceConnection->getConnection();

        $result = $connection->fetchOne(
            $connection->select()
                ->from(['o' => $connection->getTableName('eav_attribute_option')], ['o.option_id'])
                ->join(
                    ['v' => $connection->getTableName('eav_attribute_option_value')],
                    'v.option_id = o.option_id AND v.store_id = 0',
                    []
                )
                ->where('o.attribute_id = ?', $attrId)
                ->where('v.value = ?', $label)
                ->limit(1)
        );

        return ($result !== false && $result !== null) ? (int) $result : null;
    }
}

<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Generates actionable B2B growth experiment suggestions based on available data segments.
 *
 * Scans B2B tables (customers, quotes, prospects, audiences) and proposes
 * Meta Ads campaign experiments with estimated audience sizes and priorities.
 */
class GrowthExperimentsService
{
    private const XML_PATH_ENABLED = 'marketing_intelligence/growth_experiments/enabled';

    /**
     * B2B customer group IDs
     */
    private const B2B_GROUP_IDS = [4, 5, 6];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate experiment suggestions from available B2B data.
     *
     * @return array<int, array{
     *     id: string,
     *     name: string,
     *     description: string,
     *     segment: string,
     *     audience_size: int,
     *     priority: string,
     *     meta_objective: string,
     *     status: string
     * }>
     */
    public function getExperiments(): array
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED)) {
            return [];
        }

        $experiments = [];
        $connection = $this->resourceConnection->getConnection();

        try {
            $segments = $this->gatherSegments($connection);

            $experiments = array_merge(
                $this->buildLookalikeExperiment($segments),
                $this->buildQuoteRecoveryExperiment($segments),
                $this->buildCreditUpsellExperiment($segments),
                $this->buildReactivationExperiment($segments),
                $this->buildProspectNurturingExperiment($segments),
                $this->buildRepurchaseExperiment($segments),
                $this->buildCnpjListExperiment($segments)
            );

            // Sort by priority weight
            usort($experiments, static function (array $a, array $b): int {
                $weights = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
                return ($weights[$a['priority']] ?? 9) <=> ($weights[$b['priority']] ?? 9);
            });

            $this->logger->info('GrowthExperimentsService: generated experiments', [
                'count' => count($experiments),
                'segments' => array_map(
                    static fn(array $e): string => $e['id'],
                    $experiments
                ),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('GrowthExperimentsService: failed to generate experiments', [
                'error' => $e->getMessage(),
            ]);
        }

        return $experiments;
    }

    /**
     * Gather segment counts from database tables.
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return array<string, int>
     */
    private function gatherSegments($connection): array
    {
        $segments = [
            'b2b_approved' => 0,
            'b2b_total_orders' => 0,
            'b2b_repeat_buyers' => 0,
            'b2b_single_buyers' => 0,
            'quotes_pending' => 0,
            'quotes_expired' => 0,
            'quotes_accepted' => 0,
            'prospects_total' => 0,
            'prospects_hot' => 0,
            'prospects_contacted' => 0,
            'prospects_new' => 0,
            'audiences_total' => 0,
            'companies_active' => 0,
            'b2b_inactive_90d' => 0,
        ];

        // B2B approved customers
        $groupIds = implode(',', self::B2B_GROUP_IDS);
        $segments['b2b_approved'] = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM {$connection->getTableName('customer_entity')} WHERE group_id IN ({$groupIds})"
        );

        // B2B orders and repeat buyers
        $segments['b2b_total_orders'] = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT entity_id) FROM {$connection->getTableName('sales_order')} o
             INNER JOIN {$connection->getTableName('customer_entity')} c ON o.customer_id = c.entity_id
             WHERE c.group_id IN ({$groupIds})"
        );

        $segments['b2b_repeat_buyers'] = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM (
                SELECT customer_id, COUNT(*) as cnt
                FROM {$connection->getTableName('sales_order')}
                WHERE customer_id IN (SELECT entity_id FROM {$connection->getTableName('customer_entity')} WHERE group_id IN ({$groupIds}))
                GROUP BY customer_id
                HAVING cnt >= 2
            ) sub"
        );

        $segments['b2b_single_buyers'] = max(0, $segments['b2b_total_orders'] - $segments['b2b_repeat_buyers']);

        // B2B inactive 90 days (approved customers who haven't ordered in 90+ days)
        $segments['b2b_inactive_90d'] = (int) $connection->fetchOne(
            "SELECT COUNT(*)
             FROM {$connection->getTableName('customer_entity')} c
             WHERE c.group_id IN ({$groupIds})
               AND c.entity_id NOT IN (
                   SELECT DISTINCT customer_id FROM {$connection->getTableName('sales_order')}
                   WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) AND customer_id IS NOT NULL
               )"
        );

        // Quotes from B2B module
        $quotesTable = $connection->getTableName('grupoawamotos_b2b_quote_request');
        if ($this->tableExists($connection, $quotesTable)) {
            $segments['quotes_pending'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$quotesTable} WHERE status = 'pending'"
            );
            $segments['quotes_expired'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$quotesTable} WHERE status = 'expired' OR (expires_at IS NOT NULL AND expires_at < NOW() AND status = 'pending')"
            );
            $segments['quotes_accepted'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$quotesTable} WHERE status = 'accepted'"
            );
        }

        // Marketing Intelligence prospects
        $prospectsTable = $connection->getTableName('grupoawamotos_mktg_prospects');
        if ($this->tableExists($connection, $prospectsTable)) {
            $segments['prospects_total'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$prospectsTable}"
            );
            $segments['prospects_hot'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$prospectsTable} WHERE prospect_score >= 70"
            );
            $segments['prospects_contacted'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$prospectsTable} WHERE prospect_status = 'contacted'"
            );
            $segments['prospects_new'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$prospectsTable} WHERE prospect_status = 'new'"
            );
        }

        // Audiences
        $audiencesTable = $connection->getTableName('grupoawamotos_mktg_audiences');
        if ($this->tableExists($connection, $audiencesTable)) {
            $segments['audiences_total'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$audiencesTable}"
            );
        }

        // Active companies (B2B module)
        $companiesTable = $connection->getTableName('grupoawamotos_b2b_company');
        if ($this->tableExists($connection, $companiesTable)) {
            $segments['companies_active'] = (int) $connection->fetchOne(
                "SELECT COUNT(*) FROM {$companiesTable} WHERE status = 'active'"
            );
        }

        return $segments;
    }

    /**
     * Lookalike from B2B purchasers.
     *
     * @param array<string, int> $segments
     * @return array<int, array{id: string, name: string, description: string, segment: string, audience_size: int, priority: string, meta_objective: string, status: string}>
     */
    private function buildLookalikeExperiment(array $segments): array
    {
        if ($segments['b2b_approved'] < 3) {
            return [];
        }

        return [[
            'id' => 'lookalike_b2b_buyers',
            'name' => 'Lookalike de Compradores B2B',
            'description' => "Criar audiência lookalike a partir de {$segments['b2b_approved']} clientes B2B aprovados. "
                . 'Meta recomenda mínimo de 100 clientes para melhor performance de lookalike.',
            'segment' => 'B2B Aprovados',
            'audience_size' => $segments['b2b_approved'],
            'priority' => $segments['b2b_approved'] >= 20 ? 'high' : 'medium',
            'meta_objective' => 'OUTCOME_LEADS',
            'status' => $segments['audiences_total'] > 0 ? 'em_andamento' : 'sugerido',
        ]];
    }

    /**
     * Retarget expired/pending quotes.
     *
     * @param array<string, int> $segments
     * @return array<int, array{id: string, name: string, description: string, segment: string, audience_size: int, priority: string, meta_objective: string, status: string}>
     */
    private function buildQuoteRecoveryExperiment(array $segments): array
    {
        $recoverable = $segments['quotes_pending'] + $segments['quotes_expired'];
        if ($recoverable === 0) {
            return [];
        }

        return [[
            'id' => 'quote_recovery_retarget',
            'name' => 'Retargeting de Cotações Pendentes',
            'description' => "Reimpactar {$recoverable} cotações pendentes/expiradas via campanha de remarketing. "
                . 'Oferecer desconto ou condição especial para fechar a venda.',
            'segment' => 'Cotações Pendentes/Expiradas',
            'audience_size' => $recoverable,
            'priority' => 'critical',
            'meta_objective' => 'OUTCOME_SALES',
            'status' => 'sugerido',
        ]];
    }

    /**
     * Credit line utilization campaign.
     *
     * @param array<string, int> $segments
     * @return array<int, array{id: string, name: string, description: string, segment: string, audience_size: int, priority: string, meta_objective: string, status: string}>
     */
    private function buildCreditUpsellExperiment(array $segments): array
    {
        // Only suggest if there are B2B customers with accepted quotes (potential credit users)
        if ($segments['quotes_accepted'] === 0 && $segments['b2b_approved'] < 5) {
            return [];
        }

        $audience = max($segments['quotes_accepted'], (int) ($segments['b2b_approved'] * 0.3));

        return [[
            'id' => 'credit_upsell',
            'name' => 'Upsell de Linha de Crédito B2B',
            'description' => "Campanha para {$audience} clientes B2B com potencial de crédito. "
                . 'Estimular uso de crédito aprovado ou solicitar aumento de limite para compras recorrentes.',
            'segment' => 'B2B com Crédito Disponível',
            'audience_size' => $audience,
            'priority' => 'medium',
            'meta_objective' => 'OUTCOME_SALES',
            'status' => 'sugerido',
        ]];
    }

    /**
     * Reactivation for dormant B2B customers.
     *
     * @param array<string, int> $segments
     * @return array<int, array{id: string, name: string, description: string, segment: string, audience_size: int, priority: string, meta_objective: string, status: string}>
     */
    private function buildReactivationExperiment(array $segments): array
    {
        if ($segments['b2b_inactive_90d'] === 0) {
            return [];
        }

        return [[
            'id' => 'b2b_reactivation',
            'name' => 'Reativação de Clientes B2B Inativos',
            'description' => "{$segments['b2b_inactive_90d']} clientes B2B sem pedido nos últimos 90 dias. "
                . 'Campanha com oferta exclusiva para reativação (desconto, frete grátis, mix de produtos).',
            'segment' => 'B2B Inativos 90+ dias',
            'audience_size' => $segments['b2b_inactive_90d'],
            'priority' => 'high',
            'meta_objective' => 'OUTCOME_ENGAGEMENT',
            'status' => 'sugerido',
        ]];
    }

    /**
     * Nurture new/hot prospects.
     *
     * @param array<string, int> $segments
     * @return array<int, array{id: string, name: string, description: string, segment: string, audience_size: int, priority: string, meta_objective: string, status: string}>
     */
    private function buildProspectNurturingExperiment(array $segments): array
    {
        if ($segments['prospects_total'] === 0) {
            return [];
        }

        $priority = $segments['prospects_hot'] >= 5 ? 'high' : 'medium';

        return [[
            'id' => 'prospect_nurturing',
            'name' => 'Nurturing de Prospects Quentes',
            'description' => "{$segments['prospects_hot']} prospects com score >= 70 (de {$segments['prospects_total']} captados). "
                . 'Campanha de conteúdo/institucional para converter prospects em cadastros B2B.',
            'segment' => 'Prospects Captados',
            'audience_size' => $segments['prospects_total'],
            'priority' => $priority,
            'meta_objective' => 'OUTCOME_LEADS',
            'status' => 'sugerido',
        ]];
    }

    /**
     * Stimulate repeat purchases from single-buyers.
     *
     * @param array<string, int> $segments
     * @return array<int, array{id: string, name: string, description: string, segment: string, audience_size: int, priority: string, meta_objective: string, status: string}>
     */
    private function buildRepurchaseExperiment(array $segments): array
    {
        if ($segments['b2b_single_buyers'] === 0) {
            return [];
        }

        return [[
            'id' => 'b2b_repurchase_stimulation',
            'name' => 'Estímulo à Recompra B2B',
            'description' => "{$segments['b2b_single_buyers']} clientes B2B fizeram apenas 1 pedido. "
                . 'Campanha de recompra com produtos complementares e condições de atacado progressivo.',
            'segment' => 'B2B Compradores Únicos',
            'audience_size' => $segments['b2b_single_buyers'],
            'priority' => 'high',
            'meta_objective' => 'OUTCOME_SALES',
            'status' => 'sugerido',
        ]];
    }

    /**
     * Upload CNPJ list as custom audience for targeting.
     *
     * @param array<string, int> $segments
     * @return array<int, array{id: string, name: string, description: string, segment: string, audience_size: int, priority: string, meta_objective: string, status: string}>
     */
    private function buildCnpjListExperiment(array $segments): array
    {
        $totalCompanies = $segments['companies_active'] + $segments['b2b_approved'];
        if ($totalCompanies < 3) {
            return [];
        }

        return [[
            'id' => 'cnpj_custom_audience',
            'name' => 'Custom Audience por Lista CNPJ',
            'description' => "Criar Custom Audience no Meta com {$totalCompanies} CNPJs de empresas cadastradas. "
                . 'Mapeia empresas existentes para remarketing e gera lookalike expandido.',
            'segment' => 'Empresas Cadastradas (CNPJ)',
            'audience_size' => $totalCompanies,
            'priority' => $totalCompanies >= 50 ? 'high' : 'medium',
            'meta_objective' => 'OUTCOME_AWARENESS',
            'status' => 'sugerido',
        ]];
    }

    /**
     * Check if a table exists in the database.
     *
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $tableName
     * @return bool
     */
    private function tableExists($connection, string $tableName): bool
    {
        return $connection->isTableExists($tableName);
    }
}

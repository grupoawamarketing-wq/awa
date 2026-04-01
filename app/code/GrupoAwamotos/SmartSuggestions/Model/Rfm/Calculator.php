<?php

declare(strict_types=1);

namespace GrupoAwamotos\SmartSuggestions\Model\Rfm;

use GrupoAwamotos\SmartSuggestions\Api\RfmCalculatorInterface;
use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as ErpRfmCalculator;
use Psr\Log\LoggerInterface;

/**
 * RFM Calculator — Adapter/Delegate
 *
 * Delegates all RFM computation to ERPIntegration\Model\Rfm\Calculator
 * and adapts the output format for SmartSuggestions consumers.
 *
 * This eliminates the duplicate ERP query that existed when both modules
 * computed RFM independently against SECTRA.
 */
class Calculator implements RfmCalculatorInterface
{
    /**
     * Segment name mapping: ERPIntegration (snake_case) → SmartSuggestions (Title Case)
     */
    private const SEGMENT_MAP = [
        'champions'          => 'Champions',
        'loyal'              => 'Loyal',
        'potential_loyalist' => 'Potential Loyalist',
        'new_customers'      => 'New Customers',
        'promising'          => 'Promising',
        'need_attention'     => 'Need Attention',
        'at_risk'            => 'At Risk',
        'cant_lose'          => "Can't Lose",
        'about_to_sleep'     => 'Hibernating',
        'hibernating'        => 'Hibernating',
        'lost'               => 'Lost',
    ];

    private ErpRfmCalculator $erpCalculator;
    private LoggerInterface $logger;
    private ?array $cachedResults = null;

    public function __construct(
        ErpRfmCalculator $erpCalculator,
        LoggerInterface $logger
    ) {
        $this->erpCalculator = $erpCalculator;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function calculateAll(): array
    {
        if ($this->cachedResults !== null) {
            return $this->cachedResults;
        }

        try {
            $erpCustomers = $this->erpCalculator->calculateForAllCustomers();

            if (empty($erpCustomers)) {
                return [];
            }

            $result = array_map([$this, 'adaptCustomerData'], $erpCustomers);

            // Sort by RFM total score descending
            usort($result, fn(array $a, array $b) => $b['rfm_total'] <=> $a['rfm_total']);

            $this->cachedResults = $result;
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('[SmartSuggestions RFM] Error delegating to ERP Calculator: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function calculateForCustomer(int $customerId): ?array
    {
        $erpData = $this->erpCalculator->getCustomerRfm($customerId);

        if ($erpData === null) {
            return null;
        }

        return $this->adaptCustomerData($erpData);
    }

    /**
     * @inheritdoc
     */
    public function getSegmentStatistics(): array
    {
        $customers = $this->calculateAll();
        $stats = [];

        foreach ($customers as $customer) {
            $segment = $customer['segment'];

            if (!isset($stats[$segment])) {
                $stats[$segment] = [
                    'segment' => $segment,
                    'count' => 0,
                    'total_revenue' => 0,
                    'total_orders' => 0,
                    'total_recency' => 0,
                    'color' => $this->getSegmentColor($segment),
                    'priority' => $this->getSegmentPriority($segment)
                ];
            }

            $stats[$segment]['count']++;
            $stats[$segment]['total_revenue'] += $customer['monetary'];
            $stats[$segment]['total_orders'] += $customer['frequency'];
            $stats[$segment]['total_recency'] += $customer['recency_days'];
        }

        // Calculate averages
        foreach ($stats as $segment => &$data) {
            if ($data['count'] > 0) {
                $data['avg_revenue'] = $data['total_revenue'] / $data['count'];
                $data['avg_orders'] = $data['total_orders'] / $data['count'];
                $data['avg_recency'] = $data['total_recency'] / $data['count'];
                $data['avg_order_value'] = $data['total_orders'] > 0
                    ? $data['total_revenue'] / $data['total_orders']
                    : 0;
            }
        }

        // Sort by priority
        uasort($stats, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return array_values($stats);
    }

    /**
     * @inheritdoc
     */
    public function getCustomersBySegment(string $segment, int $limit = 100): array
    {
        $customers = $this->calculateAll();

        $filtered = array_filter($customers, fn($c) => $c['segment'] === $segment);

        // Sort by monetary value descending
        usort($filtered, fn($a, $b) => $b['monetary'] <=> $a['monetary']);

        return array_slice($filtered, 0, $limit);
    }

    /**
     * @inheritdoc
     */
    public function getRecommendations(string $segment): array
    {
        $recommendations = [
            'Champions' => [
                'action' => 'Recompensar e manter',
                'priority' => 'Alta',
                'strategies' => [
                    'Acesso antecipado a novos produtos',
                    'Programa VIP de fidelidade',
                    'Programa de indicação com recompensas premium',
                    'Gerente de conta dedicado'
                ],
                'channels' => ['WhatsApp', 'Email', 'Telefone'],
                'discount_range' => '5-10%'
            ],
            'Loyal' => [
                'action' => 'Upsell e cross-sell',
                'priority' => 'Alta',
                'strategies' => [
                    'Recomendar produtos de maior valor',
                    'Ofertas de pacotes/combos',
                    'Upgrades no programa de fidelidade',
                    'Solicitar avaliações e depoimentos'
                ],
                'channels' => ['WhatsApp', 'Email'],
                'discount_range' => '10-15%'
            ],
            'Potential Loyalist' => [
                'action' => 'Converter para fidelidade',
                'priority' => 'Média-Alta',
                'strategies' => [
                    'Convite para programa de fidelidade',
                    'Recomendações baseadas no histórico',
                    'Engajar em múltiplos canais'
                ],
                'channels' => ['WhatsApp', 'Email'],
                'discount_range' => '10-15%'
            ],
            'New Customers' => [
                'action' => 'Nurturing e educação',
                'priority' => 'Média',
                'strategies' => [
                    'Série de emails de boas-vindas',
                    'Mostrar catálogo completo',
                    'Oferta especial segunda compra'
                ],
                'channels' => ['Email', 'WhatsApp'],
                'discount_range' => '15-20%'
            ],
            'Promising' => [
                'action' => 'Aumentar engajamento',
                'priority' => 'Média',
                'strategies' => [
                    'Produtos relacionados aos já comprados',
                    'Ofertas por tempo limitado',
                    'Comunicação mais frequente'
                ],
                'channels' => ['WhatsApp', 'Email'],
                'discount_range' => '10-15%'
            ],
            'Need Attention' => [
                'action' => 'Reativar interesse',
                'priority' => 'Média-Alta',
                'strategies' => [
                    'Oferta especial personalizada',
                    'Destacar novidades desde última compra',
                    'Verificar satisfação'
                ],
                'channels' => ['WhatsApp', 'Telefone'],
                'discount_range' => '15-20%'
            ],
            'At Risk' => [
                'action' => 'Reativar urgentemente',
                'priority' => 'Alta',
                'strategies' => [
                    'Campanha de win-back por email',
                    'Desconto especial em produtos já comprados',
                    'Pesquisa para entender motivos',
                    'Ofertas exclusivas por tempo limitado'
                ],
                'channels' => ['WhatsApp', 'Telefone', 'Email'],
                'discount_range' => '20-30%'
            ],
            "Can't Lose" => [
                'action' => 'Atenção imediata necessária',
                'priority' => 'Crítica',
                'strategies' => [
                    'Contato pessoal da equipe de vendas',
                    'Oferta exclusiva de reativação',
                    'Entender e resolver problemas',
                    'Oferecer suporte premium'
                ],
                'channels' => ['Telefone', 'WhatsApp'],
                'discount_range' => '25-35%'
            ],
            'Hibernating' => [
                'action' => 'Tentar reativar',
                'priority' => 'Baixa',
                'strategies' => [
                    'Campanha de reativação em massa',
                    'Mostrar novos produtos',
                    'Oferta agressiva'
                ],
                'channels' => ['Email'],
                'discount_range' => '20-30%'
            ],
            'Lost' => [
                'action' => 'Tentar recuperar',
                'priority' => 'Baixa',
                'strategies' => [
                    'Campanha agressiva de win-back',
                    'Pesquisa de feedback',
                    'Considerar custo-benefício da reativação'
                ],
                'channels' => ['Email'],
                'discount_range' => '30-40%'
            ]
        ];

        return $recommendations[$segment] ?? [
            'action' => 'Engajamento geral',
            'priority' => 'Média',
            'strategies' => ['Comunicação regular de marketing'],
            'channels' => ['Email'],
            'discount_range' => '10-15%'
        ];
    }

    /**
     * Adapt ERP customer data to SmartSuggestions format
     *
     * Maps field names and segment naming conventions from ERPIntegration
     * output to the format expected by SmartSuggestions consumers.
     *
     * @param array $erpCustomer Raw data from ERPIntegration Calculator
     * @return array Adapted data for SmartSuggestions consumers
     */
    private function adaptCustomerData(array $erpCustomer): array
    {
        $erpSegment = $erpCustomer['segment'] ?? '';
        $segment = self::SEGMENT_MAP[$erpSegment] ?? 'Other';

        return [
            'customer_id' => (int)($erpCustomer['customer_id'] ?? 0),
            'customer_name' => $erpCustomer['customer_name'] ?? '',
            'trade_name' => $erpCustomer['trade_name'] ?? '',
            'cnpj' => $erpCustomer['cnpj'] ?? '',
            'city' => $erpCustomer['city'] ?? '',
            'state' => $erpCustomer['state'] ?? '',
            'email' => $erpCustomer['email'] ?? '',
            'phone' => $erpCustomer['phone'] ?? '',
            'recency_days' => (int)($erpCustomer['recency'] ?? 0),
            'frequency' => (int)($erpCustomer['frequency'] ?? 0),
            'monetary' => (float)($erpCustomer['monetary'] ?? 0),
            'r_score' => (int)($erpCustomer['r_score'] ?? 0),
            'f_score' => (int)($erpCustomer['f_score'] ?? 0),
            'm_score' => (int)($erpCustomer['m_score'] ?? 0),
            'rfm_score' => (string)($erpCustomer['rfm_score'] ?? ''),
            'rfm_total' => (int)($erpCustomer['total_score'] ?? 0),
            'segment' => $segment,
            'last_purchase' => $erpCustomer['last_purchase'] ?? null,
        ];
    }

    /**
     * Get color for segment visualization
     */
    private function getSegmentColor(string $segment): string
    {
        $colors = [
            'Champions' => '#00E396',
            'Loyal' => '#008FFB',
            'Potential Loyalist' => '#00D9E9',
            'New Customers' => '#775DD0',
            'Promising' => '#26A0FC',
            'Need Attention' => '#FEB019',
            'At Risk' => '#FF4560',
            "Can't Lose" => '#FF6178',
            'Hibernating' => '#A5978B',
            'Lost' => '#546E7A',
            'Other' => '#999999'
        ];

        return $colors[$segment] ?? '#999999';
    }

    /**
     * Get segment priority for sorting
     */
    private function getSegmentPriority(string $segment): int
    {
        $priorities = [
            'Champions' => 1,
            'Loyal' => 2,
            "Can't Lose" => 3,
            'At Risk' => 4,
            'Potential Loyalist' => 5,
            'Need Attention' => 6,
            'New Customers' => 7,
            'Promising' => 8,
            'Hibernating' => 9,
            'Lost' => 10,
            'Other' => 11
        ];

        return $priorities[$segment] ?? 99;
    }
}

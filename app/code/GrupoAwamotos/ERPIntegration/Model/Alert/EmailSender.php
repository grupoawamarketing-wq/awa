<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Alert;

use GrupoAwamotos\ERPIntegration\Model\Rfm\Calculator as RfmCalculator;
use GrupoAwamotos\ERPIntegration\Model\Forecast\SalesProjection;
use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Psr\Log\LoggerInterface;

/**
 * Email Alert Sender Service
 *
 * Sends automated email alerts for:
 * - At-Risk customers (to admin)
 * - Customer re-engagement (to customers)
 * - Weekly RFM reports (to admin)
 * - Sales forecast alerts (to admin)
 */
class EmailSender
{
    private const TEMPLATE_AT_RISK_ADMIN = 'erp_alert_at_risk_admin';
    private const TEMPLATE_REENGAGEMENT = 'erp_alert_reengagement_customer';
    private const TEMPLATE_RFM_WEEKLY = 'erp_report_rfm_weekly';
    private const TEMPLATE_FORECAST = 'erp_alert_forecast';
    private const TEMPLATE_STOCK_ANOMALY = 'erp_alert_stock_anomaly';

    private TransportBuilder $transportBuilder;
    private StateInterface $inlineTranslation;
    private StoreManagerInterface $storeManager;
    private BackendUrlInterface $backendUrl;
    private RfmCalculator $rfmCalculator;
    private SalesProjection $salesProjection;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        BackendUrlInterface $backendUrl,
        RfmCalculator $rfmCalculator,
        SalesProjection $salesProjection,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->backendUrl = $backendUrl;
        $this->rfmCalculator = $rfmCalculator;
        $this->salesProjection = $salesProjection;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    /**
     * Send At-Risk customers alert to admin
     */
    public function sendAtRiskAlert(): bool
    {
        if (!$this->helper->isAtRiskAlertEnabled()) {
            return false;
        }

        try {
            $atRiskCustomers = $this->rfmCalculator->getAtRiskCustomers(10);

            if (empty($atRiskCustomers)) {
                $this->logger->info('[ERP Alert] No at-risk customers found, skipping alert.');
                return false;
            }

            // Calculate total revenue at risk
            $totalRevenue = array_sum(array_column($atRiskCustomers, 'total_revenue'));

            // Prepare customer data for email
            $customersData = array_map(function ($customer) {
                return [
                    'name' => $customer['name'] ?? 'N/A',
                    'email' => $customer['email'] ?? '',
                    'segment_label' => $customer['segment_label'] ?? '',
                    'segment_color' => $customer['segment_color'] ?? '#6c757d',
                    'days_since_purchase' => $customer['days_since_purchase'] ?? 0,
                    'total_revenue' => number_format($customer['total_revenue'] ?? 0, 2, ',', '.'),
                    'priority_high' => ($customer['segment'] ?? '') === 'cant_lose',
                ];
            }, $atRiskCustomers);

            $store = $this->storeManager->getStore();

            $templateVars = [
                'at_risk_count' => count($atRiskCustomers),
                'at_risk_customers' => $customersData,
                'total_revenue_at_risk' => number_format($totalRevenue, 2, ',', '.'),
                'report_date' => date('d/m/Y H:i'),
                'admin_dashboard_url' => $this->backendUrl->getUrl('erpintegration/dashboard'),
                'store' => $store,
            ];

            $this->sendEmail(
                self::TEMPLATE_AT_RISK_ADMIN,
                $this->helper->getAtRiskAlertEmail(),
                $templateVars,
                'frontend'
            );

            $this->logger->info(sprintf(
                '[ERP Alert] At-Risk alert sent: %d customers, R$ %s at risk',
                count($atRiskCustomers),
                number_format($totalRevenue, 2, ',', '.')
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Alert] Error sending at-risk alert: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send re-engagement email to a specific customer
     */
    public function sendReengagementEmail(
        string $customerEmail,
        string $customerName,
        int $daysSincePurchase,
        array $suggestedProducts = [],
        ?string $couponCode = null,
        ?int $couponDiscount = null
    ): bool {
        try {
            $store = $this->storeManager->getStore();

            $templateVars = [
                'customer' => [
                    'name' => $customerName,
                    'email' => $customerEmail,
                ],
                'days_since_purchase' => $daysSincePurchase,
                'suggested_products' => array_slice($suggestedProducts, 0, 3),
                'coupon_code' => $couponCode,
                'coupon_discount' => $couponDiscount,
                'store' => $store,
                'store_url' => $store->getBaseUrl(),
                'unsubscribe_url' => $store->getBaseUrl() . 'newsletter/manage/',
            ];

            $this->sendEmail(
                self::TEMPLATE_REENGAGEMENT,
                $customerEmail,
                $templateVars,
                'frontend'
            );

            $this->logger->info(sprintf(
                '[ERP Alert] Re-engagement email sent to %s (%d days inactive)',
                $customerEmail,
                $daysSincePurchase
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Alert] Error sending re-engagement email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send weekly RFM report to admin
     */
    public function sendWeeklyRfmReport(): bool
    {
        if (!$this->helper->isRfmEnabled()) {
            return false;
        }

        try {
            $segmentStats = $this->rfmCalculator->getSegmentStats();

            if (empty($segmentStats)) {
                return false;
            }

            // Calculate totals
            $totalCustomers = array_sum(array_column($segmentStats, 'count'));
            $championsCount = 0;
            $atRiskCount = 0;
            $lostCount = 0;

            $segmentsData = [];
            foreach ($segmentStats as $segment) {
                $segmentsData[] = [
                    'label' => $segment['label'] ?? '',
                    'color' => $segment['color'] ?? '#6c757d',
                    'count' => $segment['count'] ?? 0,
                    'percentage' => $totalCustomers > 0
                        ? round(($segment['count'] / $totalCustomers) * 100, 1)
                        : 0,
                    'total_revenue' => number_format($segment['total_revenue'] ?? 0, 2, ',', '.'),
                    'week_change' => $segment['week_change'] ?? 0,
                ];

                // Count by category
                $segmentKey = $segment['segment'] ?? '';
                if ($segmentKey === 'champions') {
                    $championsCount = $segment['count'];
                } elseif (in_array($segmentKey, ['at_risk', 'cant_lose'])) {
                    $atRiskCount += $segment['count'];
                } elseif ($segmentKey === 'lost') {
                    $lostCount = $segment['count'];
                }
            }

            // Generate insights
            $insights = $this->generateWeeklyInsights($segmentStats, $totalCustomers);

            $store = $this->storeManager->getStore();

            $templateVars = [
                'segment_stats' => $segmentsData,
                'week_start' => date('d/m/Y', strtotime('monday this week')),
                'week_end' => date('d/m/Y', strtotime('sunday this week')),
                'total_customers' => $totalCustomers,
                'champions_count' => $championsCount,
                'at_risk_count' => $atRiskCount,
                'lost_count' => $lostCount,
                'insight_champions' => $insights['champions'] ?? null,
                'insight_at_risk' => $insights['at_risk'] ?? null,
                'insight_lost' => $insights['lost'] ?? null,
                'insight_general' => $insights['general'] ?? null,
                'admin_dashboard_url' => $this->backendUrl->getUrl('erpintegration/dashboard'),
                'store' => $store,
            ];

            $this->sendEmail(
                self::TEMPLATE_RFM_WEEKLY,
                $this->helper->getAtRiskAlertEmail(),
                $templateVars,
                'frontend'
            );

            $this->logger->info('[ERP Alert] Weekly RFM report sent.');

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Alert] Error sending weekly RFM report: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send sales forecast alert to admin
     */
    public function sendForecastAlert(): bool
    {
        if (!$this->helper->isForecastEnabled() || !$this->helper->isForecastAlertEnabled()) {
            return false;
        }

        try {
            $projection = $this->salesProjection->getCurrentMonthProjection();

            if (empty($projection) || isset($projection['error'])) {
                return false;
            }

            $alertLevel = $projection['alert_level'] ?? 'none';

            // Only send if alert level is warning or worse
            if ($alertLevel === 'none' || $alertLevel === 'success') {
                return false;
            }

            $alertLabels = [
                'warning' => 'Atencao',
                'danger' => 'Alerta',
                'critical' => 'Critico',
            ];

            $store = $this->storeManager->getStore();

            $templateVars = [
                'alert_level' => $alertLevel,
                'alert_level_label' => $alertLabels[$alertLevel] ?? 'Alerta',
                'current_sales' => number_format($projection['actual_sales'] ?? 0, 2, ',', '.'),
                'projected_total' => number_format($projection['projected_total'] ?? 0, 2, ',', '.'),
                'monthly_target' => number_format($projection['target'] ?? 0, 2, ',', '.'),
                'progress_percentage' => $projection['progress_percentage'] ?? 0,
                'days_remaining' => $projection['days_remaining'] ?? 0,
                'daily_needed' => number_format($projection['target_daily_needed'] ?? 0, 2, ',', '.'),
                'admin_dashboard_url' => $this->backendUrl->getUrl('erpintegration/dashboard'),
                'store' => $store,
            ];

            $this->sendEmail(
                self::TEMPLATE_FORECAST,
                $this->helper->getAtRiskAlertEmail(),
                $templateVars,
                'frontend'
            );

            $this->logger->info(sprintf(
                '[ERP Alert] Forecast alert sent: Level %s, Progress %.1f%%',
                $alertLevel,
                $projection['progress_percentage'] ?? 0
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Alert] Error sending forecast alert: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using template
     */
    private function sendEmail(
        string $templateId,
        string $recipientEmail,
        array $templateVars,
        string $area = 'frontend'
    ): void {
        if (empty($recipientEmail)) {
            throw new \InvalidArgumentException('Recipient email is required');
        }

        $this->inlineTranslation->suspend();

        try {
            $store = $this->storeManager->getStore();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => $area,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars($templateVars)
                ->setFromByScope('general')
                ->addTo($recipientEmail)
                ->getTransport();

            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * Generate weekly insights based on segment data
     */
    private function generateWeeklyInsights(array $segmentStats, int $totalCustomers): array
    {
        $insights = [];

        foreach ($segmentStats as $segment) {
            $segmentKey = $segment['segment'] ?? '';
            $count = $segment['count'] ?? 0;
            $percentage = $totalCustomers > 0 ? ($count / $totalCustomers) * 100 : 0;
            $weekChange = $segment['week_change'] ?? 0;

            if ($segmentKey === 'champions') {
                if ($weekChange > 0) {
                    $insights['champions'] = sprintf(
                        'Otimo! %d novos clientes Champions esta semana (+%d)',
                        $weekChange,
                        $weekChange
                    );
                } elseif ($percentage < 10) {
                    $insights['champions'] = 'Foco em converter mais clientes para Champions (atualmente ' . round($percentage, 1) . '%)';
                }
            }

            if (in_array($segmentKey, ['at_risk', 'cant_lose'])) {
                if ($count > $totalCustomers * 0.2) {
                    $insights['at_risk'] = sprintf(
                        'Alerta: %d clientes em risco (%.1f%% do total) - acao imediata recomendada',
                        $count,
                        $percentage
                    );
                }
            }

            if ($segmentKey === 'lost' && $weekChange > 0) {
                $insights['lost'] = sprintf(
                    '%d clientes foram classificados como perdidos esta semana',
                    $weekChange
                );
            }
        }

        // General insight
        if (empty($insights)) {
            $insights['general'] = 'Distribuicao de clientes estavel esta semana.';
        }

        return $insights;
    }

    /**
     * Send stock anomaly alert to admin when unusual stock changes are detected.
     *
     * @param int $anomalyCount Total anomalies detected
     * @param array $samples Sample anomaly data [{sku, change_percent, previous_qty, new_qty}]
     * @return bool
     */
    public function sendStockAnomalyAlert(int $anomalyCount, array $samples): bool
    {
        if (!$this->helper->isStockAnomalyAlertEnabled()) {
            return false;
        }

        $recipientEmail = $this->helper->getStockAnomalyAlertEmail();
        if (empty($recipientEmail)) {
            return false;
        }

        try {
            $store = $this->storeManager->getStore();

            $samplesFormatted = array_map(function (array $sample): array {
                return [
                    'sku' => $sample['sku'] ?? '',
                    'change_percent' => isset($sample['change_percent'])
                        ? number_format((float) $sample['change_percent'], 1) . '%'
                        : 'N/A',
                    'previous_qty' => (int) ($sample['previous_qty'] ?? 0),
                    'new_qty' => (int) ($sample['new_qty'] ?? 0),
                ];
            }, array_slice($samples, 0, 10));

            $templateVars = [
                'anomaly_count' => $anomalyCount,
                'samples' => $samplesFormatted,
                'report_date' => date('d/m/Y H:i'),
                'store' => $store,
            ];

            $this->sendEmail(
                self::TEMPLATE_STOCK_ANOMALY,
                $recipientEmail,
                $templateVars,
                'frontend'
            );

            $this->logger->info(sprintf(
                '[ERP Alert] Stock anomaly alert sent: %d anomalies detected',
                $anomalyCount
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[ERP Alert] Error sending stock anomaly alert: ' . $e->getMessage());
            return false;
        }
    }
}

<?php

declare(strict_types=1);

namespace GrupoAwamotos\SalesIntelligence\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Psr\Log\LoggerInterface;

class EmailSender
{
    private const TEMPLATE_DAILY_ALERT = 'salesintelligence_daily_alert';
    private const TEMPLATE_WEEKLY_REPORT = 'salesintelligence_weekly_report';

    private TransportBuilder $transportBuilder;
    private StateInterface $inlineTranslation;
    private StoreManagerInterface $storeManager;
    private BackendUrlInterface $backendUrl;
    private AlertEngine $alertEngine;
    private RecommendationEngine $recommendationEngine;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        BackendUrlInterface $backendUrl,
        AlertEngine $alertEngine,
        RecommendationEngine $recommendationEngine,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->backendUrl = $backendUrl;
        $this->alertEngine = $alertEngine;
        $this->recommendationEngine = $recommendationEngine;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * Send daily alert email if there are actionable alerts
     */
    public function sendDailyAlert(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $alerts = $this->alertEngine->evaluate();

            if (empty($alerts)) {
                $this->logger->info('[SalesIntelligence] No alerts to send.');
                return false;
            }

            // Only send if there are warning or critical alerts
            $actionable = array_filter($alerts, fn($a) => in_array($a['severity'], ['critical', 'warning']));
            if (empty($actionable)) {
                $this->logger->info('[SalesIntelligence] No actionable alerts, skipping email.');
                return false;
            }

            $recommendations = $this->recommendationEngine->getRecommendations(5);

            $store = $this->storeManager->getStore();
            $templateVars = [
                'alerts' => $alerts,
                'alert_count' => count($alerts),
                'critical_count' => count(array_filter($alerts, fn($a) => $a['severity'] === 'critical')),
                'warning_count' => count(array_filter($alerts, fn($a) => $a['severity'] === 'warning')),
                'recommendations' => $recommendations,
                'total_impact' => number_format(
                    array_sum(array_column($alerts, 'impact')),
                    2,
                    ',',
                    '.'
                ),
                'report_date' => date('d/m/Y H:i'),
                'dashboard_url' => $this->backendUrl->getUrl('salesintelligence/dashboard'),
                'store' => $store,
            ];

            $this->sendEmail(
                self::TEMPLATE_DAILY_ALERT,
                $this->getRecipientEmail(),
                $templateVars
            );

            $this->logger->info(sprintf(
                '[SalesIntelligence] Daily alert sent: %d critical, %d warning alerts.',
                $templateVars['critical_count'],
                $templateVars['warning_count']
            ));

            return true;
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] Error sending daily alert: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send weekly intelligence report
     */
    public function sendWeeklyReport(): bool
    {
        if (!$this->isEnabled() || !$this->isWeeklyReportEnabled()) {
            return false;
        }

        try {
            $recommendations = $this->recommendationEngine->getRecommendations(10);
            $alerts = $this->alertEngine->evaluate();

            $store = $this->storeManager->getStore();
            $templateVars = [
                'alerts' => $alerts,
                'recommendations' => $recommendations,
                'report_date' => date('d/m/Y'),
                'week_start' => date('d/m/Y', strtotime('monday this week')),
                'week_end' => date('d/m/Y', strtotime('sunday this week')),
                'dashboard_url' => $this->backendUrl->getUrl('salesintelligence/dashboard'),
                'store' => $store,
            ];

            $this->sendEmail(
                self::TEMPLATE_WEEKLY_REPORT,
                $this->getRecipientEmail(),
                $templateVars
            );

            $this->logger->info('[SalesIntelligence] Weekly report sent.');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('[SalesIntelligence] Error sending weekly report: ' . $e->getMessage());
            return false;
        }
    }

    private function sendEmail(string $templateId, string $recipientEmail, array $templateVars): void
    {
        if (empty($recipientEmail)) {
            throw new \InvalidArgumentException('Recipient email is required');
        }

        $this->inlineTranslation->suspend();
        try {
            $store = $this->storeManager->getStore();

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions([
                    'area' => 'adminhtml',
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

    private function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue('salesintelligence/alerts/enabled');
    }

    private function isWeeklyReportEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue('salesintelligence/alerts/weekly_report');
    }

    private function getRecipientEmail(): string
    {
        return (string) ($this->scopeConfig->getValue('salesintelligence/alerts/recipient_email')
            ?: $this->scopeConfig->getValue('trans_email/ident_general/email'));
    }
}

<?php
declare(strict_types=1);

namespace GrupoAwamotos\LogMonitoring\Service\LogAnalyzer;

use GrupoAwamotos\LogMonitoring\Api\Data\AlertInterfaceFactory;
use GrupoAwamotos\LogMonitoring\Api\AlertRepositoryInterface;
use GrupoAwamotos\LogMonitoring\Api\Data\LogMetricsInterfaceFactory;
use GrupoAwamotos\LogMonitoring\Api\LogMetricsRepositoryInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

class ChatwootAnalyzer implements AnalyzerInterface
{
    private Filesystem $filesystem;
    private LogMetricsRepositoryInterface $logMetricsRepository;
    private LogMetricsInterfaceFactory $logMetricsFactory;
    private AlertRepositoryInterface $alertRepository;
    private AlertInterfaceFactory $alertFactory;
    private LoggerInterface $logger;

    public function __construct(
        Filesystem $filesystem,
        LogMetricsRepositoryInterface $logMetricsRepository,
        LogMetricsInterfaceFactory $logMetricsFactory,
        AlertRepositoryInterface $alertRepository,
        AlertInterfaceFactory $alertFactory,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->logMetricsRepository = $logMetricsRepository;
        $this->logMetricsFactory = $logMetricsFactory;
        $this->alertRepository = $alertRepository;
        $this->alertFactory = $alertFactory;
        $this->logger = $logger;
    }

    public function analyze(): array
    {
        $logDir = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        $logFiles = [
            'var/log/awa_chatwoot.log',
            'var/log/system.log'
        ];

        $analysis = [];
        
        foreach ($logFiles as $logFile) {
            if ($logDir->isExist($logFile)) {
                $analysis[$logFile] = $this->analyzeLogFile($logFile);
            }
        }
        
        $this->generateChatwootAlerts($analysis);
        
        return $analysis;
    }

    public function getSpecificMetrics(): array
    {
        $metrics = $this->logMetricsRepository->getMetricsByType('chatwoot', 10);
        
        $chatwootMetrics = [
            'connection_rate' => $this->calculateConnectionRate($metrics),
            'api_success_rate' => $this->calculateApiSuccessRate($metrics),
            'message_delivery_rate' => $this->calculateMessageDeliveryRate($metrics),
            'health_score' => 100,
            'last_activity' => $this->getLastActivityTime(),
            'failed_webhooks' => $this->getFailedWebhooks($metrics),
            'integration_status' => $this->getIntegrationStatus()
        ];

        // Calculate overall health score
        $chatwootMetrics['health_score'] = $this->calculateHealthScore($chatwootMetrics);
        
        return $chatwootMetrics;
    }

    public function checkHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'score' => 100
        ];
        
        // Check recent Chatwoot errors
        $recentMetrics = $this->logMetricsRepository->getMetricsByType('chatwoot', 5);
        $errorCount = 0;
        
        foreach ($recentMetrics as $metric) {
            $errorCount += $metric->getErrorEntries();
        }
        
        if ($errorCount > 20) {
            $health['status'] = 'critical';
            $health['issues'][] = 'High error rate in Chatwoot integration';
            $health['score'] -= 40;
        } elseif ($errorCount > 10) {
            $health['status'] = 'warning';
            $health['issues'][] = 'Moderate error rate in Chatwoot integration';
            $health['score'] -= 20;
        }
        
        // Check last activity
        $lastActivity = $this->getLastActivityTime();
        if (strtotime($lastActivity) < strtotime('-2 hours')) {
            $health['status'] = 'warning';
            $health['issues'][] = 'Chatwoot integration appears inactive';
            $health['score'] -= 15;
        }
        
        return $health;
    }

    public function generateAlerts(): array
    {
        $alerts = [];
        $metrics = $this->getSpecificMetrics();
        
        // Low API success rate
        if ($metrics['api_success_rate'] < 0.9) {
            $alerts[] = $this->createAlert(
                'chatwoot_api_failures',
                'high',
                'Chatwoot API Failures',
                sprintf('Chatwoot API success rate is %.2f%%, indicating connectivity issues', $metrics['api_success_rate'] * 100),
                ['success_rate' => $metrics['api_success_rate']]
            );
        }
        
        // Message delivery issues
        if ($metrics['message_delivery_rate'] < 0.95) {
            $alerts[] = $this->createAlert(
                'chatwoot_message_delivery',
                'medium',
                'Chatwoot Message Delivery Issues',
                sprintf('Message delivery rate is %.2f%%, messages may be lost', $metrics['message_delivery_rate'] * 100),
                ['delivery_rate' => $metrics['message_delivery_rate']]
            );
        }
        
        return $alerts;
    }

    private function analyzeLogFile(string $logFile): array
    {
        $logDir = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
        
        try {
            $content = $logDir->readFile($logFile);
            $lines = explode("\n", $content);
            
            $analysis = [
                'total_lines' => count($lines),
                'error_lines' => 0,
                'warning_lines' => 0,
                'critical_lines' => 0,
                'chatwoot_specific' => [
                    'connection_errors' => 0,
                    'webhook_failures' => 0,
                    'api_timeouts' => 0,
                    'auth_errors' => 0,
                    'message_failures' => 0
                ],
                'file_size' => $logDir->stat($logFile)['size'],
                'patterns' => []
            ];
            
            foreach ($lines as $line) {
                // General log level analysis
                if (stripos($line, '[ERROR]') !== false) {
                    $analysis['error_lines']++;
                }
                if (stripos($line, '[WARNING]') !== false) {
                    $analysis['warning_lines']++;
                }
                if (stripos($line, '[CRITICAL]') !== false) {
                    $analysis['critical_lines']++;
                }
                
                // Chatwoot specific patterns
                if (stripos($line, 'chatwoot') !== false && stripos($line, 'connection') !== false && stripos($line, 'error') !== false) {
                    $analysis['chatwoot_specific']['connection_errors']++;
                }
                if (stripos($line, 'webhook') !== false && stripos($line, 'failed') !== false) {
                    $analysis['chatwoot_specific']['webhook_failures']++;
                }
                if (stripos($line, 'chatwoot') !== false && stripos($line, 'timeout') !== false) {
                    $analysis['chatwoot_specific']['api_timeouts']++;
                }
                if (stripos($line, 'authentication') !== false && stripos($line, 'chatwoot') !== false) {
                    $analysis['chatwoot_specific']['auth_errors']++;
                }
                if (stripos($line, 'message') !== false && stripos($line, 'chatwoot') !== false && stripos($line, 'failed') !== false) {
                    $analysis['chatwoot_specific']['message_failures']++;
                }
            }
            
            // Save metrics
            $logMetrics = $this->logMetricsFactory->create();
            $logMetrics->setLogType('chatwoot');
            $logMetrics->setSourceFile($logFile);
            $logMetrics->setTotalEntries($analysis['total_lines']);
            $logMetrics->setErrorEntries($analysis['error_lines']);
            $logMetrics->setWarningEntries($analysis['warning_lines']);
            $logMetrics->setCriticalEntries($analysis['critical_lines']);
            $logMetrics->setFileSizeBytes($analysis['file_size']);
            $logMetrics->setAnalysisData($analysis);
            
            $this->logMetricsRepository->save($logMetrics);
            
            return $analysis;
            
        } catch (\Exception $e) {
            $this->logger->error('Error analyzing Chatwoot log file: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    private function generateChatwootAlerts(array $analysis): void
    {
        foreach ($analysis as $file => $data) {
            if (isset($data['error'])) {
                continue;
            }
            
            // Alert on connection errors
            if ($data['chatwoot_specific']['connection_errors'] > 5) {
                $this->createAndSaveAlert(
                    'chatwoot_connection_errors',
                    'critical',
                    'Chatwoot Connection Issues',
                    sprintf('Detected %d connection errors in %s', 
                        $data['chatwoot_specific']['connection_errors'], $file),
                    ['file' => $file, 'count' => $data['chatwoot_specific']['connection_errors']]
                );
            }
            
            // Alert on webhook failures
            if ($data['chatwoot_specific']['webhook_failures'] > 10) {
                $this->createAndSaveAlert(
                    'chatwoot_webhook_failures',
                    'high',
                    'Chatwoot Webhook Failures',
                    sprintf('Found %d webhook failures in %s', 
                        $data['chatwoot_specific']['webhook_failures'], $file),
                    ['file' => $file, 'count' => $data['chatwoot_specific']['webhook_failures']]
                );
            }
        }
    }

    private function createAlert(string $type, string $severity, string $title, string $message, array $context): array
    {
        return [
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'context' => $context
        ];
    }

    private function createAndSaveAlert(string $type, string $severity, string $title, string $message, array $context): void
    {
        try {
            $alert = $this->alertFactory->create();
            $alert->setAlertType($type);
            $alert->setSeverity($severity);
            $alert->setTitle($title);
            $alert->setMessage($message);
            $alert->setContextData($context);
            $alert->setSource('chatwoot_analyzer');
            $alert->setStatus('open');
            $alert->setOccurrences(1);
            $alert->setFirstOccurrence(date('Y-m-d H:i:s'));
            $alert->setLastOccurrence(date('Y-m-d H:i:s'));
            
            $this->alertRepository->save($alert);
        } catch (\Exception $e) {
            $this->logger->error('Error creating Chatwoot alert: ' . $e->getMessage());
        }
    }

    private function calculateConnectionRate(array $metrics): float
    {
        // Calculate based on connection error patterns
        if (empty($metrics)) {
            return 1.0;
        }
        
        $totalAttempts = 0;
        $successfulConnections = 0;
        
        foreach ($metrics as $metric) {
            $analysisData = $metric->getAnalysisData();
            if (isset($analysisData['chatwoot_specific']['connection_errors'])) {
                $errors = $analysisData['chatwoot_specific']['connection_errors'];
                $attempts = max(1, $metric->getTotalEntries() / 10); // Estimate attempts
                
                $totalAttempts += $attempts;
                $successfulConnections += ($attempts - $errors);
            }
        }
        
        return $totalAttempts > 0 ? $successfulConnections / $totalAttempts : 1.0;
    }

    private function calculateApiSuccessRate(array $metrics): float
    {
        if (empty($metrics)) {
            return 1.0;
        }
        
        $totalRequests = 0;
        $successfulRequests = 0;
        
        foreach ($metrics as $metric) {
            $total = $metric->getTotalEntries();
            $errors = $metric->getErrorEntries();
            
            $totalRequests += $total;
            $successfulRequests += ($total - $errors);
        }
        
        return $totalRequests > 0 ? $successfulRequests / $totalRequests : 1.0;
    }

    private function calculateMessageDeliveryRate(array $metrics): float
    {
        if (empty($metrics)) {
            return 1.0;
        }
        
        $totalMessages = 0;
        $deliveredMessages = 0;
        
        foreach ($metrics as $metric) {
            $analysisData = $metric->getAnalysisData();
            if (isset($analysisData['chatwoot_specific']['message_failures'])) {
                $failures = $analysisData['chatwoot_specific']['message_failures'];
                $messages = max(1, $metric->getTotalEntries() / 20); // Estimate messages
                
                $totalMessages += $messages;
                $deliveredMessages += ($messages - $failures);
            }
        }
        
        return $totalMessages > 0 ? $deliveredMessages / $totalMessages : 1.0;
    }

    private function calculateHealthScore(array $metrics): float
    {
        $score = 100;
        
        // Reduce score based on various factors
        if ($metrics['api_success_rate'] < 0.8) {
            $score -= 40;
        } elseif ($metrics['api_success_rate'] < 0.9) {
            $score -= 20;
        }
        
        if ($metrics['connection_rate'] < 0.9) {
            $score -= 30;
        } elseif ($metrics['connection_rate'] < 0.95) {
            $score -= 15;
        }
        
        if ($metrics['message_delivery_rate'] < 0.95) {
            $score -= 20;
        } elseif ($metrics['message_delivery_rate'] < 0.98) {
            $score -= 10;
        }
        
        return max(0, $score);
    }

    private function getLastActivityTime(): string
    {
        // This would check actual Chatwoot activity logs
        return date('Y-m-d H:i:s', strtotime('-45 minutes')); // Placeholder
    }

    private function getFailedWebhooks(array $metrics): int
    {
        $failed = 0;
        foreach ($metrics as $metric) {
            $analysisData = $metric->getAnalysisData();
            if (isset($analysisData['chatwoot_specific']['webhook_failures'])) {
                $failed += $analysisData['chatwoot_specific']['webhook_failures'];
            }
        }
        return $failed;
    }

    private function getIntegrationStatus(): string
    {
        // Check if Chatwoot integration is properly configured and working
        $recentMetrics = $this->logMetricsRepository->getMetricsByType('chatwoot', 1);
        
        if (empty($recentMetrics)) {
            return 'inactive';
        }
        
        $latestMetric = reset($recentMetrics);
        $errorRate = $latestMetric->getErrorEntries() / max(1, $latestMetric->getTotalEntries());
        
        if ($errorRate > 0.2) {
            return 'degraded';
        } elseif ($errorRate > 0.1) {
            return 'warning';
        }
        
        return 'active';
    }
}
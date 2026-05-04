<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use Psr\Log\LoggerInterface;

/**
 * Checks if queue consumers are running; restarts them via ensure_consumers.sh if not.
 * Runs every 5 min in the default cron group (consumers cron group is not scheduled).
 */
class RestartConsumers
{
    private const CONSUMERS = [
        'erp.order.sync.consumer',
        'erp.order.sync.retry.consumer',
        'grupoawamotos.b2b.whatsapp.consumer',
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {}

    public function execute(): void
    {
        $base = BP;
        $script = $base . '/scripts/ensure_consumers.sh';

        if (!is_readable($script)) {
            $this->logger->warning('[RestartConsumers] ensure_consumers.sh not found or not readable');
            return;
        }

        $down = $this->getDownConsumers();

        if (empty($down)) {
            return;
        }

        $this->logger->notice('[RestartConsumers] Down consumers: ' . implode(', ', $down));

        // Run watchdog script in background — it checks each consumer individually with locking
        $watchdogLog = escapeshellarg($base . '/var/log/consumer_watchdog.log');
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        exec('bash ' . escapeshellarg($script) . " >> {$watchdogLog} 2>&1 &"); // nosemgrep: php.lang.security.exec-use.exec-use

        $this->logger->info('[RestartConsumers] Triggered ensure_consumers.sh');
    }

    /** @return string[] Names of consumers whose process is not found */
    private function getDownConsumers(): array
    {
        $down = [];

        foreach (self::CONSUMERS as $name) {
            if (!$this->isRunning($name)) {
                $down[] = $name;
            }
        }

        return $down;
    }

    private function isRunning(string $consumerName): bool
    {
        // pgrep returns 0 if at least one process matches, 1 if none
        $pattern = 'queue:consumers:start ' . $consumerName;
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        exec('pgrep -f ' . escapeshellarg($pattern), $output, $rc); // nosemgrep: php.lang.security.exec-use.exec-use
        return $rc === 0;
    }
}

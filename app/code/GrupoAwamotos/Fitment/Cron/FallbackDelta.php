<?php
declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Cron;

use Psr\Log\LoggerInterface;

/**
 * Cron job para atualização incremental do índice FULLTEXT fallback.
 *
 * NOTA: O script standalone (scripts/fallback_search_delta.php) chama exit(),
 * o que mataria o processo do cron scheduler do Magento.  Por isso executamos
 * como sub-processo isolado via exec().
 */
class FallbackDelta
{
    private LoggerInterface $logger;
    private string $scriptPath;

    public function __construct(LoggerInterface $logger, ?string $scriptPath = null)
    {
        $this->logger = $logger;
        $this->scriptPath = $scriptPath ?? BP . '/scripts/fallback_search_delta.php';
    }

    public function execute(): void
    {
        $script = $this->scriptPath;

        if (!file_exists($script)) {
            $this->logger->error('[Fitment] Script não encontrado: ' . $script);
            return;
        }

        $phpBin = PHP_BINARY ?: '/usr/bin/php';
        $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($script) . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $outputStr = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->logger->error(
                '[Fitment] Fallback delta falhou (exit ' . $exitCode . '): ' . $outputStr
            );
        } else {
            $this->logger->info('[Fitment] Fallback delta OK: ' . $outputStr);
        }
    }
}

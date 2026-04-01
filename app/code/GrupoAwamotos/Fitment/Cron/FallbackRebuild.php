<?php

declare(strict_types=1);

namespace GrupoAwamotos\Fitment\Cron;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Cron job para reconstrução completa do índice FULLTEXT fallback (diário 03:15).
 *
 * Executa como sub-processo para evitar que exit() do script mate o cron scheduler.
 */
class FallbackRebuild
{
    private LoggerInterface $logger;
    private string $scriptPath;

    public function __construct(LoggerInterface $logger, ?string $scriptPath = null)
    {
        $this->logger = $logger;
        $this->scriptPath = $scriptPath ?? BP . '/scripts/fallback_search_rebuild.php';
    }

    public function execute(): void
    {
        $script = $this->scriptPath;

        if (!file_exists($script)) {
            $this->logger->error('[Fitment] Script não encontrado: ' . $script);
            return;
        }

        $phpBin = PHP_BINARY ?: '/usr/bin/php';
        $process = new Process([$phpBin, $script, '--truncate']);
        $process->setTimeout(300);
        $process->run();

        $outputStr = trim($process->getOutput() . $process->getErrorOutput());
        $exitCode = $process->getExitCode();

        if ($exitCode !== 0) {
            $this->logger->error(
                '[Fitment] Fallback rebuild falhou (exit ' . $exitCode . '): ' . $outputStr
            );
        } else {
            $this->logger->info('[Fitment] Fallback rebuild OK: ' . $outputStr);
        }
    }
}

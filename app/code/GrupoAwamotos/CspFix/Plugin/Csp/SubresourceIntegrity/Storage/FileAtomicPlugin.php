<?php

/**
 * GrupoAwamotos_CspFix
 *
 * Objetivo:
 * - Evitar corrupsão do arquivo pub/static/{context}/sri-hashes.json por escrita não-atômica
 * - Evitar 500 em caso de leitura parcial (JSON inválido) retornando null (equivale a "sem dados")
 */

declare(strict_types=1);

namespace GrupoAwamotos\CspFix\Plugin\Csp\SubresourceIntegrity\Storage;

use Magento\Csp\Model\SubresourceIntegrity\Storage\File as Subject;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

class FileAtomicPlugin
{
    private const FILENAME = 'sri-hashes.json';

    private Filesystem $filesystem;
    private LoggerInterface $logger;

    public function __construct(
        Filesystem $filesystem,
        LoggerInterface $logger
    ) {
        $this->filesystem = $filesystem;
        $this->logger = $logger;
    }

    /**
     * Escrita atômica (write temp + rename) para reduzir chance de arquivo truncado.
     */
    public function aroundSave(Subject $subject, callable $proceed, string $data, ?string $context): bool
    {
        $staticDir = $this->filesystem->getDirectoryWrite(DirectoryList::STATIC_VIEW);
        $path      = $this->resolveFilePath($context);
        $absBase   = rtrim($staticDir->getAbsolutePath(''), '/');
        $tmpRel    = $path . '.tmp.' . bin2hex(random_bytes(6));
        $absTmp    = $absBase . '/' . $tmpRel;
        $absDest   = $absBase . '/' . $path;

        try {
            // Garante diretório do contexto (frontend/adminhtml) quando aplicável
            if ($context) {
                $staticDir->create($context);
            }

            // Escreve em arquivo temporário
            $staticDir->writeFile($tmpRel, $data, 'w');

            // Rename atômico via PHP nativo — evita o chmod() interno do Magento
            // que falha quando o processo não é dono do arquivo destino.
            $renameError = null;
            $writeError   = null;
            if (!$this->safeRename($absTmp, $absDest, $renameError)) {

                // Fallback sem passar pelo Driver\File do Magento. Em cenários com
                // owner/grupo corretos, sobrescrever o arquivo diretamente evita o
                // chmod() interno que gerava FileSystemException no deploy.
                if (!$this->safeWrite($absDest, $data, $writeError)) {
                    throw new \RuntimeException(sprintf(
                        '[CspFix] Falha ao persistir %s (rename: %s | write: %s)',
                        $absDest,
                        $renameError ?? 'unknown',
                        $writeError ?? 'unknown'
                    ));
                }

                $this->safeUnlink($absTmp);
            }

            return true;
        } catch (\Throwable $e) {
            // Limpa o arquivo temporário se sobrou no disco
            if (file_exists($absTmp)) {
                $this->safeUnlink($absTmp);
            }

            $this->logger->critical('[CspFix] Atomic write falhou: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Leitura tolerante: se o JSON estiver inválido (leitura parcial por concorrência),
     * retorna null para evitar exception no unserialize.
     */
    public function aroundLoad(Subject $subject, callable $proceed, ?string $context): ?string
    {
        $raw = null;

        try {
            $raw = $proceed($context);
        } catch (\Throwable $e) {
            $this->logger->warning('[CspFix] Erro ao carregar sri-hashes.json: ' . $e->getMessage());
            return null;
        }

        if (!$raw) {
            return $raw;
        }

        if ($this->isValidJson($raw)) {
            return $raw;
        }

        // Retry rápido: pode ter pego o arquivo no meio do rename/write
        try {
            $raw2 = $proceed($context);
            if ($raw2 && $this->isValidJson($raw2)) {
                return $raw2;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[CspFix] Retry de leitura do sri-hashes.json falhou: ' . $e->getMessage());
        }

        // Evita quebrar o frontend/admin por JSON inválido.
        return null;
    }

    private function isValidJson(string $raw): bool
    {
        // sri-hashes.json é um JSON (map path => hash)
        json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function resolveFilePath(?string $context): string
    {
        return ($context ? $context . DIRECTORY_SEPARATOR : '') . self::FILENAME;
    }

    private function safeRename(string $from, string $to, ?string &$errorMessage = null): bool
    {
        return $this->withFilesystemWarningCapture(
            static fn (): bool => rename($from, $to),
            $errorMessage
        );
    }

    private function safeWrite(string $path, string $content, ?string &$errorMessage = null): bool
    {
        return $this->withFilesystemWarningCapture(
            static fn (): bool => file_put_contents($path, $content, LOCK_EX) !== false,
            $errorMessage
        );
    }

    private function safeUnlink(string $path): void
    {
        $ignoreError = null;
        $this->withFilesystemWarningCapture(
            static fn (): bool => !file_exists($path) || unlink($path),
            $ignoreError
        );
    }

    private function withFilesystemWarningCapture(callable $callback, ?string &$errorMessage = null): bool
    {
        $lastError = null;
        set_error_handler(static function (int $severity, string $message) use (&$lastError): bool {
            $lastError = $message;
            return true;
        });

        try {
            $result = (bool) $callback();
        } finally {
            restore_error_handler();
        }

        $errorMessage = $lastError;

        return $result;
    }
}

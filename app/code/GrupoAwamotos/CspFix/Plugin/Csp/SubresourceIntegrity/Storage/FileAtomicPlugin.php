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
        try {
            $staticDir = $this->filesystem->getDirectoryWrite(DirectoryList::STATIC_VIEW);
            $path = $this->resolveFilePath($context);

            $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));

            // Garante diretório do contexto (frontend/adminhtml) quando aplicável
            if ($context) {
                $staticDir->create($context);
            }

            // Escrever em arquivo temporário e renomear (operação atômica no mesmo filesystem)
            $staticDir->writeFile($tmp, $data, 'w');
            $staticDir->renameFile($tmp, $path);

            // Mantém permissões previsíveis (mesmo se falhar, não deve quebrar fluxo)
            try {
                $staticDir->changePermissions($path, 0664);
            } catch (\Throwable $e) {
                $this->logger->debug('[CspFix] Could not set permissions on ' . $path . ': ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            // Fallback total: volta ao comportamento original do Magento
            $this->logger->critical($e);
            try {
                return (bool) $proceed($data, $context);
            } catch (\Throwable $e2) {
                $this->logger->critical($e2);
                return false;
            }
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
            $this->logger->critical($e);
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
            $this->logger->critical($e);
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
}

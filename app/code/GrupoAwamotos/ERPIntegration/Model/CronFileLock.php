<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model;

/**
 * Non-blocking file lock for cron jobs (prevents overlapping PHP processes).
 */
final class CronFileLock
{
    /**
     * @return resource|null
     */
    public static function acquire(string $lockName)
    {
        $lockDir = BP . '/var/lock';
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0775, true);
        }

        $handle = @fopen($lockDir . '/' . $lockName . '.lock', 'c+');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        return $handle;
    }

    /**
     * @param resource|null $handle
     */
    public static function release($handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

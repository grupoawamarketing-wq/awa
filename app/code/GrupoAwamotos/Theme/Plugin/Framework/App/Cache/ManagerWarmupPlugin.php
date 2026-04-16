<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Plugin\Framework\App\Cache;

use GrupoAwamotos\Theme\Model\CacheWarmupLauncher;
use Magento\Framework\App\Cache\Manager;

class ManagerWarmupPlugin
{
    private const RELEVANT_TYPES = [
        'config',
        'layout',
        'block_html',
        'full_page',
        'collections',
        'compiled_config',
        'translate',
    ];

    public function __construct(
        private readonly CacheWarmupLauncher $cacheWarmupLauncher
    ) {
    }

    public function afterFlush(Manager $subject, mixed $result, array $types): mixed
    {
        if ($this->shouldWarmAfterFlush($types)) {
            $this->cacheWarmupLauncher->runIfEligible('cache_flush');
        }

        return $result;
    }

    private function shouldWarmAfterFlush(array $types): bool
    {
        if ($types === []) {
            return false;
        }

        if (count($types) >= 5) {
            return true;
        }

        return array_intersect($types, self::RELEVANT_TYPES) !== [];
    }
}

<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Cnpj;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class RequestRateLimiter
{
    private const CACHE_KEY_PREFIX = 'grupoawamotos_b2b_cnpj_rate_limit_';
    private const CACHE_TAG = 'GRUPOAWAMOTOS_B2B_CNPJ_RATE_LIMIT';

    private CacheInterface $cache;
    private Json $json;
    private CnpjValidator $cnpjValidator;
    private LoggerInterface $logger;

    public function __construct(
        CacheInterface $cache,
        Json $json,
        CnpjValidator $cnpjValidator,
        LoggerInterface $logger
    ) {
        $this->cache = $cache;
        $this->json = $json;
        $this->cnpjValidator = $cnpjValidator;
        $this->logger = $logger;
    }

    public function consume(string $identifier): array
    {
        $maxRequests = max(1, $this->cnpjValidator->getRateLimitMaxRequests());
        $windowSeconds = max(1, $this->cnpjValidator->getRateLimitWindowSeconds());

        if (!$this->cnpjValidator->isRateLimitEnabled()) {
            return [
                'allowed' => true,
                'retry_after' => 0,
                'remaining' => $maxRequests,
                'limit' => $maxRequests,
                'window' => $windowSeconds
            ];
        }

        try {
            $cacheId = $this->buildCacheId($identifier);
            $now = time();
            $state = $this->loadState($cacheId);

            if ($state === null || (int) $state['expires_at'] <= $now) {
                $state = [
                    'count' => 0,
                    'expires_at' => $now + $windowSeconds
                ];
            }

            $state['count'] = (int) $state['count'] + 1;
            $retryAfter = max(1, (int) $state['expires_at'] - $now);
            $this->persistState($cacheId, $state, $retryAfter);

            $allowed = (int) $state['count'] <= $maxRequests;

            return [
                'allowed' => $allowed,
                'retry_after' => $allowed ? 0 : $retryAfter,
                'remaining' => $allowed ? max(0, $maxRequests - (int) $state['count']) : 0,
                'limit' => $maxRequests,
                'window' => $windowSeconds
            ];
        } catch (\Throwable $exception) {
            $this->logger->warning(
                sprintf('[B2B][CNPJ] Rate limiter bypassed due to error: %s', $exception->getMessage())
            );

            return [
                'allowed' => true,
                'retry_after' => 0,
                'remaining' => $maxRequests,
                'limit' => $maxRequests,
                'window' => $windowSeconds
            ];
        }
    }

    private function loadState(string $cacheId): ?array
    {
        $cached = $this->cache->load($cacheId);
        if (!$cached) {
            return null;
        }

        try {
            $state = $this->json->unserialize($cached);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->warning(
                sprintf('[B2B][CNPJ] Invalid rate limiter cache payload: %s', $exception->getMessage())
            );

            return null;
        }

        if (!is_array($state) || !isset($state['count'], $state['expires_at'])) {
            return null;
        }

        return [
            'count' => (int) $state['count'],
            'expires_at' => (int) $state['expires_at']
        ];
    }

    private function persistState(string $cacheId, array $state, int $ttl): void
    {
        $this->cache->save(
            $this->json->serialize($state),
            $cacheId,
            [self::CACHE_TAG],
            $ttl
        );
    }

    private function buildCacheId(string $identifier): string
    {
        $normalized = trim($identifier) !== '' ? trim($identifier) : 'anonymous';

        return self::CACHE_KEY_PREFIX . sha1($normalized);
    }
}

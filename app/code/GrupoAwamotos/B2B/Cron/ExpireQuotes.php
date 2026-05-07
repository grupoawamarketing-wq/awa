<?php

/**
 * Cron job para expirar cotações vencidas automaticamente
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Cron;

use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class ExpireQuotes
{
    private const TABLE = 'grupoawamotos_b2b_quote_request';
    private const ACTIVE_STATUSES = ['pending', 'processing', 'quoted'];

    private CollectionFactory $collectionFactory;
    private Config $config;
    private LoggerInterface $logger;
    private ResourceConnection $resource;

    public function __construct(
        CollectionFactory $collectionFactory,
        Config $config,
        LoggerInterface $logger,
        ResourceConnection $resource
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->config = $config;
        $this->logger = $logger;
        $this->resource = $resource;
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isQuoteEnabled()) {
            return;
        }

        try {
            $now = date('Y-m-d H:i:s');
            $connection = $this->resource->getConnection();
            $table = $connection->getTableName(self::TABLE);
            $count = 0;

            // Batch UPDATE: cotações com expires_at no passado
            $count += (int) $connection->update(
                $table,
                ['status' => 'expired'],
                [
                    'status IN (?)' => self::ACTIVE_STATUSES,
                    'expires_at IS NOT NULL',
                    'expires_at < ?' => $now,
                ]
            );

            // Batch UPDATE: cotações sem expires_at mas com prazo configurado
            $expiryDays = $this->config->getQuoteExpiryDays();
            if ($expiryDays > 0) {
                $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$expiryDays} days"));
                $count += (int) $connection->update(
                    $table,
                    ['status' => 'expired'],
                    [
                        'status IN (?)' => self::ACTIVE_STATUSES,
                        'expires_at IS NULL',
                        'created_at < ?' => $cutoffDate,
                    ]
                );
            }

            if ($count > 0) {
                $this->logger->info("B2B: {$count} cotação(ões) expirada(s) automaticamente.");
            }
        } catch (\Throwable $e) {
            $this->logger->error('[B2B] ExpireQuotes failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}

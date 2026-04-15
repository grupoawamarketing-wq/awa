<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Cron;

use GrupoAwamotos\WhatsAppCommerce\Helper\Config;
use GrupoAwamotos\WhatsAppCommerce\Model\MessageSender;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Cron: envia pedido de review via WhatsApp 7 dias após entrega.
 *
 * Condições:
 * - Pedido com status "complete"
 * - Cliente com whatsapp_optin = 1
 * - Nenhuma review request já enviada para esse pedido (tracked na tabela consent_log com source='review_request')
 * - Pedido completado há exatamente 7 dias (range de 24h)
 *
 * Roda diariamente às 10:00.
 */
class ReviewRequest
{
    private const DAYS_AFTER_DELIVERY = 7;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly MessageSender $messageSender,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled() || !$this->config->isReviewRequestEnabled()) {
            return;
        }

        try {
            $orders = $this->getEligibleOrders();

            if (empty($orders)) {
                $this->logger->debug('[ReviewRequest] No eligible orders found');
                return;
            }

            $sent = 0;
            $failed = 0;

            foreach ($orders as $order) {
                try {
                    $message = $this->buildMessage($order);
                    $success = $this->messageSender->send($order['phone'], $message);

                    if ($success) {
                        $this->markAsSent($order['order_id'], $order['customer_id']);
                        $sent++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->warning('[ReviewRequest] Send failed for order ' . $order['increment_id'], [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->info('[ReviewRequest] Completed', [
                'eligible' => count($orders),
                'sent' => $sent,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ReviewRequest] Cron error: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, array{order_id: int, increment_id: string, customer_id: int, customer_name: string, phone: string, items: string}>
     */
    private function getEligibleOrders(): array
    {
        $connection = $this->resource->getConnection();
        $days = self::DAYS_AFTER_DELIVERY;

        $optinAttrId = $this->getAttributeId($connection, 'whatsapp_optin');
        if ($optinAttrId === 0) {
            return [];
        }

        // Find completed orders from exactly N days ago (24h window)
        // where customer has opt-in and we haven't sent a request yet
        $select = $connection->select()
            ->from(
                ['so' => $this->resource->getTableName('sales_order')],
                [
                    'order_id' => 'so.entity_id',
                    'increment_id' => 'so.increment_id',
                    'customer_id' => 'so.customer_id',
                    'customer_name' => 'so.customer_firstname',
                ]
            )
            ->join(
                ['cev' => $this->resource->getTableName('customer_entity_varchar')],
                'cev.entity_id = so.customer_id AND cev.attribute_id = ' . $optinAttrId,
                []
            )
            ->joinLeft(
                ['soa' => $this->resource->getTableName('customer_address_entity')],
                'soa.parent_id = so.customer_id',
                ['phone' => 'soa.telephone']
            )
            ->where('so.state = ?', 'complete')
            ->where('so.customer_id IS NOT NULL')
            ->where('cev.value = ?', '1')
            ->where(
                'so.updated_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY) AND DATE_SUB(NOW(), INTERVAL ? DAY)',
                [$days + 1, $days - 1]
            )
            ->group('so.entity_id')
            ->limit(50); // Max 50 per run (Baileys safety)

        // Exclude orders that already had review request sent
        $sentSubselect = $connection->select()
            ->from(
                $this->resource->getTableName('awa_whatsapp_review_request_log'),
                ['order_id']
            );

        if ($connection->isTableExists($this->resource->getTableName('awa_whatsapp_review_request_log'))) {
            $select->where('so.entity_id NOT IN (?)', $sentSubselect);
        }

        $rows = $connection->fetchAll($select);

        // Get items for each order
        $result = [];
        foreach ($rows as $row) {
            if (empty($row['phone'])) {
                continue;
            }

            $phone = $this->normalizePhone($row['phone']);
            if (empty($phone)) {
                continue;
            }

            $items = $this->getOrderItemNames((int) $row['order_id'], $connection);

            $result[] = [
                'order_id' => (int) $row['order_id'],
                'increment_id' => $row['increment_id'],
                'customer_id' => (int) $row['customer_id'],
                'customer_name' => $row['customer_name'] ?? 'Cliente',
                'phone' => $phone,
                'items' => $items,
            ];
        }

        return $result;
    }

    private function buildMessage(array $order): string
    {
        $name = $order['customer_name'];
        $items = $order['items'];
        $baseUrl = '';

        try {
            $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        } catch (\Exception $e) {
            $baseUrl = 'https://awamotos.com';
        }

        return "Oi {$name}! Sua compra chegou bem? 😊\n\n"
            . "Nos ajude com uma avaliacao dos produtos que voce recebeu:\n"
            . "{$items}\n\n"
            . "Responda com uma nota de 1 a 5 estrelas e um breve comentario,\n"
            . "ou avalie no site: {$baseUrl}/review/\n\n"
            . "Sua opiniao ajuda outros motociclistas! 🏍️\n"
            . "Obrigado por comprar na AWA Motos!";
    }

    private function getOrderItemNames(int $orderId, \Magento\Framework\DB\Adapter\AdapterInterface $connection): string
    {
        $items = $connection->fetchCol(
            $connection->select()
                ->from(
                    $this->resource->getTableName('sales_order_item'),
                    ['name']
                )
                ->where('order_id = ?', $orderId)
                ->where('parent_item_id IS NULL')
                ->limit(3)
        );

        if (empty($items)) {
            return '';
        }

        $text = implode("\n", array_map(fn(string $name) => "• {$name}", $items));
        $totalItems = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('sales_order_item'), [new \Zend_Db_Expr('COUNT(*)')])
                ->where('order_id = ?', $orderId)
                ->where('parent_item_id IS NULL')
        );

        if ($totalItems > 3) {
            $text .= sprintf("\n  ...e mais %d item(ns)", $totalItems - 3);
        }

        return $text;
    }

    private function markAsSent(int $orderId, int $customerId): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('awa_whatsapp_review_request_log');

        if (!$connection->isTableExists($table)) {
            return;
        }

        $connection->insert($table, [
            'order_id' => $orderId,
            'customer_id' => $customerId,
            'sent_at' => new \Zend_Db_Expr('NOW()'),
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) <= 8) {
            return '';
        }
        if (strlen($digits) <= 11 && !str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }
        return $digits;
    }

    private function getAttributeId(\Magento\Framework\DB\Adapter\AdapterInterface $connection, string $code): int
    {
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', $code)
                ->where('entity_type_id = ?', 1)
                ->limit(1)
        );
    }
}

<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Sectra;

use GrupoAwamotos\B2B\Helper\Config as B2bConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

/**
 * Gates B2B orders until the customer is validated in Sectra GR_INTEGRACAOVALIDADOR.
 */
class OrderImportGate
{
    private const B2B_GROUP_IDS = [4, 5, 6];

    public function __construct(
        private readonly ValidatorChecker $validatorChecker,
        private readonly SectraSyncLogger $syncLogger,
        private readonly B2bConfig $b2bConfig,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Backfill sectra_import_status for orders placed before the gate existed.
     */
    public function backfillOrderImportStatus(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            "SELECT so.entity_id, so.customer_id, so.sectra_import_status
             FROM sales_order so
             INNER JOIN customer_entity ce ON ce.entity_id = so.customer_id
             WHERE ce.group_id IN (4, 5, 6)
               AND so.state NOT IN ('canceled', 'closed')
               AND (so.sectra_import_status IS NULL
                    OR so.sectra_import_status = ?)",
            [SectraImportStatus::AWAITING_CUSTOMER_VALIDATION]
        );

        $updated = 0;
        foreach ($rows as $row) {
            $customerId = (int) $row['customer_id'];
            $orderId = (int) $row['entity_id'];
            $current = (string) ($row['sectra_import_status'] ?? '');

            if ($this->validatorChecker->isCustomerValidatedInSectra($customerId)) {
                if ($current !== SectraImportStatus::READY_FOR_IMPORT) {
                    $this->setOrderImportStatus($orderId, SectraImportStatus::READY_FOR_IMPORT);
                    $updated++;
                }
                continue;
            }

            if ($current === '' || $current === SectraImportStatus::AWAITING_CUSTOMER_VALIDATION) {
                if ($current !== SectraImportStatus::AWAITING_CUSTOMER_VALIDATION) {
                    $this->setOrderImportStatus($orderId, SectraImportStatus::AWAITING_CUSTOMER_VALIDATION);
                    $updated++;
                }
            }
        }

        return $updated;
    }

    public function applyOnOrderPlace(OrderInterface $order): void
    {
        if (!$this->b2bConfig->isEnabled()) {
            return;
        }

        $customerId = (int) $order->getCustomerId();
        if ($customerId <= 0) {
            return;
        }

        if (!$this->isB2bCustomer($customerId)) {
            $this->setOrderImportStatus((int) $order->getEntityId(), SectraImportStatus::NOT_APPLICABLE);
            return;
        }

        if ($this->validatorChecker->isCustomerValidatedInSectra($customerId)) {
            $this->setOrderImportStatus((int) $order->getEntityId(), SectraImportStatus::READY_FOR_IMPORT);
            $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);
            $this->syncLogger->log(
                ProspectEvent::ORDER_RELEASED_FOR_IMPORT,
                sprintf(
                    'Pedido #%s liberado — cliente validado no Sectra.',
                    $order->getIncrementId()
                ),
                $customerId,
                (int) $order->getEntityId(),
                null,
                $sectraChave,
                'success'
            );
            return;
        }

        $this->setOrderImportStatus((int) $order->getEntityId(), SectraImportStatus::AWAITING_CUSTOMER_VALIDATION);
        $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);

        $this->syncLogger->log(
            ProspectEvent::ORDER_AWAITING_CUSTOMER,
            sprintf(
                'Pedido #%s aguardando validação ERP do cliente — não exposto em oc_order.',
                $order->getIncrementId()
            ),
            $customerId,
            (int) $order->getEntityId(),
            null,
            $sectraChave
        );

        $this->addOrderComment(
            $order,
            SectraImportStatus::label(SectraImportStatus::AWAITING_CUSTOMER_VALIDATION)
        );
    }

    /**
     * Release held orders after customer validation in Sectra.
     */
    public function releaseOrdersForCustomer(int $customerId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $orderIds = $connection->fetchCol(
            'SELECT entity_id FROM sales_order
             WHERE customer_id = ?
               AND sectra_import_status = ?
               AND state NOT IN (?, ?)',
            [
                $customerId,
                SectraImportStatus::AWAITING_CUSTOMER_VALIDATION,
                Order::STATE_CANCELED,
                Order::STATE_CLOSED,
            ]
        );

        if ($orderIds === []) {
            return 0;
        }

        $connection->update(
            'sales_order',
            ['sectra_import_status' => SectraImportStatus::READY_FOR_IMPORT],
            [
                'entity_id IN (?)' => $orderIds,
            ]
        );

        $sectraChave = $this->validatorChecker->resolveSectraChave($customerId);
        foreach ($orderIds as $orderId) {
            $incrementId = $connection->fetchOne(
                'SELECT increment_id FROM sales_order WHERE entity_id = ?',
                [(int) $orderId]
            );
            $this->syncLogger->log(
                ProspectEvent::ORDER_RELEASED_FOR_IMPORT,
                sprintf(
                    'Pedido #%s liberado após validação do cliente no Sectra.',
                    $incrementId ?: $orderId
                ),
                $customerId,
                (int) $orderId,
                null,
                $sectraChave,
                'success'
            );
        }

        return count($orderIds);
    }

    /**
     * Mark orders imported by Sectra (oc_order_imported ack).
     */
    public function syncImportedOrderFlags(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            'SELECT oi.order_id, so.increment_id, so.customer_id
             FROM oc_order_imported oi
             INNER JOIN sales_order so ON so.entity_id = oi.order_id
             WHERE so.sectra_import_status IS NULL
                OR so.sectra_import_status IN (?, ?)',
            [
                SectraImportStatus::READY_FOR_IMPORT,
                SectraImportStatus::AWAITING_CUSTOMER_VALIDATION,
            ]
        );

        $updated = 0;
        foreach ($rows as $row) {
            $orderId = (int) $row['order_id'];
            $this->setOrderImportStatus($orderId, SectraImportStatus::IMPORTED);
            $this->syncLogger->log(
                ProspectEvent::ORDER_IMPORTED_SUCCESS,
                sprintf('Pedido #%s importado com sucesso no ERP Sectra.', $row['increment_id']),
                (int) ($row['customer_id'] ?? 0) ?: null,
                $orderId,
                null,
                null,
                'success'
            );
            $updated++;
        }

        return $updated;
    }

    private function isB2bCustomer(int $customerId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $groupId = (int) $connection->fetchOne(
            'SELECT group_id FROM customer_entity WHERE entity_id = ?',
            [$customerId]
        );

        return in_array($groupId, self::B2B_GROUP_IDS, true);
    }

    private function setOrderImportStatus(int $orderId, string $status): void
    {
        $this->resourceConnection->getConnection()->update(
            'sales_order',
            ['sectra_import_status' => $status],
            ['entity_id = ?' => $orderId]
        );
    }

    private function addOrderComment(OrderInterface $order, string $comment): void
    {
        if (!$order instanceof Order) {
            return;
        }

        try {
            $order->addCommentToStatusHistory($comment, false, false);
            $order->save();
        } catch (\Exception $e) {
            $this->logger->warning(sprintf(
                '[B2B-Sectra] Pedido #%s: falha ao adicionar comentário — %s',
                $order->getIncrementId(),
                $e->getMessage()
            ));
        }
    }
}

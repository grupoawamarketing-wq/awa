<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Model\CreditLimitFactory;
use GrupoAwamotos\B2B\Model\CreditTransactionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit as CreditLimitResource;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction as CreditTransactionResource;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\CollectionFactory as CreditCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditTransaction\CollectionFactory as TxnCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class CreditService
{
    private CreditLimitFactory $creditFactory;
    private CreditTransactionFactory $txnFactory;
    private CreditLimitResource $creditResource;
    private CreditTransactionResource $txnResource;
    private CreditCollectionFactory $creditCollectionFactory;
    private TxnCollectionFactory $txnCollectionFactory;
    private ScopeConfigInterface $scopeConfig;
    private LoggerInterface $logger;

    public function __construct(
        CreditLimitFactory $creditFactory,
        CreditTransactionFactory $txnFactory,
        CreditLimitResource $creditResource,
        CreditTransactionResource $txnResource,
        CreditCollectionFactory $creditCollectionFactory,
        TxnCollectionFactory $txnCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->creditFactory = $creditFactory;
        $this->txnFactory = $txnFactory;
        $this->creditResource = $creditResource;
        $this->txnResource = $txnResource;
        $this->creditCollectionFactory = $creditCollectionFactory;
        $this->txnCollectionFactory = $txnCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue('grupoawamotos_b2b/credit/enabled');
    }

    /**
     * Get payment method title from config
     */
    public function getPaymentTitle(): string
    {
        return (string) ($this->scopeConfig->getValue('grupoawamotos_b2b/credit/payment_title') ?: __('Crédito B2B (Faturamento)'));
    }

    /**
     * Get available payment terms for a customer
     *
     * Returns an array of term objects with value/label pairs.
     *
     * @param int $customerId
     * @return array<int, array{value: string, label: string}>
     */
    public function getAvailablePaymentTerms(int $customerId): array
    {
        $credit = $this->getCreditLimit($customerId);
        $allowedTerms = $credit->getPaymentTerms();
        $labels = CreditLimit::getPaymentTermLabels();

        $result = [];
        foreach ($allowedTerms as $term) {
            $result[] = [
                'value' => $term,
                'label' => $labels[$term] ?? $term,
            ];
        }

        return $result;
    }

    /**
     * Set allowed payment terms for a customer (admin action)
     *
     * @param int      $customerId
     * @param string[] $terms
     * @param int|null $adminId
     * @return CreditLimit
     */
    public function setPaymentTerms(int $customerId, array $terms, ?int $adminId = null): CreditLimit
    {
        $valid = array_keys(CreditLimit::getPaymentTermLabels());
        $terms = array_values(array_intersect($terms, $valid));

        $credit = $this->getCreditLimit($customerId);
        $credit->setPaymentTerms($terms);
        $this->creditResource->save($credit);

        $this->logger->info(sprintf(
            'B2B Payment terms updated: customer=%d terms=[%s] admin=%s',
            $customerId,
            implode(',', $terms),
            $adminId ?? 'system'
        ));

        return $credit;
    }

    /**
     * Get or create credit limit for customer
     */
    public function getCreditLimit(int $customerId): CreditLimit
    {
        $collection = $this->creditCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $credit = $collection->getFirstItem();

        if (!$credit->getId()) {
            $credit = $this->creditFactory->create();
            $credit->setCustomerId($customerId);
            $credit->setCreditLimit(0);
            $credit->setUsedCredit(0);
            $credit->setCurrencyCode('BRL');
            $this->creditResource->save($credit);
        }

        return $credit;
    }

    /**
     * Set credit limit for customer (admin action)
     */
    public function setLimit(int $customerId, float $limit, ?int $adminId = null, string $comment = ''): CreditLimit
    {
        $credit = $this->getCreditLimit($customerId);
        $oldLimit = $credit->getCreditLimit();
        $credit->setCreditLimit($limit);
        $this->creditResource->save($credit);

        $this->logTransaction(
            $customerId,
            CreditTransaction::TYPE_ADJUSTMENT,
            $limit - $oldLimit,
            $credit->getAvailableCredit(),
            null,
            'limit_change',
            $comment ?: sprintf('Limite alterado de R$ %.2f para R$ %.2f', $oldLimit, $limit),
            $adminId
        );

        return $credit;
    }

    /**
     * Charge credit (debit for an order)
     *
     * Uses atomic UPDATE with row-level lock to prevent race conditions.
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function charge(int $customerId, float $amount, int $orderId, string $reference = ''): void
    {
        $connection = $this->creditResource->getConnection();
        $tableName = $this->creditResource->getMainTable();

        $connection->beginTransaction();
        try {
            // Atomic: lock row + check + update in single transaction
            $select = $connection->select()
                ->from($tableName, ['entity_id', 'credit_limit', 'used_credit'])
                ->where('customer_id = ?', $customerId)
                ->forUpdate();
            $row = $connection->fetchRow($select);

            if (!$row) {
                throw new LocalizedException(__('Crédito não encontrado para o cliente #%1.', $customerId));
            }

            $available = (float) $row['credit_limit'] - (float) $row['used_credit'];
            if ($available < $amount) {
                throw new LocalizedException(__(
                    'Crédito insuficiente. Disponível: R$ %1',
                    number_format($available, 2, ',', '.')
                ));
            }

            $connection->update(
                $tableName,
                ['used_credit' => new Expression('used_credit + ' . $connection->quote($amount))],
                ['entity_id = ?' => (int) $row['entity_id']]
            );

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        // Read updated balance for logging (outside transaction — eventual consistency is fine for log)
        $credit = $this->getCreditLimit($customerId);

        $this->logTransaction(
            $customerId,
            CreditTransaction::TYPE_CHARGE,
            -$amount,
            $credit->getAvailableCredit(),
            $orderId,
            $reference ?: "Pedido #$orderId"
        );

        $this->logger->info("B2B Credit charged: customer=$customerId amount=$amount order=$orderId");
    }

    /**
     * Refund credit
     *
     * Uses atomic UPDATE to prevent lost updates from concurrent refunds.
     */
    public function refund(int $customerId, float $amount, int $orderId, string $reference = ''): void
    {
        $connection = $this->creditResource->getConnection();
        $tableName = $this->creditResource->getMainTable();

        $connection->update(
            $tableName,
            ['used_credit' => new Expression('GREATEST(0, used_credit - ' . $connection->quote($amount) . ')')],
            ['customer_id = ?' => $customerId]
        );

        $credit = $this->getCreditLimit($customerId);

        $this->logTransaction(
            $customerId,
            CreditTransaction::TYPE_REFUND,
            $amount,
            $credit->getAvailableCredit(),
            $orderId,
            $reference ?: "Estorno Pedido #$orderId"
        );
    }

    /**
     * Record payment received (reduces used_credit)
     *
     * Uses atomic UPDATE to prevent lost updates from concurrent payments.
     */
    public function recordPayment(int $customerId, float $amount, ?int $adminId = null, string $comment = ''): void
    {
        $connection = $this->creditResource->getConnection();
        $tableName = $this->creditResource->getMainTable();

        $connection->update(
            $tableName,
            ['used_credit' => new Expression('GREATEST(0, used_credit - ' . $connection->quote($amount) . ')')],
            ['customer_id = ?' => $customerId]
        );

        $credit = $this->getCreditLimit($customerId);

        $this->logTransaction(
            $customerId,
            CreditTransaction::TYPE_PAYMENT,
            $amount,
            $credit->getAvailableCredit(),
            null,
            'payment',
            $comment,
            $adminId
        );
    }

    /**
     * Get transaction history for customer
     */
    public function getTransactions(int $customerId, int $limit = 20): \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection
    {
        $collection = $this->txnCollectionFactory->create();
        $collection->filterByCustomer($customerId);
        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);
        return $collection;
    }

    /**
     * Check if customer has sufficient credit
     */
    public function hasSufficientCredit(int $customerId, float $amount): bool
    {
        $credit = $this->getCreditLimit($customerId);
        return $credit->getAvailableCredit() >= $amount;
    }

    private function logTransaction(
        int $customerId,
        string $type,
        float $amount,
        float $balanceAfter,
        ?int $orderId = null,
        ?string $reference = null,
        ?string $comment = null,
        ?int $adminId = null
    ): void {
        $txn = $this->txnFactory->create();
        $txn->setData([
            'customer_id' => $customerId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'order_id' => $orderId,
            'reference' => $reference,
            'comment' => $comment,
            'admin_user_id' => $adminId,
        ]);
        $this->txnResource->save($txn);
    }
}

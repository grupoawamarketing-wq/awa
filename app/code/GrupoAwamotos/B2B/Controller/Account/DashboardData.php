<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\QuoteRequest\CollectionFactory as QuoteCollectionFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\CreditLimit\CollectionFactory as CreditCollectionFactory;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Psr\Log\LoggerInterface;

/**
 * AJAX endpoint for B2B dashboard data (lazy-loaded sections)
 * Route: b2b/account/dashboardData
 */
class DashboardData implements HttpGetActionInterface
{
    private CustomerSession $customerSession;
    private JsonFactory $jsonFactory;
    private RequestInterface $request;
    private OrderCollectionFactory $orderCollectionFactory;
    private QuoteCollectionFactory $quoteCollectionFactory;
    private CreditCollectionFactory $creditCollectionFactory;
    private B2BHelper $b2bHelper;
    private PricingHelper $pricingHelper;
    private LoggerInterface $logger;

    public function __construct(
        CustomerSession $customerSession,
        JsonFactory $jsonFactory,
        RequestInterface $request,
        OrderCollectionFactory $orderCollectionFactory,
        QuoteCollectionFactory $quoteCollectionFactory,
        CreditCollectionFactory $creditCollectionFactory,
        B2BHelper $b2bHelper,
        PricingHelper $pricingHelper,
        LoggerInterface $logger
    ) {
        $this->customerSession = $customerSession;
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->creditCollectionFactory = $creditCollectionFactory;
        $this->b2bHelper = $b2bHelper;
        $this->pricingHelper = $pricingHelper;
        $this->logger = $logger;
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        // Validate AJAX request (X-Requested-With header) to prevent cross-site data leakage
        if ($this->request->getHeader('X-Requested-With') !== 'XMLHttpRequest') {
            return $result->setHttpResponseCode(403)->setData(['error' => 'Forbidden']);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData(['error' => 'Not authenticated']);
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        $section = $this->request->getParam('section', 'all');

        try {
            $data = [];

            if ($section === 'all' || $section === 'orders') {
                $data['orders'] = $this->getRecentOrdersData($customerId);
            }
            if ($section === 'all' || $section === 'quotes') {
                $data['quotes'] = $this->getQuotesData($customerId);
            }
            if ($section === 'all' || $section === 'credit') {
                $data['credit'] = $this->getCreditData($customerId);
            }

            return $result->setData($data);
        } catch (\Throwable $e) {
            $this->logger->error('B2B Dashboard AJAX error: ' . $e->getMessage());
            return $result->setHttpResponseCode(500)->setData(['error' => 'Server error']);
        }
    }

    private function getRecentOrdersData(int $customerId): array
    {
        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->setOrder('created_at', 'DESC')
            ->setPageSize(5);

        $orders = [];
        foreach ($collection as $order) {
            $orders[] = [
                'increment_id' => $order->getIncrementId(),
                'created_at' => $order->getCreatedAt(),
                'status' => $order->getStatusLabel(),
                'grand_total' => $this->pricingHelper->currency((float) $order->getGrandTotal(), true, false),
                'view_url' => $this->b2bHelper->getOrderViewUrl($order),
            ];
        }

        return ['items' => $orders];
    }

    private function getQuotesData(int $customerId): array
    {
        $collection = $this->quoteCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->setOrder('created_at', 'DESC')
            ->setPageSize(5);

        $quotes = [];
        foreach ($collection as $quote) {
            $quotes[] = [
                'id' => $quote->getId(),
                'status' => $quote->getStatus(),
                'created_at' => $quote->getCreatedAt(),
                'message' => mb_substr((string) $quote->getMessage(), 0, 80),
            ];
        }

        $pendingCount = $this->quoteCollectionFactory->create()
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('status', 'pending')
            ->getSize();

        return ['items' => $quotes, 'pending_count' => $pendingCount];
    }

    private function getCreditData(int $customerId): array
    {
        $collection = $this->creditCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->setPageSize(1);

        $credit = $collection->getFirstItem();
        if (!$credit || !$credit->getId()) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'limit' => $this->pricingHelper->currency((float) $credit->getCreditLimit(), true, false),
            'used' => $this->pricingHelper->currency((float) $credit->getUsedAmount(), true, false),
            'remaining' => $this->pricingHelper->currency(
                (float) $credit->getCreditLimit() - (float) $credit->getUsedAmount(),
                true,
                false
            ),
        ];
    }
}

<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use GrupoAwamotos\ERPIntegration\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;

class CheckoutAccessValidator
{
    public const STATE_APPROVED = 'approved';
    public const STATE_PENDING = ApprovalStatus::STATUS_PENDING;
    public const STATE_REJECTED = ApprovalStatus::STATUS_REJECTED;
    public const STATE_SUSPENDED = ApprovalStatus::STATUS_SUSPENDED;
    public const STATE_PENDING_ERP = 'pending_erp';

    public function __construct(
        private readonly Config $config,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SyncLogResource $syncLogResource,
        private readonly LoggerInterface $logger
    ) {
    }

    public function resolveCustomerState(int $customerId): string
    {
        if ($customerId <= 0) {
            return self::STATE_APPROVED;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $approvalStatusAttr = $customer->getCustomAttribute('b2b_approval_status');
            $approvalStatus = $approvalStatusAttr ? (string) $approvalStatusAttr->getValue() : '';

            if ($approvalStatus !== '' && $approvalStatus !== ApprovalStatus::STATUS_APPROVED) {
                return $approvalStatus;
            }

            if ($this->config->hidePriceForNoErp() && $this->getCustomerErpCode($customerId) === null) {
                return self::STATE_PENDING_ERP;
            }

            return self::STATE_APPROVED;
        } catch (\Exception $exception) {
            $this->logger->error('[B2B CheckoutAccessValidator] resolveCustomerState error: ' . $exception->getMessage(), [
                'customer_id' => $customerId,
                'exception' => $exception,
            ]);

            return self::STATE_APPROVED;
        }
    }

    private function getCustomerErpCode(int $customerId): ?int
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $attribute = $customer->getCustomAttribute('erp_code');
            $erpCode = ($attribute && $attribute->getValue()) ? $attribute->getValue() : null;

            if ($erpCode === null) {
                $erpCode = $this->syncLogResource->getErpCodeByMagentoId('customer', $customerId);
            }

            return ($erpCode !== null && is_numeric($erpCode)) ? (int) $erpCode : null;
        } catch (\Exception $exception) {
            $this->logger->error('[B2B CheckoutAccessValidator] getCustomerErpCode error: ' . $exception->getMessage(), [
                'customer_id' => $customerId,
                'exception' => $exception,
            ]);

            return null;
        }
    }
}

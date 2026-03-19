<?php
/**
 * Customer Approval Service
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class CustomerApproval implements CustomerApprovalInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var EventManager
     */
    private $eventManager;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        Config $config,
        ResourceConnection $resourceConnection,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        DateTime $dateTime,
        LoggerInterface $logger,
        EventManager $eventManager
    ) {
        $this->customerRepository = $customerRepository;
        $this->config = $config;
        $this->resourceConnection = $resourceConnection;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->eventManager = $eventManager;
    }

    /**
     * @inheritDoc
     */
    public function setCustomerPending(int $customerId): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $customer->setCustomAttribute('b2b_approval_status', ApprovalStatus::STATUS_PENDING);
            $this->customerRepository->save($customer);

            $this->logAction($customerId, 'registered', null, ApprovalStatus::STATUS_PENDING, null, null);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('B2B setCustomerPending error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function approveCustomer(int $customerId, ?int $adminUserId = null, ?string $comment = null): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $oldStatus = $this->getCustomerAttributeValue($customer, 'b2b_approval_status');

            $customer->setCustomAttribute('b2b_approval_status', ApprovalStatus::STATUS_APPROVED);
            $customer->setCustomAttribute('b2b_approved_at', $this->dateTime->gmtDate());

            // Atribuir grupo B2B padrão se configurado
            $defaultGroup = $this->config->getDefaultB2BGroupId();
            if ($defaultGroup > 0 && $customer->getGroupId() == 1) { // Se está no grupo General
                $customer->setGroupId($defaultGroup);
            }

            $this->customerRepository->save($customer);

            $this->logAction($customerId, 'approved', $oldStatus, ApprovalStatus::STATUS_APPROVED, $adminUserId, $comment);

            // Dispatch event for ERP integration
            $this->eventManager->dispatch('grupoawamotos_b2b_customer_approved', [
                'customer_id' => $customerId,
                'customer' => $customer,
                'new_group_id' => $customer->getGroupId(),
                'old_status' => $oldStatus,
                'admin_user_id' => $adminUserId,
            ]);

            // Enviar email de aprovação
            if ($this->config->sendApprovalEmail()) {
                $this->sendApprovalEmail($customerId);
            }

            $this->logger->info(sprintf('B2B: Cliente #%d aprovado', $customerId));

            return true;
        } catch (\Exception $e) {
            $this->logger->error('B2B approveCustomer error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function rejectCustomer(int $customerId, ?int $adminUserId = null, ?string $reason = null): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $oldStatus = $this->getCustomerAttributeValue($customer, 'b2b_approval_status');

            $customer->setCustomAttribute('b2b_approval_status', ApprovalStatus::STATUS_REJECTED);
            $this->customerRepository->save($customer);

            $this->logAction($customerId, 'rejected', $oldStatus, ApprovalStatus::STATUS_REJECTED, $adminUserId, $reason);

            $this->eventManager->dispatch('grupoawamotos_b2b_customer_rejected', [
                'customer_id' => $customerId,
                'customer' => $customer,
                'old_status' => $oldStatus,
                'reason' => $reason,
                'admin_user_id' => $adminUserId,
            ]);

            // Enviar email de rejeição
            $this->sendRejectionEmail($customerId, $reason);

            $this->logger->info(sprintf('B2B: Cliente #%d rejeitado. Motivo: %s', $customerId, $reason ?? 'N/A'));

            return true;
        } catch (\Exception $e) {
            $this->logger->error('B2B rejectCustomer error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function suspendCustomer(int $customerId, ?int $adminUserId = null, ?string $reason = null): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $oldStatus = $this->getCustomerAttributeValue($customer, 'b2b_approval_status');

            $customer->setCustomAttribute('b2b_approval_status', ApprovalStatus::STATUS_SUSPENDED);
            $this->customerRepository->save($customer);

            $this->logAction($customerId, 'suspended', $oldStatus, ApprovalStatus::STATUS_SUSPENDED, $adminUserId, $reason);

            $this->logger->info(sprintf('B2B: Cliente #%d suspenso. Motivo: %s', $customerId, $reason ?? 'N/A'));

            return true;
        } catch (\Exception $e) {
            $this->logger->error('B2B suspendCustomer error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function getApprovalStatus(int $customerId): ?string
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            return $this->getCustomerAttributeValue($customer, 'b2b_approval_status');
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function isApproved(int $customerId): bool
    {
        $status = $this->getApprovalStatus($customerId);

        // Se não tem status, considerar aprovado (compatibilidade com clientes antigos)
        if ($status === null) {
            return true;
        }

        return $status === ApprovalStatus::STATUS_APPROVED;
    }

    /**
     * @inheritDoc
     */
    public function notifyAdminNewCustomer(int $customerId): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $adminEmail = $this->config->getAdminEmail();

            if (empty($adminEmail)) {
                return false;
            }

            /** @var \Magento\Store\Model\Store $store */
            $store = $this->storeManager->getStore();

            $cnpj = $this->getCustomerAttributeValue($customer, 'b2b_cnpj') ?? 'N/A';
            $razaoSocial = $this->getCustomerAttributeValue($customer, 'b2b_razao_social') ?? 'N/A';
            $phone = $this->getCustomerAttributeValue($customer, 'b2b_phone') ?? 'N/A';

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('grupoawamotos_b2b_admin_new_customer')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'customer' => $customer,
                    'customer_name' => $customer->getFirstname() . ' ' . $customer->getLastname(),
                    'customer_email' => $customer->getEmail(),
                    'cnpj' => $cnpj,
                    'razao_social' => $razaoSocial,
                    'phone' => $phone,
                    'store_name' => $store->getName(),
                    'approval_url' => $store->getBaseUrl() . 'admin/customer/index',
                ])
                ->setFromByScope('general')
                ->addTo($adminEmail)
                ->getTransport();

            $transport->sendMessage();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('B2B notifyAdminNewCustomer error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function sendApprovalEmail(int $customerId): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            /** @var \Magento\Store\Model\Store $store */
            $store = $this->storeManager->getStore($customer->getStoreId());

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('grupoawamotos_b2b_customer_approved')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'customer' => $customer,
                    'customer_name' => $customer->getFirstname(),
                    'store_name' => $store->getName(),
                    'store_url' => $store->getBaseUrl(),
                    'login_url' => $store->getBaseUrl() . 'b2b/account/login',
                ])
                ->setFromByScope('general')
                ->addTo($customer->getEmail(), $customer->getFirstname() . ' ' . $customer->getLastname())
                ->getTransport();

            $transport->sendMessage();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('B2B sendApprovalEmail error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function sendRejectionEmail(int $customerId, ?string $reason = null): bool
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            /** @var \Magento\Store\Model\Store $store */
            $store = $this->storeManager->getStore($customer->getStoreId());

            $transport = $this->transportBuilder
                ->setTemplateIdentifier('grupoawamotos_b2b_customer_rejected')
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $store->getId(),
                ])
                ->setTemplateVars([
                    'customer' => $customer,
                    'customer_name' => $customer->getFirstname(),
                    'store_name' => $store->getName(),
                    'reason' => $reason ?? 'Não foi possível aprovar seu cadastro no momento.',
                    'contact_url' => $store->getBaseUrl() . 'contact',
                ])
                ->setFromByScope('general')
                ->addTo($customer->getEmail(), $customer->getFirstname() . ' ' . $customer->getLastname())
                ->getTransport();

            $transport->sendMessage();

            return true;
        } catch (\Exception $e) {
            $this->logger->error('B2B sendRejectionEmail error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log approval action
     *
     * @param int $customerId
     * @param string $action
     * @param string|null $oldStatus
     * @param string $newStatus
     * @param int|null $adminUserId
     * @param string|null $comment
     * @return void
     */
    private function logAction(
        int $customerId,
        string $action,
        ?string $oldStatus,
        string $newStatus,
        ?int $adminUserId,
        ?string $comment
    ): void {
        try {
            $connection = $this->resourceConnection->getConnection();
            $tableName = $this->resourceConnection->getTableName('grupoawamotos_b2b_customer_approval_log');

            $connection->insert($tableName, [
                'customer_id' => $customerId,
                'action' => $action,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'admin_user_id' => $adminUserId,
                'comment' => $comment,
                'created_at' => $this->dateTime->gmtDate(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('B2B logAction error: ' . $e->getMessage());
        }
    }

    /**
     * Get customer attribute value
     *
     * @param CustomerInterface $customer
     * @param string $attributeCode
     * @return string|null
     */
    private function getCustomerAttributeValue(CustomerInterface $customer, string $attributeCode): ?string
    {
        $attribute = $customer->getCustomAttribute($attributeCode);
        return $attribute ? (string) $attribute->getValue() : null;
    }
}

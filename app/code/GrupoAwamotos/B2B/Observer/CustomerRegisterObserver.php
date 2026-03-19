<?php
/**
 * Observer for new customer registration - set as pending
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\ErpIntegration;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;
use Psr\Log\LoggerInterface;

class CustomerRegisterObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var CustomerApprovalInterface
     */
    private $customerApproval;

    /**
     * @var ErpIntegration
     */
    private $erpIntegration;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Config $config,
        CustomerRepositoryInterface $customerRepository,
        CustomerApprovalInterface $customerApproval,
        ErpIntegration $erpIntegration,
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->customerRepository = $customerRepository;
        $this->customerApproval = $customerApproval;
        $this->erpIntegration = $erpIntegration;
        $this->messageManager = $messageManager;
        $this->logger = $logger;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isEnabled() || !$this->config->requireApproval()) {
            return;
        }

        try {
            /** @var \Magento\Customer\Model\Customer $customer */
            $customer = $observer->getEvent()->getCustomer();

            if (!$customer || !$customer->getId()) {
                return;
            }

            $customerId = (int) $customer->getId();
            $groupId = (int) $customer->getGroupId();
            $erpCustomerCode = $this->linkCustomerToErpIfExists($customerId);

            // Verificar se grupo tem aprovação automática
            $autoApproveGroups = $this->config->getAutoApproveGroups();

            if (in_array($groupId, $autoApproveGroups)) {
                // Aprovação automática
                $this->customerApproval->approveCustomer(
                    $customerId,
                    null,
                    'Aprovação automática pelo grupo de cliente'
                );
                return;
            }

            if ($erpCustomerCode !== null && $this->config->autoApproveIfFoundInErp()) {
                $this->customerApproval->approveCustomer(
                    $customerId,
                    null,
                    sprintf('Aprovação automática: cliente localizado no ERP (código %s)', $erpCustomerCode)
                );

                $this->messageManager->addSuccessMessage(
                    __('Seus dados foram localizados no ERP e sua conta foi aprovada automaticamente.')
                );

                $this->logger->info(
                    sprintf(
                        'B2B: Cliente #%d aprovado automaticamente após vínculo com ERP #%s',
                        $customerId,
                        $erpCustomerCode
                    )
                );

                return;
            }

            // Definir como pendente
            $this->customerApproval->setCustomerPending($customerId);

            // Notificar administrador
            if ($this->config->notifyAdmin()) {
                $this->customerApproval->notifyAdminNewCustomer($customerId);
            }

            // Adicionar mensagem para o cliente
            $pendingMessage = $this->config->getPendingMessage();
            if (!empty($pendingMessage)) {
                $this->messageManager->addSuccessMessage($pendingMessage);
            }

            $this->logger->info(
                sprintf(
                    'B2B: Novo cliente #%d registrado como pendente de aprovação',
                    $customerId
                )
            );

        } catch (\Exception $e) {
            $this->logger->error(
                'B2B CustomerRegisterObserver error: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }

    /**
     * Link customer to ERP when the CNPJ already exists there.
     */
    private function linkCustomerToErpIfExists(int $customerId): ?int
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $cnpj = $this->getCustomerCnpj($customer);

            if (empty($cnpj)) {
                return null;
            }

            $this->logger->info(sprintf(
                'B2B: Verificando ERP para cliente %s com CNPJ %s',
                $customer->getEmail(),
                $this->maskCnpj($cnpj)
            ));

            $erpCustomer = $this->erpIntegration->findErpCustomerByCnpj($cnpj);
            if (!$erpCustomer || empty($erpCustomer['CODIGO'])) {
                return null;
            }

            $erpCustomerCode = (int) $erpCustomer['CODIGO'];
            $this->erpIntegration->linkCustomerToErp($customerId, $erpCustomerCode);
            $this->erpIntegration->syncAddressesFromErp($customerId, $erpCustomerCode);
            $this->updateCustomerFromErp($customer, $erpCustomer);

            return $erpCustomerCode;
        } catch (\Exception $e) {
            $this->logger->error(
                'B2B CustomerRegisterObserver ERP link error: ' . $e->getMessage(),
                ['customer_id' => $customerId, 'exception' => $e]
            );

            return null;
        }
    }

    /**
     * Resolve customer CNPJ from B2B/BrazilCustomer attributes and only accept 14-digit documents.
     */
    private function getCustomerCnpj($customer): ?string
    {
        foreach ([
            $this->getCustomerAttributeValue($customer, 'b2b_cnpj'),
            $this->getCustomerAttributeValue($customer, 'cnpj'),
            (string) $customer->getTaxvat(),
        ] as $value) {
            $cnpj = $this->normalizeCnpj((string) $value);
            if ($cnpj !== null) {
                return $cnpj;
            }
        }

        return null;
    }

    private function getCustomerAttributeValue($customer, string $attributeCode): ?string
    {
        $attribute = $customer->getCustomAttribute($attributeCode);

        return $attribute ? (string) $attribute->getValue() : null;
    }

    private function normalizeCnpj(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) === 14 ? $digits : null;
    }

    /**
     * Update customer fields from ERP payload when they are still empty.
     */
    private function updateCustomerFromErp($customer, array $erpData): void
    {
        $updated = false;

        if (!empty($erpData['RAZAO_SOCIAL'])) {
            $razaoAttribute = $customer->getCustomAttribute('b2b_razao_social');
            if (!$razaoAttribute || empty($razaoAttribute->getValue())) {
                $customer->setCustomAttribute('b2b_razao_social', $erpData['RAZAO_SOCIAL']);
                $updated = true;
            }
        }

        if (!empty($erpData['INSCRICAO_ESTADUAL'])) {
            $ieAttribute = $customer->getCustomAttribute('b2b_inscricao_estadual');
            if (!$ieAttribute || empty($ieAttribute->getValue())) {
                $customer->setCustomAttribute('b2b_inscricao_estadual', $erpData['INSCRICAO_ESTADUAL']);
                $updated = true;
            }
        }

        if (!$updated) {
            return;
        }

        try {
            $this->customerRepository->save($customer);
        } catch (\Exception $e) {
            $this->logger->warning(
                'B2B CustomerRegisterObserver ERP customer update warning: ' . $e->getMessage(),
                ['customer_id' => $customer->getId(), 'exception' => $e]
            );
        }
    }

    /**
     * Mask CNPJ for logs.
     */
    private function maskCnpj(string $cnpj): string
    {
        $clean = (string) preg_replace('/\D/', '', $cnpj);
        if (strlen($clean) !== 14) {
            return '******';
        }

        return substr($clean, 0, 4) . '******' . substr($clean, -4);
    }
}

<?php
/**
 * Observer for new customer registration - set as pending
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Api\CustomerApprovalInterface;
use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
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
        ManagerInterface $messageManager,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->customerRepository = $customerRepository;
        $this->customerApproval = $customerApproval;
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
}

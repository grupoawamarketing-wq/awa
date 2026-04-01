<?php

/**
 * Observer for customer login - check approval status
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Helper\Config;
use GrupoAwamotos\B2B\Model\Customer\Attribute\Source\ApprovalStatus;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Message\ManagerInterface;

class CustomerLoginObserver implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    public function __construct(
        Config $config,
        ManagerInterface $messageManager,
        CustomerRepositoryInterface $customerRepository
    ) {
        $this->config = $config;
        $this->messageManager = $messageManager;
        $this->customerRepository = $customerRepository;
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var \Magento\Customer\Model\Customer $customer */
        $customer = $observer->getEvent()->getCustomer();

        if (!$customer || !$customer->getId()) {
            return;
        }

        // Usar repository para garantir que EAV custom attributes sejam carregados
        try {
            $customerData = $this->customerRepository->getById($customer->getId());
            $attr = $customerData->getCustomAttribute('b2b_approval_status');
            $approvalStatus = $attr ? $attr->getValue() : null;
        } catch (\Exception $e) {
            return;
        }

        // Se não tem status, considerar aprovado (compatibilidade)
        if (empty($approvalStatus)) {
            return;
        }

        $whatsAppUrl = 'https://wa.me/5516997367588';

        switch ($approvalStatus) {
            case ApprovalStatus::STATUS_PENDING:
                $this->messageManager->addNoticeMessage(
                    __('Sua conta está pendente de aprovação. Você pode navegar no site, mas não poderá realizar compras até que sua conta seja aprovada. Dúvidas? <a href="%1" target="_blank" rel="noopener">Fale conosco pelo WhatsApp</a>.', $whatsAppUrl)
                );
                break;

            case ApprovalStatus::STATUS_REJECTED:
                $this->messageManager->addWarningMessage(
                    __('Sua solicitação de cadastro foi recusada. <a href="%1" target="_blank" rel="noopener">Entre em contato pelo WhatsApp</a> para mais informações.', $whatsAppUrl)
                );
                break;

            case ApprovalStatus::STATUS_SUSPENDED:
                $this->messageManager->addWarningMessage(
                    __('Sua conta está temporariamente suspensa. <a href="%1" target="_blank" rel="noopener">Entre em contato pelo WhatsApp</a> para regularizar.', $whatsAppUrl)
                );
                break;

            case ApprovalStatus::STATUS_APPROVED:
                // Cliente aprovado - nada a fazer
                break;
        }
    }
}

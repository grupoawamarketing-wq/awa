<?php

declare(strict_types=1);

namespace GrupoAwamotos\BrazilCustomer\Plugin\Customer;

use Magento\Customer\Controller\Account\EditPost;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Persists Brazilian custom attributes when customer edits their account
 */
class SaveBrazilFieldsOnEditPlugin
{
    private const BRAZIL_ATTRIBUTES = [
        'person_type',
        'cpf',
        'rg',
        'cnpj',
        'ie',
        'company_name',
        'trade_name',
    ];

    private CustomerRepositoryInterface $customerRepository;
    private CustomerSession $customerSession;
    private RequestInterface $request;
    private LoggerInterface $logger;

    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        RequestInterface $request,
        LoggerInterface $logger
    ) {
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->request = $request;
        $this->logger = $logger;
    }

    /**
     * After account edit, persist Brazil fields
     */
    public function afterExecute(EditPost $subject, $result)
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        try {
            $customerId = $this->customerSession->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $updated = false;

            foreach (self::BRAZIL_ATTRIBUTES as $attributeCode) {
                $value = $this->request->getParam($attributeCode);
                if ($value !== null) {
                    $customer->setCustomAttribute($attributeCode, $value);
                    $updated = true;
                }
            }

            if ($updated) {
                // Sync CPF/CNPJ to taxvat for ERP compatibility
                $personType = $this->request->getParam('person_type', 'pf');
                if ($personType === 'pj') {
                    $cnpj = $this->request->getParam('cnpj');
                    if ($cnpj) {
                        $customer->setTaxvat(preg_replace('/[^0-9]/', '', $cnpj));
                    }
                } else {
                    $cpf = $this->request->getParam('cpf');
                    if ($cpf) {
                        $customer->setTaxvat(preg_replace('/[^0-9]/', '', $cpf));
                    }
                }

                $this->customerRepository->save($customer);
            }
        } catch (\Exception $e) {
            $this->logger->error('[BrazilCustomer] Error saving account edit fields: ' . $e->getMessage());
        }

        return $result;
    }
}

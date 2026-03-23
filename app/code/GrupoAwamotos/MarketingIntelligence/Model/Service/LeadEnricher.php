<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Model\Service;

use GrupoAwamotos\MarketingIntelligence\Api\Data\ProspectInterface;
use GrupoAwamotos\MarketingIntelligence\Api\ProspectRepositoryInterface;
use GrupoAwamotos\MarketingIntelligence\Model\ResourceModel\Prospect\CollectionFactory as ProspectCollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Enriches prospects by cross-referencing with existing Magento customers.
 */
class LeadEnricher
{
    public function __construct(
        private readonly ProspectCollectionFactory $prospectCollectionFactory,
        private readonly ProspectRepositoryInterface $prospectRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cross-reference all non-converted prospects against existing customers.
     *
     * @return int Number of prospects matched to existing customers
     */
    public function enrichAll(): int
    {
        $collection = $this->prospectCollectionFactory->create();
        $collection->addFieldToFilter('converted_customer_id', ['null' => true]);
        $collection->addFieldToFilter('prospect_status', ['neq' => 'converted']);
        $matched = 0;

        /** @var ProspectInterface $prospect */
        foreach ($collection as $prospect) {
            try {
                if ($this->matchProspect($prospect)) {
                    $matched++;
                }
            } catch (\Exception $e) {
                $this->logger->error(sprintf(
                    'LeadEnricher: error enriching prospect %d — %s',
                    $prospect->getProspectId(),
                    $e->getMessage()
                ));
            }
        }

        $this->logger->info(sprintf('LeadEnricher: %d prospects matched to customers.', $matched));
        return $matched;
    }

    private function matchProspect(ProspectInterface $prospect): bool
    {
        $cnpj = $prospect->getCnpj();
        if (empty($cnpj)) {
            return false;
        }

        $customerId = $this->findCustomerByCnpj($cnpj);
        if ($customerId === null && !empty($prospect->getEmail())) {
            $customerId = $this->findCustomerByEmail($prospect->getEmail());
        }

        if ($customerId === null) {
            return false;
        }

        $prospect->setConvertedCustomerId($customerId);
        $prospect->setProspectStatus('converted');
        $this->prospectRepository->save($prospect);

        $this->logger->info(sprintf(
            'LeadEnricher: prospect CNPJ %s matched to customer #%d.',
            $cnpj,
            $customerId
        ));

        return true;
    }

    private function findCustomerByCnpj(string $cnpj): ?int
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('cnpj', $cnpj)
            ->setPageSize(1)
            ->create();

        $results = $this->customerRepository->getList($searchCriteria);
        $items = $results->getItems();

        if (empty($items)) {
            return null;
        }

        return (int)reset($items)->getId();
    }

    private function findCustomerByEmail(string $email): ?int
    {
        try {
            $customer = $this->customerRepository->get($email);
            return (int)$customer->getId();
        } catch (\Exception) {
            return null;
        }
    }
}

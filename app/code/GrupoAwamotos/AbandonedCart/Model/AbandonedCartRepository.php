<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Model;

use GrupoAwamotos\AbandonedCart\Api\AbandonedCartRepositoryInterface;
use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterface;
use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterfaceFactory;
use GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart as ResourceModel;
use GrupoAwamotos\AbandonedCart\Model\ResourceModel\AbandonedCart\CollectionFactory;
use GrupoAwamotos\AbandonedCart\Helper\Data as Helper;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class AbandonedCartRepository implements AbandonedCartRepositoryInterface
{
    private ResourceModel $resourceModel;
    private AbandonedCartInterfaceFactory $abandonedCartFactory;
    private CollectionFactory $collectionFactory;
    private SearchResultsInterfaceFactory $searchResultsFactory;
    private CollectionProcessorInterface $collectionProcessor;
    private Helper $helper;
    private LoggerInterface $logger;

    public function __construct(
        ResourceModel $resourceModel,
        AbandonedCartInterfaceFactory $abandonedCartFactory,
        CollectionFactory $collectionFactory,
        SearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor,
        Helper $helper,
        LoggerInterface $logger
    ) {
        $this->resourceModel = $resourceModel;
        $this->abandonedCartFactory = $abandonedCartFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->helper = $helper;
        $this->logger = $logger;
    }

    public function save(AbandonedCartInterface $abandonedCart): AbandonedCartInterface
    {
        try {
            $this->resourceModel->save($abandonedCart);
        } catch (\Exception $e) {
            $this->logger->error('[AbandonedCart] Save error: ' . $e->getMessage());
            throw new CouldNotSaveException(__('Could not save abandoned cart: %1', $e->getMessage()));
        }
        return $abandonedCart;
    }

    public function getById(int $id): AbandonedCartInterface
    {
        $abandonedCart = $this->abandonedCartFactory->create();
        $this->resourceModel->load($abandonedCart, $id);

        if (!$abandonedCart->getEntityId()) {
            throw new NoSuchEntityException(__('Abandoned cart with ID "%1" does not exist.', $id));
        }

        return $abandonedCart;
    }

    public function getByQuoteId(int $quoteId): ?AbandonedCartInterface
    {
        $abandonedCart = $this->abandonedCartFactory->create();
        $this->resourceModel->load($abandonedCart, $quoteId, 'quote_id');

        if (!$abandonedCart->getEntityId()) {
            return null;
        }

        return $abandonedCart;
    }

    public function delete(AbandonedCartInterface $abandonedCart): bool
    {
        try {
            $this->resourceModel->delete($abandonedCart);
        } catch (\Exception $e) {
            $this->logger->error('[AbandonedCart] Delete error: ' . $e->getMessage());
            throw new CouldNotDeleteException(__('Could not delete abandoned cart: %1', $e->getMessage()));
        }
        return true;
    }

    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    public function getPendingForEmail(int $emailNumber, int $limit = 100): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addPendingFilter();
        $collection->addEmailNotSentFilter($emailNumber);

        $delayHours = $this->helper->getEmailDelay($emailNumber);
        $collection->addTimeFilter($emailNumber, $delayHours);

        // Se email > 1, garantir que os anteriores foram enviados
        if ($emailNumber > 1) {
            for ($i = 1; $i < $emailNumber; $i++) {
                $collection->addFieldToFilter("email_{$i}_sent", 1);
            }
        }

        $collection->setPageSize($limit);

        return $collection->getItems();
    }

    public function markAsRecovered(int $quoteId): bool
    {
        $abandonedCart = $this->getByQuoteId($quoteId);

        if ($abandonedCart && !$abandonedCart->isRecovered()) {
            $abandonedCart->setRecovered(true);
            $abandonedCart->setRecoveredAt(date('Y-m-d H:i:s'));
            $abandonedCart->setStatus(AbandonedCartInterface::STATUS_RECOVERED);
            $this->save($abandonedCart);

            $this->logger->info(sprintf(
                '[AbandonedCart] Cart recovered: quote_id=%d, email=%s',
                $quoteId,
                $abandonedCart->getCustomerEmail()
            ));

            return true;
        }

        return false;
    }
}

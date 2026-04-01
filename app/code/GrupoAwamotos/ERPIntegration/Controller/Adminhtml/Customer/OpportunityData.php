<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use GrupoAwamotos\ERPIntegration\Model\Opportunity\Classifier;
use GrupoAwamotos\ERPIntegration\Model\ProductSuggestion;
use Psr\Log\LoggerInterface;

/**
 * Admin AJAX Controller - Opportunity Classification Data
 */
class OpportunityData extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_ERPIntegration::customers';

    private JsonFactory $jsonFactory;
    private Classifier $classifier;
    private ProductSuggestion $productSuggestion;
    private LoggerInterface $logger;

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        Classifier $classifier,
        ProductSuggestion $productSuggestion,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->classifier = $classifier;
        $this->productSuggestion = $productSuggestion;
        $this->logger = $logger;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        $customerCode = (int) $this->getRequest()->getParam('id');
        if (!$customerCode) {
            return $result->setData([
                'success' => false,
                'message' => 'Código do cliente não informado.'
            ]);
        }

        try {
            $opportunityType = $this->getRequest()->getParam('opportunity_type', 'all');
            if (!in_array($opportunityType, array_keys(Classifier::TYPES), true)) {
                $opportunityType = 'all';
            }

            $page = max((int) $this->getRequest()->getParam('page', 1), 1);
            $limit = min(max((int) $this->getRequest()->getParam('limit', 20), 1), 100);
            $offset = ($page - 1) * $limit;

            $filters = [
                'sort_by' => $this->getRequest()->getParam('sort_by', 'days_since_last'),
                'sort_dir' => strtoupper($this->getRequest()->getParam('sort_dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
                'min_price' => (float) $this->getRequest()->getParam('min_price', 0),
                'max_price' => (float) $this->getRequest()->getParam('max_price', 0),
                'limit' => $limit,
                'offset' => $offset,
            ];

            $data = $this->classifier->classify($customerCode, $opportunityType, $filters);

            $enrichedItems = [];
            if (!empty($data['items'])) {
                $mapped = array_map(function ($item) {
                    $item['codigo_material'] = $item['sku'];
                    $item['descricao'] = $item['name'];
                    return $item;
                }, $data['items']);

                $enrichedItems = $this->productSuggestion->enrichWithMagentoData($mapped);
            }

            $totalCount = $data['total_count'];
            $totalPages = $limit > 0 ? (int) ceil($totalCount / $limit) : 1;

            return $result->setData([
                'success' => true,
                'items' => $enrichedItems,
                'total_count' => $totalCount,
                'page' => $page,
                'total_pages' => $totalPages,
                'opportunity_type' => $opportunityType,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[ERP Admin] Opportunity data error: ' . $e->getMessage());
            return $result->setData([
                'success' => false,
                'message' => 'Erro ao carregar oportunidades: ' . $e->getMessage()
            ]);
        }
    }
}

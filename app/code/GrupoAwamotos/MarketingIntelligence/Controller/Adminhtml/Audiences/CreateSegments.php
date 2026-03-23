<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Controller\Adminhtml\Audiences;

use GrupoAwamotos\MarketingIntelligence\Model\Service\AudienceSyncer;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * POST action: creates the 4 B2B pre-defined audience segments on Meta.
 */
class CreateSegments extends Action
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_MarketingIntelligence::audiences';

    public function __construct(
        Context $context,
        private readonly AudienceSyncer $audienceSyncer,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $created = $this->audienceSyncer->createB2BSegments();
            return $result->setData([
                'success' => true,
                'segments_created' => count($created),
                'segments' => $created,
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

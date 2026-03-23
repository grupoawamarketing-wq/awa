<?php

declare(strict_types=1);

namespace GrupoAwamotos\MarketingIntelligence\Controller\Adminhtml\Config;

use GrupoAwamotos\MarketingIntelligence\Model\Service\MetaConfigValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Admin POST endpoint: validates Meta API access and returns JSON with capabilities.
 */
class ValidateAccess extends Action
{
    public const ADMIN_RESOURCE = 'GrupoAwamotos_MarketingIntelligence::config';

    public function __construct(
        Context $context,
        private readonly MetaConfigValidator $metaConfigValidator,
        private readonly JsonFactory $jsonFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $validation = $this->metaConfigValidator->validate();
            return $result->setData($validation);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'errors' => [$e->getMessage()],
            ]);
        }
    }
}

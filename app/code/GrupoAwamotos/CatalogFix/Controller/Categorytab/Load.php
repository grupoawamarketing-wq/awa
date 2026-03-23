<?php
declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Controller\Categorytab;

use GrupoAwamotos\CatalogFix\Block\Categorytab\AjaxProducts;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\LayoutFactory;
use Psr\Log\LoggerInterface;

class Load implements HttpGetActionInterface
{
    private const ALLOWED_TEMPLATES = ['grid', 'grid-original'];
    private const MAX_LIMIT = 20;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly LayoutFactory $layoutFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        $categoryId = (int) $this->request->getParam('category_id');
        $limit = min(max(1, (int) $this->request->getParam('limit', 8)), self::MAX_LIMIT);
        $template = (string) $this->request->getParam('template', 'grid');
        $rows = max(1, (int) $this->request->getParam('rows', 1));

        if ($categoryId < 1) {
            return $this->jsonFactory->create()->setData(['html' => '']);
        }

        if (!in_array($template, self::ALLOWED_TEMPLATES, true)) {
            $template = 'grid';
        }

        try {
            $layout = $this->layoutFactory->create();
            /** @var AjaxProducts $block */
            $block = $layout->createBlock(AjaxProducts::class);
            $block->setData('category_id', $categoryId);
            $block->setData('limit', $limit);
            $block->setData('rows', $rows);
            $block->setTemplate('GrupoAwamotos_CatalogFix::categorytab/ajax-' . $template . '.phtml');

            $html = $block->toHtml();
        } catch (\Exception $e) {
            $this->logger->error('CatalogFix lazy tab error', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            $html = '';
        }

        return $this->jsonFactory->create()->setData(['html' => $html]);
    }
}

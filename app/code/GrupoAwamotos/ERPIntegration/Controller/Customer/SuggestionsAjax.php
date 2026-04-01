<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Controller\Customer;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Customer\Model\Session as CustomerSession;
use GrupoAwamotos\ERPIntegration\Block\Customer\Suggestions;

/**
 * AJAX endpoint for loading suggestions
 * Returns rendered HTML for the suggestions widget
 */
class SuggestionsAjax implements HttpGetActionInterface
{
    private PageFactory $pageFactory;
    private RawFactory $rawFactory;
    private CustomerSession $customerSession;

    public function __construct(
        PageFactory $pageFactory,
        RawFactory $rawFactory,
        CustomerSession $customerSession
    ) {
        $this->pageFactory = $pageFactory;
        $this->rawFactory = $rawFactory;
        $this->customerSession = $customerSession;
    }

    public function execute()
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/html');

        if (!$this->customerSession->isLoggedIn()) {
            $result->setContents('');
            return $result;
        }

        try {
            $page = $this->pageFactory->create();

            /** @var Suggestions $block */
            $block = $page->getLayout()->createBlock(
                Suggestions::class,
                'erp.cart.suggestions.ajax'
            );
            $block->setTemplate('GrupoAwamotos_ERPIntegration::customer/suggestions-cart-content.phtml');

            $html = $block->toHtml();
            $result->setContents($html);
        } catch (\Exception $e) {
            $result->setContents('<div class="erp-error">Erro ao carregar sugestões</div>');
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace GrupoAwamotos\FakePurchase\Block;

use GrupoAwamotos\FakePurchase\ViewModel\FakePurchase;
use Magento\Framework\View\Element\Template;

class Notification extends Template
{
    /**
     * Add stylesheet only when the feature block can actually render.
     *
     * @return $this
     */
    protected function _prepareLayout(): static
    {
        $viewModel = $this->getData('view_model');

        if ($viewModel instanceof FakePurchase && $viewModel->isEnabled()) {
            $this->pageConfig->addPageAsset('GrupoAwamotos_FakePurchase::css/fake-purchase.css');
        }

        parent::_prepareLayout();

        return $this;
    }
}

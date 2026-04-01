<?php

/**
 * Shopping List Index Block
 */

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Block\Customer\ShoppingList;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class ListBlock extends Template
{
    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get create list URL
     *
     * @return string
     */
    public function getCreateUrl(): string
    {
        return $this->getUrl('b2b/shoppinglist/create');
    }

    /**
     * Get view list URL
     *
     * @param int $listId
     * @return string
     */
    public function getViewUrl(int $listId): string
    {
        return $this->getUrl('b2b/shoppinglist/view', ['id' => $listId]);
    }

    /**
     * Get delete list URL
     *
     * @param int $listId
     * @return string
     */
    public function getDeleteUrl(int $listId): string
    {
        return $this->getUrl('b2b/shoppinglist/delete', ['id' => $listId]);
    }

    /**
     * Get add to cart URL
     *
     * @param int $listId
     * @return string
     */
    public function getAddToCartUrl(int $listId): string
    {
        return $this->getUrl('b2b/shoppinglist/addtocart', ['id' => $listId]);
    }
}

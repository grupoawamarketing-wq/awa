<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin\Checkout;

use GrupoAwamotos\B2B\Helper\Config;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Quote\Api\Data\CartItemInterface;

class BlockGuestCartItemSavePlugin
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @throws CouldNotSaveException
     */
    public function beforeSave(object $subject, CartItemInterface $cartItem): array
    {
        if (!$this->config->isEnabled()) {
            return [$cartItem];
        }

        if (!$this->config->hideAddToCartForGuests()) {
            return [$cartItem];
        }

        throw new CouldNotSaveException(
            __('Faça login no portal B2B ou cadastre sua empresa para adicionar produtos ao carrinho.')
        );
    }
}

<?php
/**
 * Plugin to block Rokanthemes OnePageCheckout for non-approved customers
 * and enforce minimum order amount.
 */
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Plugin;

use GrupoAwamotos\B2B\Api\PriceVisibilityInterface;
use GrupoAwamotos\B2B\Helper\Config;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Rokanthemes\OnePageCheckout\Controller\Index\Index;

class BlockExpressCheckoutPlugin
{
    /**
     * @var PriceVisibilityInterface
     */
    private $priceVisibility;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var RedirectFactory
     */
    private $redirectFactory;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Http
     */
    private $request;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    public function __construct(
        PriceVisibilityInterface $priceVisibility,
        Config $config,
        RedirectFactory $redirectFactory,
        ManagerInterface $messageManager,
        CustomerSession $customerSession,
        CheckoutSession $checkoutSession,
        Http $request,
        UrlInterface $urlBuilder
    ) {
        $this->priceVisibility = $priceVisibility;
        $this->config = $config;
        $this->redirectFactory = $redirectFactory;
        $this->messageManager = $messageManager;
        $this->customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->request = $request;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Around execute - block express checkout if not approved or below minimum
     *
     * @param Index $subject
     * @param callable $proceed
     * @return \Magento\Framework\Controller\Result\Redirect|mixed
     */
    public function aroundExecute(Index $subject, callable $proceed)
    {
        if (!$this->config->isEnabled()) {
            return $proceed();
        }

        // No modo mixed, visitantes podem fazer checkout normalmente
        if (!$this->customerSession->isLoggedIn() && !$this->config->isStrictB2B()) {
            return $proceed();
        }

        // Verificar se cliente está aprovado
        if (!$this->priceVisibility->canAddToCart()) {
            $redirect = $this->redirectFactory->create();

            if (!$this->customerSession->isLoggedIn()) {
                $this->messageManager->addNoticeMessage(
                    __('Faça login no portal B2B para finalizar sua compra.')
                );
                return $redirect->setPath('b2b/account/login', $this->getLoginRedirectParams());
            }

            if ($this->priceVisibility->isApprovedPendingErp()) {
                $this->messageManager->addWarningMessage(
                    __('Sua tabela de preços ainda está sendo vinculada ao ERP. Finalize esse ajuste com o time comercial antes de concluir pedidos.')
                );
            } else {
                $this->messageManager->addWarningMessage(
                    __('Sua conta está pendente de aprovação. Você não pode finalizar compras até que sua conta seja aprovada.')
                );
            }

            return $redirect->setPath('b2b/account/dashboard');
        }

        // Verificar valor mínimo do pedido
        if ($this->config->isMinQtyEnabled()) {
            $minAmount = $this->config->getMinOrderAmount();
            if ($minAmount > 0) {
                try {
                    $quote = $this->checkoutSession->getQuote();
                    $subtotal = (float) $quote->getBaseSubtotal();

                    if ($subtotal < $minAmount) {
                        $this->messageManager->addWarningMessage(
                            __($this->config->getMinOrderMessage())
                        );
                        $redirect = $this->redirectFactory->create();
                        return $redirect->setPath('checkout/cart');
                    }
                } catch (\Exception $e) {
                    // Allow checkout to proceed if we can't check the quote
                }
            }
        }

        return $proceed();
    }

    /**
     * Preserve the storefront route when OnePageCheckout is interrupted by strict B2B login.
     *
     * @return array<string, string>
     */
    private function getLoginRedirectParams(): array
    {
        $referer = (string) $this->request->getServer('HTTP_REFERER');
        if ($referer === '') {
            return [];
        }

        $baseUrl = $this->urlBuilder->getBaseUrl();
        if ($baseUrl === '' || !str_starts_with($referer, $baseUrl)) {
            return [];
        }

        return ['referer' => base64_encode($referer)];
    }
}

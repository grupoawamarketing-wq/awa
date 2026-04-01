<?php

declare(strict_types=1);

namespace GrupoAwamotos\AbandonedCart\Test\Unit\Model;

use GrupoAwamotos\AbandonedCart\Api\CouponGeneratorInterface;
use GrupoAwamotos\AbandonedCart\Api\Data\AbandonedCartInterface;
use GrupoAwamotos\AbandonedCart\Helper\Data as Helper;
use GrupoAwamotos\AbandonedCart\Model\EmailSender;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\App\State as AppState;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \GrupoAwamotos\AbandonedCart\Model\EmailSender
 */
class EmailSenderTest extends TestCase
{
    private EmailSender $sender;
    private Helper&MockObject $helper;
    private TransportBuilder&MockObject $transportBuilder;
    private StateInterface&MockObject $inlineTranslation;
    private StoreManagerInterface&MockObject $storeManager;
    private CartRepositoryInterface&MockObject $cartRepository;
    private ProductRepositoryInterface&MockObject $productRepository;
    private CouponGeneratorInterface&MockObject $couponGenerator;
    private PricingHelper&MockObject $pricingHelper;
    private ImageHelper&MockObject $imageHelper;
    private AppState&MockObject $appState;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->helper = $this->createMock(Helper::class);
        $this->transportBuilder = $this->createMock(TransportBuilder::class);
        $this->inlineTranslation = $this->createMock(StateInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->couponGenerator = $this->createMock(CouponGeneratorInterface::class);
        $this->pricingHelper = $this->createMock(PricingHelper::class);
        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->appState = $this->createMock(AppState::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sender = new EmailSender(
            $this->helper,
            $this->transportBuilder,
            $this->inlineTranslation,
            $this->storeManager,
            $this->cartRepository,
            $this->productRepository,
            $this->couponGenerator,
            $this->pricingHelper,
            $this->logger,
            $this->imageHelper,
            $this->appState
        );
    }

    // ====================================================================
    // sendEmail — disabled module
    // ====================================================================

    public function testSendEmailReturnsFalseWhenModuleDisabled(): void
    {
        $cart = $this->createAbandonedCartMock(1);
        $this->helper->method('isEnabled')->with(1)->willReturn(false);

        $this->assertFalse($this->sender->sendEmail($cart, 1));
    }

    // ====================================================================
    // sendEmail — email number disabled
    // ====================================================================

    public function testSendEmailReturnsFalseWhenEmailNumberDisabled(): void
    {
        $cart = $this->createAbandonedCartMock(1);
        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('isEmailEnabled')->with(2, 1)->willReturn(false);

        $this->assertFalse($this->sender->sendEmail($cart, 2));
    }

    // ====================================================================
    // sendEmail — success without coupon
    // ====================================================================

    public function testSendEmailSuccessWithoutCoupon(): void
    {
        $cart = $this->createAbandonedCartMock(1);
        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('isEmailEnabled')->willReturn(true);
        $this->helper->method('getEmailTemplate')->willReturn('abandoned_cart_email_1');
        $this->helper->method('isCouponEnabled')->willReturn(false);

        $this->appState->method('getAreaCode')->willReturn('frontend');

        $store = $this->createMock(Store::class);
        $store->method('getUrl')->willReturn('https://awamotos.com.br/checkout/cart');
        $this->storeManager->method('getStore')->willReturn($store);

        $this->pricingHelper->method('currency')->willReturn('R$ 299,90');

        // Mock empty cart items (quote without items)
        $quote = $this->createMock(\Magento\Quote\Model\Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([]);
        $this->cartRepository->method('get')->willReturn($quote);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertTrue($this->sender->sendEmail($cart, 1));
    }

    // ====================================================================
    // sendEmail — success with coupon
    // ====================================================================

    public function testSendEmailSuccessWithCoupon(): void
    {
        $cart = $this->createAbandonedCartMock(1);
        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('isEmailEnabled')->willReturn(true);
        $this->helper->method('getEmailTemplate')->willReturn('abandoned_cart_email_2');
        $this->helper->method('isCouponEnabled')->willReturn(true);
        $this->helper->method('getCouponDiscount')->willReturn(10.0);
        $this->helper->method('getCouponType')->willReturn('percent');

        $this->appState->method('getAreaCode')->willReturn('frontend');

        $this->couponGenerator->method('generate')
            ->with(10.0, 'percent', 100, 'cliente@test.com')
            ->willReturn('VOLTA123456100');

        $store = $this->createMock(Store::class);
        $store->method('getUrl')->willReturn('https://awamotos.com.br/checkout/cart');
        $this->storeManager->method('getStore')->willReturn($store);

        $this->pricingHelper->method('currency')->willReturn('R$ 500,00');

        $quote = $this->createMock(\Magento\Quote\Model\Quote::class);
        $quote->method('getAllVisibleItems')->willReturn([]);
        $this->cartRepository->method('get')->willReturn($quote);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertTrue($this->sender->sendEmail($cart, 2));
    }

    // ====================================================================
    // sendEmail — exception returns false
    // ====================================================================

    public function testSendEmailReturnsFalseOnException(): void
    {
        $cart = $this->createAbandonedCartMock(1);
        $this->helper->method('isEnabled')->willReturn(true);
        $this->helper->method('isEmailEnabled')->willReturn(true);
        $this->helper->method('getEmailTemplate')->willReturn('abandoned_cart_email_1');
        $this->helper->method('isCouponEnabled')->willReturn(false);

        $this->appState->method('getAreaCode')->willReturn('frontend');

        $store = $this->createMock(Store::class);
        $store->method('getUrl')->willReturn('https://awamotos.com.br/checkout/cart');
        $this->storeManager->method('getStore')->willReturn($store);

        $this->pricingHelper->method('currency')->willReturn('R$ 100,00');

        $this->cartRepository->method('get')
            ->willThrowException(new \Exception('Quote not found'));

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();

        $transport = $this->createMock(TransportInterface::class);
        $transport->method('sendMessage')->willThrowException(new \Exception('SMTP error'));
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertFalse($this->sender->sendEmail($cart, 1));
    }

    // ====================================================================
    // sendTestEmail — success
    // ====================================================================

    public function testSendTestEmailReturnsTrue(): void
    {
        $this->appState->method('getAreaCode')->willReturn('frontend');

        $store = $this->createMock(Store::class);
        $store->method('getUrl')->willReturn('https://awamotos.com.br/checkout/cart');
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);

        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('sendMessage');

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertTrue($this->sender->sendTestEmail('test@test.com', 1));
    }

    // ====================================================================
    // sendTestEmail — exception
    // ====================================================================

    public function testSendTestEmailReturnsFalseOnException(): void
    {
        $this->appState->method('getAreaCode')
            ->willThrowException(new \Magento\Framework\Exception\LocalizedException(
                new \Magento\Framework\Phrase('Area code not set')
            ));

        $store = $this->createMock(Store::class);
        $store->method('getUrl')->willReturn('https://awamotos.com.br/checkout/cart');
        $store->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->transportBuilder->method('setTemplateIdentifier')->willReturnSelf();
        $this->transportBuilder->method('setTemplateOptions')->willReturnSelf();
        $this->transportBuilder->method('setTemplateVars')->willReturnSelf();
        $this->transportBuilder->method('setFromByScope')->willReturnSelf();
        $this->transportBuilder->method('addTo')->willReturnSelf();

        $transport = $this->createMock(TransportInterface::class);
        $transport->method('sendMessage')->willThrowException(new \Exception('SMTP error'));
        $this->transportBuilder->method('getTransport')->willReturn($transport);

        $this->assertFalse($this->sender->sendTestEmail('test@test.com', 1));
    }

    // ====================================================================
    // Helpers
    // ====================================================================

    private function createAbandonedCartMock(int $storeId): AbandonedCartInterface&MockObject
    {
        $cart = $this->createMock(AbandonedCartInterface::class);
        $cart->method('getStoreId')->willReturn($storeId);
        $cart->method('getQuoteId')->willReturn(100);
        $cart->method('getCustomerEmail')->willReturn('cliente@test.com');
        $cart->method('getCustomerName')->willReturn('João Silva');
        $cart->method('getCartValue')->willReturn(299.90);
        $cart->method('getItemsCount')->willReturn(3);
        return $cart;
    }
}

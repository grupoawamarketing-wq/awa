<?php
declare(strict_types=1);

namespace GrupoAwamotos\B2B\Observer;

use GrupoAwamotos\B2B\Helper\CnpjValidator;
use GrupoAwamotos\B2B\Helper\Data as B2BHelper;
use GrupoAwamotos\ERPIntegration\Helper\Data as ErpHelper;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class ValidatePullOrderCnpj implements ObserverInterface
{
    private ErpHelper $erpHelper;
    private B2BHelper $b2bHelper;
    private CustomerRepositoryInterface $customerRepository;
    private CnpjValidator $cnpjValidator;
    private LoggerInterface $logger;

    public function __construct(
        ErpHelper $erpHelper,
        B2BHelper $b2bHelper,
        CustomerRepositoryInterface $customerRepository,
        CnpjValidator $cnpjValidator,
        LoggerInterface $logger
    ) {
        $this->erpHelper = $erpHelper;
        $this->b2bHelper = $b2bHelper;
        $this->customerRepository = $customerRepository;
        $this->cnpjValidator = $cnpjValidator;
        $this->logger = $logger;
    }

    /**
     * Bloqueia a criação de pedidos B2B sem CNPJ válido quando o ERP opera em modo pull.
     * Também carimba o CNPJ normalizado no order para evitar fallback futuro para CPF.
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        if (!$this->erpHelper->isOrderSyncEnabled() || $this->erpHelper->sendOrderOnPlace()) {
            return;
        }

        $quote = $observer->getEvent()->getQuote();
        $order = $observer->getEvent()->getOrder();

        if (!$quote instanceof Quote || !$order instanceof Order) {
            return;
        }

        $customerId = (int) ($order->getCustomerId() ?: $quote->getCustomerId());
        if ($customerId <= 0 || !$this->b2bHelper->isB2BCustomerById($customerId)) {
            return;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $cnpj = $this->extractCnpj($quote, $order, $customer);

            if ($cnpj === '') {
                throw new LocalizedException(
                    __('Não foi possível finalizar o pedido B2B para integração ERP: cliente sem CNPJ válido. Atualize o cadastro da empresa antes de concluir a compra.')
                );
            }

            if (!$this->cnpjValidator->validateLocal($cnpj)) {
                throw new LocalizedException(
                    __('Não foi possível finalizar o pedido B2B para integração ERP: o CNPJ informado é inválido. Revise o cadastro da empresa antes de concluir a compra.')
                );
            }

            $formattedCnpj = $this->cnpjValidator->format($cnpj);
            $order->setData('b2b_cnpj', $formattedCnpj);
            $order->setCustomerTaxvat($formattedCnpj);

            $billingAddress = $order->getBillingAddress();
            if ($billingAddress) {
                $billingAddress->setVatId($formattedCnpj);
            }

            $this->logger->info('[B2B] CNPJ validado para pedido ERP pull', [
                'customer_id' => $customerId,
                'quote_id' => (int) $quote->getId(),
                'order_increment_id' => $order->getIncrementId(),
            ]);
        } catch (LocalizedException $exception) {
            $this->logger->warning('[B2B] Pedido bloqueado por ausência de CNPJ válido no modo pull', [
                'customer_id' => $customerId,
                'quote_id' => (int) $quote->getId(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        } catch (\Exception $exception) {
            $this->logger->error('[B2B] Falha ao validar CNPJ do pedido ERP pull: ' . $exception->getMessage(), [
                'customer_id' => $customerId,
                'quote_id' => (int) $quote->getId(),
            ]);

            throw new LocalizedException(
                __('Não foi possível validar o CNPJ corporativo para integração ERP neste momento. Tente novamente ou revise o cadastro da empresa.')
            );
        }
    }

    private function extractCnpj(Quote $quote, Order $order, CustomerInterface $customer): string
    {
        foreach ($this->getCandidateValues($quote, $order, $customer) as $value) {
            $cnpj = $this->normalizeCnpj($value);
            if ($cnpj !== '') {
                return $cnpj;
            }
        }

        return '';
    }

    /**
     * @return string[]
     */
    private function getCandidateValues(Quote $quote, Order $order, CustomerInterface $customer): array
    {
        $customerB2BCnpj = $customer->getCustomAttribute('b2b_cnpj');
        $quoteBillingAddress = $quote->getBillingAddress();
        $orderBillingAddress = $order->getBillingAddress();

        return [
            (string) $order->getData('b2b_cnpj'),
            (string) $order->getCustomerTaxvat(),
            (string) $quote->getData('b2b_cnpj'),
            (string) $quote->getCustomerTaxvat(),
            (string) ($orderBillingAddress ? $orderBillingAddress->getVatId() : ''),
            (string) ($quoteBillingAddress ? $quoteBillingAddress->getVatId() : ''),
            (string) ($customerB2BCnpj ? $customerB2BCnpj->getValue() : ''),
            (string) $customer->getTaxvat(),
        ];
    }

    private function normalizeCnpj(string $value): string
    {
        $digits = $this->cnpjValidator->clean($value);

        return strlen($digits) === 14 ? $digits : '';
    }
}

<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterface;
use GrupoAwamotos\ERPIntegration\Api\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * Block to display ERP SECTRA data in admin order view.
 * Shows customer ERP code + commercial conditions for manual SECTRA entry.
 */
class ErpInfo extends Template
{
    private Registry $registry;
    private ConnectionInterface $erpConnection;
    private LoggerInterface $logger;
    private ?array $erpCustomerData = null;

    public function __construct(
        Context $context,
        Registry $registry,
        ConnectionInterface $erpConnection,
        LoggerInterface $logger,
        array $data = []
    ) {
        $this->registry = $registry;
        $this->erpConnection = $erpConnection;
        $this->logger = $logger;
        parent::__construct($context, $data);
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->registry->registry('current_order');
    }

    public function getCustomerErpCode(): ?string
    {
        $order = $this->getOrder();
        return $order ? ($order->getData('customer_erp_code') ?: null) : null;
    }

    public function getErpCustomerData(): array
    {
        if ($this->erpCustomerData !== null) {
            return $this->erpCustomerData;
        }

        $erpCode = $this->getCustomerErpCode();
        if (!$erpCode) {
            $this->erpCustomerData = [];
            return [];
        }

        try {
            $sql = "SELECT f.CODIGO, f.RAZAO, f.FANTASIA, f.CGC,
                           f.CONDPAGTO, f.FATORPRECO, f.TRANSPPREF, f.VENDPREF,
                           f.TPFATOR, f.PERCFATOR,
                           tp.RAZAO AS TRANSP_NOME,
                           cp.DESCRICAO AS CONDPAGTO_DESC
                    FROM FN_FORNECEDORES f
                    LEFT JOIN FN_FORNECEDORES tp ON tp.CODIGO = f.TRANSPPREF AND tp.CKTRANSPORTADOR = 'S'
                    LEFT JOIN VE_CONDPAGTO cp ON cp.CODIGO = f.CONDPAGTO
                    WHERE f.CODIGO = :code AND f.CKCLIENTE = 'S'";

            $result = $this->erpConnection->fetchOne($sql, [':code' => (int) $erpCode]);
            $this->erpCustomerData = $result ?: [];
        } catch (\Exception $e) {
            $this->logger->warning('[ERP Admin] Failed to fetch ERP data: ' . $e->getMessage());
            $this->erpCustomerData = [];
        }

        return $this->erpCustomerData;
    }

    public function hasErpCode(): bool
    {
        return $this->getCustomerErpCode() !== null;
    }
}

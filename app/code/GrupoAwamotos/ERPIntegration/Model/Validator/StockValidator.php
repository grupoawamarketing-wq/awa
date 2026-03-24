<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Validator;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Psr\Log\LoggerInterface;

/**
 * Stock Data Validator
 *
 * Validates stock data from ERP before updating Magento inventory.
 * Includes anomaly detection for suspicious stock changes.
 */
class StockValidator
{
    /**
     * Maximum stock quantity allowed
     */
    private const MAX_QUANTITY = 999999.99;

    /**
     * Minimum stock quantity (can be negative for backorders)
     */
    private const MIN_QUANTITY = -999.99;

    /**
     * Threshold for anomaly detection (percentage change)
     */
    private const ANOMALY_THRESHOLD_PERCENT = 90;

    /**
     * Minimum current quantity for anomaly detection
     * (avoid false positives when current stock is very low)
     */
    private const ANOMALY_MIN_CURRENT_QTY = 10;

    /**
     * Minimum absolute increase to flag as anomaly (for positive stock changes).
     * Increases below this threshold are considered routine restocking and are
     * NOT flagged, even if the percentage change exceeds ANOMALY_THRESHOLD_PERCENT.
     * Decreases are always flagged when percentage threshold is exceeded.
     */
    private const ANOMALY_MIN_ABSOLUTE_INCREASE = 200;

    private StockRegistryInterface $stockRegistry;
    private LoggerInterface $logger;

    public function __construct(
        StockRegistryInterface $stockRegistry,
        LoggerInterface $logger
    ) {
        $this->stockRegistry = $stockRegistry;
        $this->logger = $logger;
    }

    /**
     * Validate stock data from ERP
     */
    public function validate(array $stockData, ?string $sku = null): ValidationResult
    {
        $result = new ValidationResult(true, [], [], []);

        // Validate SKU
        $skuResult = $this->validateSku($stockData, $sku);
        $result->merge($skuResult);

        if (!$result->isValid()) {
            return $result;
        }

        $validatedSku = $result->getField('sku');

        // Validate quantity
        $qtyResult = $this->validateQuantity($stockData);
        $result->merge($qtyResult);

        // Check for anomalies if SKU is provided
        if ($validatedSku && $result->isValid()) {
            $anomalyResult = $this->detectAnomaly($validatedSku, $result->getField('quantity', 0));
            $result->merge($anomalyResult);
        }

        // Keep per-item logs focused on true validation problems.
        // Anomaly warnings are consolidated by StockSync.
        if ($result->hasErrors() || ($result->hasWarnings() && !$result->getField('anomaly_detected', false))) {
            $this->logger->info('[ERP Validator] Stock validation', [
                'sku' => $validatedSku ?? ($stockData['MATERIAL'] ?? '?'),
                'valid' => $result->isValid(),
                'errors' => $result->getErrors(),
                'warnings' => $result->getWarnings(),
            ]);
        }

        return $result;
    }

    /**
     * Validate SKU
     */
    private function validateSku(array $data, ?string $providedSku): ValidationResult
    {
        $sku = $providedSku ?? trim($data['MATERIAL'] ?? '');

        if (empty($sku)) {
            return ValidationResult::failure(['SKU (MATERIAL) é obrigatório']);
        }

        return ValidationResult::success(['sku' => $sku]);
    }

    /**
     * Validate quantity
     */
    private function validateQuantity(array $data): ValidationResult
    {
        $qty = $data['QTDE'] ?? $data['QTDE_TOTAL'] ?? null;
        $result = new ValidationResult();

        if ($qty === null || $qty === '') {
            $result->setField('quantity', 0);
            $result->addWarning('Quantidade não informada, assumindo 0');
            return $result;
        }

        $qty = (float) $qty;

        // Check for negative stock
        if ($qty < self::MIN_QUANTITY) {
            return ValidationResult::failure([
                sprintf('Quantidade %.2f está abaixo do limite mínimo permitido (%.2f)', $qty, self::MIN_QUANTITY)
            ]);
        }

        // Check for excessive stock
        if ($qty > self::MAX_QUANTITY) {
            return ValidationResult::failure([
                sprintf('Quantidade %.2f excede o limite máximo permitido (%.2f)', $qty, self::MAX_QUANTITY)
            ]);
        }

        // Negative stock warning
        if ($qty < 0) {
            $result->addWarning(sprintf('Estoque negativo detectado: %.2f', $qty));
            // Set to 0 for Magento
            $result->setField('quantity_original', $qty);
            $qty = 0;
        }

        // Round to appropriate precision
        $qty = round($qty, 4);

        $result->setField('quantity', $qty);
        return $result;
    }

    /**
     * Detect stock anomalies (sudden large changes)
     */
    private function detectAnomaly(string $sku, float $newQty): ValidationResult
    {
        $result = new ValidationResult();

        try {
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            $currentQty = (float) $stockItem->getQty();

            // Skip anomaly detection for low current stock
            if ($currentQty < self::ANOMALY_MIN_CURRENT_QTY) {
                return $result;
            }

            // Calculate percentage change
            $change = $newQty - $currentQty;
            $percentChange = ($change / $currentQty) * 100;

            // Detect anomaly based on direction:
            // - Decreases: flag when >ANOMALY_THRESHOLD_PERCENT (data errors, theft)
            // - Increases: flag only when >ANOMALY_THRESHOLD_PERCENT AND absolute
            //   delta > ANOMALY_MIN_ABSOLUTE_INCREASE to avoid noise from routine
            //   bulk restocking operations
            $isAnomalous = abs($percentChange) > self::ANOMALY_THRESHOLD_PERCENT
                && ($change < 0 || abs($change) >= self::ANOMALY_MIN_ABSOLUTE_INCREASE);

            if ($isAnomalous) {
                $direction = $change > 0 ? 'aumento' : 'redução';
                $result->addWarning(
                    sprintf(
                        'Anomalia detectada: %s de %.1f%% no estoque (%.2f → %.2f)',
                        $direction,
                        abs($percentChange),
                        $currentQty,
                        $newQty
                    )
                );

                $result->setField('anomaly_detected', true);
                $result->setField('anomaly_percent_change', round($percentChange, 2));
                $result->setField('previous_quantity', $currentQty);
            }
        } catch (\Exception $e) {
            // Product doesn't exist in Magento - no anomaly detection possible
        }

        return $result;
    }

    /**
     * Validate cost/value
     */
    public function validateCost(array $data): ValidationResult
    {
        $cost = $data['VLRMEDIA'] ?? $data['VLRCUSTO'] ?? null;
        $result = new ValidationResult();

        if ($cost === null || $cost === '') {
            $result->setField('cost', 0);
            return $result;
        }

        $cost = (float) $cost;

        if ($cost < 0) {
            $result->addWarning('Custo negativo detectado, assumindo 0');
            $cost = 0;
        }

        if ($cost > 9999999.99) {
            $result->addWarning('Custo muito alto, verifique o valor');
        }

        $result->setField('cost', round($cost, 4));
        return $result;
    }

    /**
     * Validate branch/filial data
     */
    public function validateBranch(array $data): ValidationResult
    {
        $filial = $data['FILIAL'] ?? null;
        $result = new ValidationResult();

        if ($filial === null) {
            $result->setField('filial', null);
            return $result;
        }

        $filial = (int) $filial;

        if ($filial <= 0) {
            $result->addWarning('Código de filial inválido');
            $result->setField('filial', null);
            return $result;
        }

        $result->setField('filial', $filial);
        return $result;
    }

    /**
     * Validate multi-branch stock data
     */
    public function validateMultiBranchStock(array $branchData): ValidationResult
    {
        $result = new ValidationResult();
        $validatedBranches = [];
        $totalQty = 0;

        foreach ($branchData as $branch) {
            $branchResult = $this->validateBranch($branch);
            $result->merge($branchResult);

            $qtyResult = $this->validateQuantity($branch);
            $result->merge($qtyResult);

            if ($qtyResult->isValid()) {
                $filial = $branchResult->getField('filial');
                $qty = $qtyResult->getField('quantity', 0);

                if ($filial !== null) {
                    $validatedBranches[$filial] = $qty;
                    $totalQty += $qty;
                }
            }
        }

        $result->setField('branches', $validatedBranches);
        $result->setField('total_quantity', $totalQty);

        return $result;
    }

    /**
     * Check if stock change is within acceptable range
     */
    public function isChangeAcceptable(string $sku, float $newQty, float $maxChangePercent = 200): bool
    {
        try {
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            $currentQty = (float) $stockItem->getQty();

            if ($currentQty == 0) {
                return true; // Any change from 0 is acceptable
            }

            $percentChange = abs(($newQty - $currentQty) / $currentQty) * 100;
            return $percentChange <= $maxChangePercent;
        } catch (\Exception $e) {
            return true; // Product doesn't exist, allow any quantity
        }
    }
}

<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Validator;

use Psr\Log\LoggerInterface;

/**
 * Product Data Validator
 *
 * Validates product data from ERP before importing to Magento.
 */
class ProductValidator
{
    /**
     * Maximum SKU length in Magento
     */
    private const MAX_SKU_LENGTH = 64;

    /**
     * Maximum product name length
     */
    private const MAX_NAME_LENGTH = 255;

    /**
     * Maximum description length
     */
    private const MAX_DESCRIPTION_LENGTH = 65535;

    /**
     * Maximum price value
     */
    private const MAX_PRICE = 999999.99;

    /**
     * Maximum weight value (kg)
     */
    private const MAX_WEIGHT = 9999.99;

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Validate product data from ERP
     */
    public function validate(array $productData): ValidationResult
    {
        $result = new ValidationResult(true, [], [], []);

        // Validate SKU
        $skuResult = $this->validateSku($productData);
        $result->merge($skuResult);

        // Validate Name
        $nameResult = $this->validateName($productData);
        $result->merge($nameResult);

        // Validate Price
        $priceResult = $this->validatePrice($productData);
        $result->merge($priceResult);

        // Validate Weight
        $weightResult = $this->validateWeight($productData);
        $result->merge($weightResult);

        // Validate Status
        $statusResult = $this->validateStatus($productData);
        $result->merge($statusResult);

        // Log validation issues
        if ($result->hasErrors() || $result->hasWarnings()) {
            $this->logger->info('[ERP Validator] Product validation', [
                'sku' => $productData['CODIGO'] ?? '?',
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
    private function validateSku(array $data): ValidationResult
    {
        $sku = trim($data['CODIGO'] ?? '');
        $result = new ValidationResult();

        if (empty($sku)) {
            return ValidationResult::failure(['SKU (CODIGO) é obrigatório']);
        }

        if (strlen($sku) > self::MAX_SKU_LENGTH) {
            return ValidationResult::failure([
                sprintf('SKU excede o limite de %d caracteres (%d)', self::MAX_SKU_LENGTH, strlen($sku))
            ]);
        }

        $normalizedSku = $this->normalizeSku($sku);

        if ($normalizedSku === '') {
            return ValidationResult::failure(['SKU ficou vazio após normalização']);
        }

        if ($normalizedSku !== $sku) {
            $result->addWarning(sprintf('SKU normalizado de "%s" para "%s"', $sku, $normalizedSku));
        }

        $result->setField('sku', $normalizedSku);
        return $result;
    }

    /**
     * Validate product name
     */
    private function validateName(array $data): ValidationResult
    {
        $name = trim($data['DESCRICAO'] ?? '');
        $result = new ValidationResult();

        if (empty($name)) {
            return ValidationResult::failure(['Nome do produto (DESCRICAO) é obrigatório']);
        }

        // Truncate if too long (with warning)
        if (strlen($name) > self::MAX_NAME_LENGTH) {
            $name = mb_substr($name, 0, self::MAX_NAME_LENGTH);
            $result->addWarning(sprintf('Nome truncado para %d caracteres', self::MAX_NAME_LENGTH));
        }

        // Sanitize name
        $name = $this->sanitizeText($name);

        $result->setField('name', $name);
        return $result;
    }

    /**
     * Validate price
     */
    private function validatePrice(array $data): ValidationResult
    {
        $price = $data['VLRVENDA'] ?? null;
        $result = new ValidationResult();

        if ($price === null || $price === '') {
            $result->setField('price', 0);
            $result->addWarning('Produto sem preço definido');
            return $result;
        }

        $price = (float) $price;

        if ($price < 0) {
            return ValidationResult::failure(['Preço não pode ser negativo']);
        }

        if ($price > self::MAX_PRICE) {
            return ValidationResult::failure([
                sprintf('Preço excede o limite máximo de R$ %.2f', self::MAX_PRICE)
            ]);
        }

        // Round to 2 decimal places
        $price = round($price, 2);

        $result->setField('price', $price);
        return $result;
    }

    /**
     * Validate weight
     */
    private function validateWeight(array $data): ValidationResult
    {
        $weight = $data['VPESO'] ?? $data['CPESO'] ?? null;
        $result = new ValidationResult();

        if ($weight === null || $weight === '') {
            $result->setField('weight', 0);
            return $result;
        }

        $weight = (float) $weight;

        if ($weight < 0) {
            return ValidationResult::failure(['Peso não pode ser negativo']);
        }

        if ($weight > self::MAX_WEIGHT) {
            $result->addWarning(sprintf('Peso %.2f kg parece muito alto, verifique', $weight));
        }

        $result->setField('weight', round($weight, 4));
        return $result;
    }

    /**
     * Validate status
     */
    private function validateStatus(array $data): ValidationResult
    {
        $isActive = ($data['CCKATIVO'] ?? 'N') === 'S';
        $comercializa = ($data['CKCOMERCIALIZA'] ?? 'S') === 'S';

        $result = ValidationResult::success([
            'is_active' => $isActive,
            'comercializa' => $comercializa,
        ]);

        if (!$isActive) {
            $result->addWarning('Produto inativo no ERP');
        }

        if (!$comercializa) {
            $result->addWarning('Produto marcado como não comercializável');
        }

        return $result;
    }

    private function normalizeSku(string $sku): string
    {
        $normalizedSku = preg_replace('/[<>"\']+/', '', $sku);
        $normalizedSku = preg_replace('/\s+/', ' ', (string) $normalizedSku);

        return trim((string) $normalizedSku);
    }

    /**
     * Validate description/complement
     */
    public function validateDescription(array $data): ValidationResult
    {
        $description = trim($data['COMPLEMENTO'] ?? '');
        $result = new ValidationResult();

        if (empty($description)) {
            $result->setField('description', '');
            return $result;
        }

        // Truncate if too long
        if (strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            $description = mb_substr($description, 0, self::MAX_DESCRIPTION_LENGTH);
            $result->addWarning('Descrição truncada');
        }

        // Sanitize
        $description = $this->sanitizeText($description);

        $result->setField('description', $description);
        return $result;
    }

    /**
     * Validate NCM code
     */
    public function validateNcm(array $data): ValidationResult
    {
        $ncm = trim($data['NCM'] ?? '');
        $result = new ValidationResult();

        if (empty($ncm)) {
            $result->setField('ncm', '');
            return $result;
        }

        // Remove non-numeric characters
        $ncmClean = preg_replace('/[^0-9]/', '', $ncm);

        // NCM should be 8 digits
        if (strlen($ncmClean) !== 8) {
            $result->addWarning(sprintf('NCM "%s" não tem 8 dígitos', $ncm));
        }

        $result->setField('ncm', $ncmClean);
        return $result;
    }

    /**
     * Sanitize text input
     */
    private function sanitizeText(string $text): string
    {
        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim
        return trim($text);
    }
}

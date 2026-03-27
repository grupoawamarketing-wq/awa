<?php
declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Validator;

use GrupoAwamotos\ERPIntegration\Model\EmailSanitizer;
use Psr\Log\LoggerInterface;

/**
 * Customer Data Validator
 *
 * Validates customer data from ERP before importing to Magento.
 * Includes CPF/CNPJ checksum validation.
 */
class CustomerValidator
{
    /**
     * Valid Brazilian states
     */
    private const VALID_UF = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
        'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
        'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
    ];

    private EmailSanitizer $emailSanitizer;
    private LoggerInterface $logger;

    public function __construct(
        EmailSanitizer $emailSanitizer,
        LoggerInterface $logger
    ) {
        $this->emailSanitizer = $emailSanitizer;
        $this->logger = $logger;
    }

    /**
     * Validate customer data from ERP
     */
    public function validate(array $customerData): ValidationResult
    {
        $result = new ValidationResult(true, [], [], []);

        // Validate identification (CPF or CNPJ)
        $taxvatResult = $this->validateTaxvat($customerData);
        $result->merge($taxvatResult);

        // Validate email
        $emailResult = $this->validateEmail($customerData);
        $result->merge($emailResult);

        // Validate name
        $nameResult = $this->validateName($customerData);
        $result->merge($nameResult);

        // Validate address
        $addressResult = $this->validateAddress($customerData);
        $result->merge($addressResult);

        // Validate phone
        $phoneResult = $this->validatePhone($customerData);
        $result->merge($phoneResult);

        // Log validation issues
        if ($result->hasErrors() || $result->hasWarnings()) {
            $this->logger->info('[ERP Validator] Customer validation', [
                'code' => $customerData['CODIGO'] ?? '?',
                'valid' => $result->isValid(),
                'errors' => $result->getErrors(),
                'warnings' => $result->getWarnings(),
            ]);
        }

        return $result;
    }

    /**
     * Validate CPF or CNPJ
     */
    private function validateTaxvat(array $data): ValidationResult
    {
        $isPJ = ($data['CKPESSOA'] ?? 'F') === 'J';
        $taxvat = $isPJ ? ($data['CGC'] ?? '') : ($data['CPF'] ?? '');
        $taxvat = $this->cleanTaxvat($taxvat);

        if (empty($taxvat)) {
            return ValidationResult::failure(['CPF/CNPJ é obrigatório']);
        }

        if ($isPJ) {
            if (!$this->validateCnpj($taxvat)) {
                return ValidationResult::failure([
                    sprintf('CNPJ inválido: %s', $this->formatCnpj($taxvat))
                ]);
            }
            return ValidationResult::success([
                'taxvat' => $taxvat,
                'taxvat_formatted' => $this->formatCnpj($taxvat),
                'is_pj' => true,
            ]);
        } else {
            if (!$this->validateCpf($taxvat)) {
                return ValidationResult::failure([
                    sprintf('CPF inválido: %s', $this->formatCpf($taxvat))
                ]);
            }
            return ValidationResult::success([
                'taxvat' => $taxvat,
                'taxvat_formatted' => $this->formatCpf($taxvat),
                'is_pj' => false,
            ]);
        }
    }

    /**
     * Validate email address
     */
    private function validateEmail(array $data): ValidationResult
    {
        $rawEmail = (string) ($data['EMAIL'] ?? '');
        $email = $this->emailSanitizer->normalize($rawEmail);
        $rawSummary = $this->emailSanitizer->summarizeRaw($rawEmail);

        if (empty($email)) {
            if ($rawSummary === '[vazio]') {
                return ValidationResult::failure(['Email é obrigatório']);
            }

            return ValidationResult::failure([
                sprintf('Email inválido: %s', $rawSummary)
            ]);
        }

        // Extract domain for DNS check
        $parts = explode('@', $email);
        $domain = $parts[1] ?? '';

        $result = ValidationResult::success(['email' => $email]);

        if ($this->shouldWarnAboutEmailNormalization($rawEmail, $email)) {
            $result->addWarning(sprintf(
                'Email normalizado de %s para %s',
                $rawSummary,
                $email
            ));
        }

        // Check for common typos in popular domains
        $typoMap = [
            'gmial.com' => 'gmail.com',
            'gmal.com' => 'gmail.com',
            'gmail.com.br' => 'gmail.com',
            'hotmail.com.br' => 'hotmail.com',
            'hotmai.com' => 'hotmail.com',
            'outloo.com' => 'outlook.com',
            'outlok.com' => 'outlook.com',
            'yahoo.com.br' => 'yahoo.com.br', // valid
            'yaho.com.br' => 'yahoo.com.br',
        ];

        if (isset($typoMap[$domain]) && $typoMap[$domain] !== $domain) {
            $result->addWarning(
                sprintf('Possível erro de digitação no email: %s (sugestão: %s)', $domain, $typoMap[$domain])
            );
        }

        return $result;
    }

    private function shouldWarnAboutEmailNormalization(string $rawEmail, string $normalizedEmail): bool
    {
        $baseline = html_entity_decode($rawEmail, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $baseline = trim($baseline, " \t\n\r\0\x0B'\"<>");
        $baseline = strtolower($baseline);

        return $baseline !== $normalizedEmail;
    }

    /**
     * Validate customer name
     */
    private function validateName(array $data): ValidationResult
    {
        $isPJ = ($data['CKPESSOA'] ?? 'F') === 'J';

        if ($isPJ) {
            $name = trim($data['FANTASIA'] ?? '') ?: trim($data['RAZAO'] ?? '');
            $razao = trim($data['RAZAO'] ?? '');
        } else {
            $name = trim($data['CONTATO_NOME'] ?? '') ?: trim($data['RAZAO'] ?? '');
            $razao = trim($data['RAZAO'] ?? '');
        }

        if (empty($name)) {
            return ValidationResult::failure(['Nome/Razão Social é obrigatório']);
        }

        // Sanitize name
        $name = $this->sanitizeName($name);

        if (strlen($name) < 2) {
            return ValidationResult::failure(['Nome muito curto']);
        }

        if (strlen($name) > 255) {
            $name = mb_substr($name, 0, 255);
        }

        return ValidationResult::success([
            'name' => $name,
            'razao_social' => $razao,
        ]);
    }

    /**
     * Validate address
     */
    private function validateAddress(array $data): ValidationResult
    {
        $result = new ValidationResult();

        // CEP validation
        $cep = $this->cleanCep($data['CEP'] ?? '');
        if (!empty($cep)) {
            if (strlen($cep) !== 8) {
                $result->addWarning('CEP com formato inválido');
            }
            $result->setField('cep', $cep);
            $result->setField('cep_formatted', $this->formatCep($cep));
        } else {
            $result->addWarning('CEP não informado');
            $result->setField('cep', '');
        }

        // UF validation
        $uf = strtoupper(trim($data['UF'] ?? ''));
        if (!empty($uf)) {
            if (!in_array($uf, self::VALID_UF, true)) {
                $result->addError(sprintf('UF inválida: %s', $uf));
            }
            $result->setField('uf', $uf);
        } else {
            $result->addWarning('UF não informada');
            $result->setField('uf', 'SP'); // Default
        }

        // City
        $city = trim($data['CIDADE'] ?? '');
        if (empty($city)) {
            $result->addWarning('Cidade não informada');
        }
        $result->setField('city', $city);

        // Street
        $street = trim($data['ENDERECO'] ?? '');
        $result->setField('street', $street);

        // Number
        $number = trim($data['NUMERO'] ?? 'S/N');
        $result->setField('number', $number);

        // Neighborhood
        $neighborhood = trim($data['BAIRRO'] ?? '');
        $result->setField('neighborhood', $neighborhood);

        return $result;
    }

    /**
     * Validate phone number
     */
    private function validatePhone(array $data): ValidationResult
    {
        $result = new ValidationResult();

        $phone = $data['FONE1'] ?? $data['FONECEL'] ?? $data['WHATSAPP'] ?? '';
        $phone = $this->cleanPhone($phone);

        // Remove prefixo internacional 55 se presente
        if (strlen($phone) >= 12 && str_starts_with($phone, '55')) {
            $phone = substr($phone, 2);
        }

        if (empty($phone)) {
            $result->addWarning('Telefone não informado');
            $result->setField('phone', '');
            return $result;
        }

        // Brazilian phones: 8-11 digits (8-9 sem DDD, 10-11 com DDD)
        if (strlen($phone) < 8 || strlen($phone) > 11) {
            $result->addWarning('Telefone com formato inválido');
        }

        $result->setField('phone', $phone);
        $result->setField('phone_formatted', $this->formatPhone($phone));

        return $result;
    }

    /**
     * Validate CPF checksum (Brazilian individual tax ID)
     */
    public function validateCpf(string $cpf): bool
    {
        $cpf = $this->cleanTaxvat($cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // Check for known invalid CPFs (all same digits)
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Calculate first check digit
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$cpf[$i] * (10 - $i);
        }
        $digit1 = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);

        // Calculate second check digit
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$cpf[$i] * (11 - $i);
        }
        $digit2 = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);

        return $cpf[9] == $digit1 && $cpf[10] == $digit2;
    }

    /**
     * Validate CNPJ checksum (Brazilian company tax ID)
     */
    public function validateCnpj(string $cnpj): bool
    {
        $cnpj = $this->cleanTaxvat($cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        // Check for known invalid CNPJs (all same digits)
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        // Calculate first check digit
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$cnpj[$i] * $weights1[$i];
        }
        $digit1 = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);

        // Calculate second check digit
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int)$cnpj[$i] * $weights2[$i];
        }
        $digit2 = ($sum % 11) < 2 ? 0 : 11 - ($sum % 11);

        return $cnpj[12] == $digit1 && $cnpj[13] == $digit2;
    }

    /**
     * Clean taxvat (remove non-numeric characters)
     */
    private function cleanTaxvat(string $taxvat): string
    {
        return preg_replace('/[^0-9]/', '', $taxvat);
    }

    /**
     * Clean CEP
     */
    private function cleanCep(string $cep): string
    {
        return preg_replace('/[^0-9]/', '', $cep);
    }

    /**
     * Clean phone number
     */
    private function cleanPhone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', $phone);
    }

    /**
     * Format CPF
     */
    private function formatCpf(string $cpf): string
    {
        $cpf = $this->cleanTaxvat($cpf);
        if (strlen($cpf) !== 11) {
            return $cpf;
        }
        return sprintf('%s.%s.%s-%s',
            substr($cpf, 0, 3),
            substr($cpf, 3, 3),
            substr($cpf, 6, 3),
            substr($cpf, 9, 2)
        );
    }

    /**
     * Format CNPJ
     */
    private function formatCnpj(string $cnpj): string
    {
        $cnpj = $this->cleanTaxvat($cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return sprintf('%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }

    /**
     * Format CEP
     */
    private function formatCep(string $cep): string
    {
        $cep = $this->cleanCep($cep);
        if (strlen($cep) !== 8) {
            return $cep;
        }
        return sprintf('%s-%s', substr($cep, 0, 5), substr($cep, 5, 3));
    }

    /**
     * Format phone
     */
    private function formatPhone(string $phone): string
    {
        $phone = $this->cleanPhone($phone);
        if (strlen($phone) === 11) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 2),
                substr($phone, 2, 5),
                substr($phone, 7, 4)
            );
        } elseif (strlen($phone) === 10) {
            return sprintf('(%s) %s-%s',
                substr($phone, 0, 2),
                substr($phone, 2, 4),
                substr($phone, 6, 4)
            );
        }
        return $phone;
    }

    /**
     * Sanitize name
     */
    private function sanitizeName(string $name): string
    {
        // Remove control characters
        $name = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $name);
        // Normalize whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }
}

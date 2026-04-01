<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Validator;

/**
 * Standardized validation result
 */
class ValidationResult
{
    private bool $valid;
    private array $errors;
    private array $warnings;
    private array $data;

    public function __construct(
        bool $valid = true,
        array $errors = [],
        array $warnings = [],
        array $data = []
    ) {
        $this->valid = $valid;
        $this->errors = $errors;
        $this->warnings = $warnings;
        $this->data = $data;
    }

    /**
     * Create a successful validation result
     */
    public static function success(array $data = [], array $warnings = []): self
    {
        return new self(true, [], $warnings, $data);
    }

    /**
     * Create a failed validation result
     */
    public static function failure(array $errors, array $warnings = [], array $data = []): self
    {
        return new self(false, $errors, $warnings, $data);
    }

    /**
     * Check if validation passed
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Check if validation failed
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Check if there are warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    /**
     * Get all validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get first error message
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get all errors as single string
     */
    public function getErrorsAsString(string $separator = '; '): string
    {
        return implode($separator, $this->errors);
    }

    /**
     * Get all warnings
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get first warning message
     */
    public function getFirstWarning(): ?string
    {
        return $this->warnings[0] ?? null;
    }

    /**
     * Get validated/sanitized data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get specific data field
     */
    public function getField(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Add error to result
     */
    public function addError(string $error): self
    {
        $this->errors[] = $error;
        $this->valid = false;
        return $this;
    }

    /**
     * Add warning to result
     */
    public function addWarning(string $warning): self
    {
        $this->warnings[] = $warning;
        return $this;
    }

    /**
     * Set data field
     */
    public function setField(string $key, $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Merge another validation result
     */
    public function merge(ValidationResult $other): self
    {
        $this->errors = array_merge($this->errors, $other->getErrors());
        $this->warnings = array_merge($this->warnings, $other->getWarnings());
        $this->data = array_merge($this->data, $other->getData());

        if (!$other->isValid()) {
            $this->valid = false;
        }

        return $this;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'data' => $this->data,
        ];
    }
}

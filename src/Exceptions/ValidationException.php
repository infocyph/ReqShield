<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Exceptions;

use Exception;

/**
 * ValidationException
 *
 * Exception thrown when validation fails with throwOnFailure enabled.
 */
class ValidationException extends Exception
{
    /**
     * Validation errors.
     *
     * @var array<string, array<string>>
     */
    protected array $errors;

    /**
     * Create a new ValidationException instance.
     *
     * @param string $message Exception message
     * @param array<string, array<string>> $errors Validation errors
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(
        string $message = 'Validation failed',
        array $errors = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * Convert to string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        $errorCount = $this->getErrorCount();
        $message = $this->getMessage() . " ({$errorCount} field(s) with errors)";

        if (!empty($this->errors)) {
            $message .= "\n" . implode("\n", $this->getAllMessages());
        }

        return $message;
    }

    /**
     * Get all error messages as flat array.
     *
     * @return array<string>
     */
    public function getAllMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = $error;
            }
        }
        return $messages;
    }

    /**
     * Get error count.
     *
     * @return int
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Get validation errors.
     *
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     *
     * @param string $field Field name
     * @return array<string>
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get first error for a specific field.
     *
     * @param string $field Field name
     * @return string|null
     */
    public function getFirstFieldError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Check if a field has errors.
     *
     * @param string $field Field name
     * @return bool
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->errors[$field]);
    }
}

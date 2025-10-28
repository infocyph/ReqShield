<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * ValidationResult
 *
 * Represents the result of a validation operation with enhanced error handling.
 */
class ValidationResult
{
    protected MessageBag $messageBag;

    public function __construct(
        protected array $errors,
        protected array $validated = []
    ) {
        $this->messageBag = new MessageBag($errors);
    }

    /**
     * Magic method to get validated data as property
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Magic method to set validated data as property
     */
    public function __set(string $key, mixed $value): void
    {
        $this->validated[$key] = $value;
    }

    /**
     * Magic method to check if validated data has property
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Get all error messages as a flat array
     */
    public function allErrors(): array
    {
        return $this->messageBag->flatten();
    }

    /**
     * Get count of fields with errors
     */
    public function errorCount(): int
    {
        return $this->messageBag->count();
    }

    /**
     * Get all validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field
     */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get validated data except specific fields
     */
    public function except(array $fields): array
    {
        return array_diff_key($this->validated, array_flip($fields));
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the first error message for a field
     */
    public function firstError(?string $field = null): ?string
    {
        return $this->messageBag->first($field);
    }

    /**
     * Get a specific validated value
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->validated[$key] ?? $default;
    }

    /**
     * Check if validated data has a specific key
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->validated);
    }

    /**
     * Check if a field has errors
     */
    public function hasError(string $field): bool
    {
        return $this->messageBag->has($field);
    }

    /**
     * Get MessageBag instance for advanced error handling
     */
    public function messages(): MessageBag
    {
        return $this->messageBag;
    }

    /**
     * Get only specific validated fields
     */
    public function only(array $fields): array
    {
        return array_intersect_key($this->validated, array_flip($fields));
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get safe data (validated + additional safe fields)
     */
    public function safe(array $additionalFields = []): array
    {
        $safe = $this->validated;

        foreach ($additionalFields as $field) {
            if (!isset($safe[$field]) && !$this->hasError($field)) {
                $safe[$field] = null;
            }
        }

        return $safe;
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->passes(),
            'errors' => $this->errors,
            'validated' => $this->validated,
        ];
    }

    /**
     * Convert errors to JSON
     */
    public function toJson(): string
    {
        return $this->messageBag->toJson();
    }

    /**
     * Get validated data
     */
    public function validated(): array
    {
        return $this->validated;
    }

    /**
     * Get first error message for a field.
     *
     * @param string $field Field name
     * @return string|null First error message or null
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}

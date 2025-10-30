<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

/**
 * ValidationResult
 *
 * Represents the result of a validation operation with enhanced error handling.
 * MINOR IMPROVEMENTS: Added utility methods
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
     * Magic method to check if validated data has property
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * Magic method to set validated data as property
     */
    public function __set(string $key, mixed $value): void
    {
        $this->validated[$key] = $value;
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
        return ! empty($this->errors);
    }

    /**
     * Filter validated data by callback
     * NEW: Added for flexible data filtering
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->validated, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get first error message for a field (alias for firstError).
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
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
     * Map validated data with callback
     * NEW: Added for data transformation
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->validated);
    }

    /**
     * Merge another ValidationResult
     * NEW: Added for combining multiple validation results
     */
    public function merge(ValidationResult $other): self
    {
        $this->errors = array_merge($this->errors, $other->errors());
        $this->validated = array_merge($this->validated, $other->validated());
        $this->messageBag = new MessageBag($this->errors);

        return $this;
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
            if (! isset($safe[$field]) && ! $this->hasError($field)) {
                $safe[$field] = null;
            }
        }

        return $safe;
    }

    /**
     * Throw exception if validation failed
     * NEW: Added for fail-fast validation
     */
    public function throw(): self
    {
        if ($this->fails()) {
            throw new \Infocyph\ReqShield\Exceptions\ValidationException(
                'Validation failed',
                $this->errors
            );
        }

        return $this;
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
     * Get a DTO-friendly representation
     * NEW: Added for framework integration
     */
    public function toDTO(): object
    {
        return (object) [
            'success' => $this->passes(),
            'errors' => $this->errors,
            'data' => $this->validated,
            'errorCount' => $this->errorCount(),
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
     * Execute callback if validation fails
     * NEW: Added for fluent handling
     */
    public function whenFails(callable $callback): self
    {
        if ($this->fails()) {
            $callback($this->errors);
        }

        return $this;
    }

    /**
     * Execute callback if validation passes
     * NEW: Added for fluent handling
     */
    public function whenPasses(callable $callback): self
    {
        if ($this->passes()) {
            $callback($this->validated);
        }

        return $this;
    }
}

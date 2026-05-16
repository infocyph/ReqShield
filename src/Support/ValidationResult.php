<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

class ValidationResult
{
    protected MessageBag $messageBag;

    /**
     * @param array<string,array<int,string>> $errors
     * @param array<int|string,mixed> $validated
     * @param array<int|string,mixed> $failures
     * @param array<int|string,mixed> $typed
     */
    public function __construct(
        protected array $errors,
        protected array $validated = [],
        protected array $failures = [],
        protected array $typed = [],
        protected ?string $dtoClass = null,
    ) {
        $this->messageBag = new MessageBag($errors);
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->validated[$key] = $value;
    }

    /** @return array<int,string> */
    public function allErrors(): array
    {
        return $this->messageBag->flatten();
    }

    public function errorCount(): int
    {
        return $this->messageBag->count();
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<int,string> */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * @param array<int,string> $fields
     *
     * @return array<int|string,mixed>
     */
    public function except(array $fields): array
    {
        return array_diff_key($this->validated, array_flip($fields));
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /** @return array<int|string,mixed> */
    public function failures(): array
    {
        return $this->failures;
    }

    /** @return array<int,array<int|string,mixed>> */
    public function failuresFor(string $field): array
    {
        return array_values(array_filter(
            $this->failures,
            fn(mixed $failure): bool => is_array($failure)
                && ($failure['field'] ?? null) === $field,
        ));
    }

    /** @return array<int|string,mixed> */
    public function filter(callable $callback): array
    {
        return array_filter($this->validated, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public function first(string $field): ?string
    {
        $fieldErrors = $this->errors[$field] ?? null;
        if (!is_array($fieldErrors)) {
            return null;
        }

        $first = $fieldErrors[0] ?? null;

        return is_string($first) ? $first : null;
    }

    public function firstError(?string $field = null): ?string
    {
        return $this->messageBag->first($field);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->validated[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->validated);
    }

    public function hasError(string $field): bool
    {
        return $this->messageBag->has($field);
    }

    /** @return array<int|string,mixed> */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->validated);
    }

    public function merge(ValidationResult $other): self
    {
        $this->errors = array_merge($this->errors, $other->errors());
        $this->validated = array_merge($this->validated, $other->validated());
        $this->typed = array_merge($this->typed, $other->typed());
        $this->failures = array_merge($this->failures, $other->failures());
        $this->messageBag = new MessageBag($this->errors);

        return $this;
    }

    public function messages(): MessageBag
    {
        return $this->messageBag;
    }

    /**
     * @param array<int,string> $fields
     *
     * @return array<int|string,mixed>
     */
    public function only(array $fields): array
    {
        return array_intersect_key($this->validated, array_flip($fields));
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    /**
     * @param array<int,string> $additionalFields
     *
     * @return array<int|string,mixed>
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

    public function throw(): self
    {
        if ($this->fails()) {
            throw new \Infocyph\ReqShield\Exceptions\ValidationException(
                'Validation failed',
                $this->errors,
            );
        }

        return $this;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'valid' => $this->passes(),
            'errors' => $this->errors,
            'failures' => $this->failures,
            'validated' => $this->validated,
            'typed' => $this->typed(),
        ];
    }

    public function toDTO(): object
    {
        $payload = $this->typed();

        if ($this->dtoClass !== null && class_exists($this->dtoClass)) {
            return $this->buildDto($this->dtoClass, $payload);
        }

        return (object) [
            'success' => $this->passes(),
            'errors' => $this->errors,
            'failures' => $this->failures,
            'data' => $payload,
            'errorCount' => $this->errorCount(),
        ];
    }

    public function toJson(): string
    {
        return $this->messageBag->toJson();
    }

    /** @return array<int|string,mixed> */
    public function typed(): array
    {
        return empty($this->typed) ? $this->validated : $this->typed;
    }

    /** @return array<int|string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function whenFails(callable $callback): self
    {
        if ($this->fails()) {
            $callback($this->errors);
        }

        return $this;
    }

    public function whenPasses(callable $callback): self
    {
        if ($this->passes()) {
            $callback($this->typed());
        }

        return $this;
    }

    /**
     * @param class-string $class
     * @param array<int|string,mixed> $payload
     */
    protected function buildDto(string $class, array $payload): object
    {
        try {
            $reflection = new \ReflectionClass($class);
            $instance = $this->instantiateDto($reflection, $payload);
        } catch (\Throwable) {
            return (object) $payload;
        }

        foreach ($payload as $key => $value) {
            try {
                $instance->{$key} = $value;
            } catch (\Throwable) {
                continue;
            }
        }

        return $instance;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     * @param array<int|string,mixed> $payload
     */
    protected function instantiateDto(
        \ReflectionClass $reflection,
        array $payload,
    ): object {
        $ctor = $reflection->getConstructor();
        if ($ctor === null) {
            return $reflection->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $parameter) {
            $resolved = $this->resolveCtorParameter($parameter, $payload);
            if (!$resolved['resolved']) {
                return $reflection->newInstanceWithoutConstructor();
            }

            $args[] = $resolved['value'];
        }

        return $reflection->newInstanceArgs($args);
    }

    /**
     * @param array<int|string,mixed> $payload
     *
     * @return array{resolved:bool,value:mixed}
     */
    protected function resolveCtorParameter(
        \ReflectionParameter $parameter,
        array $payload,
    ): array {
        $name = $parameter->getName();

        if (array_key_exists($name, $payload)) {
            return ['resolved' => true, 'value' => $payload[$name]];
        }

        $snakeName = RuleNameResolver::toSnakeCase($name);
        if ($snakeName !== $name && array_key_exists($snakeName, $payload)) {
            return ['resolved' => true, 'value' => $payload[$snakeName]];
        }

        if ($parameter->isDefaultValueAvailable()) {
            return [
                'resolved' => true,
                'value' => $parameter->getDefaultValue(),
            ];
        }

        if ($parameter->allowsNull()) {
            return ['resolved' => true, 'value' => null];
        }

        return ['resolved' => false, 'value' => null];
    }
}

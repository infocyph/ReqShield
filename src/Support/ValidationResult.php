<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final readonly class ValidationResult
{
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
    ) {}

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /** @return array<int,string> */
    public function allErrors(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            array_push($messages, ...$fieldErrors);
        }

        return $messages;
    }

    public function errorCount(): int
    {
        return $this->errorMessageCount();
    }

    public function errorFieldCount(): int
    {
        return count($this->errors);
    }

    public function errorMessageCount(): int
    {
        return count($this->allErrors());
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
        if ($field !== null) {
            return $this->first($field);
        }

        foreach ($this->errors as $messages) {
            if (isset($messages[0])) {
                return $messages[0];
            }
        }

        return null;
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
        return isset($this->errors[$field]) && $this->errors[$field] !== [];
    }

    public function input(bool $typed = true): ValidatedInput
    {
        return new ValidatedInput($typed ? $this->typed() : $this->validated());
    }

    public function messages(): MessageBag
    {
        return new MessageBag($this->errors);
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
    public function toApiErrors(): array
    {
        return [
            'ok' => false,
            'message' => 'Validation failed.',
            'errors' => $this->errors(),
            'failures' => $this->toFlatErrors(),
        ];
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

    /** @return array<int,array{field:string,rule:string,message:string,value:mixed}> */
    public function toFlatErrors(): array
    {
        return array_values(array_filter(
            array_map(function (mixed $failure): ?array {
                if (!is_array($failure)) {
                    return null;
                }

                $field = $failure['field'] ?? null;
                $rule = $failure['rule'] ?? null;
                $message = $failure['message'] ?? null;

                if (!is_string($field) || !is_string($rule) || !is_string($message)) {
                    return null;
                }

                return [
                    'field' => $field,
                    'rule' => $rule,
                    'message' => $message,
                    'value' => $failure['value'] ?? null,
                ];
            }, $this->failures),
            is_array(...),
        ));
    }

    public function toJson(): string
    {
        return json_encode($this->errors, JSON_THROW_ON_ERROR);
    }

    /** @return array{errors:array<int,array<string,mixed>>} */
    public function toJsonApiErrors(): array
    {
        $errors = array_map(
            fn(array $failure): array => [
                'status' => '422',
                'source' => ['pointer' => '/data/attributes/' . $failure['field']],
                'title' => 'Validation Error',
                'detail' => $failure['message'],
                'meta' => [
                    'field' => $failure['field'],
                    'rule' => $failure['rule'],
                    'value' => $failure['value'],
                ],
            ],
            $this->toFlatErrors(),
        );

        return ['errors' => $errors];
    }

    /** @return array<string,mixed> */
    public function toProblemJson(
        string $title = 'Validation failed',
        int $status = 422,
        string $type = 'https://example.com/problems/validation-error',
    ): array {
        return [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $this->firstError() ?? 'One or more fields failed validation.',
            'errors' => $this->errors(),
            'failures' => $this->toFlatErrors(),
        ];
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
        } catch (\Throwable $exception) {
            throw new \Infocyph\ReqShield\Exceptions\DtoMappingException(
                "Unable to map validated input to DTO {$class}.",
                previous: $exception,
            );
        }

        $constructorFields = [];
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $constructorFields[$parameter->getName()] = true;
        }

        foreach ($payload as $key => $value) {
            if (!is_string($key) || !$reflection->hasProperty($key)) {
                continue;
            }

            if (isset($constructorFields[$key])) {
                continue;
            }

            try {
                $instance->{$key} = $value;
            } catch (\Throwable $exception) {
                throw new \Infocyph\ReqShield\Exceptions\DtoMappingException(
                    "Unable to map property {$key} on DTO {$class}.",
                    previous: $exception,
                );
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
                throw new \InvalidArgumentException(
                    "Missing required DTO constructor parameter {$parameter->getName()}.",
                );
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

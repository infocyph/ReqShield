<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final readonly class ValidatedInput
{
    /** @param array<int|string,mixed> $data */
    public function __construct(
        private array $data,
    ) {}

    /** @param array<int,mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        if ($name !== 'array') {
            throw new \BadMethodCallException("Method {$name} does not exist.");
        }

        $field = isset($arguments[0]) && is_string($arguments[0]) ? $arguments[0] : '';
        $default = isset($arguments[1]) && is_array($arguments[1]) ? $arguments[1] : null;

        return $this->arrayValue($field, $default);
    }

    /** @return array<int|string,mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * @param array<int|string,mixed>|null $default
     * @return array<int|string,mixed>|null
     */
    public function arrayValue(string $field, ?array $default = null): ?array
    {
        $value = $this->get($field);

        return is_array($value) ? $value : $default;
    }

    public function bool(string $field, ?bool $default = null): ?bool
    {
        $value = $this->get($field);
        if ($value === null) {
            return $default;
        }

        return InputCaster::tryBoolean($value) ?? $default;
    }

    public function date(string $field, ?\DateTimeImmutable $default = null): ?\DateTimeImmutable
    {
        $value = $this->get($field);
        if ($value === null) {
            return $default;
        }

        return InputCaster::tryDateTimeImmutable($value) ?? $default;
    }

    /**
     * @template T of \UnitEnum
     * @param class-string<T> $enumClass
     * @param T|null $default
     * @return T|null
     */
    public function enum(
        string $field,
        string $enumClass,
        ?\UnitEnum $default = null,
    ): ?\UnitEnum {
        $value = $this->get($field);
        if ($value === null || !enum_exists($enumClass)) {
            return $default;
        }

        if ($value instanceof $enumClass) {
            return $value;
        }

        if (is_subclass_of($enumClass, \BackedEnum::class)) {
            if (is_int($value) || is_string($value)) {
                $case = $enumClass::tryFrom($value);
                if ($case instanceof \UnitEnum) {
                    return $case;
                }
            }
        }

        if (is_string($value) && defined($enumClass . '::' . $value)) {
            $case = constant($enumClass . '::' . $value);

            return $case instanceof \UnitEnum ? $case : $default;
        }

        return $default;
    }

    /**
     * @param array<int,string> $fields
     * @return array<int|string,mixed>
     */
    public function except(array $fields): array
    {
        return array_diff_key($this->data, array_flip($fields));
    }

    public function float(string $field, ?float $default = null): ?float
    {
        $value = $this->get($field);
        if ($value === null) {
            return $default;
        }

        return InputCaster::tryFloat($value) ?? $default;
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->data[$field] ?? $default;
    }

    public function has(string $field): bool
    {
        return array_key_exists($field, $this->data);
    }

    public function int(string $field, ?int $default = null): ?int
    {
        $value = $this->get($field);
        if ($value === null) {
            return $default;
        }

        return InputCaster::tryInteger($value) ?? $default;
    }

    /**
     * @param array<int,string> $fields
     * @return array<int|string,mixed>
     */
    public function only(array $fields): array
    {
        return array_intersect_key($this->data, array_flip($fields));
    }

    public function string(string $field, ?string $default = null): ?string
    {
        $value = $this->get($field);
        if ($value === null) {
            return $default;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $default;
    }
}

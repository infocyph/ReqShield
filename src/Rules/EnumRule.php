<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

final class EnumRule extends BaseRule
{
    public function __construct(
        private readonly string $enumClass,
    ) {}

    public function cost(): int
    {
        return 20;
    }

    public function getEnumClass(): string
    {
        return $this->enumClass;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid enum value.";
    }

    /** @param array<int|string,mixed> $data */
    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($field, $data);

        if ($value === null || !enum_exists($this->enumClass)) {
            return false;
        }

        if ($value instanceof $this->enumClass) {
            return true;
        }

        if (is_subclass_of($this->enumClass, \BackedEnum::class)) {
            if (is_int($value) || is_string($value)) {
                if ($this->enumClass::tryFrom($value) instanceof \BackedEnum) {
                    return true;
                }
            }
        }

        return is_string($value) && defined($this->enumClass . '::' . $value);
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Timezone Rule - Cost: 12
 */
class Timezone extends BaseRule
{
    protected static ?array $timezoneLookup = null;

    public function cost(): int
    {
        return 12;
    }

    public function message(string $field): string
    {
        return "The {$field} must be a valid timezone.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if (!is_string($value)) {
            return false;
        }

        if (self::$timezoneLookup === null) {
            self::$timezoneLookup = array_fill_keys(
                \DateTimeZone::listIdentifiers(),
                true,
            );
        }

        return isset(self::$timezoneLookup[$value]);
    }

}

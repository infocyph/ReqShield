<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredArrayKeys Rule - Cost: 5
 */
class RequiredArrayKeys extends StringValuesRule
{
    public function message(string $field): string
    {
        return "The {$field} must have keys: {$this->joinedValues()}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_array($value)) {
            return false;
        }

        return array_all($this->values, fn(string $key): bool => array_key_exists($key, $value));
    }
}

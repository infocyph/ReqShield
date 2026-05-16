<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * ExcludeWith Rule - Cost: 2
 */
class ExcludeWith extends AbstractOtherFieldRule
{
    public function message(string $field): string
    {
        return "The {$field} will be excluded when {$this->otherField} is present.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return !array_key_exists($this->otherField, $data);
    }
}

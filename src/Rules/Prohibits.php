<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Prohibits Rule - Cost: 2
 */
class Prohibits extends FieldListRule
{
    public function message(string $field): string
    {
        return "The {$field} prohibits {$this->joinedFields()} from being present.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if ($this->isEmpty($value)) {
            return true;
        }

        return array_all(
            $this->fields,
            fn(string $f): bool => !(array_key_exists($f, $data) && !$this->isEmpty($data[$f])),
        );
    }
}

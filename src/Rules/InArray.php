<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * InArray Rule - Cost: 5
 */
class InArray extends AbstractOtherFieldRule
{
    public function message(string $field): string
    {
        return "The {$field} must be in {$this->otherField}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return array_key_exists($this->otherField, $data)
          && is_array($data[$this->otherField])
          && in_array($value, $data[$this->otherField], true);
    }

    #[\Override]
    protected function ruleCost(): int
    {
        return 5;
    }
}

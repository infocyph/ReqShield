<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractRequiredIfStateRule extends AbstractOtherFieldRule
{
    abstract protected function triggerLabel(): string;

    /** @return list<string|int|bool> */
    abstract protected function triggerValues(): array;

    public function message(string $field): string
    {
        return "The {$field} is required when {$this->otherField} is {$this->triggerLabel()}.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!array_key_exists($this->otherField, $data) || !in_array($data[$this->otherField], $this->triggerValues(), true)) {
            return true;
        }

        return !$this->isEmpty($value);
    }
}

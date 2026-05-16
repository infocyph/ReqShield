<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractPresentWithRule extends FieldListRule
{
    abstract protected function requiresAllFields(): bool;

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        $isTriggered = $this->requiresAllFields()
            ? array_all($this->fields, fn(string $other): bool => array_key_exists($other, $data))
            : array_any($this->fields, fn(string $other): bool => array_key_exists($other, $data));

        return !$isTriggered || array_key_exists($field, $data);
    }
}

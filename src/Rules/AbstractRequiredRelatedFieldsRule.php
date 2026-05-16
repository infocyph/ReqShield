<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractRequiredRelatedFieldsRule extends FieldListRule
{
    /** @param array<array-key, mixed> $data */
    abstract protected function isRequiredFromRelatedFields(array $data): bool;

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!$this->isRequiredFromRelatedFields($data)) {
            return true;
        }

        return !$this->isEmpty($value);
    }

    /** @param array<array-key, mixed> $data */
    protected function hasNonEmptyField(string $field, array $data): bool
    {
        return array_key_exists($field, $data) && !$this->isEmpty($data[$field]);
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredIfAccepted Rule - Cost: 2
 */
class RequiredIfAccepted extends BaseRule
{
    protected string $otherField;

    public function __construct(string $otherField)
    {
        $this->otherField = $otherField;
    }

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} is required when {$this->otherField} is accepted.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $acceptable = ['yes', 'on', '1', 1, true, 'true'];
        if (! isset($data[$this->otherField]) || ! in_array($data[$this->otherField], $acceptable, true)) {
            return true;
        }

        return ! $this->isEmpty($value);
    }
}

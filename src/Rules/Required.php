<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Required Rule - Cost: 1
 * Validates that a value is not empty.
 */
class Required extends BaseRule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} field is required.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if ($this->isNullOrBlankString($value)) {
            return false;
        }

        if (!$this->isEmptyCountable($value)) {
            return true;
        }

        if ($this->hasNonEmptyStringRepresentation($value)) {
            return true;
        }

        return $this->isStreamResource($value);
    }

    protected function hasNonEmptyStringRepresentation(mixed $value): bool
    {
        if (!is_object($value) || !method_exists($value, '__toString')) {
            return false;
        }

        $stringValue = (string) $value;

        return $stringValue !== '' && trim($stringValue) !== '';
    }

    protected function isEmptyCountable(mixed $value): bool
    {
        return (is_array($value) || is_countable($value)) && count($value) === 0;
    }

    protected function isStreamResource(mixed $value): bool
    {
        if (!is_resource($value)) {
            return false;
        }

        return get_resource_type($value) === 'stream';
    }
}

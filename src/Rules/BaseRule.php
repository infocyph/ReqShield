<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

use Infocyph\ReqShield\Contracts\Rule;

abstract class BaseRule implements Rule
{
    /**
     * Default implementation - rules are not batchable unless overridden.
     */
    public function isBatchable(): bool
    {
        return false;
    }

    /**
     * Get the size of a value.
     *
     * @param mixed $value
     *
     * @return int|float
     */
    protected function getSize(mixed $value): float|int|string
    {
        if (is_numeric($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_strlen($value);
        }

        if (is_array($value) || is_countable($value)) {
            return count($value);
        }

        return 0;
    }

    /**
     * Helper method to check if value is empty.
     *
     * @param mixed $value
     */
    protected function isEmpty(mixed $value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if ((is_array($value) || is_countable($value)) && count($value) === 0) {
            return true;
        }

        return false;
    }

}

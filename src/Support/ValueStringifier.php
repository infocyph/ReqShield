<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class ValueStringifier
{
    public static function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '';
        }
    }
}

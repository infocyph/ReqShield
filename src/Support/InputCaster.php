<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Exceptions\CastException;

final class InputCaster
{
    public static function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === 1.0) {
            return true;
        }

        if ($value === 0 || $value === 0.0) {
            return false;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        throw new CastException('Value cannot be cast to boolean.');
    }

    public static function tryBoolean(mixed $value): ?bool
    {
        try {
            return self::toBoolean($value);
        } catch (CastException) {
            return null;
        }
    }

    public static function tryDateTimeImmutable(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!is_scalar($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function tryFloat(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (is_string($value) && is_numeric($value)) {
            $float = (float) $value;

            return is_finite($float) ? $float : null;
        }

        return null;
    }

    public static function tryInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) && floor($value) === $value ? (int) $value : null;
        }

        if (is_string($value) && preg_match('/^[+-]?\d+$/D', $value) === 1) {
            $filtered = filter_var($value, FILTER_VALIDATE_INT);

            return $filtered === false ? null : $filtered;
        }

        return null;
    }
}

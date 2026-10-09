<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Sanitizer;

/**
 * Cold-path pipeline lookup and named sanitizer resolution.
 */
final class SanitizerCallableResolver
{
    /** @param array<int,mixed> $sanitizers */
    public static function cacheKey(array $sanitizers): ?string
    {
        $parts = [];

        foreach ($sanitizers as $sanitizer) {
            if (!is_string($sanitizer)) {
                return null;
            }

            $parts[] = $sanitizer;
        }

        return serialize($parts);
    }

    public static function resolve(mixed $sanitizer): ?callable
    {
        if (is_string($sanitizer)) {
            if (method_exists(Sanitizer::class, $sanitizer)) {
                return static fn(mixed $input): mixed => Sanitizer::{$sanitizer}($input);
            }

            if (is_callable($sanitizer)) {
                return static fn(mixed $input): mixed => $sanitizer($input);
            }

            return null;
        }

        return is_callable($sanitizer)
            ? static fn(mixed $input): mixed => $sanitizer($input)
            : null;
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

final class HashAlgorithm
{
    /**
     * @var array<string,bool>
     */
    protected static array $checked = [];

    public static function require(string $algorithm): string
    {
        if (isset(self::$checked[$algorithm])) {
            return $algorithm;
        }

        if (!in_array($algorithm, hash_algos(), true)) {
            throw new \RuntimeException(
                sprintf(
                    'Hash algorithm "%s" is required but not available.',
                    $algorithm,
                ),
            );
        }

        self::$checked[$algorithm] = true;

        return $algorithm;
    }
}

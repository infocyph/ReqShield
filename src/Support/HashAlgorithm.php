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
    /** @param array<int|string,mixed> $data */
    public static function shapeSignature(array $data): string
    {
        $context = hash_init('sha256');
        static::updateShapeHash($context, $data);

        return hash_final($context);
    }

    /** @param array<int|string,mixed> $data */
    protected static function updateShapeHash(\HashContext $context, array $data): void
    {
        hash_update($context, '{');

        foreach ($data as $key => $value) {
            $keyType = is_int($key) ? 'i' : 's';
            $keyBytes = (string) $key;
            hash_update($context, $keyType . strlen($keyBytes) . ':' . $keyBytes);

            if (is_array($value)) {
                static::updateShapeHash($context, $value);
            } else {
                hash_update($context, 's;');
            }
        }

        hash_update($context, '}');
    }

}

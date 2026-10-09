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
        $buffer = '';
        static::updateShapeHash($context, $data, $buffer);

        if ($buffer !== '') {
            hash_update($context, $buffer);
        }

        return hash_final($context);
    }

    /** @param array<int|string,mixed> $data */
    protected static function updateShapeHash(\HashContext $context, array $data, string &$buffer): void
    {
        $buffer .= '{';

        foreach ($data as $key => $value) {
            $keyBytes = (string) $key;
            $buffer .= (is_int($key) ? 'i' : 's') . strlen($keyBytes) . ':' . $keyBytes;

            if (strlen($buffer) >= 8_192) {
                hash_update($context, $buffer);
                $buffer = '';
            }

            if (is_array($value)) {
                static::updateShapeHash($context, $value, $buffer);
            } else {
                $buffer .= 's;';
            }
        }

        $buffer .= '}';
    }
}

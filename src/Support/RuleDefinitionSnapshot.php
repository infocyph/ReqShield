<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Exceptions\InvalidSchemaException;

final class RuleDefinitionSnapshot
{
    /**
     * @param array<int,array{field:string,rules:string|array<int,mixed>,condition:callable}> $rules
     * @return array<int,array{field:string,rules:string|array<int|string,mixed>,condition:callable}>
     */
    public static function conditionalRules(array $rules): array
    {
        foreach ($rules as &$rule) {
            if (is_array($rule['rules'])) {
                $rule['rules'] = self::map($rule['rules']);
            }
        }
        unset($rule);

        return $rules;
    }

    /**
     * @param array<int|string,mixed> $definitions
     * @return array<int|string,mixed>
     */
    public static function map(array $definitions): array
    {
        $snapshot = [];

        foreach ($definitions as $key => $definition) {
            $snapshot[$key] = self::value($definition, (string) $key);
        }

        return $snapshot;
    }

    private static function value(mixed $definition, string $field): mixed
    {
        if ($definition instanceof Rule) {
            $reflection = new \ReflectionObject($definition);
            if (!$reflection->isCloneable()) {
                throw InvalidSchemaException::forField(
                    $field,
                    'Rule objects must be cloneable.',
                );
            }

            return clone $definition;
        }

        return is_array($definition)
            ? self::map($definition)
            : $definition;
    }
}

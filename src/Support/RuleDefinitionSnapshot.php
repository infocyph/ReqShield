<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Exceptions\InvalidSchemaException;

final class RuleDefinitionSnapshot
{
    /**
     * @param array<int,array{field:string,rules:string|array<int,mixed>,condition:callable}> $rules
     * @return array<int,array{field:string,rules:string|array<int,mixed>,condition:callable}>
     */
    public static function conditionalRules(array $rules): array
    {
        foreach ($rules as &$rule) {
            if (is_array($rule['rules'])) {
                $rule['rules'] = array_values(self::map($rule['rules']));
            }
        }
        unset($rule);

        return $rules;
    }

    /**
     * @template TKey of array-key
     * @param array<TKey,mixed> $definitions
     * @return array<TKey,mixed>
     */
    public static function map(array $definitions): array
    {
        $snapshot = [];

        foreach ($definitions as $key => $definition) {
            $snapshot[$key] = self::value($definition, (string) $key);
        }

        return $snapshot;
    }

    public static function rule(Rule $rule, string $field = 'rule'): Rule
    {
        if (!new \ReflectionObject($rule)->isCloneable()) {
            throw InvalidSchemaException::forField($field, 'Rule objects must be cloneable.');
        }

        $originals = [];
        self::collectObjects($rule, $originals, $field);
        $snapshot = clone $rule;
        $copies = [];
        self::collectObjects($snapshot, $copies, $field);
        foreach ($copies as $id => $object) {
            if (isset($originals[$id])) {
                throw InvalidSchemaException::forField(
                    $field,
                    'Rule __clone() must detach all nested mutable objects.',
                );
            }
        }

        return $snapshot;
    }

    private static function assertInspectable(object $value, string $field): void
    {
        $reflection = new \ReflectionObject($value);
        do {
            if ($reflection->isInternal() && !in_array($reflection->getName(), ['stdClass', 'DateTime'], true)) {
                throw InvalidSchemaException::forField($field, 'Rule state contains an unsupported internal object.');
            }
            $reflection = $reflection->getParentClass();
        } while ($reflection !== false);
    }

    /**
     * @param array<array-key,mixed> $values
     * @param array<int,object> $objects
     */
    private static function collectArray(array $values, array &$objects, string $field, int $depth): void
    {
        foreach ($values as $key => $value) {
            if (\ReflectionReference::fromArrayElement($values, $key) !== null) {
                throw InvalidSchemaException::forField($field, 'Rule state must not contain PHP references.');
            }
            self::collectObjects($value, $objects, $field, $depth + 1);
        }
    }

    /** @param array<int,object> $objects */
    private static function collectObjects(mixed $value, array &$objects, string $field, int $depth = 0): void
    {
        if ($depth > 64 || is_resource($value)) {
            throw InvalidSchemaException::forField($field, 'Rule state must be bounded and contain no resources.');
        }
        if (is_array($value)) {
            self::collectArray($value, $objects, $field, $depth);

            return;
        }
        if (!is_object($value) || self::isSharedValue($value) || isset($objects[spl_object_id($value)])) {
            return;
        }

        self::assertInspectable($value, $field);
        $objects[spl_object_id($value)] = $value;
        self::collectArray((array) $value, $objects, $field, $depth);
    }

    private static function isSharedValue(object $value): bool
    {
        return $value instanceof \Closure
            || $value instanceof \UnitEnum
            || ($value::class === \DateTimeImmutable::class && new \ReflectionObject($value)->getProperties() === []);
    }

    private static function value(mixed $definition, string $field): mixed
    {
        if ($definition instanceof Rule) {
            return self::rule($definition, $field);
        }

        return is_array($definition)
            ? self::map($definition)
            : $definition;
    }
}

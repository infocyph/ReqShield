<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Exceptions\CastException;
use Infocyph\ReqShield\Sanitizer;

/**
 * @phpstan-type JsonNode array<int|string, mixed>
 * @phpstan-type Pipeline array<int, mixed>
 * @phpstan-type SanitizerMap array<int|string, mixed>
 * @phpstan-type DataMap array<int|string, mixed>
 */
trait HasValidatorSchemaCasting
{
    /**
     * @param JsonNode $schema
     * @param JsonNode $property
     */
    protected function addJsonSchemaProperty(
        array &$schema,
        string $path,
        array $property,
        bool $required,
    ): void {
        $this->jsonSchemaExporter->addProperty($schema, $path, $property, $required);
    }

    /** @param JsonNode $node */
    protected function appendJsonSchemaRequiredProperty(
        array &$node,
        string $segment,
    ): void {
        if (!isset($node['required']) || !is_array($node['required'])) {
            $node['required'] = [];
        }

        $node['required'][] = $segment;
    }

    /** @param list<callable(mixed):mixed> $castDefinition */
    protected function applyCastDefinition(
        mixed $value,
        array $castDefinition,
    ): mixed {
        foreach ($castDefinition as $cast) {
            $value = $cast($value);
        }

        return $value;
    }

    /**
     * @param DataMap $validated
     * @return DataMap
     */
    protected function applyCasts(array $validated): array
    {
        $castMap = $this->mergeCastMaps();
        if (empty($castMap)) {
            return $validated;
        }

        $typed = $validated;

        foreach ($castMap as $field => $castDefinition) {
            if (str_contains((string) $field, '*')) {
                continue;
            }

            if (!array_key_exists($field, $typed)) {
                continue;
            }

            $typed[$field] = $this->applyCastDefinition(
                $typed[$field],
                $castDefinition,
            );
        }

        $wildcardCasts = array_filter(
            $castMap,
            fn(string $field): bool => str_contains($field, '*'),
            ARRAY_FILTER_USE_KEY,
        );

        foreach ($wildcardCasts as $fieldPattern => $castDefinition) {
            $pattern = $this->castWildcardRegexes[$fieldPattern];

            foreach ($typed as $field => $value) {
                if (preg_match($pattern, (string) $field) !== 1) {
                    continue;
                }

                $typed[$field] = $this->applyCastDefinition($value, $castDefinition);
            }
        }

        return $typed;
    }

    /** @param JsonNode $property */
    protected function applyJsonSchemaBound(
        array &$property,
        string $type,
        string $bound,
        mixed $rawValue,
    ): void {
        $value = $this->jsonSchemaNumericValue($rawValue);
        if ($value === null) {
            return;
        }

        $targetKey = match ($type) {
            'string' => $bound === 'min' ? 'minLength' : 'maxLength',
            'array' => $bound === 'min' ? 'minItems' : 'maxItems',
            'integer', 'number' => $bound === 'min' ? 'minimum' : 'maximum',
            default => null,
        };

        if (!is_string($targetKey)) {
            return;
        }

        $property[$targetKey] = in_array($targetKey, ['minLength', 'maxLength', 'minItems', 'maxItems'], true)
            ? (int) $value
            : $value;
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyJsonSchemaBoundedConstraint(
        array &$property,
        string $type,
        string $ruleName,
        array $params,
    ): bool {
        switch ($ruleName) {
            case 'min':
                $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);

                return true;
            case 'max':
                $this->applyJsonSchemaBound($property, $type, 'max', $params[0] ?? null);

                return true;
            case 'between':
                $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);
                $this->applyJsonSchemaBound($property, $type, 'max', $params[1] ?? null);

                return true;
            case 'size':
                $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);
                $this->applyJsonSchemaBound($property, $type, 'max', $params[0] ?? null);

                return true;
        }

        return false;
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyJsonSchemaDigitsPatternConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): bool {
        switch ($ruleName) {
            case 'digits':
                if (!isset($params[0]) || !is_numeric($params[0])) {
                    return false;
                }

                $digits = (int) $params[0];
                if ($digits > 0) {
                    $property['pattern'] = '^\\d{' . $digits . '}$';
                }

                return true;
            case 'digits_between':
                if (!isset($params[0], $params[1]) || !is_numeric($params[0]) || !is_numeric($params[1])) {
                    return false;
                }

                $min = max(0, (int) $params[0]);
                $max = max($min, (int) $params[1]);
                $property['pattern'] = '^\\d{' . $min . ',' . $max . '}$';

                return true;
            default:
                return false;
        }
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyJsonSchemaEnumConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): bool {
        if ($ruleName !== 'in' || empty($params)) {
            return false;
        }

        $property['enum'] = array_values($params);

        return true;
    }

    /** @param JsonNode $property */
    protected function applyJsonSchemaFormatConstraint(
        array &$property,
        string $ruleName,
    ): bool {
        $format = match (true) {
            $ruleName === 'email' => 'email',
            $ruleName === 'uuid' => 'uuid',
            in_array($ruleName, ['url', 'active_url'], true) => 'uri',
            $ruleName === 'date' => 'date',
            in_array($ruleName, ['date_format', 'date_equals', 'before', 'before_or_equal', 'after', 'after_or_equal'], true) => 'date-time',
            default => null,
        };

        if (!is_string($format)) {
            return false;
        }

        $property['format'] = $format;

        return true;
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyJsonSchemaRegexPatternConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): void {
        if ($ruleName !== 'regex' || !isset($params[0]) || !is_string($params[0])) {
            return;
        }

        $pattern = $this->normalizeRegexForJsonSchema($params[0]);
        if ($pattern === null || $pattern === '') {
            return;
        }

        $property['pattern'] = $pattern;
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyJsonSchemaRuleConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): void {
        $this->jsonSchemaExporter->applyRuleConstraint(
            $property,
            $ruleName,
            $params,
            fn(string $pattern): ?string => $this->normalizeRegexForJsonSchema($pattern),
        );
    }

    protected function applyNamedSanitizerCast(string $cast, mixed $value): mixed
    {
        if (!method_exists(Sanitizer::class, $cast)) {
            throw new CastException("Unknown cast '{$cast}'.");
        }

        return Sanitizer::{$cast}($value);
    }

    /** @param list<callable(mixed):mixed> $pipeline */
    protected function applySanitizerPipeline(mixed $value, array $pipeline): mixed
    {
        return Sanitizer::applyCompiled($value, $pipeline);
    }

    /**
     * @param DataMap $data
     * @return DataMap
     */
    protected function applySanitizers(array $data): array
    {
        return $this->sanitizerMapApplier->apply(
            $data,
            $this->effectiveSanitizers,
            fn(mixed $value, array $pipeline): mixed => $this->applySanitizerPipeline($value, $pipeline),
            fn(string $pattern): string => $this->sanitizerWildcardRegexes[$pattern],
        );
    }

    /**
     * @param JsonNode $node
     * @param JsonNode $property
     */
    protected function assignJsonSchemaLeafProperty(
        array &$node,
        string $segment,
        array $property,
        bool $required,
    ): void {
        if (!isset($node['properties']) || !is_array($node['properties'])) {
            $node['properties'] = [];
        }

        $node['properties'][$segment] = $property;
        if ($required) {
            $this->appendJsonSchemaRequiredProperty($node, $segment);
        }
    }

    protected function castDateTime(mixed $value): \DateTimeImmutable
    {
        $date = $this->castToDateTimeImmutable($value);
        if (!$date instanceof \DateTimeImmutable) {
            throw new CastException('Value cannot be cast to date/time.');
        }

        return $date;
    }

    protected function castJson(mixed $value): mixed
    {
        if (!is_string($value)) {
            throw new CastException('JSON cast requires a string.');
        }

        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new CastException('Value cannot be cast from JSON.', previous: $exception);
        }
    }

    /** @return array<int|string, mixed> */
    protected function castToArrayValue(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return [$value];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [$value];
    }

    /**
     * @template T of \UnitEnum
     * @param class-string<T> $enumClass
     */
    protected function castToEnum(mixed $value, string $enumClass): mixed
    {
        if ($value === null || !enum_exists($enumClass)) {
            return $value;
        }

        if ($value instanceof $enumClass) {
            return $value;
        }

        if (is_subclass_of($enumClass, \BackedEnum::class) && (is_int($value) || is_string($value))) {
            $case = $enumClass::tryFrom($value);
            if ($case instanceof \BackedEnum) {
                return $case;
            }
        }

        if (is_string($value) && defined($enumClass . '::' . $value)) {
            $case = constant($enumClass . '::' . $value);

            return $case instanceof \UnitEnum ? $case : $value;
        }

        throw new CastException("Value cannot be cast to enum '{$enumClass}'.");
    }

    protected function castToFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value) || (is_string($value) && is_numeric($value))) {
            return (float) $value;
        }

        if (is_object($value) && method_exists($value, '__toString') && is_numeric((string) $value)) {
            return (float) (string) $value;
        }

        throw new CastException('Value cannot be cast to float.');
    }

    protected function castToInt(mixed $value): int
    {
        $cast = \Infocyph\ReqShield\Support\InputCaster::tryInteger($value);
        if ($cast !== null) {
            return $cast;
        }

        throw new CastException('Value cannot be cast to integer.');
    }

    protected function castToObjectValue(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        return (object) (is_array($value) ? $value : ['value' => $value]);
    }

    /**
     * @param array<string,mixed> $map
     * @return array<string,list<callable(mixed):mixed>>
     */
    protected function compileCastMap(array $map): array
    {
        $compiled = [];

        foreach ($map as $field => $definition) {
            $pipeline = is_array($definition) ? $definition : [$definition];
            $compiled[$field] = [];
            foreach ($pipeline as $cast) {
                $compiled[$field][] = $this->compileSingleCast($cast);
            }
        }

        return $compiled;
    }

    /** @return callable(mixed):mixed */
    protected function compileSingleCast(mixed $cast): callable
    {
        if (is_callable($cast)) {
            return static fn(mixed $value): mixed => $cast($value);
        }

        if (!is_string($cast) || $cast === '') {
            throw new CastException('Cast definitions must be non-empty strings or callables.');
        }

        if (enum_exists($cast)) {
            return fn(mixed $value): mixed => $this->castToEnum($value, $cast);
        }

        $normalized = strtolower($cast);

        return match ($normalized) {
            'int', 'integer' => fn(mixed $value): int => $this->castToInt($value),
            'float', 'double', 'real' => fn(mixed $value): float => $this->castToFloat($value),
            'bool', 'boolean' => fn(mixed $value): bool => $this->castToBoolean($value),
            'string' => fn(mixed $value): string => $this->castToString($value),
            'array' => fn(mixed $value): array => $this->castToArrayValue($value),
            'object' => fn(mixed $value): object => $this->castToObjectValue($value),
            'json' => fn(mixed $value): mixed => $this->castJson($value),
            'date', 'datetime', 'datetimeimmutable' => fn(mixed $value): \DateTimeImmutable => $this->castDateTime($value),
            default => method_exists(Sanitizer::class, $cast)
                ? fn(mixed $value): mixed => $this->applyNamedSanitizerCast($cast, $value)
                : throw new CastException("Unknown cast '{$cast}'."),
        };
    }

    /** @param JsonNode $node */
    protected function ensureJsonSchemaArrayItemsNode(array &$node): void
    {
        $node['type'] = 'array';
        if (!isset($node['items']) || !is_array($node['items'])) {
            $node['items'] = ['type' => 'object', 'properties' => []];
        }
    }

    /** @param JsonNode $node */
    protected function ensureJsonSchemaChildNode(
        array &$node,
        string $segment,
    ): void {
        if (!isset($node['properties']) || !is_array($node['properties'])) {
            $node['properties'] = [];
        }

        if (!isset($node['properties'][$segment]) || !is_array($node['properties'][$segment])) {
            $node['properties'][$segment] = ['type' => 'object', 'properties' => []];
        }
    }

    /** @param JsonNode $node */
    protected function ensureJsonSchemaObjectNode(array &$node): void
    {
        if (($node['type'] ?? 'object') !== 'object') {
            $node['type'] = 'object';
        }

        $node['properties'] ??= [];
    }

    protected function jsonSchemaNumericValue(mixed $rawValue): int|float|null
    {
        if (!is_numeric($rawValue)) {
            return null;
        }

        return str_contains((string) $rawValue, '.')
            ? (float) $rawValue
            : (int) $rawValue;
    }
}

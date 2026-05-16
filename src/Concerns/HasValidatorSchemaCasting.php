<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Sanitizer;
use Infocyph\ReqShield\Support\NestedValidator;

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

    protected function applyCastDefinition(
        mixed $value,
        mixed $castDefinition,
    ): mixed {
        if (is_array($castDefinition)) {
            foreach ($castDefinition as $cast) {
                $value = $this->applySingleCast($value, $cast);
            }

            return $value;
        }

        return $this->applySingleCast($value, $castDefinition);
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
            $pattern = $this->wildcardPatternToRegex($fieldPattern);

            foreach ($typed as $field => $value) {
                if (preg_match($pattern, (string) $field) !== 1) {
                    continue;
                }

                $typed[$field] = $this->applyCastDefinition($value, $castDefinition);
            }
        }

        return $typed;
    }

    /**
     * @param DataMap $data
     * @param SanitizerMap $sanitizerMap
     * @return DataMap
     */
    protected function applyDirectFieldSanitizers(
        array $data,
        array $sanitizerMap,
    ): array {
        $this->iterateSanitizerMap(
            $sanitizerMap,
            static fn(string $field): bool => !str_contains($field, '*'),
            function (string $field, array $pipeline) use (&$data): void {
                $this->applyFieldSanitizer($data, $field, $pipeline);
            },
        );

        return $data;
    }

    /**
     * @param DataMap $data
     * @param Pipeline $pipeline
     */
    protected function applyFieldSanitizer(
        array &$data,
        string $field,
        array $pipeline,
    ): void {
        if (str_contains($field, '.')) {
            $this->applyNestedFieldSanitizer($data, $field, $pipeline);

            return;
        }

        if (!array_key_exists($field, $data)) {
            return;
        }

        $data[$field] = $this->applySanitizerPipeline($data[$field], $pipeline);
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
        return method_exists(Sanitizer::class, $cast)
            ? Sanitizer::{$cast}($value)
            : $value;
    }

    /**
     * @param DataMap $data
     * @param Pipeline $pipeline
     */
    protected function applyNestedFieldSanitizer(
        array &$data,
        string $field,
        array $pipeline,
    ): void {
        if (array_key_exists($field, $data)) {
            $data[$field] = $this->applySanitizerPipeline($data[$field], $pipeline);

            return;
        }

        if (!NestedValidator::has($data, $field)) {
            return;
        }

        $current = NestedValidator::extractValue($data, $field);
        NestedValidator::setValue(
            $data,
            $field,
            $this->applySanitizerPipeline($current, $pipeline),
        );
    }

    /** @param Pipeline $pipeline */
    protected function applySanitizerPipeline(mixed $value, array $pipeline): mixed
    {
        return Sanitizer::apply($value, $pipeline);
    }

    /**
     * @param DataMap $data
     * @return DataMap
     */
    protected function applySanitizers(array $data): array
    {
        $sanitizerMap = $this->mergeSanitizerMaps();

        return $this->sanitizerMapApplier->apply(
            $data,
            $sanitizerMap,
            fn(mixed $pipeline): array => array_values($this->normalizeSanitizerPipeline($pipeline)),
            fn(mixed $value, array $pipeline): mixed => $this->applySanitizerPipeline($value, array_values($pipeline)),
            fn(string $pattern): string => $this->wildcardPatternToRegex($pattern),
        );
    }

    protected function applySingleCast(mixed $value, mixed $cast): mixed
    {
        if (is_callable($cast)) {
            return $cast($value);
        }

        if (!is_string($cast) || $cast === '') {
            return $value;
        }

        $normalized = strtolower($cast);

        if (in_array($normalized, ['int', 'integer'], true)) {
            return $this->castToInt($value);
        }

        if (in_array($normalized, ['float', 'double', 'real'], true)) {
            return $this->castToFloat($value);
        }

        if (in_array($normalized, ['bool', 'boolean'], true)) {
            return $this->castToBoolean($value);
        }

        return match ($normalized) {
            'string' => $this->castToString($value),
            'array' => $this->castToArrayValue($value),
            'object' => $this->castToObjectValue($value),
            'json' => is_string($value) ? $this->decodeJsonOrFallback($value, $value) : $value,
            'date', 'datetime', 'datetimeimmutable' => $this->castToDateTimeImmutable($value),
            default => $this->applyNamedSanitizerCast($cast, $value),
        };
    }

    /**
     * @param DataMap $data
     * @param SanitizerMap $sanitizerMap
     * @return DataMap
     */
    protected function applyWildcardFieldSanitizers(
        array $data,
        array $sanitizerMap,
    ): array {
        if (!$this->hasWildcardSanitizers($sanitizerMap)) {
            return $data;
        }

        $flattened = NestedValidator::flattenData($data);

        $this->iterateSanitizerMap(
            $sanitizerMap,
            static fn(string $field): bool => str_contains($field, '*'),
            function (string $field, array $pipeline) use (&$flattened): void {
                $this->applyWildcardSanitizerToFlattened(
                    $flattened,
                    $field,
                    $pipeline,
                );
            },
        );

        return NestedValidator::unflattenData($flattened);
    }

    /**
     * @param DataMap $flattened
     * @param Pipeline $pipeline
     */
    protected function applyWildcardSanitizerToFlattened(
        array &$flattened,
        string $fieldPattern,
        array $pipeline,
    ): void {
        $pattern = $this->wildcardPatternToRegex($fieldPattern);

        foreach ($flattened as $path => $value) {
            if (preg_match($pattern, (string) $path) !== 1) {
                continue;
            }

            $flattened[$path] = $this->applySanitizerPipeline(
                $value,
                $pipeline,
            );
        }
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

    protected function castToFloat(mixed $value): float
    {
        if (is_float($value)) {
            return $value;
        }

        if (is_int($value) || is_bool($value) || $value === null || is_string($value)) {
            return (float) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (float) (string) $value;
        }

        return 0.0;
    }

    protected function castToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || is_bool($value) || $value === null || is_string($value)) {
            return (int) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (int) (string) $value;
        }

        return 0;
    }

    protected function castToObjectValue(mixed $value): object
    {
        if (is_object($value)) {
            return $value;
        }

        return (object) (is_array($value) ? $value : ['value' => $value]);
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

    /** @param SanitizerMap $sanitizerMap */
    protected function hasWildcardSanitizers(array $sanitizerMap): bool
    {
        return array_any(
            array_keys($sanitizerMap),
            fn(int|string $field): bool => str_contains((string) $field, '*'),
        );
    }

    /**
     * @param SanitizerMap $sanitizerMap
     * @param callable(string): bool $shouldProcessField
     * @param callable(string, Pipeline): void $applyField
     */
    protected function iterateSanitizerMap(
        array $sanitizerMap,
        callable $shouldProcessField,
        callable $applyField,
    ): void {
        foreach ($sanitizerMap as $field => $pipeline) {
            if (!is_string($field) || !$shouldProcessField($field)) {
                continue;
            }

            $normalizedPipeline = $this->normalizeSanitizerPipeline($pipeline);
            if (empty($normalizedPipeline)) {
                continue;
            }

            $applyField($field, $normalizedPipeline);
        }
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

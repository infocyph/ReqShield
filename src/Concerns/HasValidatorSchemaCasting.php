<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Sanitizer;
use Infocyph\ReqShield\Support\NestedValidator;

trait HasValidatorSchemaCasting
{
    /**
     * @param array<string,mixed> $schema
     * @param array<string,mixed> $property
     */
    protected function addJsonSchemaProperty(
        array &$schema,
        string $path,
        array $property,
        bool $required,
    ): void {
        $this->jsonSchemaExporter->addProperty($schema, $path, $property, $required);
    }

    /**
     * @param array<string,mixed> $node
     */
    protected function appendJsonSchemaRequiredProperty(
        array &$node,
        string $segment,
    ): void {
        $node['required'] ??= [];
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
            fn(mixed $_, string $field): bool => str_contains($field, '*'),
            ARRAY_FILTER_USE_BOTH,
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

    protected function applyDirectFieldSanitizers(
        array $data,
        array $sanitizerMap,
    ): array {
        foreach ($sanitizerMap as $field => $pipeline) {
            if (str_contains((string) $field, '*')) {
                continue;
            }

            $normalizedPipeline = $this->normalizeSanitizerPipeline($pipeline);
            if (empty($normalizedPipeline)) {
                continue;
            }

            $this->applyFieldSanitizer($data, $field, $normalizedPipeline);
        }

        return $data;
    }

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

    /**
     * @param array<string,mixed> $property
     */
    protected function applyJsonSchemaBound(
        array &$property,
        string $type,
        string $bound,
        mixed $rawValue,
    ): void {
        if (!is_numeric($rawValue)) {
            return;
        }

        $value = str_contains((string) $rawValue, '.')
            ? (float) $rawValue
            : (int) $rawValue;

        if ($type === 'string') {
            $key = $bound === 'min' ? 'minLength' : 'maxLength';
            $property[$key] = (int) $value;
            return;
        }

        if ($type === 'array') {
            $key = $bound === 'min' ? 'minItems' : 'maxItems';
            $property[$key] = (int) $value;
            return;
        }

        if (in_array($type, ['integer', 'number'], true)) {
            $key = $bound === 'min' ? 'minimum' : 'maximum';
            $property[$key] = $value;
        }
    }

    /**
     * @param array<string,mixed> $property
     */
    protected function applyJsonSchemaBoundedConstraint(
        array &$property,
        string $type,
        string $ruleName,
        array $params,
    ): bool {
        if ($ruleName === 'min') {
            $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);
            return true;
        }

        if ($ruleName === 'max') {
            $this->applyJsonSchemaBound($property, $type, 'max', $params[0] ?? null);
            return true;
        }

        if ($ruleName === 'between') {
            $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);
            $this->applyJsonSchemaBound($property, $type, 'max', $params[1] ?? null);
            return true;
        }

        if ($ruleName === 'size') {
            $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);
            $this->applyJsonSchemaBound($property, $type, 'max', $params[0] ?? null);
            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $property
     */
    protected function applyJsonSchemaDigitsPatternConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): bool {
        if ($ruleName === 'digits' && isset($params[0])) {
            $digits = (int) $params[0];
            if ($digits > 0) {
                $property['pattern'] = '^\\d{' . $digits . '}$';
            }

            return true;
        }

        if ($ruleName === 'digits_between' && isset($params[0], $params[1])) {
            $min = max(0, (int) $params[0]);
            $max = max($min, (int) $params[1]);
            $property['pattern'] = '^\\d{' . $min . ',' . $max . '}$';

            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $property
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

    /**
     * @param array<string,mixed> $property
     */
    protected function applyJsonSchemaFormatConstraint(
        array &$property,
        string $ruleName,
    ): bool {
        if ($ruleName === 'email') {
            $property['format'] = 'email';

            return true;
        }

        if ($ruleName === 'uuid') {
            $property['format'] = 'uuid';

            return true;
        }

        if (in_array($ruleName, ['url', 'active_url'], true)) {
            $property['format'] = 'uri';

            return true;
        }

        if ($ruleName === 'date') {
            $property['format'] = 'date';

            return true;
        }

        if (in_array($ruleName, ['date_format', 'date_equals', 'before', 'before_or_equal', 'after', 'after_or_equal'], true)) {
            $property['format'] = 'date-time';

            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $property
     */
    protected function applyJsonSchemaRegexPatternConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): void {
        if ($ruleName === 'regex' && isset($params[0]) && is_string($params[0])) {
            $pattern = $this->normalizeRegexForJsonSchema($params[0]);
            if ($pattern !== null) {
                $property['pattern'] = $pattern;
            }
        }
    }

    /**
     * @param array<string,mixed> $property
     * @param array<int,mixed> $params
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

    protected function applySanitizerPipeline(mixed $value, array $pipeline): mixed
    {
        return Sanitizer::apply($value, $pipeline);
    }

    protected function applySanitizers(array $data): array
    {
        $sanitizerMap = $this->mergeSanitizerMaps();

        return $this->sanitizerMapApplier->apply(
            $data,
            $sanitizerMap,
            fn(mixed $pipeline): array => $this->normalizeSanitizerPipeline($pipeline),
            fn(mixed $value, array $pipeline): mixed => $this->applySanitizerPipeline($value, $pipeline),
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

        return match ($normalized) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'real' => (float) $value,
            'bool', 'boolean' => $this->castToBoolean($value),
            'string' => $this->castToString($value),
            'array' => is_array($value)
                ? $value
                : (is_string($value)
                    ? (json_decode($value, true) ?: [$value])
                    : [$value]),
            'object' => is_object($value) ? $value : (object) (
                is_array($value) ? $value : ['value' => $value]
            ),
            'json' => is_string($value)
                ? $this->decodeJsonOrFallback($value, $value)
                : $value,
            'date', 'datetime', 'datetimeimmutable' => $this->castToDateTimeImmutable($value),
            default => method_exists(Sanitizer::class, $cast)
                ? Sanitizer::{$cast}($value)
                : $value,
        };
    }

    protected function applyWildcardFieldSanitizers(
        array $data,
        array $sanitizerMap,
    ): array {
        if (!$this->hasWildcardSanitizers($sanitizerMap)) {
            return $data;
        }

        $flattened = NestedValidator::flattenData($data);

        foreach ($sanitizerMap as $field => $pipeline) {
            if (!str_contains((string) $field, '*')) {
                continue;
            }

            $normalizedPipeline = $this->normalizeSanitizerPipeline($pipeline);
            if (empty($normalizedPipeline)) {
                continue;
            }

            $this->applyWildcardSanitizerToFlattened(
                $flattened,
                $field,
                $normalizedPipeline,
            );
        }

        return NestedValidator::unflattenData($flattened);
    }

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
     * @param array<string,mixed> $node
     * @param array<string,mixed> $property
     */
    protected function assignJsonSchemaLeafProperty(
        array &$node,
        string $segment,
        array $property,
        bool $required,
    ): void {
        $node['properties'][$segment] = $property;
        if ($required) {
            $this->appendJsonSchemaRequiredProperty($node, $segment);
        }
    }

    /**
     * @param array<string,mixed> $node
     */
    protected function ensureJsonSchemaArrayItemsNode(array &$node): void
    {
        $node['type'] = 'array';
        if (!isset($node['items']) || !is_array($node['items'])) {
            $node['items'] = ['type' => 'object', 'properties' => []];
        }
    }

    /**
     * @param array<string,mixed> $node
     */
    protected function ensureJsonSchemaChildNode(
        array &$node,
        string $segment,
    ): void {
        if (!isset($node['properties'][$segment]) || !is_array($node['properties'][$segment])) {
            $node['properties'][$segment] = ['type' => 'object', 'properties' => []];
        }
    }

    /**
     * @param array<string,mixed> $node
     */
    protected function ensureJsonSchemaObjectNode(array &$node): void
    {
        if (($node['type'] ?? 'object') !== 'object') {
            $node['type'] = 'object';
        }

        $node['properties'] ??= [];
    }

    protected function hasWildcardSanitizers(array $sanitizerMap): bool
    {
        return array_any(
            array_keys($sanitizerMap),
            fn(string $field): bool => str_contains($field, '*'),
        );
    }
}

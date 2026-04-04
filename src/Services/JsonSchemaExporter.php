<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Services;

use Infocyph\ReqShield\Support\RuleExpressionParser;

final class JsonSchemaExporter
{
    /**
     * @param array<string,mixed> $schema
     * @param array<string,mixed> $property
     */
    public function addProperty(
        array &$schema,
        string $path,
        array $property,
        bool $required,
    ): void {
        $segments = explode('.', $path);
        $node = &$schema;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $isLast = $index === $lastIndex;

            if ($segment === '*') {
                $this->ensureArrayNode($node);
                $node = &$node['items'];
                continue;
            }

            $this->ensureObjectNode($node);

            if ($isLast) {
                $node['properties'][$segment] = $property;
                if ($required) {
                    $node['required'] ??= [];
                    $node['required'][] = $segment;
                }

                return;
            }

            if ($required) {
                $node['required'] ??= [];
                $node['required'][] = $segment;
            }

            if (!isset($node['properties'][$segment]) || !is_array($node['properties'][$segment])) {
                $node['properties'][$segment] = ['type' => 'object', 'properties' => []];
            }

            $node = &$node['properties'][$segment];
        }
    }

    /**
     * @param array<string,mixed> $property
     * @param array<int,mixed> $params
     */
    public function applyRuleConstraint(
        array &$property,
        string $ruleName,
        array $params,
        callable $normalizeRegexForJsonSchema,
    ): void {
        $type = $this->primaryType($property['type'] ?? 'string');

        if ($this->applyFormatConstraint($property, $ruleName)) {
            return;
        }

        if ($this->applyBoundedConstraint($property, $type, $ruleName, $params)) {
            return;
        }

        if ($ruleName === 'in' && $params !== []) {
            $property['enum'] = array_values($params);

            return;
        }

        if ($ruleName === 'digits' && isset($params[0])) {
            $digits = (int)$params[0];
            if ($digits > 0) {
                $property['pattern'] = '^\\d{' . $digits . '}$';
            }

            return;
        }

        if ($ruleName === 'digits_between' && isset($params[0], $params[1])) {
            $min = max(0, (int)$params[0]);
            $max = max($min, (int)$params[1]);
            $property['pattern'] = '^\\d{' . $min . ',' . $max . '}$';

            return;
        }

        if ($ruleName === 'regex' && isset($params[0]) && is_string($params[0])) {
            $pattern = $normalizeRegexForJsonSchema($params[0]);
            if (is_string($pattern) && $pattern !== '') {
                $property['pattern'] = $pattern;
            }
        }
    }
    /**
     * @param array<string,string|array<int,mixed>> $rules
     * @param array<string,mixed> $schema
     * @param array<string,mixed> $schemaSanitizers
     * @param array<string,mixed> $schemaCasts
     *
     * @return array<string,mixed>
     */
    public function export(
        array $rules,
        array $schema,
        array $schemaSanitizers,
        array $schemaCasts,
        callable $resolveRuleNameForObject,
        callable $normalizeRegexForJsonSchema,
    ): array {
        $document = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($rules as $field => $definition) {
            $parsedRules = $this->parseRuleDefinitions($definition, $resolveRuleNameForObject);
            $ruleNames = array_column($parsedRules, 'name');
            $property = ['type' => $this->inferJsonSchemaType($ruleNames)];

            foreach ($parsedRules as $rule) {
                $this->applyRuleConstraint(
                    $property,
                    $rule['name'],
                    $rule['params'],
                    $normalizeRegexForJsonSchema,
                );
            }

            if (in_array('nullable', $ruleNames, true)) {
                $this->applyNullableType($property);
            }

            if (isset($schemaSanitizers[$field])) {
                $property['x-reqshield-sanitizers'] = $schemaSanitizers[$field];
            }

            if (isset($schemaCasts[$field])) {
                $property['x-reqshield-cast'] = $schemaCasts[$field];
            }

            $isRequired = isset($schema[$field]) && !$schema[$field]->isOptional;
            $this->addProperty($document, $field, $property, $isRequired);
        }

        $this->normalizeSchemaNode($document);

        return $document;
    }

    /**
     * @param array<string,mixed> $property
     */
    protected function applyBound(
        array &$property,
        string $type,
        string $bound,
        mixed $rawValue,
    ): void {
        if (!is_numeric($rawValue)) {
            return;
        }

        $value = str_contains((string)$rawValue, '.')
            ? (float)$rawValue
            : (int)$rawValue;

        if ($type === 'string') {
            $property[$bound === 'min' ? 'minLength' : 'maxLength'] = (int)$value;

            return;
        }

        if ($type === 'array') {
            $property[$bound === 'min' ? 'minItems' : 'maxItems'] = (int)$value;

            return;
        }

        if (in_array($type, ['integer', 'number'], true)) {
            $property[$bound === 'min' ? 'minimum' : 'maximum'] = $value;
        }
    }

    /**
     * @param array<string,mixed> $property
     * @param array<int,mixed> $params
     */
    protected function applyBoundedConstraint(
        array &$property,
        string $type,
        string $ruleName,
        array $params,
    ): bool {
        if ($ruleName === 'min') {
            $this->applyBound($property, $type, 'min', $params[0] ?? null);

            return true;
        }

        if ($ruleName === 'max') {
            $this->applyBound($property, $type, 'max', $params[0] ?? null);

            return true;
        }

        if ($ruleName === 'between') {
            $this->applyBound($property, $type, 'min', $params[0] ?? null);
            $this->applyBound($property, $type, 'max', $params[1] ?? null);

            return true;
        }

        if ($ruleName === 'size') {
            $this->applyBound($property, $type, 'min', $params[0] ?? null);
            $this->applyBound($property, $type, 'max', $params[0] ?? null);

            return true;
        }

        return false;
    }

    /**
     * @param array<string,mixed> $property
     */
    protected function applyFormatConstraint(
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
    protected function applyNullableType(array &$property): void
    {
        $type = $property['type'] ?? 'string';
        if (is_string($type)) {
            $property['type'] = [$type, 'null'];

            return;
        }

        if (is_array($type) && !in_array('null', $type, true)) {
            $property['type'][] = 'null';
        }
    }

    /**
     * @param array<string,mixed> $node
     */
    protected function ensureArrayNode(array &$node): void
    {
        $node['type'] = 'array';
        if (!isset($node['items']) || !is_array($node['items'])) {
            $node['items'] = ['type' => 'object', 'properties' => []];
        }
    }

    /**
     * @param array<string,mixed> $node
     */
    protected function ensureObjectNode(array &$node): void
    {
        if (($node['type'] ?? 'object') !== 'object') {
            $node['type'] = 'object';
        }

        $node['properties'] ??= [];
    }

    /**
     * @param array<int,string> $ruleNames
     */
    protected function inferJsonSchemaType(array $ruleNames): string
    {
        if (in_array('array', $ruleNames, true) || in_array('is_list', $ruleNames, true)) {
            return 'array';
        }

        if (in_array('integer', $ruleNames, true)) {
            return 'integer';
        }

        if (in_array('numeric', $ruleNames, true) || in_array('decimal', $ruleNames, true)) {
            return 'number';
        }

        if (in_array('boolean', $ruleNames, true)) {
            return 'boolean';
        }

        return 'string';
    }

    /**
     * @param array<string,mixed> $node
     */
    protected function normalizeSchemaNode(array &$node): void
    {
        if (isset($node['required']) && is_array($node['required'])) {
            $node['required'] = array_values(array_unique($node['required']));
        }

        if (isset($node['properties']) && is_array($node['properties'])) {
            foreach ($node['properties'] as &$child) {
                if (is_array($child)) {
                    $this->normalizeSchemaNode($child);
                }
            }
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $this->normalizeSchemaNode($node['items']);
        }
    }

    /**
     * @param string|array<int,mixed> $definition
     *
     * @return array<int,array{name:string,params:array<int,mixed>}>
     */
    protected function parseRuleDefinitions(
        string|array $definition,
        callable $resolveRuleNameForObject,
    ): array {
        $rules = is_string($definition) ? RuleExpressionParser::splitRules($definition) : $definition;
        $parsed = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                [$name, $params] = RuleExpressionParser::parse($rule);
                $parsed[] = ['name' => $name, 'params' => $params];
                continue;
            }

            if (!is_object($rule)) {
                continue;
            }

            $name = $resolveRuleNameForObject($rule);
            $parsed[] = [
                'name' => is_string($name) ? $name : '',
                'params' => [],
            ];
        }

        return array_values(array_filter(
            $parsed,
            static fn (array $entry): bool => $entry['name'] !== '',
        ));
    }

    protected function primaryType(string|array $type): string
    {
        if (is_string($type)) {
            return $type;
        }

        foreach ($type as $item) {
            if ($item !== 'null') {
                return (string)$item;
            }
        }

        return 'string';
    }
}

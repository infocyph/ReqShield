<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Services;

use Infocyph\ReqShield\Support\JsonSchemaTypeHelper;
use Infocyph\ReqShield\Support\RuleExpressionParser;
use Infocyph\ReqShield\Support\ValidationNode;

/**
 * @phpstan-type JsonNode array<int|string, mixed>
 * @phpstan-type RuleDefinition string|array<int, mixed>
 * @phpstan-type ParsedRule array{name:string, params:array<int, mixed>}
 * @phpstan-type ParsedRuleList array<int, ParsedRule>
 */
final class JsonSchemaExporter
{
    public function __construct(
        protected ?JsonSchemaNodeBuilder $nodeBuilder = null,
    ) {
        $this->nodeBuilder ??= new JsonSchemaNodeBuilder();
    }

    /**
     * @param JsonNode $schema
     * @param JsonNode $property
     */
    public function addProperty(
        array &$schema,
        string $path,
        array $property,
        bool $required,
    ): void {
        $this->nodeBuilder?->addProperty($schema, $path, $property, $required);
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     * @param callable(string): ?string $normalizeRegexForJsonSchema
     */
    public function applyRuleConstraint(
        array &$property,
        string $ruleName,
        array $params,
        callable $normalizeRegexForJsonSchema,
    ): void {
        $rawType = $property['type'] ?? 'string';
        if (is_string($rawType)) {
            $type = $this->primaryType($rawType);
        } elseif (is_array($rawType)) {
            $types = array_values(array_filter(
                $rawType,
                is_string(...),
            ));
            $type = $this->primaryType($types === [] ? 'string' : $types);
        } else {
            $type = 'string';
        }

        if ($this->applyFormatConstraint($property, $ruleName)) {
            return;
        }

        if ($this->applyBoundedConstraint($property, $type, $ruleName, $params)) {
            return;
        }

        if ($this->applyEnumConstraint($property, $ruleName, $params)) {
            return;
        }

        if ($this->applyDigitPatternConstraint($property, $ruleName, $params)) {
            return;
        }

        $this->applyRegexConstraint($property, $ruleName, $params, $normalizeRegexForJsonSchema);
    }

    /**
     * @param array<int|string, mixed> $rules
     * @param array<int|string, mixed> $schema
     * @param array<int|string, mixed> $schemaSanitizers
     * @param array<int|string, mixed> $schemaCasts
     * @param callable(object): string $resolveRuleNameForObject
     * @param callable(string): ?string $normalizeRegexForJsonSchema
     * @return JsonNode
     */
    public function export(
        array $rules,
        array $schema,
        array $schemaSanitizers,
        array $schemaCasts,
        callable $resolveRuleNameForObject,
        callable $normalizeRegexForJsonSchema,
    ): array {
        $document = $this->initializeDocument();

        foreach ($rules as $field => $definition) {
            if (!is_string($field) || (!is_string($definition) && !is_array($definition))) {
                continue;
            }
            $ruleDefinition = is_array($definition) ? array_values($definition) : $definition;

            $property = $this->buildPropertyForField(
                $field,
                $ruleDefinition,
                $schemaSanitizers,
                $schemaCasts,
                $resolveRuleNameForObject,
                $normalizeRegexForJsonSchema,
            );
            $node = $schema[$field] ?? null;
            $isRequired = $node instanceof ValidationNode && !$node->isOptional;
            $this->addProperty($document, $field, $property, $isRequired);
        }

        if ($this->nodeBuilder !== null) {
            $this->nodeBuilder->normalizeNode($document);
        }

        return $this->finalizeDocument($document);
    }

    /** @param JsonNode $property */
    protected function applyBound(
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
            $property[$bound === 'min' ? 'minLength' : 'maxLength'] = (int) $value;

            return;
        }

        if ($type === 'array') {
            $property[$bound === 'min' ? 'minItems' : 'maxItems'] = (int) $value;

            return;
        }

        if (in_array($type, ['integer', 'number'], true)) {
            $property[$bound === 'min' ? 'minimum' : 'maximum'] = $value;
        }
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
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
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyDigitPatternConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): bool {
        return match ($ruleName) {
            'digits' => $this->applyFixedDigitPattern($property, $params),
            'digits_between' => $this->applyDigitRangePattern($property, $params),
            default => false,
        };
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyDigitRangePattern(array &$property, array $params): bool
    {
        if (!isset($params[0], $params[1]) || !is_numeric($params[0]) || !is_numeric($params[1])) {
            return false;
        }

        $min = max(0, (int) $params[0]);
        $max = max($min, (int) $params[1]);
        $property['pattern'] = '^\\d{' . $min . ',' . $max . '}$';

        return true;
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyEnumConstraint(
        array &$property,
        string $ruleName,
        array $params,
    ): bool {
        if ($ruleName === 'in' && $params !== []) {
            $property['enum'] = array_values($params);

            return true;
        }

        if (
            $ruleName !== 'enum'
            || !isset($params[0])
            || !is_string($params[0])
            || !enum_exists($params[0])
        ) {
            return false;
        }

        $enumClass = $params[0];
        $cases = $enumClass::cases();
        if ($cases === []) {
            return false;
        }

        if (is_subclass_of($enumClass, \BackedEnum::class)) {
            $values = [];
            foreach ($cases as $case) {
                if ($case instanceof \BackedEnum) {
                    $values[] = $case->value;
                }
            }
            $property['enum'] = $values;

            return true;
        }

        $property['enum'] = array_map(
            static fn(\UnitEnum $case): string => $case->name,
            $cases,
        );

        return true;
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     */
    protected function applyFixedDigitPattern(array &$property, array $params): bool
    {
        if (!isset($params[0]) || !is_numeric($params[0])) {
            return false;
        }

        $digits = (int) $params[0];
        if ($digits > 0) {
            $property['pattern'] = '^\\d{' . $digits . '}$';
        }

        return true;
    }

    /** @param JsonNode $property */
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

    /** @param JsonNode $property */
    protected function applyNullableType(array &$property): void
    {
        JsonSchemaTypeHelper::applyNullableType($property);
    }

    /**
     * @param JsonNode $property
     * @param array<int, mixed> $params
     * @param callable(string): ?string $normalizeRegexForJsonSchema
     */
    protected function applyRegexConstraint(
        array &$property,
        string $ruleName,
        array $params,
        callable $normalizeRegexForJsonSchema,
    ): void {
        if ($ruleName !== 'regex' || !isset($params[0]) || !is_string($params[0])) {
            return;
        }

        $pattern = $normalizeRegexForJsonSchema($params[0]);
        if (is_string($pattern) && $pattern !== '') {
            $property['pattern'] = $pattern;
        }
    }

    /**
     * @param RuleDefinition $definition
     * @param array<int|string, mixed> $schemaSanitizers
     * @param array<int|string, mixed> $schemaCasts
     * @param callable(object): string $resolveRuleNameForObject
     * @param callable(string): ?string $normalizeRegexForJsonSchema
     * @return JsonNode
     */
    protected function buildPropertyForField(
        string $field,
        string|array $definition,
        array $schemaSanitizers,
        array $schemaCasts,
        callable $resolveRuleNameForObject,
        callable $normalizeRegexForJsonSchema,
    ): array {
        $parsedRules = $this->parseRuleDefinitions($definition, $resolveRuleNameForObject);
        $ruleNames = array_values(array_filter(
            array_column($parsedRules, 'name'),
            static fn(string $name): bool => $name !== '',
        ));
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

        return $property;
    }

    /**
     * @param JsonNode $document
     * @return JsonNode
     */
    protected function finalizeDocument(array $document): array
    {
        return [
            '$schema' => is_string($document['$schema'] ?? null)
                ? $document['$schema']
                : 'https://json-schema.org/draft/2020-12/schema',
            'type' => is_string($document['type'] ?? null) ? $document['type'] : 'object',
            'properties' => isset($document['properties']) && is_array($document['properties'])
                ? $document['properties']
                : [],
            'required' => $this->requiredListFromDocument($document),
        ];
    }

    /** @param array<int, string> $ruleNames */
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

    /** @return JsonNode */
    protected function initializeDocument(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
    }

    /**
     * @param RuleDefinition $definition
     * @param callable(object): string $resolveRuleNameForObject
     * @return ParsedRuleList
     */
    protected function parseRuleDefinitions(string|array $definition, callable $resolveRuleNameForObject): array
    {
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
            $params = method_exists($rule, 'getEnumClass')
                ? [$rule->getEnumClass()]
                : [];
            $parsed[] = [
                'name' => $name,
                'params' => $params,
            ];
        }

        return array_values(array_filter(
            $parsed,
            static fn(array $entry): bool => $entry['name'] !== '',
        ));
    }

    /** @param string|array<int, string> $type */
    protected function primaryType(string|array $type): string
    {
        if (is_string($type)) {
            return $type;
        }

        foreach ($type as $item) {
            if ($item !== 'null') {
                return $item;
            }
        }

        return 'string';
    }

    /**
     * @param JsonNode $document
     * @return array<int, string>
     */
    protected function requiredListFromDocument(array $document): array
    {
        if (!isset($document['required']) || !is_array($document['required'])) {
            return [];
        }

        return array_values(array_filter(
            $document['required'],
            is_string(...),
        ));
    }
}

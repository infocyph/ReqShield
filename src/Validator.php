<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;
use Infocyph\ReqShield\Exceptions\ValidationException;
use Infocyph\ReqShield\Executors\BatchExecutor;
use Infocyph\ReqShield\Support\FieldAlias;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Support\SchemaCompiler;
use Infocyph\ReqShield\Support\ValidationNode;
use Infocyph\ReqShield\Support\ValidationResult;

class Validator
{
    protected static array $fragments = [];

    protected BatchExecutor $batchExecutor;

    protected array $casts = [];

    protected SchemaCompiler $compiler;

    protected array $conditionalRules = [];

    protected array $customMessageExact = [];

    protected array $customMessages = [];

    protected array $customMessageWildcard = [];

    protected array $customMessageWildcardPatterns = [];

    protected ?string $dtoClass = null;

    protected bool $failFast = true;

    protected array $fieldAliases = [];

    protected string $locale = 'en';

    protected bool $localeMessagesEnabled = false;

    protected array $localePacks = [];

    protected string $nestedFlattenMode = 'all';

    protected bool $nestedValidation = false;

    protected array $rules;

    protected string $rulesCacheKey = '';

    protected array $sanitizers = [];

    protected array $schema;

    protected array $schemaCasts = [];

    protected array $schemaSanitizers = [];

    protected bool $stopOnFirstError = false;

    protected bool $throwOnFailure = false;

    protected array $whenCallbacks = [];

    protected array $wildcardSchemaCache = [];

    public function __construct(array $rules, ?DatabaseProvider $db = null)
    {
        if (empty($rules)) {
            throw InvalidRuleException::invalidFormat(
                'rules',
                'Rules array cannot be empty',
            );
        }

        foreach ($rules as $field => $rule) {
            if (!is_string($field)) {
                throw InvalidRuleException::invalidFormat(
                    (string)$field,
                    'Field names must be strings',
                );
            }

            if (!is_string($rule) && !is_array($rule)) {
                throw InvalidRuleException::invalidFormat(
                    $field,
                    'Rules must be string or array',
                );
            }
        }

        [$normalizedRules, $schemaSanitizers, $schemaCasts] = $this->normalizeRuleDefinitions($rules);
        $this->rules = $normalizedRules;
        $this->schemaSanitizers = $schemaSanitizers;
        $this->schemaCasts = $schemaCasts;
        $this->rulesCacheKey = $this->buildRulesCacheKey($normalizedRules);
        $this->localePacks = $this->defaultLocalePacks();
        $this->compiler = new SchemaCompiler();
        $this->schema = $this->compiler->compile($normalizedRules);
        $this->batchExecutor = new BatchExecutor($db);

        if (!empty($this->fieldAliases)) {
            FieldAlias::setBatch($this->fieldAliases);
        }
    }

    public static function composeSchemas(array ...$schemas): array
    {
        $composed = [];

        foreach ($schemas as $schema) {
            foreach ($schema as $field => $rules) {
                if (!isset($composed[$field])) {
                    $composed[$field] = $rules;
                    continue;
                }

                $composed[$field] = array_merge(
                    is_array($composed[$field]) ? $composed[$field] : explode('|', (string)$composed[$field]),
                    is_array($rules) ? $rules : explode('|', (string)$rules),
                );
            }
        }

        return $composed;
    }

    public static function defineFragment(string $name, array $rules): void
    {
        static::$fragments[$name] = $rules;
    }

    public static function fragment(string $name, string $prefix = ''): array
    {
        if (!isset(static::$fragments[$name])) {
            throw new InvalidRuleException("Unknown schema fragment: {$name}");
        }

        if ($prefix === '') {
            return static::$fragments[$name];
        }

        $prefixed = [];
        foreach (static::$fragments[$name] as $field => $rules) {
            $prefixed["{$prefix}.{$field}"] = $rules;
        }

        return $prefixed;
    }

    public static function hasFragment(string $name): bool
    {
        return array_key_exists($name, static::$fragments);
    }

    public static function make(
        array $rules,
        ?DatabaseProvider $db = null,
    ): self {
        return new static($rules, $db);
    }

    public function addLocalePack(string $locale, array $messages): self
    {
        $this->localePacks[$locale] = $messages;
        $this->localeMessagesEnabled = true;

        return $this;
    }

    public function enableNestedValidation(bool $flattenAll = true): self
    {
        $this->nestedValidation = true;
        $this->nestedFlattenMode = $flattenAll ? 'all' : 'required';

        return $this;
    }

    public function exportSchema(string $format = 'json_schema'): array
    {
        $jsonSchema = $this->exportJsonSchema();

        return match ($format) {
            'json_schema' => $jsonSchema,
            'openapi' => [
                'type' => 'object',
                'properties' => $jsonSchema['properties'],
                'required' => $jsonSchema['required'],
            ],
            'introspection' => $this->schemaIntrospection(),
            default => throw new InvalidRuleException("Unsupported schema export format: {$format}")
        };
    }

    public function getSchema(): array
    {
        return $this->schema;
    }

    public function getSchemaStats(): array
    {
        $stats = [
            'total_fields' => count($this->schema),
            'fields' => [],
        ];

        foreach ($this->schema as $field => $node) {
            $stats['fields'][$field] = $node->getStats();
        }

        return $stats;
    }

    public function registerRule(string $name, string $class): self
    {
        $this->compiler->registerRule($name, $class);

        return $this;
    }

    public function schemaIntrospection(): array
    {
        $meta = [];

        foreach ($this->schema as $field => $node) {
            $meta[$field] = [
                'rules' => $node->getAllRuleNames(),
                'optional' => $node->isOptional,
                'implicit' => $node->requiresValidationWhenMissing,
                'sanitizers' => $this->schemaSanitizers[$field] ?? null,
                'cast' => $this->schemaCasts[$field] ?? null,
            ];
        }

        return $meta;
    }

    public function setCasts(array $casts): self
    {
        $this->casts = $casts;

        return $this;
    }

    public function setCustomMessages(array $messages): self
    {
        $this->customMessages = $messages;
        $this->customMessageExact = [];
        $this->customMessageWildcard = [];
        $this->customMessageWildcardPatterns = [];

        foreach ($messages as $key => $message) {
            if (!is_string($key) || !is_string($message)) {
                continue;
            }

            if (str_contains($key, '*')) {
                $this->customMessageWildcard[$key] = $message;
                $escaped = preg_quote($key, '/');
                $this->customMessageWildcardPatterns[] = [
                    'pattern' => '/^' . str_replace('\*', '[^.]+', $escaped) . '$/',
                    'message' => $message,
                ];
                continue;
            }

            $this->customMessageExact[$key] = $message;
        }

        return $this;
    }

    public function setDtoClass(?string $class): self
    {
        $this->dtoClass = $class;

        return $this;
    }

    public function setFailFast(bool $failFast): self
    {
        $this->failFast = $failFast;

        return $this;
    }

    public function setFieldAliases(array $aliases): self
    {
        $this->fieldAliases = $aliases;
        FieldAlias::setBatch($aliases);

        return $this;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        $this->localeMessagesEnabled = true;

        return $this;
    }

    public function setLocalePacks(array $packs): self
    {
        $this->localePacks = $packs;
        $this->localeMessagesEnabled = true;

        return $this;
    }

    public function setNestedFlattenMode(string $mode): self
    {
        if (!in_array($mode, ['all', 'required'], true)) {
            throw new InvalidRuleException(
                "Invalid nested flatten mode: {$mode}",
            );
        }

        $this->nestedFlattenMode = $mode;

        return $this;
    }

    /**
     * @param array<string,string|callable|array<int,string|callable>> $sanitizers
     */
    public function setSanitizers(array $sanitizers): self
    {
        $this->sanitizers = $sanitizers;

        return $this;
    }

    public function setStopOnFirstError(bool $stop): self
    {
        $this->stopOnFirstError = $stop;

        return $this;
    }

    public function sometimes(
        string $field,
        string|array $rules,
        callable $condition,
    ): self {
        $this->conditionalRules[] = [
            'field' => $field,
            'rules' => $rules,
            'condition' => $condition,
        ];

        return $this;
    }

    public function throwOnFailure(bool $throw = true): self
    {
        $this->throwOnFailure = $throw;

        return $this;
    }

    public function useFragment(string $name, string $prefix = ''): self
    {
        [$fragmentRules, $fragmentSanitizers, $fragmentCasts] = $this->normalizeRuleDefinitions(
            static::fragment($name, $prefix),
        );

        $this->rules = static::composeSchemas($this->rules, $fragmentRules);
        $this->schemaSanitizers = array_merge($this->schemaSanitizers, $fragmentSanitizers);
        $this->schemaCasts = array_merge($this->schemaCasts, $fragmentCasts);
        $this->rulesCacheKey = $this->buildRulesCacheKey($this->rules);
        $this->schema = $this->compiler->compile($this->rules);
        $this->wildcardSchemaCache = [];

        return $this;
    }

    public function validate(array $data): ValidationResult
    {
        if (!empty($this->sanitizers) || !empty($this->schemaSanitizers)) {
            $data = $this->applySanitizers($data);
        }

        $activeRules = $this->prepareRuntimeRules($data);
        $activeRulesCacheKey = $this->buildRulesCacheKey($activeRules);
        $schema = $activeRules === $this->rules
            ? $this->schema
            : $this->compiler->compile($activeRules);

        if ($this->nestedValidation) {
            $data = $this->prepareNestedData(
                $data,
                $schema,
                $activeRules,
                $activeRulesCacheKey,
            );
        }

        $context = $this->initializeValidationContext();
        $fieldsToValidate = $this->getFieldsToValidate($data, $schema);

        foreach ($fieldsToValidate as $field) {
            if (!isset($schema[$field])) {
                continue;
            }

            $node = $schema[$field];
            $fieldExists = array_key_exists($field, $data);
            $value = $fieldExists ? $data[$field] : null;

            if ($this->shouldSkipOptionalField($node, $value, $fieldExists)) {
                continue;
            }

            if (!$this->processFieldValidation(
                $field,
                $value,
                $node,
                $data,
                $context,
            ) && $this->stopOnFirstError) {
                break;
            }
        }

        $this->executeBatchedRules($context);
        $typed = $this->applyCasts($context['validated']);

        $result = new ValidationResult(
            $context['errors'],
            $context['validated'],
            $context['failures'],
            $typed,
            $this->dtoClass,
        );

        if ($this->throwOnFailure && $result->fails()) {
            throw new ValidationException(
                'Validation failed',
                $context['errors'],
                422,
            );
        }

        return $result;
    }

    public function when(
        bool|callable $condition,
        callable $callback,
        ?callable $default = null,
    ): self {
        $this->whenCallbacks[] = [
            'condition' => $condition,
            'callback' => $callback,
            'default' => $default,
        ];

        return $this;
    }

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
        $segments = explode('.', $path);
        $node = &$schema;
        $lastIndex = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            $isLast = $index === $lastIndex;

            if ($segment === '*') {
                $node['type'] = 'array';
                if (!isset($node['items']) || !is_array($node['items'])) {
                    $node['items'] = ['type' => 'object', 'properties' => []];
                }
                $node = &$node['items'];
                continue;
            }

            if (($node['type'] ?? 'object') !== 'object') {
                $node['type'] = 'object';
            }

            $node['properties'] ??= [];

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
            if (str_contains($field, '*')) {
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
            fn (mixed $_, string $field): bool => str_contains($field, '*'),
            ARRAY_FILTER_USE_BOTH,
        );

        foreach ($wildcardCasts as $fieldPattern => $castDefinition) {
            $pattern = $this->wildcardPatternToRegex($fieldPattern);

            foreach ($typed as $field => $value) {
                if (preg_match($pattern, $field) !== 1) {
                    continue;
                }

                $typed[$field] = $this->applyCastDefinition($value, $castDefinition);
            }
        }

        return $typed;
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

        $value = str_contains((string)$rawValue, '.')
            ? (float)$rawValue
            : (int)$rawValue;

        if ($type === 'string') {
            $key = $bound === 'min' ? 'minLength' : 'maxLength';
            $property[$key] = (int)$value;
            return;
        }

        if ($type === 'array') {
            $key = $bound === 'min' ? 'minItems' : 'maxItems';
            $property[$key] = (int)$value;
            return;
        }

        if (in_array($type, ['integer', 'number'], true)) {
            $key = $bound === 'min' ? 'minimum' : 'maximum';
            $property[$key] = $value;
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
        $type = $this->primaryJsonSchemaType($property['type'] ?? 'string');

        if ($ruleName === 'email') {
            $property['format'] = 'email';
            return;
        }

        if ($ruleName === 'uuid') {
            $property['format'] = 'uuid';
            return;
        }

        if (in_array($ruleName, ['url', 'active_url'], true)) {
            $property['format'] = 'uri';
            return;
        }

        if ($ruleName === 'date') {
            $property['format'] = 'date';
            return;
        }

        if (in_array($ruleName, ['date_format', 'date_equals', 'before', 'before_or_equal', 'after', 'after_or_equal'], true)) {
            $property['format'] = 'date-time';
            return;
        }

        if ($ruleName === 'min') {
            $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);
            return;
        }

        if ($ruleName === 'max') {
            $this->applyJsonSchemaBound($property, $type, 'max', $params[0] ?? null);
            return;
        }

        if ($ruleName === 'between') {
            $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);
            $this->applyJsonSchemaBound($property, $type, 'max', $params[1] ?? null);
            return;
        }

        if ($ruleName === 'size') {
            $this->applyJsonSchemaBound($property, $type, 'min', $params[0] ?? null);
            $this->applyJsonSchemaBound($property, $type, 'max', $params[0] ?? null);
            return;
        }

        if ($ruleName === 'in' && !empty($params)) {
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
            $pattern = $this->normalizeRegexForJsonSchema($params[0]);
            if ($pattern !== null) {
                $property['pattern'] = $pattern;
            }
        }
    }

    protected function applySanitizerPipeline(mixed $value, array $pipeline): mixed
    {
        return Sanitizer::apply($value, $pipeline);
    }

    protected function applySanitizers(array $data): array
    {
        $sanitizerMap = $this->mergeSanitizerMaps();
        if (empty($sanitizerMap)) {
            return $data;
        }

        foreach ($sanitizerMap as $field => $pipeline) {
            if (str_contains($field, '*')) {
                continue;
            }

            $normalizedPipeline = $this->normalizeSanitizerPipeline($pipeline);
            if (empty($normalizedPipeline)) {
                continue;
            }

            if (str_contains($field, '.')) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = $this->applySanitizerPipeline(
                        $data[$field],
                        $normalizedPipeline,
                    );
                    continue;
                }

                if (!NestedValidator::has($data, $field)) {
                    continue;
                }

                $current = NestedValidator::extractValue($data, $field);
                NestedValidator::setValue(
                    $data,
                    $field,
                    $this->applySanitizerPipeline($current, $normalizedPipeline),
                );

                continue;
            }

            if (!array_key_exists($field, $data)) {
                continue;
            }

            $data[$field] = $this->applySanitizerPipeline(
                $data[$field],
                $normalizedPipeline,
            );
        }

        $hasWildcardPipelines = array_any(
            array_keys($sanitizerMap),
            fn (string $field): bool => str_contains($field, '*'),
        );

        if (!$hasWildcardPipelines) {
            return $data;
        }

        $flattened = NestedValidator::flattenData($data);

        foreach ($sanitizerMap as $field => $pipeline) {
            if (!str_contains($field, '*')) {
                continue;
            }

            $normalizedPipeline = $this->normalizeSanitizerPipeline($pipeline);
            if (empty($normalizedPipeline)) {
                continue;
            }

            $pattern = $this->wildcardPatternToRegex($field);

            foreach ($flattened as $path => $value) {
                if (preg_match($pattern, $path) !== 1) {
                    continue;
                }

                $flattened[$path] = $this->applySanitizerPipeline(
                    $value,
                    $normalizedPipeline,
                );
            }
        }

        return NestedValidator::unflattenData($flattened);
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
            'int', 'integer' => (int)$value,
            'float', 'double', 'real' => (float)$value,
            'bool', 'boolean' => $this->castToBoolean($value),
            'string' => $this->castToString($value),
            'array' => is_array($value)
                ? $value
                : (is_string($value)
                    ? (json_decode($value, true) ?: [$value])
                    : [$value]),
            'object' => is_object($value) ? $value : (object)(
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

    protected function buildMessageTokens(
        string $field,
        string $fieldLabel,
        string $ruleName,
        mixed $value,
        object $rule,
        array $data,
        array $rulePlaceholders = [],
    ): array {
        $tokens = [
            'field' => $fieldLabel,
            'attribute' => $fieldLabel,
            'key' => $field,
            'rule' => $ruleName,
            'value' => $this->valueToString($value),
            'input' => $this->valueToString($value),
        ];

        foreach ($rulePlaceholders as $token => $tokenValue) {
            $tokens[$token] = $tokenValue;
        }

        if (!isset($tokens['other']) && method_exists($rule, 'getOtherField')) {
            $otherField = $rule->getOtherField();
            if (is_string($otherField) && $otherField !== '') {
                $tokens['other'] = FieldAlias::get($otherField);
            }
        }

        if (!isset($tokens['other']) && method_exists($rule, 'getOtherFields')) {
            $otherFields = $rule->getOtherFields();
            if (is_array($otherFields) && !empty($otherFields)) {
                $tokens['other'] = implode(', ', array_map(
                    fn (mixed $other): string => FieldAlias::get((string)$other),
                    $otherFields,
                ));
            }
        }

        if (!isset($tokens['value']) && isset($data[$field])) {
            $tokens['value'] = $this->valueToString($data[$field]);
        }

        if (isset($tokens['other']) && is_string($tokens['other'])) {
            $tokens['other'] = $this->normalizeOtherPlaceholder($tokens['other']);
        }

        return $tokens;
    }

    protected function buildRulesCacheKey(array $rules): string
    {
        return hash(
            $this->resolveCacheHashAlgorithm(),
            $this->normalizeRulesForCache($rules),
        );
    }

    protected function castToBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (float)$value !== 0.0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return (bool)$value;
    }

    protected function castToDateTimeImmutable(mixed $value): mixed
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!is_scalar($value) || $value === '') {
            return $value;
        }

        try {
            return new \DateTimeImmutable((string)$value);
        } catch (\Throwable) {
            return $value;
        }
    }

    protected function castToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR);

            return is_string($encoded) ? $encoded : '';
        } catch (\Throwable) {
            return '';
        }
    }

    protected function collectExpensiveRules(
        array $rules,
        array $ruleNames,
        array $rulePlaceholders,
        mixed $value,
        string $field,
        string $fieldLabel,
        array $data,
        array &$batch,
    ): void {
        if (empty($rules)) {
            return;
        }

        foreach ($rules as $index => $rule) {
            $ruleName = $ruleNames[$index] ?? $this->compiler->getRuleNameForRule($rule);
            $tokens = $this->buildMessageTokens(
                $field,
                $fieldLabel,
                $ruleName,
                $value,
                $rule,
                $data,
                $rulePlaceholders[$index] ?? [],
            );
            $template = $this->resolveMessageTemplate($field, $ruleName);

            $batch[] = [
                'rule' => $rule,
                'rule_name' => $ruleName,
                'value' => $value,
                'field' => $field,
                'field_label' => $fieldLabel,
                'message' => $template !== null
                    ? $this->interpolateMessage($template, $tokens)
                    : $this->interpolateMessage($rule->message($fieldLabel), $tokens),
            ];
        }
    }

    protected function decodeJsonOrFallback(
        string $value,
        mixed $fallback = null,
    ): mixed {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $fallback;
        }
    }

    protected function defaultLocalePacks(): array
    {
        return [
            'en' => [
                'required' => 'The :field field is required.',
                'string' => 'The :field must be a string.',
                'integer' => 'The :field must be an integer.',
                'numeric' => 'The :field must be a number.',
                'array' => 'The :field must be an array.',
                'boolean' => 'The :field field must be true or false.',
                'email' => 'The :field must be a valid email address.',
                'min' => 'The :field must be at least :min.',
                'max' => 'The :field may not be greater than :max.',
                'between' => 'The :field must be between :min and :max.',
                'size' => 'The :field must be :size.',
                'digits' => 'The :field must be :digits digits.',
                'digits_between' => 'The :field must be between :min and :max digits.',
                'same' => 'The :field and :other must match.',
                'different' => 'The :field and :other must be different.',
                'in' => 'The selected :field is invalid.',
                'not_in' => 'The selected :field is invalid.',
                'unique' => 'The :field has already been taken.',
                'exists' => 'The selected :field is invalid.',
                '*' => 'The :field field is invalid.',
            ],
        ];
    }

    protected function evaluateCondition(
        mixed $condition,
        array $data,
        array $rules,
    ): bool {
        if (is_bool($condition)) {
            return $condition;
        }

        if (!is_callable($condition)) {
            return false;
        }

        try {
            return (bool)$condition($data, $rules, $this);
        } catch (\ArgumentCountError) {
            try {
                return (bool)$condition($data, $this);
            } catch (\ArgumentCountError) {
                try {
                    return (bool)$condition($data);
                } catch (\ArgumentCountError) {
                    return (bool)$condition();
                }
            }
        }
    }

    protected function executeBatchedRules(array &$context): void
    {
        if (
            empty($context['expensiveBatch'])
            || (!empty($context['errors']) && $this->stopOnFirstError)
        ) {
            return;
        }

        $this->batchExecutor->executeBatch(
            $context['expensiveBatch'],
            $context['errors'],
            $context['failures'],
        );

        if (!empty($context['errors'])) {
            $context['validated'] = array_diff_key(
                $context['validated'],
                $context['errors'],
            );
        }
    }

    protected function exportJsonSchema(): array
    {
        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($this->rules as $field => $definition) {
            $parsedRules = $this->parseRuleDefinitions($definition);
            $ruleNames = array_column($parsedRules, 'name');
            $property = ['type' => $this->inferJsonSchemaType($ruleNames)];

            foreach ($parsedRules as $rule) {
                $this->applyJsonSchemaRuleConstraint(
                    $property,
                    $rule['name'],
                    $rule['params'],
                );
            }

            if (in_array('nullable', $ruleNames, true)) {
                $type = $property['type'] ?? 'string';
                if (is_string($type)) {
                    $property['type'] = [$type, 'null'];
                } elseif (is_array($type) && !in_array('null', $type, true)) {
                    $property['type'][] = 'null';
                }
            }

            if (isset($this->schemaSanitizers[$field])) {
                $property['x-reqshield-sanitizers'] = $this->schemaSanitizers[$field];
            }

            if (isset($this->schemaCasts[$field])) {
                $property['x-reqshield-cast'] = $this->schemaCasts[$field];
            }

            $isRequired = isset($this->schema[$field]) && !$this->schema[$field]->isOptional;
            $this->addJsonSchemaProperty($schema, $field, $property, $isRequired);
        }

        $this->normalizeSchemaNode($schema);

        return $schema;
    }

    protected function extractRuleNames(string|array $definition): array
    {
        $parsed = $this->parseRuleDefinitions($definition);

        return array_values(array_unique(array_column($parsed, 'name')));
    }

    protected function getFieldsToValidate(
        array $data,
        ?array $schema = null,
    ): array {
        $schema ??= $this->schema;
        $fields = [];

        foreach (array_keys($data) as $field) {
            $fields[$field] = true;
        }

        foreach ($schema as $field => $node) {
            if (
                $node instanceof ValidationNode
                && (!$node->isOptional || $node->requiresValidationWhenMissing)
            ) {
                $fields[$field] = true;
            }
        }

        return array_keys($fields);
    }

    protected function hasRulePrefix(object $rule, string $prefix): bool
    {
        $class = $rule::class;
        $pos = strrpos($class, '\\');
        $shortName = $pos === false ? $class : substr($class, $pos + 1);

        return str_starts_with($shortName, $prefix);
    }

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

    protected function initializeValidationContext(): array
    {
        return [
            'errors' => [],
            'failures' => [],
            'validated' => [],
            'expensiveBatch' => [],
        ];
    }

    protected function interpolateMessage(string $template, array $tokens): string
    {
        if ($template === '' || !str_contains($template, ':')) {
            return $template;
        }

        $replace = [];
        foreach ($tokens as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $replace[":{$key}"] = $this->stringifyTokenValue($value);
        }

        return strtr($template, $replace);
    }

    protected function invokeConditionalCallback(
        callable $callback,
        array $data,
        array $rules,
    ): mixed {
        $workingRules = $rules;

        try {
            $result = $callback($data, $workingRules, $this);
        } catch (\ArgumentCountError) {
            try {
                $result = $callback($data, $this);
            } catch (\ArgumentCountError) {
                try {
                    $result = $callback($data);
                } catch (\ArgumentCountError) {
                    $result = $callback();
                }
            }
        }

        return $result;
    }

    protected function isSchemaRuleDefinition(array $definition): bool
    {
        return array_key_exists('rules', $definition)
            || array_key_exists('sanitize', $definition)
            || array_key_exists('sanitizers', $definition)
            || array_key_exists('cast', $definition)
            || array_key_exists('alias', $definition);
    }

    /**
     * Build locale fallback chain (e.g. en_US -> en-us -> en -> en).
     *
     * @return array<int,string>
     */
    protected function localeCandidates(string $locale): array
    {
        $normalized = trim($locale);
        if ($normalized === '') {
            return ['en'];
        }

        $candidates = [$normalized];
        $candidates[] = str_replace('-', '_', $normalized);
        $candidates[] = str_replace('_', '-', $normalized);

        if (str_contains($normalized, '_') || str_contains($normalized, '-')) {
            $parts = preg_split('/[-_]/', $normalized);
            if (is_array($parts) && isset($parts[0]) && $parts[0] !== '') {
                $candidates[] = strtolower($parts[0]);
            }
        }

        $candidates[] = 'en';

        return array_values(array_unique(array_map(
            fn (string $item): string => trim($item),
            $candidates,
        )));
    }

    protected function looksLikeFieldKey(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_.*-]+$/', $value) === 1;
    }

    protected function mergeCastMaps(): array
    {
        return array_merge($this->schemaCasts, $this->casts);
    }

    protected function mergeRuleSets(array $baseRules, array $incomingRules): array
    {
        return static::composeSchemas($baseRules, $incomingRules);
    }

    protected function mergeSanitizerMaps(): array
    {
        $merged = $this->schemaSanitizers;

        foreach ($this->sanitizers as $field => $pipeline) {
            if (isset($merged[$field])) {
                $merged[$field] = array_merge(
                    $this->normalizeSanitizerPipeline($merged[$field]),
                    $this->normalizeSanitizerPipeline($pipeline),
                );
                continue;
            }

            $merged[$field] = $pipeline;
        }

        return $merged;
    }

    protected function normalizeOtherPlaceholder(string $value): string
    {
        $parts = array_values(array_filter(
            array_map('trim', explode(',', $value)),
            fn (string $part): bool => $part !== '',
        ));

        if (empty($parts)) {
            return $value;
        }

        return implode(', ', array_map(
            function (string $part): string {
                if (!$this->looksLikeFieldKey($part)) {
                    return $part;
                }

                return FieldAlias::get($part);
            },
            $parts,
        ));
    }

    protected function normalizeRegexForJsonSchema(string $pattern): ?string
    {
        $trimmed = trim($pattern);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(.)(.*)\\1[imsxuADSUXJu]*$/', $trimmed, $matches) === 1) {
            return $matches[2];
        }

        return $trimmed;
    }

    /**
     * @return array{0:array,1:array,2:array}
     */
    protected function normalizeRuleDefinitions(array $rules): array
    {
        $normalized = [];
        $schemaSanitizers = [];
        $schemaCasts = [];

        foreach ($rules as $field => $definition) {
            if (!is_string($field)) {
                throw InvalidRuleException::invalidFormat(
                    (string)$field,
                    'Field names must be strings',
                );
            }

            if (is_string($definition)) {
                $normalized[$field] = $definition;
                continue;
            }

            if (!is_array($definition)) {
                throw InvalidRuleException::invalidFormat(
                    $field,
                    'Rules must be string or array',
                );
            }

            if (!$this->isSchemaRuleDefinition($definition)) {
                $normalized[$field] = $definition;
                continue;
            }

            $normalized[$field] = $this->normalizeRuleList(
                $field,
                $definition['rules'] ?? [],
            );

            if (array_key_exists('sanitize', $definition)) {
                $schemaSanitizers[$field] = $definition['sanitize'];
            } elseif (array_key_exists('sanitizers', $definition)) {
                $schemaSanitizers[$field] = $definition['sanitizers'];
            }

            if (array_key_exists('cast', $definition)) {
                $schemaCasts[$field] = $definition['cast'];
            }

            if (isset($definition['alias']) && is_string($definition['alias'])) {
                $this->fieldAliases[$field] = $definition['alias'];
            }
        }

        return [$normalized, $schemaSanitizers, $schemaCasts];
    }

    protected function normalizeRuleList(string $field, mixed $rules): string|array
    {
        if (is_string($rules) || is_array($rules)) {
            return $rules;
        }

        throw InvalidRuleException::invalidFormat(
            $field,
            'Schema "rules" must be string or array',
        );
    }

    protected function normalizeRulesForCache(mixed $value): string
    {
        if (is_array($value)) {
            $parts = [];

            foreach ($value as $key => $item) {
                $parts[] = $key . '=' . $this->normalizeRulesForCache($item);
            }

            return '[' . implode(',', $parts) . ']';
        }

        if (is_object($value)) {
            return get_class($value) . '#' . spl_object_id($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return (string)$value;
    }

    protected function normalizeSanitizerPipeline(mixed $pipeline): array
    {
        if (is_array($pipeline)) {
            return $pipeline;
        }

        if ($pipeline === null || $pipeline === '') {
            return [];
        }

        return [$pipeline];
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

    protected function normalizeWildcardField(string $field): string
    {
        return preg_replace('/\.\d+(?=\.|$)/', '.*', $field) ?? $field;
    }

    /**
     * @param string|array<int,mixed> $definition
     *
     * @return array<int,array{name:string,params:array<int,mixed>}>
     */
    protected function parseRuleDefinitions(string|array $definition): array
    {
        $rules = is_string($definition) ? explode('|', $definition) : $definition;
        $parsed = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                [$name, $params] = $this->parseRuleExpression($rule);
                $parsed[] = ['name' => $name, 'params' => $params];
                continue;
            }

            if (is_object($rule)) {
                $parsed[] = [
                    'name' => $this->compiler->getRuleNameForRule($rule),
                    'params' => [],
                ];
            }
        }

        return $parsed;
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    protected function parseRuleExpression(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $rawParams = $parts[1] ?? '';

        if ($rawParams === '') {
            return [$name, []];
        }

        if (in_array($name, ['regex', 'not_regex'], true)) {
            return [$name, [$rawParams]];
        }

        return [$name, array_values(array_filter(
            explode(',', $rawParams),
            fn (string $value): bool => $value !== '',
        ))];
    }

    protected function prepareNestedData(
        array $data,
        array &$schema,
        ?array $activeRules = null,
        ?string $activeRulesCacheKey = null,
    ): array {
        $rules = $activeRules ?? $this->rules;
        $rulesCacheKey = $activeRulesCacheKey ?? $this->rulesCacheKey;

        $hasNestedRules = array_any(
            array_keys($rules),
            fn ($field) => str_contains($field, '.') || str_contains($field, '*'),
        );

        if (!$hasNestedRules) {
            return $data;
        }

        $hasWildcardRules = array_any(
            array_keys($rules),
            fn ($field) => str_contains($field, '*'),
        );

        if ($hasWildcardRules) {
            $cacheKey = implode(
                ':',
                [
                    $rulesCacheKey,
                    $this->nestedFlattenMode,
                    NestedValidator::shapeSignature($data),
                ],
            );

            if (!isset($this->wildcardSchemaCache[$cacheKey])) {
                $parsedRules = NestedValidator::parseRules($rules);
                $expandedRules = NestedValidator::expandWildcards($data, $parsedRules);
                $this->wildcardSchemaCache[$cacheKey] = $this->compiler->compile(
                    $expandedRules,
                );
            }

            $schema = $this->wildcardSchemaCache[$cacheKey];
        }

        if ($this->nestedFlattenMode === 'required') {
            return NestedValidator::flattenForPaths($data, array_keys($schema));
        }

        return NestedValidator::flattenData($data);
    }

    protected function prepareRuntimeRules(array $data): array
    {
        if (empty($this->conditionalRules) && empty($this->whenCallbacks)) {
            return $this->rules;
        }

        $activeRules = $this->rules;

        foreach ($this->conditionalRules as $conditionalRule) {
            $condition = $conditionalRule['condition'] ?? null;
            if (!$this->evaluateCondition($condition, $data, $activeRules)) {
                continue;
            }

            $activeRules = $this->mergeRuleSets(
                $activeRules,
                [$conditionalRule['field'] => $conditionalRule['rules']],
            );
        }

        foreach ($this->whenCallbacks as $whenCallback) {
            $conditionMet = $this->evaluateCondition(
                $whenCallback['condition'] ?? false,
                $data,
                $activeRules,
            );

            $callback = $conditionMet
                ? ($whenCallback['callback'] ?? null)
                : ($whenCallback['default'] ?? null);

            if (!is_callable($callback)) {
                continue;
            }

            $result = $this->invokeConditionalCallback($callback, $data, $activeRules);
            if ($result === null) {
                continue;
            }

            if (!is_array($result)) {
                throw InvalidRuleException::invalidFormat(
                    'when',
                    'When callback must return an array of rules or null',
                );
            }

            if (!empty($result)) {
                $activeRules = $this->mergeRuleSets($activeRules, $result);
            }
        }

        return $activeRules;
    }

    protected function primaryJsonSchemaType(string|array $type): string
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

    protected function processFieldValidation(
        string $field,
        mixed $value,
        ValidationNode $node,
        array $data,
        array &$context,
    ): bool {
        $fieldLabel = FieldAlias::get($field);

        if (
            $node->hasExcludeRules
            && $this->shouldExcludeField($node, $field, $value, $data)
        ) {
            return true;
        }

        $fieldFailFast = $this->failFast || $node->hasBailRule;
        $hasError = false;

        if (
            !$this->validatePhase(
                $node->cheapRules,
                $node->cheapRuleNames,
                $node->cheapRulePlaceholders,
                $value,
                $field,
                $fieldLabel,
                $data,
                $context['errors'],
                $context['failures'],
                $fieldFailFast,
            )
        ) {
            $hasError = true;
        }

        if (!$hasError || !$fieldFailFast) {
            if (
                !$this->validatePhase(
                    $node->mediumRules,
                    $node->mediumRuleNames,
                    $node->mediumRulePlaceholders,
                    $value,
                    $field,
                    $fieldLabel,
                    $data,
                    $context['errors'],
                    $context['failures'],
                    $fieldFailFast,
                )
            ) {
                $hasError = true;
            }
        }

        if (!$hasError || !$fieldFailFast) {
            $this->collectExpensiveRules(
                $node->expensiveRules,
                $node->expensiveRuleNames,
                $node->expensiveRulePlaceholders,
                $value,
                $field,
                $fieldLabel,
                $data,
                $context['expensiveBatch'],
            );
        }

        if (!$hasError) {
            $context['validated'][$field] = $value;
        }

        return !$hasError;
    }

    protected function resolveCacheHashAlgorithm(): string
    {
        static $checked = false;

        if (!$checked && !in_array('xxh3', hash_algos(), true)) {
            throw new \RuntimeException(
                'Hash algorithm "xxh3" is required but not available.',
            );
        }

        $checked = true;

        return 'xxh3';
    }

    protected function resolveCustomMessage(string $field, string $ruleName): ?string
    {
        $normalizedField = $this->normalizeWildcardField($field);
        $candidates = [
            "{$field}.{$ruleName}",
            "{$field}.*",
            "*.{$ruleName}",
        ];

        if ($normalizedField !== $field) {
            $candidates[] = "{$normalizedField}.{$ruleName}";
            $candidates[] = "{$normalizedField}.*";
        }

        $candidates[] = $field;

        if ($normalizedField !== $field) {
            $candidates[] = $normalizedField;
        }

        $candidates[] = $ruleName;

        foreach ($candidates as $key) {
            if (array_key_exists($key, $this->customMessageExact)) {
                return $this->customMessageExact[$key];
            }

            if (array_key_exists($key, $this->customMessageWildcard)) {
                return $this->customMessageWildcard[$key];
            }
        }

        foreach ($this->customMessageWildcardPatterns as $entry) {
            if (preg_match($entry['pattern'], "{$field}.{$ruleName}") === 1) {
                return $entry['message'];
            }
        }

        return null;
    }

    protected function resolveLocaleMessage(string $ruleName): ?string
    {
        foreach ($this->localeCandidates($this->locale) as $candidate) {
            $localePack = $this->localePacks[$candidate] ?? null;
            if (!is_array($localePack)) {
                continue;
            }

            if (isset($localePack[$ruleName]) && is_string($localePack[$ruleName])) {
                return $localePack[$ruleName];
            }

            if (isset($localePack['*']) && is_string($localePack['*'])) {
                return $localePack['*'];
            }
        }

        return null;
    }

    protected function resolveMessageTemplate(
        string $field,
        string $ruleName,
    ): ?string {
        $custom = $this->resolveCustomMessage($field, $ruleName);
        if ($custom !== null) {
            return $custom;
        }

        if (!$this->localeMessagesEnabled) {
            return null;
        }

        return $this->resolveLocaleMessage($ruleName);
    }

    protected function shouldExcludeField(
        ValidationNode $node,
        string $field,
        mixed $value,
        array $data,
    ): bool {
        foreach ($node->getAllRules() as $rule) {
            if (!$this->hasRulePrefix($rule, 'Exclude')) {
                continue;
            }

            if (!$rule->passes($value, $field, $data)) {
                return true;
            }
        }

        return false;
    }

    protected function shouldSkipOptionalField(
        ValidationNode $node,
        mixed $value,
        bool $fieldExists,
    ): bool {
        if (!$node->isOptional || $node->requiresValidationWhenMissing) {
            return false;
        }

        if ($node->hasFilledRule && $fieldExists) {
            return false;
        }

        if (!$fieldExists) {
            return true;
        }

        return $value === null
            || ($value === '' || (is_string($value) && trim($value) === ''))
            || (is_countable($value) && count($value) === 0);
    }

    protected function stringifyTokenValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        if (is_array($value)) {
            return implode(', ', array_map(
                fn (mixed $item): string => $this->stringifyTokenValue($item),
                $value,
            ));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string)$value;
        }

        try {
            return (string)json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '';
        }
    }

    protected function validatePhase(
        array $rules,
        array $ruleNames,
        array $rulePlaceholders,
        mixed $value,
        string $field,
        string $fieldLabel,
        array $data,
        array &$errors,
        array &$failures,
        bool $stopOnFirstFailure,
    ): bool {
        if (empty($rules)) {
            return true;
        }

        $hasError = false;

        foreach ($rules as $index => $rule) {
            $ruleName = $ruleNames[$index] ?? $this->compiler->getRuleNameForRule($rule);

            if ($this->hasRulePrefix($rule, 'Exclude')) {
                continue;
            }

            if ($rule->passes($value, $field, $data)) {
                continue;
            }

            $tokens = $this->buildMessageTokens(
                $field,
                $fieldLabel,
                $ruleName,
                $value,
                $rule,
                $data,
                $rulePlaceholders[$index] ?? [],
            );

            $template = $this->resolveMessageTemplate($field, $ruleName);
            $message = $template !== null
                ? $this->interpolateMessage($template, $tokens)
                : $this->interpolateMessage($rule->message($fieldLabel), $tokens);

            $errors[$field][] = $message;
            $failures[] = [
                'field' => $field,
                'rule' => $ruleName,
                'message' => $message,
                'value' => $value,
            ];
            $hasError = true;

            if ($stopOnFirstFailure) {
                return false;
            }
        }

        return !$hasError;
    }

    protected function valueToString(mixed $value): string
    {
        return $this->stringifyTokenValue($value);
    }

    protected function wildcardPatternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');

        return '/^' . str_replace('\*', '[^.]+', $escaped) . '$/';
    }
}

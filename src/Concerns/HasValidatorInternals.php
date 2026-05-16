<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;
use Infocyph\ReqShield\Support\HashAlgorithm;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Support\RuleExpressionParser;
use Infocyph\ReqShield\Support\ValidationNode;
use Infocyph\ReqShield\Support\ValidationPlan;
use Infocyph\ReqShield\Support\WildcardPath;

/**
 * @phpstan-type RuleMap array<int|string, mixed>
 * @phpstan-type RulePlaceholderMap array<int, array<string, mixed>>
 * @phpstan-type ParsedRule array{name:string, params:array<int, mixed>}
 * @phpstan-type ParsedRuleList array<int, ParsedRule>
 * @phpstan-type RulePartitionBucket array{
 *   rules:array<int, Rule>,
 *   ruleNames:array<int, string>,
 *   rulePlaceholders:array<int, array<string, mixed>>
 * }
 * @phpstan-type RulePartition array{
 *   inline:RulePartitionBucket,
 *   batch:RulePartitionBucket
 * }
 * @phpstan-type ValidationFailure array{
 *   field:string,
 *   rule:string,
 *   message:string,
 *   value:mixed
 * }
 * @phpstan-type ExpensiveBatchItem array{
 *   rule:Rule,
 *   rule_name:string,
 *   value:mixed,
 *   field:string,
 *   field_label:string,
 *   message_resolver:callable(): string
 * }
 * @phpstan-type ValidationContext array{
 *   errors:array<string, array<int, string>>,
 *   failures:array<int, ValidationFailure>,
 *   validated:array<string, mixed>,
 *   expensiveBatch:array<int, ExpensiveBatchItem>
 * }
 */
trait HasValidatorInternals
{
    /** @var array<string, int|null> */
    protected static array $callableMaxArityCache = [];

    /** @param array<int|string, mixed> $definition */
    protected function appendSchemaAliasDefinition(
        string $field,
        array $definition,
    ): void {
        if (isset($definition['alias']) && is_string($definition['alias'])) {
            $this->fieldAliases[$field] = $definition['alias'];
        }
    }

    /**
     * @param array<int|string, mixed> $definition
     * @param array<string, mixed> $schemaCasts
     */
    protected function appendSchemaCastDefinition(
        string $field,
        array $definition,
        array &$schemaCasts,
    ): void {
        if (array_key_exists('cast', $definition)) {
            $schemaCasts[$field] = $definition['cast'];
        }
    }

    /**
     * @param array<int|string, mixed> $definition
     * @param array<string, mixed> $schemaSanitizers
     */
    protected function appendSchemaSanitizerDefinition(
        string $field,
        array $definition,
        array &$schemaSanitizers,
    ): void {
        if (array_key_exists('sanitize', $definition)) {
            $schemaSanitizers[$field] = $definition['sanitize'];

            return;
        }

        if (array_key_exists('sanitizers', $definition)) {
            $schemaSanitizers[$field] = $definition['sanitizers'];
        }
    }

    /**
     * @param RuleMap $activeRules
     * @param array<int|string, mixed> $data
     * @return RuleMap
     */
    protected function applyConditionalRuntimeRules(
        array $activeRules,
        array $data,
    ): array {
        foreach ($this->conditionalRules as $conditionalRule) {
            $condition = $conditionalRule['condition'];
            if (!$this->evaluateCondition($condition, $data, $activeRules)) {
                continue;
            }

            $activeRules = $this->mergeRuleSets(
                $activeRules,
                [$conditionalRule['field'] => $conditionalRule['rules']],
            );
        }

        return $activeRules;
    }

    /**
     * @param RuleMap $activeRules
     * @param array<int|string, mixed> $data
     * @return RuleMap
     */
    protected function applyWhenRuntimeRules(
        array $activeRules,
        array $data,
    ): array {
        foreach ($this->whenCallbacks as $whenCallback) {
            $callback = $this->resolveWhenCallback($whenCallback, $data, $activeRules);
            if (!is_callable($callback)) {
                continue;
            }

            $result = $this->invokeConditionalCallback(
                $callback,
                $data,
                $activeRules,
            );
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
                $activeRules = $this->mergeRuleSets(
                    $activeRules,
                    $this->normalizeRuntimeRules($result),
                );
            }
        }

        return $activeRules;
    }

    /** @return array<int|string, mixed> */
    protected function assertRuleDefinitionIsArray(
        string $field,
        mixed $definition,
    ): array {
        if (is_array($definition)) {
            return $definition;
        }

        throw InvalidRuleException::invalidFormat(
            $field,
            'Rules must be string or array',
        );
    }

    protected function assertRuleFieldIsString(mixed $field): string
    {
        if (is_string($field)) {
            return $field;
        }

        throw InvalidRuleException::invalidFormat(
            get_debug_type($field),
            'Field names must be strings',
        );
    }

    protected function callableArityCacheKey(callable $callback): string
    {
        if ($callback instanceof \Closure) {
            return 'closure:' . spl_object_id($callback);
        }

        if (is_array($callback)) {
            $target = is_object($callback[0])
                ? $callback[0]::class . '#' . spl_object_id($callback[0])
                : $callback[0];

            return 'array:' . $target . '::' . $callback[1];
        }

        if (is_object($callback)) {
            return 'invokable:' . $callback::class . '#' . spl_object_id($callback);
        }

        if (is_string($callback)) {
            return 'string:' . $callback;
        }

        return 'callable:' . spl_object_id(\Closure::fromCallable($callback));
    }

    protected function getCachedWildcardPlan(string $cacheKey): ?ValidationPlan
    {
        if (!isset($this->wildcardSchemaCache[$cacheKey])) {
            return null;
        }

        $cached = $this->wildcardSchemaCache[$cacheKey];
        unset($this->wildcardSchemaCache[$cacheKey]);
        $this->wildcardSchemaCache[$cacheKey] = $cached;

        return $cached;
    }

    /** @param array<int, mixed> $args */
    protected function invokeCallbackWithSupportedArity(
        callable $callback,
        array $args,
    ): mixed {
        $maxArity = $this->resolveCallableMaxArity($callback);
        $invokeArgs = $maxArity === null
            ? $args
            : array_slice($args, 0, min($maxArity, count($args)));

        return $callback(...$invokeArgs);
    }

    /**
     * @param array<int|string, mixed> $data
     * @param RuleMap $rules
     */
    protected function invokeConditionalCallback(
        callable $callback,
        array $data,
        array $rules,
    ): mixed {
        return $this->invokeCallbackWithSupportedArity(
            $callback,
            [$data, $rules, $this],
        );
    }

    /** @param array<int|string, mixed> $definition */
    protected function isSchemaRuleDefinition(array $definition): bool
    {
        return array_key_exists('rules', $definition)
            || array_key_exists('sanitize', $definition)
            || array_key_exists('sanitizers', $definition)
            || array_key_exists('cast', $definition)
            || array_key_exists('alias', $definition);
    }

    /** @return array<int, string> */
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
            if (is_array($parts) && $parts[0] !== '') {
                $candidates[] = strtolower($parts[0]);
            }
        }

        $candidates[] = 'en';

        return array_values(array_unique(array_map(
            trim(...),
            $candidates,
        )));
    }

    protected function looksLikeFieldKey(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9_.*-]+$/', $value) === 1;
    }

    /** @return array<string, mixed> */
    protected function mergeCastMaps(): array
    {
        return array_merge($this->schemaCasts, $this->casts);
    }

    /**
     * @param RuleMap $baseRules
     * @param RuleMap $incomingRules
     * @return RuleMap
     */
    protected function mergeRuleSets(array $baseRules, array $incomingRules): array
    {
        return static::composeSchemas($baseRules, $incomingRules);
    }

    /** @return array<string, mixed> */
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
            array_map(trim(...), explode(',', $value)),
            fn(string $part): bool => $part !== '',
        ));

        if (empty($parts)) {
            return $value;
        }

        return implode(', ', array_map(
            function (string $part): string {
                if (!$this->looksLikeFieldKey($part)) {
                    return $part;
                }

                return $this->fieldAliasResolver->get($part);
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
     * @param array<string,mixed> $rules
     *
     * @return array{
     *   0:array<string,mixed>,
     *   1:array<string,mixed>,
     *   2:array<string,mixed>
     * }
     */
    protected function normalizeRuleDefinitions(array $rules): array
    {
        /** @var array<string, string|array<int, mixed>> $normalized */
        $normalized = [];
        /** @var array<string, mixed> $schemaSanitizers */
        $schemaSanitizers = [];
        /** @var array<string, mixed> $schemaCasts */
        $schemaCasts = [];

        foreach ($rules as $field => $definition) {
            $this->normalizeSingleRuleDefinition(
                $field,
                $definition,
                $normalized,
                $schemaSanitizers,
                $schemaCasts,
            );
        }

        return [$normalized, $schemaSanitizers, $schemaCasts];
    }

    /** @return string|array<int, mixed> */
    protected function normalizeRuleList(string $field, mixed $rules): string|array
    {
        if (is_string($rules)) {
            return $rules;
        }

        if (is_array($rules)) {
            return array_values($rules);
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
            return $value::class . '#' . spl_object_id($value);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }

    /**
     * @param array<int|string, mixed> $rules
     * @return RuleMap
     */
    protected function normalizeRuntimeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $field => $definition) {
            if (!is_string($field)) {
                throw InvalidRuleException::invalidFormat(
                    get_debug_type($field),
                    'Runtime rule keys must be strings',
                );
            }

            if (is_string($definition)) {
                $normalized[$field] = $definition;

                continue;
            }

            if (is_array($definition)) {
                $normalized[$field] = array_values($definition);

                continue;
            }

            throw InvalidRuleException::invalidFormat(
                $field,
                'Runtime rule definitions must be strings or arrays',
            );
        }

        return $normalized;
    }

    /** @return array<int, mixed> */
    protected function normalizeSanitizerPipeline(mixed $pipeline): array
    {
        if (is_array($pipeline)) {
            return array_values($pipeline);
        }

        if ($pipeline === null || $pipeline === '') {
            return [];
        }

        return [$pipeline];
    }

    /** @param array<int|string, mixed> $node */
    protected function normalizeSchemaNode(array &$node): void
    {
        $required = $node['required'] ?? null;
        if (is_array($required)) {
            $required = array_values(array_filter($required, is_string(...)));
            $node['required'] = array_values(array_unique($required));
        }

        $properties = $node['properties'] ?? null;
        if (is_array($properties)) {
            foreach ($properties as $key => $childNode) {
                if (!is_array($childNode)) {
                    continue;
                }

                $this->normalizeSchemaNode($childNode);
                $properties[$key] = $childNode;
            }

            $node['properties'] = $properties;
        }

        $items = $node['items'] ?? null;
        if (is_array($items)) {
            $this->normalizeSchemaNode($items);
            $node['items'] = $items;
        }
    }

    /**
     * @param array<string, string|array<int, mixed>> $normalized
     * @param array<string, mixed> $schemaSanitizers
     * @param array<string, mixed> $schemaCasts
     */
    protected function normalizeSingleRuleDefinition(
        mixed $field,
        mixed $definition,
        array &$normalized,
        array &$schemaSanitizers,
        array &$schemaCasts,
    ): void {
        $field = $this->assertRuleFieldIsString($field);

        if (is_string($definition)) {
            $normalized[$field] = $definition;

            return;
        }

        $definition = $this->assertRuleDefinitionIsArray($field, $definition);
        if (!$this->isSchemaRuleDefinition($definition)) {
            $normalized[$field] = array_values($definition);

            return;
        }

        $normalized[$field] = $this->normalizeRuleList(
            $field,
            $definition['rules'] ?? [],
        );
        $this->appendSchemaSanitizerDefinition(
            $field,
            $definition,
            $schemaSanitizers,
        );
        $this->appendSchemaCastDefinition($field, $definition, $schemaCasts);
        $this->appendSchemaAliasDefinition($field, $definition);
    }

    protected function normalizeWildcardField(string $field): string
    {
        return WildcardPath::normalizeIndexedField($field);
    }

    /**
     * @param string|array<int, mixed> $definition
     * @return ParsedRuleList
     */
    protected function parseRuleDefinitions(string|array $definition): array
    {
        $rules = is_string($definition) ? RuleExpressionParser::splitRules($definition) : $definition;
        $parsed = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                [$name, $params] = RuleExpressionParser::parse($rule);
                $parsed[] = ['name' => $name, 'params' => $params];

                continue;
            }

            if ($rule instanceof Rule) {
                $parsed[] = [
                    'name' => $this->compiler->getRuleNameForRule($rule),
                    'params' => [],
                ];
            }
        }

        return $parsed;
    }

    /**
     * @param array<int, Rule> $rules
     * @param array<int, string> $ruleNames
     * @param RulePlaceholderMap $rulePlaceholders
     * @return RulePartition
     */
    protected function partitionExpensiveRules(
        array $rules,
        array $ruleNames,
        array $rulePlaceholders,
    ): array {
        $partitioned = [
            'inline' => [
                'rules' => [],
                'ruleNames' => [],
                'rulePlaceholders' => [],
            ],
            'batch' => [
                'rules' => [],
                'ruleNames' => [],
                'rulePlaceholders' => [],
            ],
        ];

        foreach ($rules as $index => $rule) {
            $bucket = $rule->isBatchable() ? 'batch' : 'inline';

            $partitioned[$bucket]['rules'][] = $rule;
            $partitioned[$bucket]['ruleNames'][] = $ruleNames[$index]
                ?? $this->compiler->getRuleNameForRule($rule);
            $partitioned[$bucket]['rulePlaceholders'][] = $rulePlaceholders[$index]
                ?? [];
        }

        return $partitioned;
    }

    /**
     * @param array<int|string,mixed> $data
     * @param array<int|string,mixed>|null $activeRules
     *
     * @return array{0:array<int|string,mixed>,1:ValidationPlan}
     */
    protected function prepareNestedData(
        array $data,
        ValidationPlan $plan,
        ?array $activeRules = null,
        ?string $activeRulesCacheKey = null,
    ): array {
        $rules = $activeRules ?? $this->rules;
        $rulesCacheKey = $activeRulesCacheKey ?? $this->rulesCacheKey;

        $hasNestedRules = array_any(
            array_keys($rules),
            fn($field) => str_contains((string) $field, '.') || str_contains((string) $field, '*'),
        );

        if (!$hasNestedRules) {
            return [$data, $plan];
        }

        $hasWildcardRules = array_any(
            array_keys($rules),
            fn($field) => str_contains((string) $field, '*'),
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

            $cachedPlan = $this->getCachedWildcardPlan($cacheKey);
            if ($cachedPlan === null) {
                $parsedRules = NestedValidator::parseRules($rules);
                $expandedRules = NestedValidator::expandWildcards($data, $parsedRules);
                $cachedPlan = new ValidationPlan(
                    $this->compiler->compile($this->normalizeRuntimeRules($expandedRules)),
                );
                $this->rememberWildcardPlan($cacheKey, $cachedPlan);
            }

            $plan = $cachedPlan;
        }

        if ($this->nestedFlattenMode === 'required') {
            return [
                NestedValidator::flattenForPaths($data, array_values($plan->fields)),
                $plan,
            ];
        }

        return [NestedValidator::flattenData($data), $plan];
    }

    /**
     * @param array<int|string,mixed> $data
     *
     * @return array<int|string,mixed>
     */
    protected function prepareRuntimeRules(array $data): array
    {
        if (empty($this->conditionalRules) && empty($this->whenCallbacks)) {
            return $this->rules;
        }

        $activeRules = $this->rules;
        $activeRules = $this->applyConditionalRuntimeRules($activeRules, $data);

        return $this->applyWhenRuntimeRules($activeRules, $data);
    }

    /** @param string|array<int, string> $type */
    protected function primaryJsonSchemaType(string|array $type): string
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
     * @param array<int|string, mixed> $data
     * @param ValidationContext $context
     */
    protected function processExpensivePhases(
        ValidationNode $node,
        mixed $value,
        string $field,
        string $fieldLabel,
        array $data,
        array &$context,
        bool $fieldFailFast,
        bool $hasError,
    ): bool {
        if ($hasError && $fieldFailFast) {
            return true;
        }

        $partitioned = $this->partitionExpensiveRules(
            array_values($node->expensiveRules),
            array_values($node->expensiveRuleNames),
            $node->expensiveRulePlaceholders,
        );

        if (
            !empty($partitioned['inline']['rules'])
            && !$this->validatePhase(
                $partitioned['inline']['rules'],
                $partitioned['inline']['ruleNames'],
                $partitioned['inline']['rulePlaceholders'],
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
            $this->collectExpensiveRules(
                $partitioned['batch']['rules'],
                $partitioned['batch']['ruleNames'],
                $partitioned['batch']['rulePlaceholders'],
                $value,
                $field,
                $fieldLabel,
                $data,
                $context['expensiveBatch'],
            );
        }

        return $hasError;
    }

    /**
     * @param array<int|string, mixed> $data
     * @param ValidationContext $context
     */
    protected function processFieldValidation(
        string $field,
        mixed $value,
        ValidationNode $node,
        array $data,
        array &$context,
    ): bool {
        $fieldLabel = $this->fieldAliasResolver->get($field);
        if ($this->shouldBypassExcludedField($node, $field, $value, $data)) {
            return true;
        }

        $fieldFailFast = $this->failFast || $node->hasBailRule;
        $hasError = $this->validateCheapAndMediumPhases(
            $node,
            $value,
            $field,
            $fieldLabel,
            $data,
            $context,
            $fieldFailFast,
        );
        $hasError = $this->processExpensivePhases(
            $node,
            $value,
            $field,
            $fieldLabel,
            $data,
            $context,
            $fieldFailFast,
            $hasError,
        );

        if (!$hasError) {
            $context['validated'][$field] = $value;
        }

        return !$hasError;
    }

    protected function rememberCompiledSchema(
        string $cacheKey,
        ValidationPlan $plan,
    ): void {
        $this->compiledSchemaCache[$cacheKey] = $plan;

        if (count($this->compiledSchemaCache) <= self::MAX_COMPILED_SCHEMA_CACHE) {
            return;
        }

        array_shift($this->compiledSchemaCache);
    }

    protected function rememberWildcardPlan(
        string $cacheKey,
        ValidationPlan $plan,
    ): void {
        $this->wildcardSchemaCache[$cacheKey] = $plan;

        if (count($this->wildcardSchemaCache) <= self::MAX_WILDCARD_SCHEMA_CACHE) {
            return;
        }

        array_shift($this->wildcardSchemaCache);
    }

    protected function resolveCacheHashAlgorithm(): string
    {
        return HashAlgorithm::require('xxh3');
    }

    protected function resolveCallableMaxArity(callable $callback): ?int
    {
        $cacheKey = $this->callableArityCacheKey($callback);
        if (isset(self::$callableMaxArityCache[$cacheKey])) {
            return self::$callableMaxArityCache[$cacheKey];
        }

        try {
            $reflection = match (true) {
                is_array($callback) => new \ReflectionMethod($callback[0], $callback[1]),
                is_string($callback) && str_contains($callback, '::') => new \ReflectionMethod($callback),
                is_object($callback) && !($callback instanceof \Closure) && method_exists($callback, '__invoke') => new \ReflectionMethod($callback, '__invoke'),
                default => new \ReflectionFunction(\Closure::fromCallable($callback)),
            };
        } catch (\Throwable) {
            self::$callableMaxArityCache[$cacheKey] = null;

            return null;
        }

        if ($reflection->isVariadic()) {
            self::$callableMaxArityCache[$cacheKey] = null;

            return null;
        }

        $maxArity = $reflection->getNumberOfParameters();
        self::$callableMaxArityCache[$cacheKey] = $maxArity;

        return $maxArity;
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

    /** @param array<int|string,mixed> $activeRules */
    protected function resolvePlanForRules(
        array $activeRules,
        string $activeRulesCacheKey,
    ): ValidationPlan {
        if ($activeRulesCacheKey === $this->rulesCacheKey) {
            return $this->validationPlan;
        }

        if (isset($this->compiledSchemaCache[$activeRulesCacheKey])) {
            return $this->compiledSchemaCache[$activeRulesCacheKey];
        }

        $plan = new ValidationPlan($this->compiler->compile($activeRules));
        $this->rememberCompiledSchema($activeRulesCacheKey, $plan);

        return $plan;
    }

    /**
     * @param array<string, mixed> $whenCallback
     * @param array<int|string, mixed> $data
     * @param RuleMap $activeRules
     */
    protected function resolveWhenCallback(
        array $whenCallback,
        array $data,
        array $activeRules,
    ): ?callable {
        $conditionMet = $this->evaluateCondition(
            $whenCallback['condition'],
            $data,
            $activeRules,
        );

        $callback = $conditionMet
            ? $whenCallback['callback']
            : $whenCallback['default'];

        return is_callable($callback) ? $callback : null;
    }

    /** @param array<int|string, mixed> $data */
    protected function shouldBypassExcludedField(
        ValidationNode $node,
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return $node->hasExcludeRules
            && $this->shouldExcludeField($node, $field, $value, $data);
    }

    /** @param array<int|string, mixed> $data */
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
            return (string) $value;
        }

        if (is_array($value)) {
            return implode(', ', array_map(
                fn(mixed $item): string => $this->stringifyTokenValue($item),
                $value,
            ));
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * @param array<int|string, mixed> $data
     * @param ValidationContext $context
     */
    protected function validateCheapAndMediumPhases(
        ValidationNode $node,
        mixed $value,
        string $field,
        string $fieldLabel,
        array $data,
        array &$context,
        bool $fieldFailFast,
    ): bool {
        $hasError = !$this->validatePhase(
            array_values($node->cheapRules),
            array_values($node->cheapRuleNames),
            $node->cheapRulePlaceholders,
            $value,
            $field,
            $fieldLabel,
            $data,
            $context['errors'],
            $context['failures'],
            $fieldFailFast,
        );

        if ($hasError && $fieldFailFast) {
            return true;
        }

        if (
            !$this->validatePhase(
                array_values($node->mediumRules),
                array_values($node->mediumRuleNames),
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
            return true;
        }

        return false;
    }

    /**
     * @param array<int, Rule> $rules
     * @param array<int, string> $ruleNames
     * @param RulePlaceholderMap $rulePlaceholders
     * @param array<int|string, mixed> $data
     * @param array<string, array<int, string>> $errors
     * @param array<int, ValidationFailure> $failures
     */
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

            $message = $this->buildRuleFailureMessage(
                $rule,
                $ruleName,
                $value,
                $field,
                $fieldLabel,
                $data,
                $rulePlaceholders[$index] ?? [],
            );

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
        return WildcardPath::toRegex($pattern);
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Exceptions\InvalidRuleException;
use Infocyph\ReqShield\Support\HashAlgorithm;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Support\RuleExpressionParser;
use Infocyph\ReqShield\Support\ValidationNode;
use Infocyph\ReqShield\Support\ValidationPlan;
use Infocyph\ReqShield\Support\WildcardPath;

trait HasValidatorInternals
{
    /**
     * @var array<string,int|null>
     */
    protected static array $callableMaxArityCache = [];

    protected function appendSchemaAliasDefinition(
        string $field,
        array $definition,
    ): void {
        if (isset($definition['alias']) && is_string($definition['alias'])) {
            $this->fieldAliases[$field] = $definition['alias'];
        }
    }

    protected function appendSchemaCastDefinition(
        string $field,
        array $definition,
        array &$schemaCasts,
    ): void {
        if (array_key_exists('cast', $definition)) {
            $schemaCasts[$field] = $definition['cast'];
        }
    }

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

    protected function applyConditionalRuntimeRules(
        array $activeRules,
        array $data,
    ): array {
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

        return $activeRules;
    }

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
                $activeRules = $this->mergeRuleSets($activeRules, $result);
            }
        }

        return $activeRules;
    }

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
            (string)$field,
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
                ? get_class($callback[0]) . '#' . spl_object_id($callback[0])
                : (string)$callback[0];

            return 'array:' . $target . '::' . $callback[1];
        }

        if (is_object($callback)) {
            return 'invokable:' . get_class($callback) . '#' . spl_object_id($callback);
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
     * @return array{0:array,1:array,2:array}
     */
    protected function normalizeRuleDefinitions(array $rules): array
    {
        $normalized = [];
        $schemaSanitizers = [];
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
            $normalized[$field] = $definition;

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
                [$name, $params] = RuleExpressionParser::parse($rule);
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
     * Split expensive rules into direct and batched execution sets.
     *
     * @return array{
     *   inline: array{rules: array, ruleNames: array, rulePlaceholders: array},
     *   batch: array{rules: array, ruleNames: array, rulePlaceholders: array}
     * }
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
            fn ($field) => str_contains($field, '.') || str_contains($field, '*'),
        );

        if (!$hasNestedRules) {
            return [$data, $plan];
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

            $cachedPlan = $this->getCachedWildcardPlan($cacheKey);
            if ($cachedPlan === null) {
                $parsedRules = NestedValidator::parseRules($rules);
                $expandedRules = NestedValidator::expandWildcards($data, $parsedRules);
                $cachedPlan = new ValidationPlan($this->compiler->compile($expandedRules));
                $this->rememberWildcardPlan($cacheKey, $cachedPlan);
            }

            $plan = $cachedPlan;
        }

        if ($this->nestedFlattenMode === 'required') {
            return [
                NestedValidator::flattenForPaths($data, $plan->fields),
                $plan,
            ];
        }

        return [NestedValidator::flattenData($data), $plan];
    }

    protected function prepareRuntimeRules(array $data): array
    {
        if (empty($this->conditionalRules) && empty($this->whenCallbacks)) {
            return $this->rules;
        }

        $activeRules = $this->rules;
        $activeRules = $this->applyConditionalRuntimeRules($activeRules, $data);

        return $this->applyWhenRuntimeRules($activeRules, $data);
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
            $node->expensiveRules,
            $node->expensiveRuleNames,
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

    protected function resolveWhenCallback(
        array $whenCallback,
        array $data,
        array $activeRules,
    ): ?callable {
        $conditionMet = $this->evaluateCondition(
            $whenCallback['condition'] ?? false,
            $data,
            $activeRules,
        );

        $callback = $conditionMet
            ? ($whenCallback['callback'] ?? null)
            : ($whenCallback['default'] ?? null);

        return is_callable($callback) ? $callback : null;
    }

    protected function shouldBypassExcludedField(
        ValidationNode $node,
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return $node->hasExcludeRules
            && $this->shouldExcludeField($node, $field, $value, $data);
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
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return '';
        }
    }

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
        );

        if ($hasError && $fieldFailFast) {
            return true;
        }

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
            return true;
        }

        return false;
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

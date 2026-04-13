<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Contracts\Rule;

trait HasValidatorRuntime
{
    protected function appendFallbackValueToken(
        array &$tokens,
        string $field,
        array $data,
    ): void {
        if (!isset($tokens['value']) && isset($data[$field])) {
            $tokens['value'] = $this->valueToString($data[$field]);
        }
    }

    protected function appendJsonSchemaMetadataExtensions(
        array &$property,
        string $field,
    ): void {
        if (isset($this->schemaSanitizers[$field])) {
            $property['x-reqshield-sanitizers'] = $this->schemaSanitizers[$field];
        }

        if (isset($this->schemaCasts[$field])) {
            $property['x-reqshield-cast'] = $this->schemaCasts[$field];
        }
    }

    protected function appendOtherTokenFromMultiFieldRule(
        array &$tokens,
        object $rule,
    ): void {
        if (!method_exists($rule, 'getOtherFields')) {
            return;
        }

        $otherFields = $rule->getOtherFields();
        if (!is_array($otherFields) || empty($otherFields)) {
            return;
        }

        $tokens['other'] = implode(', ', array_map(
            fn(mixed $other): string => $this->fieldAliasResolver->get((string) $other),
            $otherFields,
        ));
    }

    protected function appendOtherTokenFromRule(
        array &$tokens,
        object $rule,
    ): void {
        if (!isset($tokens['other'])) {
            $this->appendOtherTokenFromSingleFieldRule($tokens, $rule);
        }

        if (!isset($tokens['other'])) {
            $this->appendOtherTokenFromMultiFieldRule($tokens, $rule);
        }
    }

    protected function appendOtherTokenFromSingleFieldRule(
        array &$tokens,
        object $rule,
    ): void {
        if (!method_exists($rule, 'getOtherField')) {
            return;
        }

        $otherField = $rule->getOtherField();
        if (!is_string($otherField) || $otherField === '') {
            return;
        }

        $tokens['other'] = $this->fieldAliasResolver->get($otherField);
    }

    protected function applyNullableTypeToJsonSchemaProperty(
        array &$property,
        array $ruleNames,
    ): void {
        if (!in_array('nullable', $ruleNames, true)) {
            return;
        }

        $type = $property['type'] ?? 'string';
        if (is_string($type)) {
            $property['type'] = [$type, 'null'];

            return;
        }

        if (is_array($type) && !in_array('null', $type, true)) {
            $property['type'][] = 'null';
        }
    }

    protected function applyRuleConstraintsToJsonSchemaProperty(
        array &$property,
        array $parsedRules,
    ): void {
        foreach ($parsedRules as $rule) {
            $this->applyJsonSchemaRuleConstraint(
                $property,
                $rule['name'],
                $rule['params'],
            );
        }
    }

    protected function baseMessageTokens(
        string $field,
        string $fieldLabel,
        string $ruleName,
        mixed $value,
    ): array {
        return [
            'field' => $fieldLabel,
            'attribute' => $fieldLabel,
            'key' => $field,
            'rule' => $ruleName,
            'value' => $this->valueToString($value),
            'input' => $this->valueToString($value),
        ];
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
        return $this->messageTokenBuilder->build(
            $field,
            $fieldLabel,
            $ruleName,
            $value,
            $rule,
            $data,
            $rulePlaceholders,
            fn(mixed $tokenValue): string => $this->stringifyTokenValue($tokenValue),
            fn(string $path): string => $this->fieldAliasResolver->get($path),
            fn(string $other): string => $this->normalizeOtherPlaceholder($other),
        );
    }

    /**
     * @return array{
     *   parsedRules: array<int,array{name:string,params:array<int,mixed>}>,
     *   ruleNames: array<int,string>
     * }
     */
    protected function buildRuleContextForJsonSchema(
        string|array $definition,
    ): array {
        $parsedRules = $this->parseRuleDefinitions($definition);

        return [
            'parsedRules' => $parsedRules,
            'ruleNames' => array_column($parsedRules, 'name'),
        ];
    }

    protected function buildRuleFailureMessage(
        Rule $rule,
        string $ruleName,
        mixed $value,
        string $field,
        string $fieldLabel,
        array $data,
        array $placeholders = [],
    ): string {
        $tokens = $this->buildMessageTokens(
            $field,
            $fieldLabel,
            $ruleName,
            $value,
            $rule,
            $data,
            $placeholders,
        );
        $template = $this->resolveMessageTemplate($field, $ruleName);

        return $template !== null
            ? $this->interpolateMessage($template, $tokens)
            : $this->interpolateMessage($rule->message($fieldLabel), $tokens);
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
            return (float) $value !== 0.0;
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

        return (bool) $value;
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
            return new \DateTimeImmutable((string) $value);
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
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
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
        foreach ($rules as $index => $rule) {
            $ruleName = $ruleNames[$index] ?? $this->compiler->getRuleNameForRule($rule);
            $placeholders = $rulePlaceholders[$index] ?? [];

            $batch[] = [
                'rule' => $rule,
                'rule_name' => $ruleName,
                'value' => $value,
                'field' => $field,
                'field_label' => $fieldLabel,
                // Build error messages lazily. Most expensive checks pass, so
                // this avoids token/template work in the hot path.
                'message_resolver' => fn(): string => $this->buildRuleFailureMessage(
                    $rule,
                    $ruleName,
                    $value,
                    $field,
                    $fieldLabel,
                    $data,
                    $placeholders,
                ),
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

        return (bool) $this->invokeCallbackWithSupportedArity(
            $condition,
            [$data, $rules, $this],
        );
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
        return $this->jsonSchemaExporter->export(
            $this->rules,
            $this->schema,
            $this->schemaSanitizers,
            $this->schemaCasts,
            fn(object $rule): string => $this->compiler->getRuleNameForRule($rule),
            fn(string $pattern): ?string => $this->normalizeRegexForJsonSchema($pattern),
        );
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

    protected function initializeJsonSchemaDocument(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];
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

    protected function mergeRulePlaceholders(
        array &$tokens,
        array $rulePlaceholders,
    ): void {
        foreach ($rulePlaceholders as $token => $tokenValue) {
            $tokens[$token] = $tokenValue;
        }
    }

    protected function normalizeOtherToken(array &$tokens): void
    {
        if (isset($tokens['other']) && is_string($tokens['other'])) {
            $tokens['other'] = $this->normalizeOtherPlaceholder($tokens['other']);
        }
    }
}

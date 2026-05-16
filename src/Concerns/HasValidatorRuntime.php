<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Concerns;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Support\InputCaster;
use Infocyph\ReqShield\Support\JsonSchemaTypeHelper;
use Infocyph\ReqShield\Support\ValueStringifier;

/**
 * @phpstan-type ParsedRule array{name:string, params:array<int, mixed>}
 * @phpstan-type ParsedRuleList array<int, ParsedRule>
 * @phpstan-type RuleMap array<int|string, mixed>
 * @phpstan-type RulePlaceholderMap array<int, array<string, mixed>>
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
 * @phpstan-type ValidationContext array<string, mixed>
 */
trait HasValidatorRuntime
{
    /**
     * @param array<string, mixed> $tokens
     * @param array<int|string, mixed> $data
     */
    protected function appendFallbackValueToken(
        array &$tokens,
        string $field,
        array $data,
    ): void {
        if (!isset($tokens['value']) && isset($data[$field])) {
            $tokens['value'] = $this->valueToString($data[$field]);
        }
    }

    /** @param array<string, mixed> $property */
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

    /** @param array<string, mixed> $tokens */
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

        $aliases = [];
        foreach ($otherFields as $other) {
            if (!is_scalar($other) && !(is_object($other) && method_exists($other, '__toString'))) {
                continue;
            }

            $aliases[] = $this->fieldAliasResolver->get($this->valueToString($other));
        }

        if ($aliases !== []) {
            $tokens['other'] = implode(', ', $aliases);
        }
    }

    /** @param array<string, mixed> $tokens */
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

    /** @param array<string, mixed> $tokens */
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

    /**
     * @param array<int|string, mixed> $property
     * @param array<int, string> $ruleNames
     */
    protected function applyNullableTypeToJsonSchemaProperty(
        array &$property,
        array $ruleNames,
    ): void {
        if (!in_array('nullable', $ruleNames, true)) {
            return;
        }

        JsonSchemaTypeHelper::applyNullableType($property);
    }

    /**
     * @param array<int|string, mixed> $property
     * @param ParsedRuleList $parsedRules
     */
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

    /** @return array<string, string> */
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

    /**
     * @param array<int|string, mixed> $data
     * @param array<string, mixed> $rulePlaceholders
     * @return array<int|string, mixed>
     */
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
     * @param string|array<int, mixed> $definition
     * @return array{parsedRules: ParsedRuleList, ruleNames: array<int, string>}
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

    /**
     * @param array<int|string, mixed> $data
     * @param array<string, mixed> $placeholders
     */
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

    /** @param RuleMap $rules */
    protected function buildRulesCacheKey(array $rules): string
    {
        return hash(
            $this->resolveCacheHashAlgorithm(),
            $this->normalizeRulesForCache($rules),
        );
    }

    protected function castToBoolean(mixed $value): bool
    {
        return InputCaster::toBoolean($value);
    }

    protected function castToDateTimeImmutable(mixed $value): mixed
    {
        return InputCaster::tryDateTimeImmutable($value) ?? $value;
    }

    protected function castToString(mixed $value): string
    {
        return ValueStringifier::stringify($value);
    }

    /**
     * @param array<int, Rule> $rules
     * @param array<int, string> $ruleNames
     * @param RulePlaceholderMap $rulePlaceholders
     * @param array<int|string, mixed> $data
     * @param array<int, ExpensiveBatchItem> $batch
     */
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

    /** @return array<string,array<string,mixed>> */
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

    /**
     * @param array<int|string, mixed> $data
     * @param RuleMap $rules
     */
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

    /** @param array<string, mixed> $context */
    protected function executeBatchedRules(array &$context): void
    {
        $batchRaw = isset($context['expensiveBatch']) && is_array($context['expensiveBatch'])
            ? $context['expensiveBatch']
            : [];
        $errorsRaw = isset($context['errors']) && is_array($context['errors'])
            ? $context['errors']
            : [];
        $failuresRaw = isset($context['failures']) && is_array($context['failures'])
            ? $context['failures']
            : [];

        $batch = $this->normalizeBatchPayload($batchRaw);
        $errors = $this->normalizeErrorPayload($errorsRaw);
        $failures = $this->normalizeFailurePayload($failuresRaw);

        if (
            empty($batch)
            || (!empty($errors) && $this->stopOnFirstError)
        ) {
            return;
        }

        $this->batchExecutor->executeBatch(
            $batch,
            $errors,
            $failures,
        );
        $context['expensiveBatch'] = $batch;
        $context['errors'] = $errors;
        $context['failures'] = $failures;

        if (!empty($errors)) {
            $validated = isset($context['validated']) && is_array($context['validated'])
                ? $context['validated']
                : [];
            $context['validated'] = array_diff_key(
                $validated,
                $errors,
            );
        }
    }

    /** @return array<int|string, mixed> */
    protected function exportJsonSchema(): array
    {
        return $this->jsonSchemaExporter->export(
            $this->rules,
            $this->schema,
            $this->schemaSanitizers,
            $this->schemaCasts,
            fn(object $rule): string => $rule instanceof Rule
                ? $this->compiler->getRuleNameForRule($rule)
                : '',
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

    /** @param array<int, string> $ruleNames */
    protected function inferJsonSchemaType(array $ruleNames): string
    {
        $typesByRule = [
            'array' => 'array',
            'is_list' => 'array',
            'integer' => 'integer',
            'numeric' => 'number',
            'decimal' => 'number',
            'boolean' => 'boolean',
        ];

        foreach ($ruleNames as $ruleName) {
            $mappedType = $typesByRule[$ruleName] ?? null;
            if (is_string($mappedType)) {
                return $mappedType;
            }
        }

        return 'string';
    }

    /**
     * @return array{
     *   '$schema':string,
     *   type:string,
     *   properties:array<string, mixed>,
     *   required:array<int, string>
     * }
     */
    protected function initializeJsonSchemaDocument(): array
    {
        $document = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
        ];
        $document['properties'] = [];
        $document['required'] = [];

        return $document;
    }

    /**
     * @return array{
     *   errors:array<string,array<int,string>>,
     *   failures:array<int,array{field:string,rule:string,message:string,value:mixed}>,
     *   validated:array<string,mixed>,
     *   expensiveBatch:array<int,ExpensiveBatchItem>
     * }
     */
    protected function initializeValidationContext(): array
    {
        return [
            'errors' => [],
            'failures' => [],
            'validated' => [],
            'expensiveBatch' => [],
        ];
    }

    /** @param array<int|string, mixed> $tokens */
    protected function interpolateMessage(string $template, array $tokens): string
    {
        if ($template === '' || !str_contains($template, ':')) {
            return $template;
        }

        $replace = [];
        foreach ($tokens as $key => $value) {
            if ($key === '') {
                continue;
            }

            $replace[":{$key}"] = $this->stringifyTokenValue($value);
        }

        return strtr($template, $replace);
    }

    /**
     * @param array<string, mixed> $tokens
     * @param array<string, mixed> $rulePlaceholders
     */
    protected function mergeRulePlaceholders(
        array &$tokens,
        array $rulePlaceholders,
    ): void {
        foreach ($rulePlaceholders as $token => $tokenValue) {
            $tokens[$token] = $tokenValue;
        }
    }

    /**
     * @param array<int|string, mixed> $batchRaw
     * @return array<int, array{
     *   rule:Rule,
     *   value:mixed,
     *   field:string,
     *   rule_name?:string,
     *   field_label?:string,
     *   message?:string,
     *   message_resolver?:callable(): string
     * }>
     */
    protected function normalizeBatchPayload(array $batchRaw): array
    {
        $batch = [];

        foreach ($batchRaw as $item) {
            if (!is_array($item)) {
                continue;
            }

            $rule = $item['rule'] ?? null;
            $field = $item['field'] ?? null;

            if (!$rule instanceof Rule || !is_string($field) || $field === '') {
                continue;
            }

            $normalized = [
                'rule' => $rule,
                'value' => $item['value'] ?? null,
                'field' => $field,
            ];

            if (isset($item['rule_name']) && is_string($item['rule_name'])) {
                $normalized['rule_name'] = $item['rule_name'];
            }

            if (isset($item['field_label']) && is_string($item['field_label'])) {
                $normalized['field_label'] = $item['field_label'];
            }

            if (isset($item['message']) && is_string($item['message'])) {
                $normalized['message'] = $item['message'];
            }

            if (isset($item['message_resolver']) && is_callable($item['message_resolver'])) {
                $resolver = $item['message_resolver'];
                $normalized['message_resolver'] = fn(): string => $this->stringifyTokenValue($resolver());
            }

            $batch[] = $normalized;
        }

        return $batch;
    }

    /**
     * @param array<int|string, mixed> $errorsRaw
     * @return array<string, array<int, string>>
     */
    protected function normalizeErrorPayload(array $errorsRaw): array
    {
        $errors = [];

        foreach ($errorsRaw as $field => $messages) {
            if (!is_string($field) || !is_array($messages)) {
                continue;
            }

            $errors[$field] = array_values(array_filter(
                $messages,
                is_string(...),
            ));
        }

        return $errors;
    }

    /**
     * @param array<int|string, mixed> $failuresRaw
     * @return array<int, array{field:string,rule:string,message:string,value:mixed}>
     */
    protected function normalizeFailurePayload(array $failuresRaw): array
    {
        $failures = [];

        foreach ($failuresRaw as $failure) {
            if (!is_array($failure)) {
                continue;
            }

            $field = $failure['field'] ?? null;
            $rule = $failure['rule'] ?? null;
            $message = $failure['message'] ?? null;

            if (!is_string($field) || !is_string($rule) || !is_string($message)) {
                continue;
            }

            $failures[] = [
                'field' => $field,
                'rule' => $rule,
                'message' => $message,
                'value' => $failure['value'] ?? null,
            ];
        }

        return $failures;
    }

    /** @param array<string, mixed> $tokens */
    protected function normalizeOtherToken(array &$tokens): void
    {
        if (isset($tokens['other']) && is_string($tokens['other'])) {
            $tokens['other'] = $this->normalizeOtherPlaceholder($tokens['other']);
        }
    }
}

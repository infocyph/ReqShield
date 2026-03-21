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

/**
 * High-performance validation engine with cost-based rule execution and
 * comprehensive features.
 *
 * This class provides a robust validation system with performance
 * optimizations and a clean API. It supports complex validation scenarios
 * including nested data, conditional rules, and custom validators.
 *
 * Key Features:
 * - Cost-based rule execution (cheap → medium → expensive)
 * - Batched database queries for efficient validation
 * - Nested validation with dot notation support
 * - Comprehensive error handling and reporting
 * - Extensible through custom validation rules
 * - Type-safe with strict type hints
 *
 * Performance Optimizations:
 * - Single-pass validation design
 * - Rule categorization by execution cost
 * - Minimal memory overhead
 * - Efficient error collection and reporting
 */
class Validator
{
    protected BatchExecutor $batchExecutor;

    protected SchemaCompiler $compiler;

    protected array $customMessages = [];

    protected bool $failFast = true;

    protected array $fieldAliases = [];

    protected bool $nestedValidation = false;

    protected ?array $ruleClassToNameMap = null;

    protected array $rules;

    protected array $schema;

    protected bool $stopOnFirstError = false;

    protected bool $throwOnFailure = false;
    public function __construct(array $rules, ?DatabaseProvider $db = null)
    {
        // Validate rules format
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

        $this->rules = $rules;
        $this->compiler = new SchemaCompiler();
        $this->schema = $this->compiler->compile($rules);
        $this->batchExecutor = new BatchExecutor($db);
    }

    public static function make(
        array $rules,
        ?DatabaseProvider $db = null,
    ): self {
        return new static($rules, $db);
    }

    /**
     * Enable nested validation with dot notation support.
     * IMPROVED: Actually implements nested validation
     *
     * @return self
     */
    public function enableNestedValidation(): self
    {
        $this->nestedValidation = true;
        return $this;
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

    /**
     * Register a custom rule.
     * IMPROVED: Better documentation
     *
     * @param string $name Rule name as used in validation strings
     * @param string $class Fully qualified class name implementing Rule
     *     interface
     *
     * @return self
     */
    public function registerRule(string $name, string $class): self
    {
        $this->compiler->registerRule($name, $class);
        return $this;
    }

    public function setCustomMessages(array $messages): self
    {
        $this->customMessages = $messages;
        return $this;
    }

    /**
     * Configures the validator to stop validation after the first error.
     *
     * When enabled, the validator will stop processing rules for a field as
     * soon as it encounters the first validation error. This can significantly
     * improve performance by skipping unnecessary validation rules.
     *
     * @param bool $failFast Whether to enable fail-fast mode (default: true)
     *
     * @return self Returns the current validator instance for method chaining
     *
     * @see Validator::setStopOnFirstError() To control stopping after the
     *   first field with errors
     * @see Validator::validate() To perform the validation
     * @example
     * // Enable fail-fast (default behavior)
     * $validator->setFailFast(true);
     *
     * // Disable to collect all validation errors
     * $validator->setFailFast(false);
     *
     * // Chaining example
     * $result = $validator
     *     ->setFailFast(true)
     *     ->setStopOnFirstError(true)
     *     ->validate($data);
     *
     */
    public function setFailFast(bool $failFast): self
    {
        $this->failFast = $failFast;
        return $this;
    }

    /**
     * Set field name aliases for better error messages.
     * IMPROVED: Uses new setBatch() method for better performance
     *
     * @param array $aliases Map of field names to display names
     *
     * @return self
     */
    public function setFieldAliases(array $aliases): self
    {
        $this->fieldAliases = $aliases;
        FieldAlias::setBatch($aliases);
        return $this;
    }

    public function setStopOnFirstError(bool $stop): self
    {
        $this->stopOnFirstError = $stop;
        return $this;
    }

    /**
     * Set whether to throw exception on validation failure.
     *
     * @param bool $throw True to throw ValidationException on failure
     *
     * @return self
     */
    public function throwOnFailure(bool $throw = true): self
    {
        $this->throwOnFailure = $throw;
        return $this;
    }

    /**
     * Validate the given data.
     *
     * IMPROVED:
     * - Handles required fields properly (validates schema fields not in data)
     * - Supports nested validation when enabled
     * - Throws exception when throwOnFailure is enabled
     * - Better performance with optimized field processing
     *
     * @param array $data Data to validate
     *
     * @return ValidationResult
     * @throws ValidationException When validation fails and throwOnFailure is
     *     enabled
     */
    public function validate(array $data): ValidationResult
    {
        $schema = $this->schema;

        // Handle nested validation if enabled
        if ($this->nestedValidation) {
            $data = $this->prepareNestedData($data, $schema);
        }

        $context = $this->initializeValidationContext();

        // Process data fields plus schema fields requiring implicit validation.
        $fieldsToValidate = $this->getFieldsToValidate($data, $schema);

        foreach ($fieldsToValidate as $field) {
            if (!isset($schema[$field])) {
                continue;
            }

            $node = $schema[$field];
            $fieldExists = array_key_exists($field, $data);
            $value = $fieldExists ? $data[$field] : null;

            // Quick skip for optional empty fields
            if ($this->shouldSkipOptionalField($node, $value, $fieldExists)) {
                continue;
            }

            // Process field through all validation phases
            if (!$this->processFieldValidation(
                $field,
                $value,
                $node,
                $data,
                $context,
            )) {
                if ($this->stopOnFirstError) {
                    break;
                }
            }
        }

        // Execute batched expensive rules if needed
        $this->executeBatchedRules($context);

        $result = new ValidationResult(
            $context['errors'],
            $context['validated'],
        );

        // IMPROVED: Handle throwOnFailure using ValidationException
        if ($this->throwOnFailure && $result->fails()) {
            throw new ValidationException(
                'Validation failed',
                $context['errors'],
                422,
            );
        }

        return $result;
    }

    /**
     * Collect expensive rules for batch execution.
     * OPTIMIZED: Direct array append, no checks needed
     *
     * @param array $rules Rules to collect
     * @param mixed $value Field value
     * @param string $field Field name
     * @param array $batch Batch array (passed by reference)
     */
    protected function collectExpensiveRules(
        array $rules,
        mixed $value,
        string $field,
        array &$batch,
    ): void {
        if (empty($rules)) {
            return;
        }

        foreach ($rules as $rule) {
            $batch[] = [
              'rule' => $rule,
              'value' => $value,
              'field' => $field,
            ];
        }
    }

    /**
     * Execute batched expensive rules.
     * OPTIMIZED: Single condition check, cleaner logic
     *
     * @param array $context Validation context (passed by reference)
     */
    protected function executeBatchedRules(array &$context): void
    {
        // Skip if no expensive rules or validation already failed
        if (empty($context['expensiveBatch'])
          || (!empty($context['errors']) && $this->stopOnFirstError)) {
            return;
        }

        $this->batchExecutor->executeBatch(
            $context['expensiveBatch'],
            $context['errors'],
        );

        // Remove validated data for fields with errors from expensive checks
        if (!empty($context['errors'])) {
            $context['validated'] = array_diff_key(
                $context['validated'],
                $context['errors'],
            );
        }
    }

    /**
     * Get list of fields to validate.
     * IMPROVED: Combines data fields + required schema fields
     *
     * This ensures that required fields missing from data are also validated,
     * fixing the issue where missing required fields were silently ignored.
     *
     * @param array $data Input data
     *
     * @return array List of field names to validate
     */
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

    /**
     * Fast helper for checking rule short-name prefixes.
     */
    protected function hasRulePrefix(object $rule, string $prefix): bool
    {
        $class = $rule::class;
        $pos = strrpos($class, '\\');
        $shortName = $pos === false ? $class : substr($class, $pos + 1);

        return str_starts_with($shortName, $prefix);
    }

    /**
     * Initialize validation context to avoid recreating arrays.
     *
     * @return array Initial context structure
     */
    protected function initializeValidationContext(): array
    {
        return [
          'errors' => [],
          'validated' => [],
          'expensiveBatch' => [],
        ];
    }

    /**
     * Convert indexed dot paths to wildcard paths for custom message lookup.
     *
     * Example: contacts.0.email => contacts.*.email
     */
    protected function normalizeWildcardField(string $field): string
    {
        return preg_replace('/\.\d+(?=\.|$)/', '.*', $field) ?? $field;
    }

    /**
     * Prepare nested data for validation.
     * IMPROVED: Implements nested validation support
     *
     * Flattens nested data structures to match dot notation rules.
     * For example: ['user' => ['email' => 'test@example.com']]
     * becomes: ['user.email' => 'test@example.com']
     *
     * @param array $data Nested data
     *
     * @return array Flattened data with dot notation keys
     */
    protected function prepareNestedData(array $data, array &$schema): array
    {
        $hasNestedRules = array_any(
            array_keys($this->rules),
            fn ($field) => str_contains($field, '.') || str_contains($field, '*'),
        );
        if (!$hasNestedRules) {
            return $data;
        }

        // Expand wildcard rules at runtime based on actual payload structure.
        $hasWildcardRules = array_any(
            array_keys($this->rules),
            fn ($field) => str_contains($field, '*'),
        );

        if ($hasWildcardRules) {
            $parsedRules = NestedValidator::parseRules($this->rules);
            $expandedRules = NestedValidator::expandWildcards($data, $parsedRules);
            $schema = $this->compiler->compile($expandedRules);
        }

        // Flatten nested data to match dot notation rules
        return NestedValidator::flattenData($data);
    }

    /**
     * Process all validation phases for a single field.
     *
     * OPTIMIZED:
     * - Combined phase processing in one method
     * - Reduced isset() calls (single hasError flag)
     * - Early skip of medium/expensive phases when failFast enabled
     * - Better performance through reduced branching
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param ValidationNode $node Validation node with rules
     * @param array $data All input data
     * @param array $context Validation context (passed by reference)
     *
     * @return bool True if validation passed, false otherwise
     */
    protected function processFieldValidation(
        string $field,
        mixed $value,
        ValidationNode $node,
        array $data,
        array &$context,
    ): bool {
        if ($node->hasExcludeRules && $this->shouldExcludeField(
            $node,
            $field,
            $value,
            $data,
        )) {
            return true;
        }

        $fieldFailFast = $this->failFast || $node->hasBailRule;
        $hasError = false;

        // Phase 1: Cheap rules (cost < 50)
        if (!$this->validatePhase(
            $node->cheapRules,
            $value,
            $field,
            $data,
            $context['errors'],
            $fieldFailFast,
        )) {
            $hasError = true;
        }

        // Phase 2: Medium rules (cost 50-99)
        // OPTIMIZED: Skip if already failed and failFast is enabled
        if (!$hasError || !$fieldFailFast) {
            if (!$this->validatePhase(
                $node->mediumRules,
                $value,
                $field,
                $data,
                $context['errors'],
                $fieldFailFast,
            )) {
                $hasError = true;
            }
        }

        // Phase 3: Collect expensive rules for batching
        // OPTIMIZED: Skip if already failed and failFast is enabled
        if (!$hasError || !$fieldFailFast) {
            $this->collectExpensiveRules(
                $node->expensiveRules,
                $value,
                $field,
                $context['expensiveBatch'],
            );
        }

        // Mark field as validated if no errors
        // OPTIMIZED: Single boolean check instead of isset()
        if (!$hasError) {
            $context['validated'][$field] = $value;
        }

        return !$hasError;
    }

    /**
     * Resolve custom message for a specific field/rule failure.
     *
     * Supported keys (in priority order):
     * - field.rule (e.g. email.required)
     * - field.* (any rule for that field)
     * - *.rule (any field for that rule)
     * - normalized_wildcard_field.rule (e.g. contacts.*.email.required)
     * - normalized_wildcard_field.*
     * - field
     * - normalized_wildcard_field
     * - rule (global fallback)
     */
    protected function resolveCustomMessage(string $field, object $rule): ?string
    {
        $ruleName = $this->resolveRuleName($rule);
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
            if (!array_key_exists($key, $this->customMessages)) {
                continue;
            }

            $message = $this->customMessages[$key];
            return is_string($message) ? $message : null;
        }

        return null;
    }

    /**
     * Resolve canonical rule name for custom message key lookup.
     */
    protected function resolveRuleName(object $rule): string
    {
        if ($this->ruleClassToNameMap === null) {
            $this->ruleClassToNameMap = [];
            foreach ($this->compiler->getRuleMap() as $name => $class) {
                $this->ruleClassToNameMap[ltrim($class, '\\')] = $name;
            }
        }

        $class = ltrim($rule::class, '\\');
        if (isset($this->ruleClassToNameMap[$class])) {
            return $this->ruleClassToNameMap[$class];
        }

        $pos = strrpos($class, '\\');
        $shortName = $pos === false ? $class : substr($class, $pos + 1);
        $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName) ?? $shortName);

        return str_ends_with($snake, '_rule')
            ? substr($snake, 0, -5)
            : $snake;
    }

    /**
     * Check if a field should be excluded from the validated payload.
     */
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

    /**
     * Check if optional field should be skipped.
     * OPTIMIZED: Inline isEmpty check for better performance
     *
     * @param ValidationNode $node Validation node
     * @param mixed $value Field value
     *
     * @return bool True if should skip validation
     */
    protected function shouldSkipOptionalField(
        ValidationNode $node,
        mixed $value,
        bool $fieldExists,
    ): bool {
        if (!$node->isOptional || $node->requiresValidationWhenMissing) {
            return false;
        }

        // Filled must run when the field exists, even if empty.
        if ($node->hasFilledRule && $fieldExists) {
            return false;
        }

        // Missing optional field with no implicit requirement can be skipped.
        if (!$fieldExists) {
            return true;
        }

        // Inline isEmpty for performance (avoid method call overhead)
        return $value === null
          || ($value === '' || (is_string($value) && trim($value) === ''))
          || (is_countable($value) && count($value) === 0);
    }

    /**
     * Validate a single phase of rules.
     *
     * OPTIMIZED:
     * - Early return on first error when failFast enabled
     * - Reduced branching
     * - Single error flag tracking
     *
     * @param array $rules Rules to validate
     * @param mixed $value Field value
     * @param string $field Field name
     * @param array $data All input data
     * @param array $errors Errors array (passed by reference)
     *
     * @return bool True if all rules passed, false otherwise
     */
    protected function validatePhase(
        array $rules,
        mixed $value,
        string $field,
        array $data,
        array &$errors,
        bool $stopOnFirstFailure,
    ): bool {
        if (empty($rules)) {
            return true;
        }

        $hasError = false;

        foreach ($rules as $rule) {
            // Exclusion directives are handled before phase execution.
            if ($this->hasRulePrefix($rule, 'Exclude')) {
                continue;
            }

            if ($rule->passes($value, $field, $data)) {
                continue;
            }

            // Rule failed - add error
            $message = $this->resolveCustomMessage($field, $rule) ?? $rule->message(
                FieldAlias::get($field),
            );

            // Initialize error array if needed
            if (!isset($errors[$field])) {
                $errors[$field] = [];
            }

            $errors[$field][] = $message;
            $hasError = true;

            // Fail fast if enabled
            if ($stopOnFirstFailure) {
                return false;
            }
        }

        return !$hasError;
    }

}

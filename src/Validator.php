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
 * Validator
 *
 * High-performance validation engine using cost-based rule execution.
 *
 * Features:
 * - Single-pass validation
 * - Rules grouped by cost (cheap → medium → expensive)
 * - Batched database queries
 * - Fail-fast optimization
 * - Nested field support with dot notation
 * - Required field detection
 *
 * IMPROVED:
 * - Fixed required field detection
 * - Implemented nested validation support
 * - Better error handling with throwOnFailure
 * - Optimized phase processing with early skips
 * - Reduced isset() calls for better performance
 * - Uses enhanced Support classes properly
 */
class Validator
{
    protected BatchExecutor $batchExecutor;

    protected SchemaCompiler $compiler;

    protected array $customMessages = [];

    protected bool $failFast = true;

    protected array $fieldAliases = [];

    protected bool $nestedValidation = false;

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
        // Handle nested validation if enabled
        if ($this->nestedValidation) {
            $data = $this->prepareNestedData($data);
        }

        $context = $this->initializeValidationContext();

        // IMPROVED: Process all fields (data + required schema fields)
        // This ensures required fields that are missing from data are validated
        $fieldsToValidate = $this->getFieldsToValidate($data);

        foreach ($fieldsToValidate as $field) {
            if (!isset($this->schema[$field])) {
                continue;
            }

            $node = $this->schema[$field];
            $value = $data[$field] ?? null;

            // Quick skip for optional empty fields
            if ($this->shouldSkipOptionalField($node, $value)) {
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
    protected function getFieldsToValidate(array $data): array
    {
        // Get all fields from data
        $fields = array_keys($data);

        // Add all required fields from schema that aren't in data
        foreach ($this->schema as $field => $node) {
            if (!$node->isOptional && !in_array($field, $fields, true)) {
                $fields[] = $field;
            }
        }

        return array_unique($fields);
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
    protected function prepareNestedData(array $data): array
    {
        $hasNestedRules = array_any(
            array_keys($this->schema),
            fn ($field) => str_contains($field, '.'),
        );
        if (!$hasNestedRules) {
            return $data;
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
        $hasError = false;

        // Phase 1: Cheap rules (cost < 50)
        if (!$this->validatePhase(
            $node->cheapRules,
            $value,
            $field,
            $data,
            $context['errors'],
        )) {
            $hasError = true;
        }

        // Phase 2: Medium rules (cost 50-99)
        // OPTIMIZED: Skip if already failed and failFast is enabled
        if (!$hasError || !$this->failFast) {
            if (!$this->validatePhase(
                $node->mediumRules,
                $value,
                $field,
                $data,
                $context['errors'],
            )) {
                $hasError = true;
            }
        }

        // Phase 3: Collect expensive rules for batching
        // OPTIMIZED: Skip if already failed and failFast is enabled
        if (!$hasError || !$this->failFast) {
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
    ): bool {
        if (!$node->isOptional) {
            return false;
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
    ): bool {
        if (empty($rules)) {
            return true;
        }

        $hasError = false;

        foreach ($rules as $rule) {
            if ($rule->passes($value, $field, $data)) {
                continue;
            }

            // Rule failed - add error
            $message = $this->customMessages[$field] ?? $rule->message(
                FieldAlias::get($field),
            );

            // Initialize error array if needed
            if (!isset($errors[$field])) {
                $errors[$field] = [];
            }

            $errors[$field][] = $message;
            $hasError = true;

            // Fail fast if enabled
            if ($this->failFast) {
                return false;
            }
        }

        return !$hasError;
    }

}

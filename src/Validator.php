<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Executors\BatchExecutor;
use Infocyph\ReqShield\Support\SchemaCompiler;
use Infocyph\ReqShield\Support\ValidationNode;
use Infocyph\ReqShield\Support\ValidationResult;
use Infocyph\ReqShield\Support\FieldAlias;
use Infocyph\ReqShield\Support\NestedValidator;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;

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
 * - Nested field support
 */
class Validator
{
    protected BatchExecutor $batchExecutor;

    protected SchemaCompiler $compiler;

    protected array $customMessages = [];

    /**
     * Field name aliases for better error messages.
     */
    protected array $fieldAliases = [];

    protected bool $failFast = true;

    /**
     * Whether nested validation is enabled.
     */
    protected bool $nestedValidation = false;

    protected array $schema;

        /**
     * Whether to throw exception on validation failure.
     */
    protected bool $throwOnFailure = false;

    protected bool $stopOnFirstError = false;

    public function __construct(array $rules, ?DatabaseProvider $db = null)
    {
        // Validate rules format
        if (empty($rules)) {
            throw InvalidRuleException::invalidFormat('rules', 'Rules array cannot be empty');
        }

        foreach ($rules as $field => $rule) {
            if (!is_string($field)) {
                throw InvalidRuleException::invalidFormat(
                    (string)$field,
                    'Field names must be strings'
                );
            }

            if (!is_string($rule) && !is_array($rule)) {
                throw InvalidRuleException::invalidFormat(
                    $field,
                    'Rules must be string or array'
                );
            }
        }

        $this->compiler = new SchemaCompiler();
        $this->schema = $this->compiler->compile($rules);
        $this->batchExecutor = new BatchExecutor($db);
    }

    public static function make(array $rules, ?DatabaseProvider $db = null): self
    {
        return new static($rules, $db);
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

    public function setStopOnFirstError(bool $stop): self
    {
        $this->stopOnFirstError = $stop;

        return $this;
    }

    /**
     * Validate the given data.
     *
     * OPTIMIZED: Single-pass validation with minimal loops
     */
    
    /**
     * Enable nested validation with dot notation support.
     *
     * @return self
     */
    public function enableNestedValidation(): self
    {
        $this->nestedValidation = true;
        return $this;
    }

    /**
     * Set field name aliases for better error messages.
     *
     * @param array $aliases Map of field names to display names
     * @return self
     */
    public function setFieldAliases(array $aliases): self
    {
        $this->fieldAliases = $aliases;
        FieldAlias::set($aliases);
        return $this;
    }

    /**
     * Set whether to throw exception on validation failure.
     *
     * @param bool $throw True to throw ValidationException on failure
     * @return self
     */
    public function throwOnFailure(bool $throw = true): self
    {
        $this->throwOnFailure = $throw;
        return $this;
    }

    public function validate(array $data): ValidationResult
    {
        $context = $this->initializeValidationContext();

        // Single pass through data with all phases
        foreach ($data as $field => $value) {
            if (! isset($this->schema[$field])) {
                continue;
            }

            $node = $this->schema[$field];

            // Quick skip for optional empty fields
            if ($this->shouldSkipOptionalField($node, $value)) {
                continue;
            }

            // Process field through all validation phases
            if (! $this->processFieldValidation($field, $value, $node, $data, $context)) {
                if ($this->stopOnFirstError) {
                    break;
                }
            }
        }

        // Execute batched expensive rules if needed
        $this->executeBatchedRules($context);

        return new ValidationResult($context['errors'], $context['validated']);
    }

    /**
     * Collect expensive rules for batch execution
     * OPTIMIZED: Direct array append, no checks needed
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
     * Execute batched expensive rules
     * OPTIMIZED: Single condition check, cleaner logic
     */
    protected function executeBatchedRules(array &$context): void
    {
        // Skip if no expensive rules or validation already failed
        if (empty($context['expensiveBatch'])
            || (! empty($context['errors']) && $this->stopOnFirstError)) {
            return;
        }

        $this->batchExecutor->executeBatch($context['expensiveBatch'], $context['errors']);

        // Remove validated data for fields with errors from expensive checks
        if (! empty($context['errors'])) {
            $context['validated'] = array_diff_key(
                $context['validated'],
                $context['errors'],
            );
        }
    }

    /**
     * Initialize validation context to avoid recreating arrays
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
     * Process all validation phases for a single field
     * OPTIMIZED: Combined phase processing in one method
     */
    protected function processFieldValidation(
        string $field,
        mixed $value,
        ValidationNode $node,
        array $data,
        array &$context,
    ): bool {
        // Phase 1: Cheap rules (cost < 50)
        if (! $this->validatePhase($node->cheapRules, $value, $field, $data, $context['errors'])) {
            return false;
        }

        // Phase 2: Medium rules (cost 50-99)
        if (! $this->validatePhase($node->mediumRules, $value, $field, $data, $context['errors'])) {
            return false;
        }

        // Phase 3: Collect expensive rules for batching
        $this->collectExpensiveRules($node->expensiveRules, $value, $field, $context['expensiveBatch']);

        // Mark field as validated if no errors
        if (! isset($context['errors'][$field])) {
            $context['validated'][$field] = $value;
        }

        return true;
    }

    /**
     * Check if optional field should be skipped
     * OPTIMIZED: Inline isEmpty check for better performance
     */
    protected function shouldSkipOptionalField(ValidationNode $node, mixed $value): bool
    {
        if (! $node->isOptional) {
            return false;
        }

        // Inline isEmpty for performance (avoid method call)
        return $value === null
            || ($value === '' || (is_string($value) && trim($value) === ''))
            || (is_countable($value) && count($value) === 0);
    }

    /**
     * Validate a single phase of rules
     * OPTIMIZED: Early return on first error, reduced branching
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

        foreach ($rules as $rule) {
            if ($rule->passes($value, $field, $data)) {
                continue;
            }

            // Rule failed - add error
            $message = $this->customMessages[$field] ?? $rule->message(FieldAlias::get($field));
            $errors[$field][] = $message;

            // Fail fast if enabled
            if ($this->failFast) {
                return false;
            }
        }

        // Return true if no errors were added for this field
        return ! isset($errors[$field]);
    }

}

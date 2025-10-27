<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Executors\BatchExecutor;

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
    protected bool $failFast = true;
    protected array $schema;
    protected bool $stopOnFirstError = false;

    public function __construct(array $rules, ?DatabaseProvider $db = null)
    {
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
    public function validate(array $data): ValidationResult
    {
        $context = $this->initializeValidationContext();

        // Single pass through data with all phases
        foreach ($data as $field => $value) {
            if (!isset($this->schema[$field])) {
                continue;
            }

            $node = $this->schema[$field];

            // Quick skip for optional empty fields
            if ($this->shouldSkipOptionalField($node, $value)) {
                continue;
            }

            // Process field through all validation phases
            if (!$this->processFieldValidation($field, $value, $node, $data, $context)) {
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
        array &$batch
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
            || (!empty($context['errors']) && $this->stopOnFirstError)) {
            return;
        }

        $this->batchExecutor->executeBatch($context['expensiveBatch'], $context['errors']);

        // Remove validated data for fields with errors from expensive checks
        if (!empty($context['errors'])) {
            $context['validated'] = array_diff_key(
                $context['validated'],
                $context['errors']
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
     * Check if a value is considered empty
     * OPTIMIZED: Inline this where used for better performance
     * Kept for BC compatibility
     */
    protected function isEmpty(mixed $value): bool
    {
        return $value === null
            || ($value === '' || (is_string($value) && trim($value) === ''))
            || (is_countable($value) && count($value) === 0);
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
        array &$context
    ): bool {
        // Phase 1: Cheap rules (cost < 50)
        if (!$this->validatePhase($node->cheapRules, $value, $field, $data, $context['errors'])) {
            return false;
        }

        // Phase 2: Medium rules (cost 50-99)
        if (!$this->validatePhase($node->mediumRules, $value, $field, $data, $context['errors'])) {
            return false;
        }

        // Phase 3: Collect expensive rules for batching
        $this->collectExpensiveRules($node->expensiveRules, $value, $field, $context['expensiveBatch']);

        // Mark field as validated if no errors
        if (!isset($context['errors'][$field])) {
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
        if (!$node->isOptional) {
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
        array &$errors
    ): bool {
        if (empty($rules)) {
            return true;
        }

        foreach ($rules as $rule) {
            if ($rule->passes($value, $field, $data)) {
                continue;
            }

            // Rule failed - add error
            $errors[$field][] = $this->customMessages[$field] ?? $rule->message($field);

            // Fail fast if enabled
            if ($this->failFast) {
                return false;
            }
        }

        // Return true if no errors were added for this field
        return !isset($errors[$field]);
    }

    /**
     * DEPRECATED: Use validatePhase() instead
     * Kept for backward compatibility
     */
    protected function validateRuleSet(
        array $rules,
        mixed $value,
        string $field,
        array $data,
        array &$errors
    ): bool {
        return $this->validatePhase($rules, $value, $field, $data, $errors);
    }
}

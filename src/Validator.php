<?php

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
    /**
     * Batch executor for expensive rules.
     */
    protected BatchExecutor $batchExecutor;

    /**
     * Schema compiler instance.
     */
    protected SchemaCompiler $compiler;

    /**
     * Custom error messages.
     */
    protected array $customMessages = [];

    /**
     * Whether to fail fast (stop at first error per field).
     */
    protected bool $failFast = true;

    /**
     * Compiled validation schema.
     *
     * @var array<string, ValidationNode>
     */
    protected array $schema;

    /**
     * Whether to stop validation entirely on first error.
     */
    protected bool $stopOnFirstError = false;

    /**
     * Create a new Validator instance.
     */
    public function __construct(array $rules, ?DatabaseProvider $db = null)
    {
        $this->compiler = new SchemaCompiler();
        $this->schema = $this->compiler->compile($rules);
        $this->batchExecutor = new BatchExecutor($db);
    }

    /**
     * Static factory method.
     *
     * @return static
     */
    public static function make(array $rules, ?DatabaseProvider $db = null): self
    {
        return new static($rules, $db);
    }

    /**
     * Get the compiled schema (for debugging).
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Get schema statistics (for debugging/optimization).
     */
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
     * Register a custom rule with the compiler.
     *
     * @return $this
     */
    public function registerRule(string $name, string $class): self
    {
        $this->compiler->registerRule($name, $class);

        return $this;
    }

    /**
     * Set custom error messages.
     *
     * @param array $messages ['field' => 'message']
     * @return $this
     */
    public function setCustomMessages(array $messages): self
    {
        $this->customMessages = $messages;

        return $this;
    }

    /**
     * Set whether to fail fast (stop at first error per field).
     *
     * @return $this
     */
    public function setFailFast(bool $failFast): self
    {
        $this->failFast = $failFast;

        return $this;
    }

    /**
     * Set whether to stop validation entirely on first error.
     *
     * @return $this
     */
    public function setStopOnFirstError(bool $stop): self
    {
        $this->stopOnFirstError = $stop;

        return $this;
    }

    /**
     * Validate the given data.
     */
    public function validate(array $data): ValidationResult
    {
        $errors = [];
        $validated = [];

        // Phase 1 & 2: Cheap and medium rules (with fail-fast)
        $expensiveBatch = [];

        foreach ($data as $field => $value) {
            if (!isset($this->schema[$field])) {
                continue; // No validation rules for this field
            }

            $node = $this->schema[$field];

            // Skip validation for optional fields with null/empty values
            if ($node->isOptional && $this->isEmpty($value)) {
                continue;
            }

            // Phase 1: Cheap rules (cost < 50)
            if (!$this->validateRuleSet($node->cheapRules, $value, $field, $data, $errors)) {
                if ($this->stopOnFirstError) {
                    break;
                }

                continue; // Skip remaining rules for this field
            }

            // Phase 2: Medium rules (cost 50-99)
            if (!$this->validateRuleSet($node->mediumRules, $value, $field, $data, $errors)) {
                if ($this->stopOnFirstError) {
                    break;
                }

                continue; // Skip expensive rules for this field
            }

            // Collect expensive rules for batch processing
            foreach ($node->expensiveRules as $rule) {
                $expensiveBatch[] = [
                    'rule' => $rule,
                    'value' => $value,
                    'field' => $field,
                ];
            }

            // If no errors so far, add to validated data
            if (!isset($errors[$field])) {
                $validated[$field] = $value;
            }
        }

        // Phase 3: Expensive rules (batched database queries)
        // Only execute if no errors in cheap/medium rules
        if (!empty($expensiveBatch) && (empty($errors) || !$this->stopOnFirstError)) {
            $this->batchExecutor->executeBatch($expensiveBatch, $errors);

            // Remove validated data for fields that failed expensive checks
            foreach ($errors as $field => $fieldErrors) {
                unset($validated[$field]);
            }
        }

        return new ValidationResult($errors, $validated);
    }

    /**
     * Check if a value is considered empty.
     *
     * @param mixed $value
     */
    protected function isEmpty($value): bool
    {
        if (is_null($value)) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if ((is_array($value) || is_countable($value)) && count($value) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Validate a set of rules for a field.
     *
     * @param mixed $value
     * @return bool True if all rules passed, false otherwise
     */
    protected function validateRuleSet(array $rules, $value, string $field, array $data, array &$errors): bool
    {
        foreach ($rules as $rule) {
            if (!$rule->passes($value, $field, $data)) {
                // Get custom message if available
                $message = $this->customMessages[$field] ?? $rule->message($field);
                $errors[$field][] = $message;

                if ($this->failFast) {
                    return false; // Stop validating this field
                }
            }
        }

        return !isset($errors[$field]); // Return true if no errors
    }
}

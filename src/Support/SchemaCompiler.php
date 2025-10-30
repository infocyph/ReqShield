<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;

/**
 * SchemaCompiler
 *
 * Compiles validation rules into optimized ValidationNode structures.
 * IMPROVED: Simplified rule parsing using rule-map configuration.
 */
class SchemaCompiler
{
    /**
     * Rule map configuration - loaded from rule-map.php.
     */
    protected array $ruleMap = [];

    /**
     * Rules that require special parameter handling.
     */
    protected array $specialRules = [
        // Numeric rules with type casting
        'min', 'max', 'size', 'digits', 'min_digits', 'max_digits', 'multiple_of',

        // Rules with multiple parameters
        'between', 'digits_between', 'decimal',

        // Database rules with complex constructors
        'unique', 'exists',

        // File rules
        'mimes', 'mimetypes', 'extensions', 'dimensions',

        // Variadic rules (use spread operator)
        'starts_with', 'ends_with', 'contains', 'doesnt_contain',
        'doesnt_start_with', 'doesnt_end_with', 'required_with',
        'required_with_all', 'required_without', 'required_without_all',
        'required_array_keys', 'present_with', 'present_with_all',
        'prohibits', 'exclude_with', 'exclude_without',

        // Comparison rules
        'same', 'different', 'regex', 'not_regex', 'in_array',

        // Date rules
        'date_format', 'date_equals', 'before', 'before_or_equal',
        'after', 'after_or_equal',

        // Conditional rules
        'required_if', 'required_unless', 'required_if_accepted',
        'required_if_declined', 'present_if', 'present_unless',
        'missing_if', 'missing_unless', 'prohibited_if', 'prohibited_unless',
        'exclude_if', 'exclude_unless', 'accepted_if', 'declined_if',

        // Array rules
        'in', 'not_in',

        // Callback
        'callback',

        // Comparison rules with field reference
        'gt', 'gte', 'lt', 'lte',
    ];

    public function __construct()
    {
        $this->loadRuleMap();
    }

    /**
     * Compile validation rules into optimized schema.
     */
    public function compile(array $rules): array
    {
        $schema = [];

        foreach ($rules as $field => $ruleSet) {
            // Convert string rules to array
            if (is_string($ruleSet)) {
                $ruleSet = explode('|', $ruleSet);
            }

            // Handle nested fields with dot notation
            if (str_contains($field, '.')) {
                $this->compileNestedField($schema, $field, $ruleSet);
            } else {
                $schema[$field] = $this->compileField($ruleSet);
            }
        }

        // Sort rules by cost in all nodes
        $this->sortAllNodes($schema);

        return $schema;
    }

    /**
     * Register a custom rule.
     */
    public function registerRule(string $name, string $class): void
    {
        if (! class_exists($class)) {
            throw new InvalidRuleException("Rule class does not exist: {$class}");
        }

        $this->ruleMap[$name] = $class;
    }

    /**
     * Compile a single field's rules into a ValidationNode.
     */
    protected function compileField(array $ruleSet): ValidationNode
    {
        $node = new ValidationNode();

        foreach ($ruleSet as $rule) {
            $ruleObject = $this->parseRule($rule);
            $node->addRule($ruleObject);
        }

        return $node;
    }

    /**
     * Compile nested field with dot notation.
     */
    protected function compileNestedField(array &$schema, string $field, array $ruleSet): void
    {
        $parts = explode('.', $field);
        $current = &$schema;

        foreach ($parts as $index => $part) {
            $isLast = $index === count($parts) - 1;

            if (! isset($current[$part])) {
                $current[$part] = new ValidationNode();
            }

            if ($isLast) {
                // Last part: add rules
                foreach ($ruleSet as $rule) {
                    $ruleObject = $this->parseRule($rule);
                    $current[$part]->addRule($ruleObject);
                }
            } else {
                // Intermediate part: prepare for nesting
                if ($current[$part]->children === null) {
                    $current[$part]->children = [];
                }
                $current = &$current[$part]->children;
            }
        }
    }

    /**
     * Create rule instance based on rule configuration.
     * IMPROVED: Simplified using rule-map and dynamic parameter handling
     */
    protected function createRuleInstance(string $name, array $params): Rule
    {
        if (! isset($this->ruleMap[$name])) {
            throw new InvalidRuleException("Unknown rule: {$name}");
        }

        $class = $this->ruleMap[$name];

        // Handle special rules with custom parameter logic
        if (in_array($name, $this->specialRules, true)) {
            return $this->createSpecialRule($name, $class, $params);
        }

        // Simple rules without parameters
        return new $class();
    }

    /**
     * Create special rules with parameter handling.
     * IMPROVED: Centralized parameter handling logic
     */
    protected function createSpecialRule(string $name, string $class, array $params): Rule
    {
        return match ($name) {
            // Numeric rules
            'min', 'max', 'size', 'digits', 'min_digits', 'max_digits', 'multiple_of' => new $class((int) $params[0]),

            'between', 'digits_between' => new $class((int) $params[0], (int) $params[1]),

            'decimal' => new $class(
                isset($params[0]) ? (int) $params[0] : null,
                isset($params[1]) ? (int) $params[1] : null
            ),

            // Database rules
            'unique' => new $class(
                $params[0], // table
                $params[1] ?? null, // column
                isset($params[2]) ? (int) $params[2] : null, // ignoreId
                $params[3] ?? false, // withTrashed
                $params[4] ?? 'deleted_at' // softDeleteColumn
            ),
            'exists' => new $class(
                $params[0], // table
                $params[1] ?? null // column
            ),

            // File rules (variadic)
            'mimes', 'mimetypes', 'extensions' => new $class(...$params),

            'dimensions' => new $class($this->parseDimensionsParams($params)),

            // Variadic string rules
            'starts_with', 'ends_with', 'contains', 'doesnt_contain',
            'doesnt_start_with', 'doesnt_end_with' => new $class(...$params),

            // Variadic conditional rules
            'required_with', 'required_with_all', 'required_without',
            'required_without_all', 'required_array_keys', 'present_with',
            'present_with_all', 'prohibits', 'exclude_with', 'exclude_without' => new $class(...$params),

            // Array rules
            'in', 'not_in' => new $class($params),
            'in_array' => new $class($params[0]),

            // Comparison rules (single parameter)
            'same', 'different', 'regex', 'not_regex' => new $class($params[0]),

            // Field comparison rules
            'gt', 'gte', 'lt', 'lte' => new $class($params[0]),

            // Date rules
            'date_format', 'date_equals', 'before', 'before_or_equal',
            'after', 'after_or_equal' => new $class($params[0]),

            // Conditional rules with two params
            'required_if', 'required_unless', 'present_if', 'present_unless',
            'missing_if', 'missing_unless', 'prohibited_if', 'prohibited_unless',
            'exclude_if', 'exclude_unless', 'accepted_if', 'declined_if' => new $class($params[0], $params[1] ?? null),

            // Single field conditional rules
            'required_if_accepted', 'required_if_declined' => new $class($params[0]),

            // Callback
            'callback' => new $class($params[0]),

            default => throw new InvalidRuleException("Unknown special rule: {$name}")
        };
    }

    /**
     * Load rule map from configuration file.
     */
    protected function loadRuleMap(): void
    {
        $mapPath = __DIR__.'/../Rules/rule-map.php';

        if (! file_exists($mapPath)) {
            throw new InvalidRuleException("Rule map file not found: {$mapPath}");
        }

        $this->ruleMap = require $mapPath;

        if (! is_array($this->ruleMap)) {
            throw new InvalidRuleException('Rule map must return an array');
        }
    }

    /**
     * Parse dimensions parameters from string format.
     *
     * @param  array  $params  Parameters like ['min_width=100', 'max_height=500']
     * @return array Parsed dimensions array
     */
    protected function parseDimensionsParams(array $params): array
    {
        $dimensions = [];
        foreach ($params as $param) {
            if (str_contains($param, '=')) {
                [$key, $value] = explode('=', $param, 2);
                $dimensions[$key] = $value;
            }
        }

        return $dimensions;
    }

    /**
     * Parse a rule from various formats.
     */
    protected function parseRule(mixed $rule): Rule
    {
        // Already a Rule object
        if ($rule instanceof Rule) {
            return $rule;
        }

        // String rule
        if (is_string($rule)) {
            return $this->parseStringRule($rule);
        }

        throw new InvalidRuleException('Invalid rule format: '.gettype($rule));
    }

    /**
     * Parse rule string into name and parameters.
     */
    protected function parseRuleString(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];
        $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

        return [$name, $params];
    }

    /**
     * Parse string rule (e.g., "min:18", "unique:users,email,5").
     * IMPROVED: Simplified - no more giant match statement!
     */
    protected function parseStringRule(string $rule): Rule
    {
        [$name, $params] = $this->parseRuleString($rule);

        return $this->createRuleInstance($name, $params);
    }

    /**
     * Sort rules in all nodes recursively.
     */
    protected function sortAllNodes(array $schema): void
    {
        foreach ($schema as $node) {
            if ($node instanceof ValidationNode) {
                $node->sortRules();

                if ($node->hasChildren()) {
                    $this->sortAllNodes($node->children);
                }
            }
        }
    }
}

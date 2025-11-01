<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;

/**
 * SchemaCompiler
 *
 * Compiles validation rules into optimized ValidationNode structures.
 * FIXED: Properly handles nested validation with flat schema structure.
 *
 * NESTED VALIDATION FIX:
 * - Schema is always kept FLAT with dot notation as keys
 * - Example: 'user.email' is ONE field in schema, not nested structure
 * - NestedValidator::flattenData() flattens input data to match schema keys
 * - This allows validation to work on flattened data with dot notation
 */
class SchemaCompiler
{
    /**
     * Rules that expect array parameters (all params as single array).
     * Example: 'in:1,2,3' → new In([1, 2, 3])
     */
    protected array $arrayRules = ['in', 'not_in'];

    /**
     * Rule map configuration - loaded from rule-map.php.
     */
    protected array $ruleMap = [];

    public function __construct()
    {
        $this->loadRuleMap();
    }

    /**
     * Compile validation rules into optimized schema.
     *
     * FIXED: Always keeps schema flat - nested fields are just field names
     * with dots. The NestedValidator will flatten the data to match these
     * keys.
     *
     * Example:
     * Input: ['user.email' => 'required|email', 'user.name' => 'required']
     * Output: [
     *   'user.email' => ValidationNode (with required, email rules),
     *   'user.name' => ValidationNode (with required rule)
     * ]
     */
    public function compile(array $rules): array
    {
        $schema = [];

        foreach ($rules as $field => $ruleSet) {
            // Convert string rules to array
            if (is_string($ruleSet)) {
                $ruleSet = explode('|', $ruleSet);
            }

            // Always use flat structure - nested fields with dots are just field names
            // The NestedValidator will handle flattening the data to match
            $schema[$field] = $this->compileField($ruleSet);
        }

        // Sort rules by cost in all nodes
        foreach ($schema as $node) {
            if ($node instanceof ValidationNode) {
                $node->sortRules();
            }
        }

        return $schema;
    }

    /**
     * Register a custom rule.
     */
    public function registerRule(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new InvalidRuleException(
                "Rule class does not exist: {$class}",
            );
        }

        $this->ruleMap[$name] = $class;
    }

    /**
     * Cast string parameters to appropriate types based on content.
     * Uses pattern-based heuristics for fast, simple type casting.
     */
    protected function castParameters(array $params): array
    {
        return array_map(function ($param) {
            return match (true) {
                $param === '' || $param === 'null' => null,
                $param === 'true' => true,
                $param === 'false' => false,
                is_numeric($param) => str_contains($param, '.')
                    ? (float)$param
                    : (int)$param,
                default => $param,
            };
        }, $params);
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
     * Create rule instance - delegates parameter handling to the rule class.
     * SIMPLIFIED: Just pass parameters and let the class validate them.
     */
    protected function createRuleInstance(string $name, array $params): Rule
    {
        if (!isset($this->ruleMap[$name])) {
            throw new InvalidRuleException("Unknown rule: {$name}");
        }

        $class = $this->ruleMap[$name];

        // Cast parameters to appropriate types
        $params = $this->castParameters($params);

        // Handle array rules - they expect all params as a single array
        // Example: 'in:1,2,3' becomes ['1','2','3'], needs to be [['1','2','3']]
        if (in_array($name, $this->arrayRules, true)) {
            $params = [$params];
        }

        try {
            // Pass all parameters to constructor - let the class handle them
            return match (count($params)) {
                0 => new $class(),
                default => new $class(...$params),
            };
        } catch (\ArgumentCountError $e) {
            throw new InvalidRuleException(
                "Invalid parameters for rule '{$name}': {$e->getMessage()}",
            );
        } catch (\TypeError $e) {
            throw new InvalidRuleException(
                "Invalid parameter types for rule '{$name}': {$e->getMessage()}",
            );
        }
    }

    /**
     * Load rule map from configuration file.
     */
    protected function loadRuleMap(): void
    {
        $mapPath = __DIR__ . '/../Rules/rule-map.php';

        if (!file_exists($mapPath)) {
            throw new InvalidRuleException(
                "Rule map file not found: {$mapPath}",
            );
        }

        $this->ruleMap = require $mapPath;
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

        throw new InvalidRuleException(
            'Invalid rule format: ' . gettype($rule),
        );
    }

    /**
     * Parse rule string into name and parameters.
     */
    protected function parseRuleString(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];

        // Don't split regex parameters - commas are part of pattern!
        if (in_array($name, ['regex', 'not_regex'])) {
            return [$name, [$parts[1]]];
        }

        // Normal rules: split on comma
        $params = explode(',', $parts[1] ?? '');
        return [$name, $params];
    }

    /**
     * Parse string rule (e.g., "min:18", "unique:users,email,5").
     * SIMPLIFIED: Just parse and pass to createRuleInstance.
     */
    protected function parseStringRule(string $rule): Rule
    {
        [$name, $params] = $this->parseRuleString($rule);

        return $this->createRuleInstance($name, $params);
    }

}

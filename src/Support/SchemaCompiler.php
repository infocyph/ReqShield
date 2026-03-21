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
     * Reverse lookup of rule class => canonical rule name.
     */
    protected ?array $reverseRuleMap = null;

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
     * Get the active rule map (rule name => class).
     */
    public function getRuleMap(): array
    {
        return $this->ruleMap;
    }

    /**
     * Get the canonical rule name for a rule object.
     */
    public function getRuleNameForRule(Rule $rule): string
    {
        $class = ltrim($rule::class, '\\');

        if ($this->reverseRuleMap === null) {
            $this->reverseRuleMap = [];
            foreach ($this->ruleMap as $name => $mappedClass) {
                $this->reverseRuleMap[ltrim($mappedClass, '\\')] = $name;
            }
        }

        if (isset($this->reverseRuleMap[$class])) {
            return $this->reverseRuleMap[$class];
        }

        $pos = strrpos($class, '\\');
        $shortName = $pos === false ? $class : substr($class, $pos + 1);
        $snake = strtolower(
            preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName) ?? $shortName,
        );

        return str_ends_with($snake, '_rule')
            ? substr($snake, 0, -5)
            : $snake;
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
        $this->reverseRuleMap = null;
    }

    /**
     * Build placeholder token map for a parsed rule.
     *
     * @param array<int,mixed> $params
     *
     * @return array<string,mixed>
     */
    protected function buildRulePlaceholders(
        string $ruleName,
        array $params,
    ): array {
        $params = array_values(array_filter(
            $params,
            fn (mixed $value): bool => $value !== '' && $value !== null,
        ));

        if (empty($params)) {
            return [];
        }

        $placeholders = [];
        foreach ($params as $index => $value) {
            $placeholders['param' . ($index + 1)] = $value;
        }

        $singleValueRules = ['min', 'max', 'size', 'digits', 'min_digits', 'max_digits', 'multiple_of'];
        if (in_array($ruleName, $singleValueRules, true)) {
            $key = match ($ruleName) {
                'max' => 'max',
                'size' => 'size',
                'digits' => 'digits',
                'min_digits' => 'min',
                'max_digits' => 'max',
                'multiple_of' => 'multiple',
                default => 'min',
            };
            $placeholders[$key] = $params[0];
        }

        if (in_array($ruleName, ['between', 'digits_between'], true)) {
            $placeholders['min'] = $params[0] ?? null;
            $placeholders['max'] = $params[1] ?? null;
        }

        if ($ruleName === 'decimal') {
            $placeholders['min'] = $params[0] ?? null;
            $placeholders['max'] = $params[1] ?? ($params[0] ?? null);
        }

        if (in_array($ruleName, ['same', 'different', 'gt', 'gte', 'lt', 'lte'], true)) {
            $placeholders['other'] = $params[0] ?? null;
        }

        if (in_array(
            $ruleName,
            ['in', 'not_in', 'contains', 'doesnt_contain', 'starts_with', 'ends_with', 'doesnt_start_with', 'doesnt_end_with', 'required_array_keys'],
            true,
        )) {
            $placeholders['values'] = implode(', ', array_map('strval', $params));
        }

        if (in_array(
            $ruleName,
            ['required_with', 'required_with_all', 'required_without', 'required_without_all', 'present_with', 'present_with_all', 'exclude_with', 'exclude_without', 'prohibits'],
            true,
        )) {
            $placeholders['other'] = implode(', ', array_map('strval', $params));
        }

        if (in_array(
            $ruleName,
            ['required_if', 'required_unless', 'present_if', 'present_unless', 'missing_if', 'missing_unless', 'prohibited_if', 'prohibited_unless', 'accepted_if', 'declined_if'],
            true,
        )) {
            $placeholders['other'] = $params[0] ?? null;
            $placeholders['value'] = implode(
                ', ',
                array_map('strval', array_slice($params, 1)),
            );
        }

        if (in_array($ruleName, ['required_if_accepted', 'required_if_declined'], true)) {
            $placeholders['other'] = implode(', ', array_map('strval', $params));
        }

        if (in_array(
            $ruleName,
            ['before', 'before_or_equal', 'after', 'after_or_equal', 'date_equals', 'date_format'],
            true,
        )) {
            $placeholders['date'] = $params[0] ?? null;
            $placeholders['format'] = $params[0] ?? null;
        }

        if (in_array($ruleName, ['regex', 'not_regex'], true)) {
            $placeholders['pattern'] = $params[0] ?? null;
        }

        if ($ruleName === 'unique') {
            $placeholders['table'] = $params[0] ?? null;
            $placeholders['column'] = $params[1] ?? null;
            $placeholders['ignore'] = $params[2] ?? null;
            $placeholders['id_column'] = $params[3] ?? null;
            $placeholders['with_trashed'] = $params[4] ?? null;
            $placeholders['soft_delete_column'] = $params[5] ?? null;
        }

        if ($ruleName === 'exists') {
            $placeholders['table'] = $params[0] ?? null;
            $placeholders['column'] = $params[1] ?? null;
        }

        return array_filter(
            $placeholders,
            fn (mixed $value): bool => $value !== null && $value !== '',
        );
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
            if (is_string($rule)) {
                [$ruleName, $params] = $this->parseRuleString($rule);
                $ruleObject = $this->createRuleInstance($ruleName, $params);
                $placeholders = $this->buildRulePlaceholders($ruleName, $params);
            } else {
                $ruleObject = $this->parseRule($rule);
                $ruleName = $this->getRuleNameForRule($ruleObject);
                $placeholders = [];
            }

            $node->addRule($ruleObject, $ruleName, $placeholders);
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
     * Parses a validation rule from various formats into a Rule object.
     *
     * This method handles different rule formats:
     * - Rule objects (passed through directly)
     * - String rules (e.g., 'required', 'min:3')
     *
     * @param mixed $rule The rule to parse (string or Rule object)
     *
     * @return Rule The parsed rule instance
     * @throws InvalidRuleException If the rule format is invalid
     *
     * @see parseStringRule() For handling string-based rules
     * @see createRuleInstance() For creating rule instances from names
     * @example
     * // Using string rule
     * $rule = $this->parseRule('required');
     *
     * // Using Rule object
     * $rule = $this->parseRule(new RequiredRule());
     *
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
     * Splits a rule string into its name and parameters.
     *
     * Handles special cases for rules like 'regex' where parameters
     * should not be split on commas.
     *
     * @param string $rule The rule string to parse (e.g., 'min:3', 'in:1,2,3')
     *
     * @return array{string, string[]} Tuple containing [ruleName,
     *   parameters[]]
     *
     * @see parseStringRule() For creating a Rule instance from the parsed
     *   string
     * @example
     * // Returns ['min', ['3']]
     * $this->parseRuleString('min:3');
     *
     * // Returns ['in', ['1', '2', '3']]
     * $this->parseRuleString('in:1,2,3');
     *
     * // Returns ['regex', ['/^[a-z]+$/i']] (special case, no comma splitting)
     * $this->parseRuleString('regex:/^[a-z]+$/i');
     *
     */
    protected function parseRuleString(string $rule): array
    {
        $parts = explode(':', $rule, 2);
        $name = $parts[0];

        // Don't split regex parameters - commas are part of pattern!
        if (in_array($name, ['regex', 'not_regex'])) {
            return [$name, [$parts[1] ?? '']];
        }

        // Normal rules: split on comma
        $params = explode(',', $parts[1] ?? '');
        return [$name, $params];
    }

    /**
     * Parses a string-based validation rule into a Rule instance.
     *
     * This is the main entry point for processing string rules like:
     * - 'required'
     * - 'min:18'
     * - 'unique:users,email,5'
     * - 'regex:/^[a-z]+$/i'
     *
     * @param string $rule The rule string to parse
     *
     * @return Rule The instantiated rule object
     * @throws InvalidRuleException If the rule is unknown or invalid
     *
     * @see parseRuleString() For splitting the rule into components
     * @see createRuleInstance() For instantiating the rule class
     * @example
     * // Returns a RequiredRule instance
     * $rule = $this->parseStringRule('required');
     *
     * // Returns a MinRule instance with parameter 18
     * $rule = $this->parseStringRule('min:18');
     *
     * // Returns a UniqueRule instance with table and column parameters
     * $rule = $this->parseStringRule('unique:users,email');
     *
     */
    protected function parseStringRule(string $rule): Rule
    {
        [$name, $params] = $this->parseRuleString($rule);

        return $this->createRuleInstance($name, $params);
    }

}

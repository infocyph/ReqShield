<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;

class SchemaCompiler
{
    /** @var array<int,string> */
    protected array $arrayRules = ['in', 'not_in'];

    /** @var array<string,string>|null */
    protected ?array $reverseRuleMap = null;

    /** @var array<string,class-string<Rule>> */
    protected array $ruleMap = [];

    public function __construct()
    {
        $this->loadRuleMap();
    }

    /**
     * @param array<int|string,mixed> $rules
     *
     * @return array<string,ValidationNode>
     */
    public function compile(array $rules): array
    {
        $schema = [];

        foreach ($rules as $field => $ruleSet) {
            if (!is_string($field)) {
                continue;
            }

            // Convert string rules to array
            if (is_string($ruleSet)) {
                $ruleSet = RuleExpressionParser::splitRules($ruleSet);
            }
            if (!is_array($ruleSet)) {
                continue;
            }

            // Always use flat structure - nested fields with dots are just field names
            // The NestedValidator will handle flattening the data to match
            $schema[$field] = $this->compileField(array_values($ruleSet));
        }

        // Sort rules by cost in all nodes
        foreach ($schema as $node) {
            $node->sortRules();
        }

        return $schema;
    }

    /** @return array<string,class-string<Rule>> */
    public function getRuleMap(): array
    {
        return $this->ruleMap;
    }

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

        return RuleNameResolver::canonicalRuleNameFromClass($shortName);
    }

    public function registerRule(string $name, string $class): void
    {
        if (!class_exists($class)) {
            throw new InvalidRuleException(
                "Rule class does not exist: {$class}",
            );
        }

        if (!is_subclass_of($class, Rule::class)) {
            throw new InvalidRuleException(
                'Rule class must implement ' . Rule::class . ": {$class}",
            );
        }

        $this->ruleMap[$name] = $class;
        $this->reverseRuleMap = null;
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyAcceptedDeclinedPlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        $this->applyJoinedPlaceholder(
            $placeholders,
            $ruleName,
            $params,
            'other',
            ['required_if_accepted', 'required_if_declined'],
        );
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyAggregateOtherPlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        $this->applyJoinedPlaceholder(
            $placeholders,
            $ruleName,
            $params,
            'other',
            ['required_with', 'required_with_all', 'required_without', 'required_without_all', 'present_with', 'present_with_all', 'exclude_with', 'exclude_without', 'prohibits'],
        );
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyComparisonPlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        if (!in_array($ruleName, ['same', 'different', 'gt', 'gte', 'lt', 'lte'], true)) {
            return;
        }

        $placeholders['other'] = $params[0] ?? null;
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyConditionalPlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        if (!in_array(
            $ruleName,
            ['required_if', 'required_unless', 'present_if', 'present_unless', 'missing_if', 'missing_unless', 'prohibited_if', 'prohibited_unless', 'accepted_if', 'declined_if'],
            true,
        )) {
            return;
        }

        $placeholders['other'] = $params[0] ?? null;
        $placeholders['value'] = $this->implodeScalarParams(array_slice($params, 1));
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyDatabasePlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        if ($ruleName === 'unique') {
            $placeholders['table'] = $params[0] ?? null;
            $placeholders['column'] = $params[1] ?? null;
            $placeholders['ignore'] = $params[2] ?? null;
            $placeholders['id_column'] = $params[3] ?? null;
            $placeholders['with_trashed'] = $params[4] ?? null;
            $placeholders['soft_delete_column'] = $params[5] ?? null;

            return;
        }

        if ($ruleName !== 'exists') {
            return;
        }

        $placeholders['table'] = $params[0] ?? null;
        $placeholders['column'] = $params[1] ?? null;
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyDatePlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        if (!in_array(
            $ruleName,
            ['before', 'before_or_equal', 'after', 'after_or_equal', 'date_equals', 'date_format'],
            true,
        )) {
            return;
        }

        $placeholders['date'] = $params[0] ?? null;
        $placeholders['format'] = $params[0] ?? null;
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     * @param array<int,string> $ruleNames
     */
    protected function applyJoinedPlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
        string $key,
        array $ruleNames,
    ): void {
        if (!in_array($ruleName, $ruleNames, true)) {
            return;
        }

        $placeholders[$key] = $this->implodeScalarParams($params);
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyPatternPlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        if (!in_array($ruleName, ['regex', 'not_regex'], true)) {
            return;
        }

        $placeholders['pattern'] = $params[0] ?? null;
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyRangePlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        if (in_array($ruleName, ['between', 'digits_between'], true)) {
            $placeholders['min'] = $params[0] ?? null;
            $placeholders['max'] = $params[1] ?? null;

            return;
        }

        if ($ruleName !== 'decimal') {
            return;
        }

        $placeholders['min'] = $params[0] ?? null;
        $placeholders['max'] = $params[1] ?? ($params[0] ?? null);
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applySingleValuePlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        $key = match ($ruleName) {
            'min' => 'min',
            'max' => 'max',
            'size' => 'size',
            'digits' => 'digits',
            'min_digits' => 'min',
            'max_digits' => 'max',
            'multiple_of' => 'multiple',
            default => null,
        };

        if ($key === null) {
            return;
        }

        $placeholders[$key] = $params[0] ?? null;
    }

    /**
     * @param array<string,mixed> $placeholders
     * @param array<int,mixed> $params
     */
    protected function applyValuesPlaceholder(
        array &$placeholders,
        string $ruleName,
        array $params,
    ): void {
        $this->applyJoinedPlaceholder(
            $placeholders,
            $ruleName,
            $params,
            'values',
            ['in', 'not_in', 'contains', 'doesnt_contain', 'starts_with', 'ends_with', 'doesnt_start_with', 'doesnt_end_with', 'required_array_keys'],
        );
    }

    /**
     * @param array<int,mixed> $params
     *
     * @return array<string,mixed>
     */
    protected function buildIndexedPlaceholders(array $params): array
    {
        $placeholders = [];

        foreach ($params as $index => $value) {
            $placeholders['param' . ($index + 1)] = $value;
        }

        return $placeholders;
    }

    /**
     * @param array<int,mixed> $params
     *
     * @return array<string,mixed>
     */
    protected function buildRulePlaceholders(
        string $ruleName,
        array $params,
    ): array {
        $params = $this->normalizePlaceholderParams($params);
        if ($params === []) {
            return [];
        }

        $placeholders = $this->buildIndexedPlaceholders($params);
        $this->applySingleValuePlaceholder($placeholders, $ruleName, $params);
        $this->applyRangePlaceholder($placeholders, $ruleName, $params);
        $this->applyComparisonPlaceholder($placeholders, $ruleName, $params);
        $this->applyValuesPlaceholder($placeholders, $ruleName, $params);
        $this->applyAggregateOtherPlaceholder($placeholders, $ruleName, $params);
        $this->applyConditionalPlaceholder($placeholders, $ruleName, $params);
        $this->applyAcceptedDeclinedPlaceholder($placeholders, $ruleName, $params);
        $this->applyDatePlaceholder($placeholders, $ruleName, $params);
        $this->applyPatternPlaceholder($placeholders, $ruleName, $params);
        $this->applyDatabasePlaceholder($placeholders, $ruleName, $params);

        return array_filter(
            $placeholders,
            fn(mixed $value): bool => $value !== null && $value !== '',
        );
    }

    /**
     * @param array<int,mixed> $params
     *
     * @return array<int,mixed>
     */
    protected function castParameters(array $params): array
    {
        return array_map(fn($param) => match (true) {
            $param === '' || $param === 'null' => null,
            $param === 'true' => true,
            $param === 'false' => false,
            is_numeric($param) => str_contains((string) $param, '.')
              ? (float) $param
              : (int) $param,
            default => $param,
        }, $params);
    }

    /** @param array<int,mixed> $ruleSet */
    protected function compileField(array $ruleSet): ValidationNode
    {
        $node = new ValidationNode();

        foreach ($ruleSet as $rule) {
            if (is_string($rule)) {
                [$ruleName, $params] = $this->parseRuleString($rule);
                $params = array_values($params);
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

    /** @param array<int,mixed> $params */
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

    /** @param array<int,mixed> $params */
    protected function implodeScalarParams(array $params): string
    {
        return implode(', ', array_map(
            static fn(mixed $value): string => is_scalar($value) || $value === null
                ? (string) $value
                : '',
            $params,
        ));
    }

    protected function loadRuleMap(): void
    {
        $mapPath = __DIR__ . '/../Rules/rule-map.php';

        if (!file_exists($mapPath)) {
            throw new InvalidRuleException(
                "Rule map file not found: {$mapPath}",
            );
        }

        $map = require $mapPath;
        if (!is_array($map)) {
            throw new InvalidRuleException("Rule map file must return an array: {$mapPath}");
        }

        $normalized = [];
        foreach ($map as $name => $class) {
            if (!is_string($name) || !is_string($class) || !is_subclass_of($class, Rule::class)) {
                continue;
            }

            $normalized[$name] = $class;
        }

        $this->ruleMap = $normalized;
    }

    /**
     * @param array<int,mixed> $params
     *
     * @return array<int,mixed>
     */
    protected function normalizePlaceholderParams(array $params): array
    {
        return array_values(array_filter(
            $params,
            fn(mixed $value): bool => $value !== '' && $value !== null,
        ));
    }

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

    /** @return array{0:string,1:array<int,mixed>} */
    protected function parseRuleString(string $rule): array
    {
        $parsed = RuleExpressionParser::parse($rule);
        $name = $parsed[0];
        $params = $parsed[1];

        return [$name, array_values($params)];
    }

    protected function parseStringRule(string $rule): Rule
    {
        [$name, $params] = $this->parseRuleString($rule);

        return $this->createRuleInstance($name, $params);
    }
}

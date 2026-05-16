<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Enums\BuiltinRule;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;

class SchemaCompiler
{
    /** @var array<string, class-string<Rule>> */
    protected static array $resolvedBuiltinRuleClassCache = [];

    /** @var array<int,string> */
    protected array $arrayRules = ['in', 'not_in'];

    /** @var array<string,class-string<Rule>> */
    protected array $customRuleMap = [];

    /** @var array<string,class-string<Rule>>|null */
    protected ?array $mergedRuleMap = null;

    /** @var array<string,string>|null */
    protected ?array $reverseCustomRuleMap = null;

    public static function clearResolvedBuiltinRuleClassCache(): void
    {
        self::$resolvedBuiltinRuleClassCache = [];
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
        if ($this->customRuleMap === []) {
            return BuiltinRule::tokenToClassMap();
        }

        if ($this->mergedRuleMap !== null) {
            return $this->mergedRuleMap;
        }

        $this->mergedRuleMap = array_merge(
            BuiltinRule::tokenToClassMap(),
            $this->customRuleMap,
        );

        return $this->mergedRuleMap;
    }

    public function getRuleNameForRule(Rule $rule): string
    {
        $class = ltrim($rule::class, '\\');

        if ($this->reverseCustomRuleMap === null) {
            $this->reverseCustomRuleMap = [];

            foreach ($this->customRuleMap as $name => $mappedClass) {
                $this->reverseCustomRuleMap[ltrim($mappedClass, '\\')] = $name;
            }
        }

        if (isset($this->reverseCustomRuleMap[$class])) {
            return $this->reverseCustomRuleMap[$class];
        }

        $builtin = BuiltinRule::resolveNameForClass($class);
        if (is_string($builtin)) {
            return $builtin;
        }

        $pos = strrpos($class, '\\');
        $shortName = $pos === false ? $class : substr($class, $pos + 1);

        return RuleNameResolver::canonicalRuleNameFromClass($shortName);
    }

    public function registerRule(string $name, string $class): void
    {
        $this->assertRuleClass($name, $class);

        $this->customRuleMap[$name] = $class;
        $this->mergedRuleMap = null;
        $this->reverseCustomRuleMap = null;
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
        if (!BuiltinRule::supportsAcceptedDeclinedPlaceholder($ruleName)) {
            return;
        }

        $placeholders['other'] = $this->implodeScalarParams($params);
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
        if (!BuiltinRule::supportsAggregateOtherPlaceholder($ruleName)) {
            return;
        }

        $placeholders['other'] = $this->implodeScalarParams($params);
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
        if (!BuiltinRule::supportsComparisonPlaceholder($ruleName)) {
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
        if (!BuiltinRule::supportsConditionalPlaceholder($ruleName)) {
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
        if (BuiltinRule::isUniqueRule($ruleName)) {
            $placeholders['table'] = $params[0] ?? null;
            $placeholders['column'] = $params[1] ?? null;
            $placeholders['ignore'] = $params[2] ?? null;
            $placeholders['id_column'] = $params[3] ?? null;
            $placeholders['with_trashed'] = $params[4] ?? null;
            $placeholders['soft_delete_column'] = $params[5] ?? null;

            return;
        }

        if (!BuiltinRule::isExistsRule($ruleName)) {
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
        if (!BuiltinRule::supportsDatePlaceholder($ruleName)) {
            return;
        }

        $placeholders['date'] = $params[0] ?? null;
        $placeholders['format'] = $params[0] ?? null;
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
        if (!BuiltinRule::supportsPatternPlaceholder($ruleName)) {
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
        if (BuiltinRule::supportsBetweenRangePlaceholder($ruleName)) {
            $placeholders['min'] = $params[0] ?? null;
            $placeholders['max'] = $params[1] ?? null;

            return;
        }

        if (!BuiltinRule::supportsDecimalRangePlaceholder($ruleName)) {
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
        $key = BuiltinRule::singleValuePlaceholderKey($ruleName);

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
        if (!BuiltinRule::supportsValuesPlaceholder($ruleName)) {
            return;
        }

        $placeholders['values'] = $this->implodeScalarParams($params);
    }

    /**
     * @param class-string<Rule>|string $class
     * @phpstan-assert class-string<Rule> $class
     */
    protected function assertRuleClass(string $ruleName, string $class): void
    {
        if (!class_exists($class)) {
            throw InvalidRuleException::invalidFormat(
                $ruleName,
                "Resolved rule class does not exist: {$class}",
            );
        }

        if (!is_subclass_of($class, Rule::class)) {
            throw InvalidRuleException::invalidFormat(
                $ruleName,
                'Resolved rule class must implement ' . Rule::class . ": {$class}",
            );
        }
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
        /** @var class-string<Rule> $class */
        $class = $this->resolveRuleClass($name);

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

    /**
     * @return class-string<Rule>
     */
    protected function resolveRuleClass(string $ruleName): string
    {
        if (isset($this->customRuleMap[$ruleName])) {
            return $this->customRuleMap[$ruleName];
        }

        if (isset(self::$resolvedBuiltinRuleClassCache[$ruleName])) {
            return self::$resolvedBuiltinRuleClassCache[$ruleName];
        }

        $class = BuiltinRule::resolve($ruleName);
        if ($class === null) {
            throw InvalidRuleException::unknownRule($ruleName);
        }

        $this->assertRuleClass($ruleName, $class);
        self::$resolvedBuiltinRuleClassCache[$ruleName] = $class;

        return $class;
    }
}

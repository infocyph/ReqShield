<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Enums\BuiltinRule;
use Infocyph\ReqShield\Exceptions\InvalidRuleParameterException;
use Infocyph\ReqShield\Exceptions\InvalidSchemaException;
use Infocyph\ReqShield\Exceptions\UnknownRuleException;

class SchemaCompiler
{
    protected const int MAX_RESOLVED_RULE_CACHE = 256;

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
     * @return array<string,FieldPlan>
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
            $schema[$field] = $this->compileField($field, array_values($ruleSet));
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
            throw InvalidSchemaException::forField(
                $ruleName,
                "Resolved rule class does not exist: {$class}",
            );
        }

        if (!is_subclass_of($class, Rule::class)) {
            throw InvalidSchemaException::forField(
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
    protected function castParameters(string $ruleName, array $params): array
    {
        $integerRules = ['digits', 'min_digits', 'max_digits', 'digits_between', 'decimal'];
        if (in_array($ruleName, $integerRules, true)) {
            return array_map($this->coerceIntegerParameter(...), $params);
        }

        $numberRules = ['min', 'max', 'size', 'between', 'multiple_of'];
        if (in_array($ruleName, $numberRules, true)) {
            return array_map($this->coerceNumberParameter(...), $params);
        }

        return $params;
    }

    protected function coerceIntegerParameter(mixed $parameter): int
    {
        $value = filter_var($parameter, FILTER_VALIDATE_INT);
        if ($value === false) {
            throw new \InvalidArgumentException('Expected an integer parameter.');
        }

        return $value;
    }

    protected function coerceNumberParameter(mixed $parameter): int|float
    {
        if (!is_numeric($parameter)) {
            throw new \InvalidArgumentException('Expected a numeric parameter.');
        }

        return str_contains((string) $parameter, '.')
            ? (float) $parameter
            : (int) $parameter;
    }

    /** @param array<int,mixed> $ruleSet */
    protected function compileField(string $field, array $ruleSet): FieldPlan
    {
        $builder = new FieldPlanBuilder();

        foreach ($ruleSet as $rule) {
            if (is_string($rule)) {
                [$ruleName, $params] = $this->parseRuleString($rule);
                $params = array_values($params);
                $ruleObject = $this->createRuleInstance($ruleName, $params);
                $placeholders = $this->buildRulePlaceholders($ruleName, $params);
                $dependencies = $this->dependencyPaths($field, $ruleName, $params, $ruleObject);
            } else {
                $ruleObject = $this->parseRule($rule);
                $ruleName = $this->getRuleNameForRule($ruleObject);
                $placeholders = [];
                $dependencies = $this->dependencyPaths($field, $ruleName, [], $ruleObject);
            }

            $builder->add($ruleObject, $ruleName, $placeholders, $dependencies);
        }

        return $builder->build();
    }

    /** @param array<int,mixed> $params */
    protected function createRuleInstance(string $name, array $params): Rule
    {
        if ($name === 'unique' && count($params) > 2) {
            throw InvalidRuleParameterException::forRule(
                $name,
                'String syntax accepts only table and column; use Rule::unique() for advanced options.',
            );
        }

        /** @var class-string<Rule> $class */
        $class = $this->resolveRuleClass($name);

        // Cast parameters to appropriate types
        try {
            $params = $this->castParameters($name, $params);
        } catch (\InvalidArgumentException $exception) {
            throw InvalidRuleParameterException::forRule($name, $exception->getMessage());
        }

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
        } catch (\ArgumentCountError|\TypeError|\InvalidArgumentException $e) {
            throw InvalidRuleParameterException::forRule($name, $e->getMessage());
        }
    }

    /**
     * @param array<int,mixed> $params
     * @return list<string>
     */
    protected function dependencyPaths(string $field, string $name, array $params, Rule $rule): array
    {
        if ($name === 'confirmed') {
            return [$field . '_confirmation'];
        }

        if (method_exists($rule, 'getOtherFields')) {
            $fields = $rule->getOtherFields();

            return is_array($fields) ? array_values(array_filter($fields, is_string(...))) : [];
        }

        if (method_exists($rule, 'getOtherField')) {
            $other = $rule->getOtherField();

            return is_string($other) && $other !== '' ? [$other] : [];
        }

        $allParameters = [
            'exclude_with', 'exclude_without', 'present_with', 'present_with_all',
            'prohibits', 'required_with', 'required_with_all', 'required_without',
            'required_without_all',
        ];
        if (in_array($name, $allParameters, true)) {
            return array_values(array_filter($params, is_string(...)));
        }

        $firstParameter = [
            'accepted_if', 'after', 'after_or_equal', 'before', 'before_or_equal',
            'date_equals', 'declined_if', 'different', 'exclude_if', 'exclude_unless',
            'gt', 'gte', 'in_array', 'lt', 'lte', 'missing_if', 'missing_unless',
            'present_if', 'present_unless', 'prohibited_if', 'prohibited_unless',
            'required_if', 'required_if_accepted', 'required_if_declined', 'required_unless', 'same',
        ];
        $dependency = $params[0] ?? null;
        if (!in_array($name, $firstParameter, true) || !is_string($dependency) || $dependency === '') {
            return [];
        }

        if (in_array($name, ['after', 'after_or_equal', 'before', 'before_or_equal', 'date_equals'], true)
            && strtotime($dependency) !== false) {
            return [];
        }

        return [$dependency];
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
            $reflection = new \ReflectionObject($rule);
            if (!$reflection->isCloneable()) {
                throw InvalidSchemaException::forField('rule', 'Rule objects must be cloneable.');
            }

            return clone $rule;
        }

        // String rule
        if (is_string($rule)) {
            return $this->parseStringRule($rule);
        }

        throw InvalidSchemaException::forField(
            'rule',
            'Expected a rule object or string, got ' . get_debug_type($rule),
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
            throw UnknownRuleException::forName($ruleName);
        }

        $this->assertRuleClass($ruleName, $class);
        self::$resolvedBuiltinRuleClassCache[$ruleName] = $class;
        if (count(self::$resolvedBuiltinRuleClassCache) > self::MAX_RESOLVED_RULE_CACHE) {
            array_shift(self::$resolvedBuiltinRuleClassCache);
        }

        return $class;
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Contracts\Rule;
use Infocyph\ReqShield\Exceptions\InvalidRuleException;

/**
 * SchemaCompiler
 *
 * Compiles array-based validation rules into an optimized ValidationNode structure.
 * Supports nested validation and wildcard array validation.
 */
class SchemaCompiler
{
    /**
     * Map of string rule names to rule classes.
     */
    protected array $ruleMap = [
        // Basic (from CheapRules.php)
        'required' => 'Infocyph\ReqShield\Rules\Required',
        'string' => 'Infocyph\ReqShield\Rules\StringRule',
        'integer' => 'Infocyph\ReqShield\Rules\IntegerRule',
        'numeric' => 'Infocyph\ReqShield\Rules\Numeric',
        'boolean' => 'Infocyph\ReqShield\Rules\Boolean',
        'array' => 'Infocyph\ReqShield\Rules\ArrayRule',

        // Format (from MediumRules.php)
        'email' => 'Infocyph\ReqShield\Rules\Email',
        'url' => 'Infocyph\ReqShield\Rules\Url',
        'ip' => 'Infocyph\ReqShield\Rules\Ip',
        'json' => 'Infocyph\ReqShield\Rules\Json',
        'alpha' => 'Infocyph\ReqShield\Rules\Alpha',
        'alpha_num' => 'Infocyph\ReqShield\Rules\AlphaNum',
        'alpha_dash' => 'Infocyph\ReqShield\Rules\AlphaDash',
        'date' => 'Infocyph\ReqShield\Rules\Date',

        // Additional (from AdditionalRules.php)
        'uuid' => 'Infocyph\ReqShield\Rules\Uuid',
        'mac' => 'Infocyph\ReqShield\Rules\Mac',
        'distinct' => 'Infocyph\ReqShield\Rules\Distinct',
        'confirmed' => 'Infocyph\ReqShield\Rules\Confirmed',
        'accepted' => 'Infocyph\ReqShield\Rules\Accepted',
        'declined' => 'Infocyph\ReqShield\Rules\Declined',
        'lowercase' => 'Infocyph\ReqShield\Rules\Lowercase',
        'uppercase' => 'Infocyph\ReqShield\Rules\Uppercase',

        // Conditional (from ConditionalRules.php)
        'sometimes' => 'Infocyph\ReqShield\Rules\Sometimes',
        'nullable' => 'Infocyph\ReqShield\Rules\Nullable',
        'prohibited' => 'Infocyph\ReqShield\Rules\Prohibited',
    ];

    /**
     * Compile validation rules into a schema.
     */
    public function compile(array $rules): array
    {
        $schema = [];

        foreach ($rules as $field => $ruleSet) {
            // Handle nested dot notation (e.g., "user.name", "items.*.price")
            if (str_contains($field, '.')) {
                $this->compileNestedField($schema, $field, $ruleSet);
            } else {
                $schema[$field] = $this->compileField($ruleSet);
            }
        }

        // Sort all rules for optimal execution
        $this->sortAllNodes($schema);

        return $schema;
    }

    /**
     * Get all registered rules.
     */
    public function getRegisteredRules(): array
    {
        return $this->ruleMap;
    }

    /**
     * Register a custom rule.
     */
    public function registerRule(string $name, string $class): void
    {
        $this->ruleMap[$name] = $class;
    }

    /**
     * Compile rules for a single field.
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

            if (!isset($current[$part])) {
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
     * Create a simple rule without parameters.
     */
    protected function createSimpleRule(string $name): Rule
    {
        if (isset($this->ruleMap[$name])) {
            $class = $this->ruleMap[$name];
            return new $class();
        }

        throw new InvalidRuleException("Unknown rule: {$name}");
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

        throw new InvalidRuleException('Invalid rule format: ' . gettype($rule));
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
     */
    protected function parseStringRule(string $rule): Rule
    {
        [$name, $params] = $this->parseRuleString($rule);

        // Handle parameterized rules
        return match ($name) {
            'min' => new \Infocyph\ReqShield\Rules\Min((int)$params[0]),
            'max' => new \Infocyph\ReqShield\Rules\Max((int)$params[0]),
            'between' => new \Infocyph\ReqShield\Rules\Between((int)$params[0], (int)$params[1]),
            'in' => new \Infocyph\ReqShield\Rules\In($params),
            'not_in' => new \Infocyph\ReqShield\Rules\NotIn($params),
            'same' => new \Infocyph\ReqShield\Rules\Same($params[0]),
            'different' => new \Infocyph\ReqShield\Rules\Different($params[0]),
            'regex' => new \Infocyph\ReqShield\Rules\Regex($params[0]),
            'date_format' => new \Infocyph\ReqShield\Rules\DateFormat($params[0]),
            'before' => new \Infocyph\ReqShield\Rules\Before($params[0]),
            'after' => new \Infocyph\ReqShield\Rules\After($params[0]),
            'size' => new \Infocyph\ReqShield\Rules\Size((int)$params[0]),
            'starts_with' => new \Infocyph\ReqShield\Rules\StartsWith(...$params),
            'ends_with' => new \Infocyph\ReqShield\Rules\EndsWith(...$params),

            // Conditional rules
            'required_if' => new \Infocyph\ReqShield\Rules\RequiredIf($params[0], $params[1] ?? null),
            'required_unless' => new \Infocyph\ReqShield\Rules\RequiredUnless($params[0], $params[1] ?? null),
            'required_with' => new \Infocyph\ReqShield\Rules\RequiredWith(...$params),
            'required_with_all' => new \Infocyph\ReqShield\Rules\RequiredWithAll(...$params),
            'required_without' => new \Infocyph\ReqShield\Rules\RequiredWithout(...$params),
            'prohibited_if' => new \Infocyph\ReqShield\Rules\ProhibitedIf($params[0], $params[1] ?? null),

            // Database rules
            'unique' => new \Infocyph\ReqShield\Rules\Unique(
                $params[0],
                $params[1] ?? null,
                isset($params[2]) ? (int)$params[2] : null,
                $params[3] ?? 'id',
            ),
            'exists' => new \Infocyph\ReqShield\Rules\Exists($params[0], $params[1]),

            // Simple rules (no parameters)
            default => $this->createSimpleRule($name),
        };
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

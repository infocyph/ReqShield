<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;

/**
 * Represents a node in the validation tree that holds rules for a specific
 * field.
 *
 * This class organizes validation rules into cost-based categories (cheap,
 * medium, expensive) to optimize validation performance by executing less
 * expensive rules first. It also supports nested validation through child
 * nodes.
 *
 * Rules are categorized by cost:
 * - Cheap rules (cost < 50): Simple validations like type checking, format
 * validation
 * - Medium rules (50 <= cost < 100): Moderate validations like string length,
 * numeric ranges
 * - Expensive rules (cost >= 100): Complex validations like database lookups,
 * API calls
 *
 * @package Infocyph\ReqShield\Support
 */
class ValidationNode
{
    /**
     * Canonical names for cheap rules, aligned with cheapRules.
     *
     * @var string[]
     */
    public array $cheapRuleNames = [];

    /**
     * Placeholder token maps for cheap rules, aligned with cheapRules.
     *
     * @var array<int,array<string,mixed>>
     */
    public array $cheapRulePlaceholders = [];
    /**
     * Cheap rules (cost < 50)
     *
     * @var Rule[]
     */
    public array $cheapRules = [];

    /**
     * Child nodes for nested validation
     *
     * @var array<string, ValidationNode>|null
     */
    public ?array $children = null;

    /**
     * Canonical names for expensive rules, aligned with expensiveRules.
     *
     * @var string[]
     */
    public array $expensiveRuleNames = [];

    /**
     * Placeholder token maps for expensive rules, aligned with expensiveRules.
     *
     * @var array<int,array<string,mixed>>
     */
    public array $expensiveRulePlaceholders = [];

    /**
     * Expensive rules (cost >= 100)
     *
     * @var Rule[]
     */
    public array $expensiveRules = [];

    /**
     * Whether this field has a bail rule (field-level fail-fast).
     */
    public bool $hasBailRule = false;

    /**
     * Whether this field has any exclude* rules.
     */
    public bool $hasExcludeRules = false;

    /**
     * Whether this field has a filled rule.
     */
    public bool $hasFilledRule = false;

    /**
     * Whether this field is optional (no required rule)
     */
    public bool $isOptional = true;

    /**
     * Canonical names for medium rules, aligned with mediumRules.
     *
     * @var string[]
     */
    public array $mediumRuleNames = [];

    /**
     * Placeholder token maps for medium rules, aligned with mediumRules.
     *
     * @var array<int,array<string,mixed>>
     */
    public array $mediumRulePlaceholders = [];

    /**
     * Medium rules (cost 50-99)
     *
     * @var Rule[]
     */
    public array $mediumRules = [];

    /**
     * Whether this field must be evaluated even when missing from input.
     */
    public bool $requiresValidationWhenMissing = false;

    /**
     * Fast lookup from rule object ID to canonical rule name.
     *
     * @var array<int,string>
     */
    protected array $ruleNamesByObjectId = [];

    /**
     * Add a child node for nested validation.
     */
    public function addChild(string $key, ValidationNode $node): void
    {
        if ($this->children === null) {
            $this->children = [];
        }
        $this->children[$key] = $node;
    }

    /**
     * Add a rule to the appropriate cost bucket.
     */
    public function addRule(
        Rule $rule,
        string $ruleName = '',
        array $placeholders = [],
    ): void {
        $cost = $rule->cost();
        $shortName = $ruleName !== '' ? $ruleName : $this->resolveRuleName($rule);
        $this->ruleNamesByObjectId[spl_object_id($rule)] = $shortName;

        // Check if this is a required rule
        if (str_starts_with($shortName, 'required')) {
            $this->isOptional = false;
        }

        if ($shortName === 'bail') {
            $this->hasBailRule = true;
        }

        if ($shortName === 'filled') {
            $this->hasFilledRule = true;
        }

        if (str_starts_with($shortName, 'exclude')) {
            $this->hasExcludeRules = true;
        }

        if (
            str_starts_with($shortName, 'required')
            || str_starts_with($shortName, 'present')
            || str_starts_with($shortName, 'missing')
            || str_starts_with($shortName, 'prohibited')
            || $shortName === 'prohibits'
        ) {
            $this->requiresValidationWhenMissing = true;
        }

        if ($cost < 50) {
            $this->cheapRules[] = $rule;
            $this->cheapRuleNames[] = $shortName;
            $this->cheapRulePlaceholders[] = $placeholders;
        } elseif ($cost < 100) {
            $this->mediumRules[] = $rule;
            $this->mediumRuleNames[] = $shortName;
            $this->mediumRulePlaceholders[] = $placeholders;
        } else {
            $this->expensiveRules[] = $rule;
            $this->expensiveRuleNames[] = $shortName;
            $this->expensiveRulePlaceholders[] = $placeholders;
        }
    }

    /**
     * Get all canonical rule names for this node.
     *
     * @return string[]
     */
    public function getAllRuleNames(): array
    {
        return array_merge(
            $this->cheapRuleNames,
            $this->mediumRuleNames,
            $this->expensiveRuleNames,
        );
    }

    /**
     * Get all rules (for debugging/inspection).
     * NOTE: Uses array_merge but only called for debugging/stats - not in hot
     * path
     *
     * @return Rule[]
     */
    public function getAllRules(): array
    {
        return array_merge(
            $this->cheapRules,
            $this->mediumRules,
            $this->expensiveRules,
        );
    }

    /**
     * Retrieves a child validation node by its key.
     *
     * This method allows accessing nested validation rules for structured data.
     * Returns null if no child exists with the specified key.
     *
     * @param string $key The key of the child node to retrieve
     *
     * @return ValidationNode|null The child node if found, null otherwise
     *
     * @see ValidationNode::hasChildren() To check if any child nodes exist
     * @example
     * // Get the 'address' child node
     * $addressNode = $node->getChild('address');
     * if ($addressNode) {
     *     // Process address validation rules
     * }
     *
     */
    public function getChild(string $key): ?ValidationNode
    {
        return $this->children[$key] ?? null;
    }

    /**
     * Returns the total number of validation rules in this node.
     *
     * This is a convenience method that sums up all rules across all cost
     * categories. It's useful for debugging and for determining if a node has
     * any validation rules.
     *
     * @return int Total count of all validation rules in this node
     *
     * @see ValidationNode::isEmpty() To check if a node has no rules
     * @see ValidationNode::getAllRules() To get all rules as an array
     * @example
     * if ($node->getRuleCount() > 0) {
     *     echo "Node has " . $node->getRuleCount() . " validation rules";
     * }
     *
     */
    public function getRuleCount(): int
    {
        return count($this->cheapRules) + count($this->mediumRules) + count(
            $this->expensiveRules,
        );
    }

    /**
     * Get the canonical name for a rule object.
     */
    public function getRuleName(Rule $rule): ?string
    {
        return $this->ruleNamesByObjectId[spl_object_id($rule)] ?? null;
    }

    /**
     * Get rules by cost category.
     * NEW: Added for more flexible access
     */
    public function getRulesByCost(string $category): array
    {
        return match ($category) {
            'cheap' => $this->cheapRules,
            'medium' => $this->mediumRules,
            'expensive' => $this->expensiveRules,
            default => []
        };
    }

    /**
     * Get statistics about this node (for debugging).
     * IMPROVED: More comprehensive stats
     */
    public function getStats(): array
    {
        $childrenCount = $this->hasChildren() ? count($this->children) : 0;

        $stats = [
          'cheap_rules' => count($this->cheapRules),
          'medium_rules' => count($this->mediumRules),
          'expensive_rules' => count($this->expensiveRules),
          'total_rules' => $this->getRuleCount(),
          'is_optional' => $this->isOptional,
          'requires_validation_when_missing' => $this->requiresValidationWhenMissing,
          'has_exclude_rules' => $this->hasExcludeRules,
          'has_filled_rule' => $this->hasFilledRule,
          'has_bail_rule' => $this->hasBailRule,
          'has_children' => $this->hasChildren(),
          'children_count' => $childrenCount,
        ];

        // Add detailed rule names for debugging
        if ($this->getRuleCount() > 0) {
            $stats['rule_types'] = $this->getAllRuleNames();
        }

        // Add child statistics recursively
        if ($this->hasChildren()) {
            $stats['children'] = [];
            foreach ($this->children as $key => $child) {
                $stats['children'][$key] = $child->getStats();
            }
        }

        return $stats;
    }

    /**
     * Check if this node has children (nested validation).
     */
    public function hasChildren(): bool
    {
        return !empty($this->children);
    }

    /**
     * Check if this node has any expensive rules.
     */
    public function hasExpensiveRules(): bool
    {
        return !empty($this->expensiveRules);
    }

    /**
     * Check if node is empty (no rules).
     * NEW: Added for validation
     */
    public function isEmpty(): bool
    {
        return empty($this->cheapRules)
          && empty($this->mediumRules)
          && empty($this->expensiveRules);
    }

    /**
     * Sort rules within each cost bucket by their exact cost.
     * This ensures even within a bucket, cheaper rules run first.
     */
    public function sortRules(): void
    {
        $this->sortRuleBucket(
            $this->cheapRules,
            $this->cheapRuleNames,
            $this->cheapRulePlaceholders,
        );
        $this->sortRuleBucket(
            $this->mediumRules,
            $this->mediumRuleNames,
            $this->mediumRulePlaceholders,
        );
        $this->sortRuleBucket(
            $this->expensiveRules,
            $this->expensiveRuleNames,
            $this->expensiveRulePlaceholders,
        );
    }

    /**
     * Resolve a fallback canonical rule name for rules created outside the compiler.
     */
    protected function resolveRuleName(Rule $rule): string
    {
        $class = $rule::class;
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
     * Sort a rule bucket while keeping rule names aligned with rule objects.
     *
     * @param Rule[] $rules
     * @param string[] $ruleNames
     * @param array<int,array<string,mixed>> $placeholders
     */
    protected function sortRuleBucket(
        array &$rules,
        array &$ruleNames,
        array &$placeholders,
    ): void {
        $paired = [];

        foreach ($rules as $index => $rule) {
            $paired[] = [
                $rule,
                $ruleNames[$index] ?? '',
                $placeholders[$index] ?? [],
            ];
        }

        usort($paired, fn (array $left, array $right) => $left[0]->cost() <=> $right[0]->cost());

        $rules = [];
        $ruleNames = [];
        $placeholders = [];

        foreach ($paired as [$rule, $ruleName, $tokenMap]) {
            $rules[] = $rule;
            $ruleNames[] = $ruleName;
            $placeholders[] = $tokenMap;
        }
    }

}

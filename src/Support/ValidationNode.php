<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;

/**
 * Represents a node in the validation tree that holds rules for a specific field.
 *
 * This class organizes validation rules into cost-based categories (cheap, medium, expensive)
 * to optimize validation performance by executing less expensive rules first. It also supports
 * nested validation through child nodes.
 *
 * Rules are categorized by cost:
 * - Cheap rules (cost < 50): Simple validations like type checking, format validation
 * - Medium rules (50 <= cost < 100): Moderate validations like string length, numeric ranges
 * - Expensive rules (cost >= 100): Complex validations like database lookups, API calls
 *
 * @package Infocyph\ReqShield\Support
 */
class ValidationNode
{
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
     * Expensive rules (cost >= 100)
     *
     * @var Rule[]
     */
    public array $expensiveRules = [];

    /**
     * Whether this field is optional (no required rule)
     */
    public bool $isOptional = true;

    /**
     * Medium rules (cost 50-99)
     *
     * @var Rule[]
     */
    public array $mediumRules = [];

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
    public function addRule(Rule $rule): void
    {
        $cost = $rule->cost();

        // Check if this is a required rule
        if ($rule instanceof \Infocyph\ReqShield\Rules\Required) {
            $this->isOptional = false;
        }

        if ($cost < 50) {
            $this->cheapRules[] = $rule;
        } elseif ($cost < 100) {
            $this->mediumRules[] = $rule;
        } else {
            $this->expensiveRules[] = $rule;
        }
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
     * @return ValidationNode|null The child node if found, null otherwise
     *
     * @example
     * // Get the 'address' child node
     * $addressNode = $node->getChild('address');
     * if ($addressNode) {
     *     // Process address validation rules
     * }
     *
     * @see ValidationNode::hasChildren() To check if any child nodes exist
     */
    public function getChild(string $key): ?ValidationNode
    {
        return $this->children[$key] ?? null;
    }

    /**
     * Returns the total number of validation rules in this node.
     *
     * This is a convenience method that sums up all rules across all cost categories.
     * It's useful for debugging and for determining if a node has any validation rules.
     *
     * @return int Total count of all validation rules in this node
     *
     * @example
     * if ($node->getRuleCount() > 0) {
     *     echo "Node has " . $node->getRuleCount() . " validation rules";
     * }
     *
     * @see ValidationNode::isEmpty() To check if a node has no rules
     * @see ValidationNode::getAllRules() To get all rules as an array
     */
    public function getRuleCount(): int
    {
        return count($this->cheapRules) + count($this->mediumRules) + count(
            $this->expensiveRules,
        );
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
        $stats = [
            'cheap_rules' => count($this->cheapRules),
            'medium_rules' => count($this->mediumRules),
            'expensive_rules' => count($this->expensiveRules),
            'total_rules' => $this->getRuleCount(),
            'is_optional' => $this->isOptional,
            'has_children' => $this->hasChildren(),
            'children_count' => $this->hasChildren() ? count(
                $this->children,
            ) : 0,
        ];

        // Add detailed rule names for debugging
        if ($this->getRuleCount() > 0) {
            $stats['rule_types'] = array_map(
                fn ($rule) => new \ReflectionClass($rule)->getShortName(),
                $this->getAllRules(),
            );
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
        usort($this->cheapRules, fn ($a, $b) => $a->cost() <=> $b->cost());
        usort($this->mediumRules, fn ($a, $b) => $a->cost() <=> $b->cost());
        usort($this->expensiveRules, fn ($a, $b) => $a->cost() <=> $b->cost());
    }

}

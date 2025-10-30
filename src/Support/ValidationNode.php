<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;

/**
 * ValidationNode
 *
 * Represents a compiled set of validation rules for a single field.
 * Rules are grouped by cost for optimal execution order.
 *
 * MINOR IMPROVEMENTS: Added helper methods and better statistics
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
     * NOTE: Uses array_merge but only called for debugging/stats - not in hot path
     *
     * @return Rule[]
     */
    public function getAllRules(): array
    {
        return array_merge(
            $this->cheapRules,
            $this->mediumRules,
            $this->expensiveRules
        );
    }

    /**
     * Get a child node by key.
     */
    public function getChild(string $key): ?ValidationNode
    {
        return $this->children[$key] ?? null;
    }

    /**
     * Get total rule count.
     * NEW: Added for convenience
     */
    public function getRuleCount(): int
    {
        return count($this->cheapRules) + count($this->mediumRules) + count($this->expensiveRules);
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
            'children_count' => $this->hasChildren() ? count($this->children) : 0,
        ];

        // Add detailed rule names for debugging
        if ($this->getRuleCount() > 0) {
            $stats['rule_types'] = array_map(
                fn ($rule) => new \ReflectionClass($rule)->getShortName(),
                $this->getAllRules()
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
        return $this->children !== null && ! empty($this->children);
    }

    /**
     * Check if this node has any expensive rules.
     */
    public function hasExpensiveRules(): bool
    {
        return ! empty($this->expensiveRules);
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

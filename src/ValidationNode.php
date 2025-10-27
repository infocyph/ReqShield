<?php

namespace Infocyph\ReqShield;

use Infocyph\ReqShield\Contracts\Rule;

/**
 * ValidationNode
 *
 * Represents a compiled set of validation rules for a single field.
 * Rules are grouped by cost for optimal execution order.
 */
class ValidationNode
{
    /**
     * Cheap rules (cost < 50)
     * @var Rule[]
     */
    public array $cheapRules = [];

    /**
     * Child nodes for nested validation
     * @var array<string, ValidationNode>|null
     */
    public ?array $children = null;

    /**
     * Expensive rules (cost >= 100)
     * @var Rule[]
     */
    public array $expensiveRules = [];

    /**
     * Whether this field is optional (no required rule)
     * @var bool
     */
    public bool $isOptional = true;

    /**
     * Medium rules (cost 50-99)
     * @var Rule[]
     */
    public array $mediumRules = [];

    /**
     * Add a child node for nested validation.
     *
     * @param string $key
     * @param ValidationNode $node
     * @return void
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
     *
     * @param Rule $rule
     * @return void
     */
    public function addRule(Rule $rule): void
    {
        $cost = $rule->cost();

        // Check if this is a required rule
        if ($rule instanceof \Validation\Rules\Required) {
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
     *
     * @param string $key
     * @return ValidationNode|null
     */
    public function getChild(string $key): ?ValidationNode
    {
        return $this->children[$key] ?? null;
    }

    /**
     * Get statistics about this node (for debugging).
     *
     * @return array
     */
    public function getStats(): array
    {
        return [
            'cheap_rules' => count($this->cheapRules),
            'medium_rules' => count($this->mediumRules),
            'expensive_rules' => count($this->expensiveRules),
            'total_rules' => count($this->getAllRules()),
            'is_optional' => $this->isOptional,
            'has_children' => $this->hasChildren(),
            'children_count' => $this->hasChildren() ? count($this->children) : 0,
        ];
    }

    /**
     * Check if this node has children (nested validation).
     *
     * @return bool
     */
    public function hasChildren(): bool
    {
        return $this->children !== null && !empty($this->children);
    }

    /**
     * Check if this node has any expensive rules.
     *
     * @return bool
     */
    public function hasExpensiveRules(): bool
    {
        return !empty($this->expensiveRules);
    }

    /**
     * Sort rules within each cost bucket by their exact cost.
     * This ensures even within a bucket, cheaper rules run first.
     *
     * @return void
     */
    public function sortRules(): void
    {
        usort($this->cheapRules, fn ($a, $b) => $a->cost() <=> $b->cost());
        usort($this->mediumRules, fn ($a, $b) => $a->cost() <=> $b->cost());
        usort($this->expensiveRules, fn ($a, $b) => $a->cost() <=> $b->cost());
    }
}

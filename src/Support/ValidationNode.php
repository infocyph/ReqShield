<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\Rule;

class ValidationNode
{
    /** @var array<int,string> */
    public array $cheapRuleNames = [];

    /** @var array<int,array<string,mixed>> */
    public array $cheapRulePlaceholders = [];

    /** @var array<int,Rule> */
    public array $cheapRules = [];

    /** @var array<int,string> */
    public array $expensiveRuleNames = [];

    /** @var array<int,array<string,mixed>> */
    public array $expensiveRulePlaceholders = [];

    /** @var array<int,Rule> */
    public array $expensiveRules = [];

    public bool $hasBailRule = false;

    public bool $hasExcludeRules = false;

    public bool $hasFilledRule = false;

    public bool $isOptional = true;

    /** @var array<int,string> */
    public array $mediumRuleNames = [];

    /** @var array<int,array<string,mixed>> */
    public array $mediumRulePlaceholders = [];

    /** @var array<int,Rule> */
    public array $mediumRules = [];

    public bool $requiresValidationWhenMissing = false;

    /** @var array<int,string> */
    protected array $ruleNamesByObjectId = [];

    /** @param array<string,mixed> $placeholders */
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

    /** @return array<int,string> */
    public function getAllRuleNames(): array
    {
        return array_merge(
            $this->cheapRuleNames,
            $this->mediumRuleNames,
            $this->expensiveRuleNames,
        );
    }

    /** @return array<int,Rule> */
    public function getAllRules(): array
    {
        return array_merge(
            $this->cheapRules,
            $this->mediumRules,
            $this->expensiveRules,
        );
    }

    public function getRuleCount(): int
    {
        return count($this->cheapRules) + count($this->mediumRules) + count(
            $this->expensiveRules,
        );
    }

    public function getRuleName(Rule $rule): ?string
    {
        $name = $this->ruleNamesByObjectId[spl_object_id($rule)] ?? null;

        return is_string($name) ? $name : null;
    }

    /** @return array<string,mixed> */
    public function getStats(): array
    {
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
        ];

        // Add detailed rule names for debugging
        if ($this->getRuleCount() > 0) {
            $stats['rule_types'] = $this->getAllRuleNames();
        }

        return $stats;
    }

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

    protected function resolveRuleName(Rule $rule): string
    {
        return RuleNameResolver::canonicalRuleNameFromClass($rule::class);
    }

    /**
     * @param array<int,Rule> $rules
     * @param array<int,string> $ruleNames
     * @param array<int,array<string,mixed>> $placeholders
     */
    protected function sortRuleBucket(
        array &$rules,
        array &$ruleNames,
        array &$placeholders,
    ): void {
        $indices = array_keys($rules);
        usort(
            $indices,
            fn(int $left, int $right): int => $rules[$left]->cost() <=> $rules[$right]->cost(),
        );

        $sortedRules = [];
        $sortedRuleNames = [];
        $sortedPlaceholders = [];

        foreach ($indices as $index) {
            $sortedRules[] = $rules[$index];
            $sortedRuleNames[] = $ruleNames[$index] ?? '';
            $sortedPlaceholders[] = $placeholders[$index] ?? [];
        }

        $rules = $sortedRules;
        $ruleNames = $sortedRuleNames;
        $placeholders = $sortedPlaceholders;
    }
}

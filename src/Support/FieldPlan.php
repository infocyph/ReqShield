<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

use Infocyph\ReqShield\Contracts\DatabaseBatchRule;
use Infocyph\ReqShield\Contracts\Rule;

final readonly class FieldPlan
{
    /**
     * @param list<Rule> $implicitRules
     * @param list<string> $implicitRuleNames
     * @param list<array<string,mixed>> $implicitRulePlaceholders
     * @param list<Rule> $cheapRules
     * @param list<string> $cheapRuleNames
     * @param list<array<string,mixed>> $cheapRulePlaceholders
     * @param list<Rule> $mediumRules
     * @param list<string> $mediumRuleNames
     * @param list<array<string,mixed>> $mediumRulePlaceholders
     * @param list<Rule> $expensiveRules
     * @param list<string> $expensiveRuleNames
     * @param list<array<string,mixed>> $expensiveRulePlaceholders
     * @param list<DatabaseBatchRule> $batchRules
     * @param list<string> $batchRuleNames
     * @param list<array<string,mixed>> $batchRulePlaceholders
     * @param list<Rule> $excludeRules
     * @param list<string> $excludeRuleNames
     * @param list<string> $allRuleNames
     * @param list<string> $dependencyPaths
     */
    public function __construct(
        public array $implicitRules,
        public array $implicitRuleNames,
        public array $implicitRulePlaceholders,
        public array $cheapRules,
        public array $cheapRuleNames,
        public array $cheapRulePlaceholders,
        public array $mediumRules,
        public array $mediumRuleNames,
        public array $mediumRulePlaceholders,
        public array $expensiveRules,
        public array $expensiveRuleNames,
        public array $expensiveRulePlaceholders,
        public array $batchRules,
        public array $batchRuleNames,
        public array $batchRulePlaceholders,
        public array $excludeRules,
        public array $excludeRuleNames,
        public array $allRuleNames,
        public array $dependencyPaths,
        public bool $hasBailRule,
        public bool $hasExcludeRules,
        public bool $hasFilledRule,
        public bool $isOptional,
        public bool $nullable,
        public bool $requiresValidationWhenMissing,
    ) {}

    /** @return list<string> */
    public function getAllRuleNames(): array
    {
        return $this->allRuleNames;
    }

    /** @return list<Rule> */
    public function getAllRules(): array
    {
        return array_merge(
            $this->implicitRules,
            $this->cheapRules,
            $this->mediumRules,
            $this->expensiveRules,
            $this->batchRules,
            $this->excludeRules,
        );
    }

    public function getRuleCount(): int
    {
        return count($this->implicitRules)
            + count($this->cheapRules)
            + count($this->mediumRules)
            + count($this->expensiveRules)
            + count($this->batchRules)
            + count($this->excludeRules)
            + (int) $this->hasBailRule
            + (int) $this->nullable;
    }

    /** @return array<string,mixed> */
    public function getStats(): array
    {
        $stats = [
            'implicit_rules' => count($this->implicitRules),
            'cheap_rules' => count($this->cheapRules),
            'medium_rules' => count($this->mediumRules),
            'expensive_rules' => count($this->expensiveRules),
            'batch_rules' => count($this->batchRules),
            'total_rules' => $this->getRuleCount(),
            'is_optional' => $this->isOptional,
            'requires_validation_when_missing' => $this->requiresValidationWhenMissing,
            'has_exclude_rules' => $this->hasExcludeRules,
            'has_filled_rule' => $this->hasFilledRule,
            'has_bail_rule' => $this->hasBailRule,
        ];

        if ($this->allRuleNames !== []) {
            $stats['rule_types'] = $this->allRuleNames;
        }

        return $stats;
    }
}

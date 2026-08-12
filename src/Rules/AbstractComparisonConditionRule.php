<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractComparisonConditionRule extends BaseRule
{
    public function __construct(
        protected string $otherField,
        protected mixed $value,
    ) {}

    /** @param array<array-key, mixed> $data */
    abstract protected function passesWhenConditionApplies(mixed $value, string $field, array $data): bool;

    public function cost(): int
    {
        return 2;
    }

    public function getOtherField(): string
    {
        return $this->otherField;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        if ($this->otherFieldMatches($data) !== $this->shouldEvaluateOnMatch()) {
            return true;
        }

        return $this->passesWhenConditionApplies($value, $field, $data);
    }

    /** @param array<array-key, mixed> $data */
    protected function otherFieldMatches(array $data): bool
    {
        return array_key_exists($this->otherField, $data)
            && $data[$this->otherField] === $this->value;
    }

    protected function shouldEvaluateOnMatch(): bool
    {
        return true;
    }
}

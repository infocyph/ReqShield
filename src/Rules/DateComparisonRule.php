<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class DateComparisonRule extends BaseRule
{
    public function __construct(protected string $date) {}

    abstract protected function compareDates(
        \DateTimeImmutable $valueDate,
        \DateTimeImmutable $referenceDate,
    ): bool;

    public function cost(): int
    {
        return 25;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!$this->isStringable($value)) {
            return false;
        }

        $referenceDate = $this->resolveReferenceDate($data);
        if (!$this->isStringable($referenceDate)) {
            return false;
        }

        try {
            return $this->compareDates(
                new \DateTimeImmutable($this->stringifyValue($value)),
                new \DateTimeImmutable($this->stringifyValue($referenceDate)),
            );
        } catch (\Exception) {
            return false;
        }
    }

    protected function isStringable(mixed $value): bool
    {
        return is_scalar($value) || (is_object($value) && method_exists($value, '__toString'));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function resolveReferenceDate(array $data): mixed
    {
        return array_key_exists($this->date, $data)
            ? $data[$this->date]
            : $this->date;
    }
}

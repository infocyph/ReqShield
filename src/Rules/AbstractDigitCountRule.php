<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractDigitCountRule extends BaseRule
{
    public function __construct(protected int $digits) {}

    abstract protected function passesForDigitCount(int $count): bool;

    public function cost(): int
    {
        return 5;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_numeric($value)) {
            return false;
        }

        return $this->passesForDigitCount(strlen((string) $value));
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractSizeRule extends BaseRule
{
    public function __construct(protected int|float $target) {}

    abstract protected function passesForSize(int|float|string $size): bool;

    public function cost(): int
    {
        return 2;
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);

        return $this->passesForSize($this->getSize($value));
    }
}

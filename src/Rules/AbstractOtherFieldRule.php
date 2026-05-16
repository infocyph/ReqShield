<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractOtherFieldRule extends BaseRule
{
    public function __construct(protected string $otherField) {}

    public function cost(): int
    {
        return $this->ruleCost();
    }

    protected function ruleCost(): int
    {
        return 2;
    }
}

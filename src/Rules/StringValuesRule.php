<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class StringValuesRule extends BaseRule
{
    /** @var list<string> */
    protected array $values;

    public function __construct(string ...$values)
    {
        $this->values = array_values($values);
    }

    public function cost(): int
    {
        return 5;
    }

    protected function joinedValues(): string
    {
        return implode(', ', $this->values);
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class FieldListRule extends BaseRule
{
    /** @var list<string> */
    protected array $fields;

    public function __construct(string ...$fields)
    {
        $this->fields = array_values($fields);
    }

    public function cost(): int
    {
        return 2;
    }

    protected function joinedFields(): string
    {
        return implode(', ', $this->fields);
    }
}

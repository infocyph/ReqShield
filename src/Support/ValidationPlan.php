<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

readonly class ValidationPlan
{
    /** @var array<int,string> */
    public array $fields;

    /** @param array<string,ValidationNode> $schema */
    public function __construct(public array $schema)
    {
        $this->fields = array_values(array_filter(
            array_keys($this->schema),
            is_string(...),
        ));
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Support;

readonly class ValidationPlan
{
    /**
     * @var array<int,string>
     */
    public array $fields;
    /**
     * @var array<string,ValidationNode>
     */
    public array $schema;

    /**
     * @param array<string,ValidationNode> $schema
     */
    public function __construct(array $schema)
    {
        $this->schema = $schema;
        $this->fields = array_keys($schema);
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * PresentWithAll Rule - Cost: 2
 */
class PresentWithAll extends BaseRule
{
    protected array $fields;

    public function __construct(string ...$fields)
    {
        $this->fields = $fields;
    }

    public function cost(): int
    {
        return 2;
    }

    public function message(string $field): string
    {
        return "The {$field} must be present when all of " . implode(
            ', ',
            $this->fields,
        ) . ' are present.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $hasAll = array_all($this->fields, fn ($f) => isset($data[$f]));

        return !$hasAll || isset($data[$field]);
    }

}

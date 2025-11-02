<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Prohibits Rule - Cost: 2
 */
class Prohibits extends BaseRule
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
        return "The {$field} prohibits " . implode(
            ', ',
            $this->fields,
        ) . ' from being present.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        if ($this->isEmpty($value)) {
            return true;
        }

        return array_all(
            $this->fields,
            fn ($f) => !(isset($data[$f]) && !$this->isEmpty($data[$f])),
        );
    }

}

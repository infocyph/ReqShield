<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * PresentWith Rule - Cost: 2
 */
class PresentWith extends BaseRule
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
        return "The {$field} must be present when any of " . implode(
            ', ',
            $this->fields,
        ) . ' are present.';
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $hasAny = array_any($this->fields, fn ($f) => isset($data[$f]));

        return !$hasAny || isset($data[$field]);
    }

}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * RequiredWithoutAll Rule - Cost: 2
 */
class RequiredWithoutAll extends BaseRule
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
        return "The {$field} is required when none of " . implode(', ', $this->fields) . " are present.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        $hasAny = array_any($this->fields, fn ($f) => isset($data[$f]) && !$this->isEmpty($data[$f]));
        return $hasAny || !$this->isEmpty($value);
    }
}

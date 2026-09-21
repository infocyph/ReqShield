<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Fixtures\Validation;

use Infocyph\ReqShield\Contracts\Rule;

final class MutableAuditRule implements Rule
{
    public bool $passes = true;

    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} failed the mutable audit rule.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return $this->passes && array_key_exists($field, $data) && $data[$field] === $value;
    }
}

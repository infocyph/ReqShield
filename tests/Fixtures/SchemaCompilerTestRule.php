<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Fixtures;

use Infocyph\ReqShield\Contracts\Rule;

final class SchemaCompilerTestRule implements Rule
{
    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "{$field} is invalid.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return true;
    }
}

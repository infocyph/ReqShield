<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Tests\Fixtures\Validation;

use Infocyph\ReqShield\Contracts\Rule;

final class NestedStateRule implements Rule
{
    public readonly object $config;

    public function __construct(private bool $detach = true)
    {
        $this->config = (object) ['allow' => true];
    }

    public function __clone(): void
    {
        if ($this->detach) {
            $this->config = clone $this->config;
        }
    }

    public function cost(): int
    {
        return 1;
    }

    public function message(string $field): string
    {
        return "The {$field} is invalid.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        unset($value, $field, $data);

        return $this->config->allow;
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * DoesntContain Rule - Cost: 5
 */
class DoesntContain extends BaseRule
{
    protected mixed $needle;

    public function __construct(mixed $needle)
    {
        $this->needle = $needle;
    }

    public function cost(): int
    {
        return 5;
    }

    public function message(string $field): string
    {
        return "The $field must not contain the specified value.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return is_array($value) && ! in_array($this->needle, $value, true);
    }
}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * CurrentPassword Rule - Cost: 150
 */
class CurrentPassword extends BaseRule
{
    protected mixed $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    public function cost(): int
    {
        return 150;
    }

    public function message(string $field): string
    {
        return "The {$field} does not match current password.";
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return (bool)call_user_func($this->callback, $value, $field, $data);
    }

}

<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * CurrentPassword Rule - Cost: 150
 */
class CurrentPassword extends BaseRule
{
    /** @var callable(mixed,string,array<int|string,mixed>):bool */
    protected $callback;

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
        $this->consumeRuleContext($value, $field, $data);

        return call_user_func($this->callback, $value, $field, $data);
    }
}

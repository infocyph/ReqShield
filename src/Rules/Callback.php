<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

/**
 * Custom Callback Rule - Cost: Variable
 * Allows custom validation logic via callback.
 * Cost can be specified based on the operation complexity.
 */
class Callback extends BaseRule
{
    protected $callback;

    public function __construct(
        callable $callback,
        protected int $cost = 50,
        protected string $message = 'The :field is invalid.',
    ) {
        $this->callback = $callback;
    }

    public function cost(): int
    {
        return $this->cost;
    }

    public function message(string $field): string
    {
        return str_replace(':field', $field, $this->message);
    }

    public function passes(mixed $value, string $field, array $data): bool
    {
        return (bool) call_user_func($this->callback, $value, $field, $data);
    }

}

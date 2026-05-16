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
    /** @var callable(mixed,string,array<int|string,mixed>):mixed */
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
        $this->consumeRuleContext($value, $field, $data);
        $result = call_user_func($this->callback, $value, $field, $data);

        if (is_bool($result)) {
            return $result;
        }

        if (is_int($result)) {
            return $result !== 0;
        }

        if (is_string($result)) {
            $normalized = strtolower(trim($result));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        return $result !== null;
    }
}

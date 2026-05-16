<?php

declare(strict_types=1);

namespace Infocyph\ReqShield\Rules;

abstract class AbstractTrimmedStringRule extends BaseRule
{
    abstract protected function passesNormalized(string $value): bool;

    public function passes(mixed $value, string $field, array $data): bool
    {
        $this->consumeRuleContext($value, $field, $data);
        if (!is_string($value)) {
            return false;
        }

        $normalized = trim($value);
        if ($normalized === '' || str_contains($normalized, "\0")) {
            return false;
        }

        return $this->passesNormalized($normalized);
    }
}

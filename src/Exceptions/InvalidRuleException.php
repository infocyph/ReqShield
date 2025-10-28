<?php

namespace Infocyph\ReqShield\Exceptions;

class InvalidRuleException extends ValidationException
{
    /**
     * Create exception for invalid rule format.
     */
    public static function invalidFormat(string $rule, string $reason = ''): self
    {
        $message = "Invalid rule format: '{$rule}'";
        if ($reason) {
            $message .= ". {$reason}";
        }
        return new self($message);
    }

    /**
     * Create exception for unknown rule.
     */
    public static function unknownRule(string $ruleName): self
    {
        return new self("Unknown validation rule: '{$ruleName}'");
    }

    /**
     * Create exception for invalid rule parameters.
     */
    public static function invalidParameters(string $rule, string $reason): self
    {
        return new self("Invalid parameters for rule '{$rule}': {$reason}");
    }
}

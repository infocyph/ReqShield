<?php

declare(strict_types=1);

use Infocyph\ReqShield\Contracts\DatabaseProvider;
use Infocyph\ReqShield\Support\Sanitizer;
use Infocyph\ReqShield\Validator;

if (!function_exists('validate')) {
    /**
     * Create a new validator instance.
     */
    function validate(array $rules, ?DatabaseProvider $db = null): Validator
    {
        return new Validator($rules, $db);
    }
}

if (!function_exists('validator')) {
    /**
     * Alias for validate function.
     */
    function validator(array $rules, ?DatabaseProvider $db = null): Validator
    {
        return validate($rules, $db);
    }
}

if (!function_exists('sanitize')) {
    /**
     * Sanitize a value using specified sanitizers.
     */
    function sanitize(mixed $value, string|array $sanitizers): mixed
    {
        if (is_string($sanitizers)) {
            return Sanitizer::$sanitizers($value);
        }

        return Sanitizer::apply($value, $sanitizers);
    }
}
